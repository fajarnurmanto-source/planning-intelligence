"""Explicitly optional zero-paid local embedding providers. No network except loopback Ollama.

No auto-install, no silent model download, no external HTTP, no secrets.
"""
from __future__ import annotations
import http.client
import json
from pathlib import Path
import re
from .engine import EmbeddingUnavailable

_MODEL=re.compile(r'^[a-zA-Z0-9][a-zA-Z0-9._:/-]{0,100}$')

class OllamaLocal:
    def __init__(self,model='embeddinggemma'):
        if not _MODEL.fullmatch(model):raise ValueError('G6_MODEL_NAME_INVALID')
        self.model=model
        self.identity='ollama-local:'+model

    def embed(self,texts):
        if (not isinstance(texts,list) or not 1<=len(texts)<=16 or
            any(not isinstance(x,str) or len(x)>12000 for x in texts)):
            raise ValueError('G6_EMBED_INPUT_BOUNDARY')
        body=json.dumps({'model':self.model,'input':texts,'truncate':False}).encode()
        conn=http.client.HTTPConnection('127.0.0.1',11434,timeout=20)
        try:
            conn.request('POST','/api/embed',body,{'Content-Type':'application/json'})
            rsp=conn.getresponse()
            if rsp.status!=200:
                raise EmbeddingUnavailable('G6_LOCAL_OLLAMA_MODEL_UNAVAILABLE')
            if int(rsp.getheader('Content-Length','0') or '0')>1_000_000:
                raise EmbeddingUnavailable('G6_EMBED_RESPONSE_TOO_LARGE')
            raw=rsp.read(1_000_001)
            if len(raw)>1_000_000:raise EmbeddingUnavailable('G6_EMBED_RESPONSE_TOO_LARGE')
            vectors=json.loads(raw).get('embeddings')
            if not isinstance(vectors,list) or len(vectors)!=len(texts):
                raise EmbeddingUnavailable('G6_OLLAMA_VECTOR_COUNT_INVALID')
            return vectors
        except (OSError,TimeoutError,json.JSONDecodeError) as exc:
            raise EmbeddingUnavailable('G6_LOCAL_OLLAMA_NOT_READY') from exc
        finally:conn.close()

class CachedSentenceTransformer:
    """Locally cached SentenceTransformer only; NEVER download automatically."""
    def __init__(self,model_path):
        p=Path(model_path).expanduser()
        if not p.is_dir():raise EmbeddingUnavailable('G6_LOCAL_MODEL_DIRECTORY_REQUIRED')
        try:
            from sentence_transformers import SentenceTransformer
        except ImportError as exc:
            raise EmbeddingUnavailable('G6_SENTENCE_TRANSFORMERS_NOT_INSTALLED') from exc
        self.model=SentenceTransformer(str(p.resolve()),device='cpu',local_files_only=True,
                                       trust_remote_code=False)
        self.identity='sentence-transformer-cpu:'+p.name

    def embed(self,texts):
        return self.model.encode(texts,normalize_embeddings=True,
                                 convert_to_numpy=True).tolist()


def choose_provider(name='disabled',model='embeddinggemma',local_model_path=''):
    if name=='disabled':return None
    if name=='ollama-local':return OllamaLocal(model)
    if name=='cached-sentence-transformer':return CachedSentenceTransformer(local_model_path)
    raise ValueError('G6_PROVIDER_NOT_ALLOWED')
