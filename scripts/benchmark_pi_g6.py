"""Optional local semantic benchmark; no model, no network by default."""
from __future__ import annotations
import json,os,sys
from pathlib import Path


def main():
    repo=Path(__file__).resolve().parents[1]
    sys.path.insert(0,str(repo/'backend'))
    from app.graph_g6.provider import choose_provider
    from app.graph_g6.benchmark import evaluate_benchmark
    name=os.environ.get('PI_G6_PROVIDER','disabled')
    model=os.environ.get('PI_G6_OLLAMA_MODEL','embeddinggemma')
    local=os.environ.get('PI_G6_LOCAL_MODEL_DIR','')
    embedder=choose_provider(name,model,local)
    print(json.dumps(evaluate_benchmark(embedder),indent=2,ensure_ascii=False))

if __name__=='__main__':main()
