"""G6: optional *local* embedding relevance triage for verified PI graph edges.

A cosine score is not entailment, policy support, causal proof, or probability.
Never call third-party endpoints; admission remains SYNTHETIC_ONLY.
"""
from __future__ import annotations
from collections import Counter
from hashlib import sha256
from math import sqrt, isfinite
import json

from app.graph_g5.engine import assess_direction, _digest
from app.graph_g4.engine import RELATION_PHRASES
from app.vs1.canonical_bridge import Refused
from app.vs2.reviews import validate_result

SCHEMA = 'pi.graph_g6.semantic_relevance.synthetic.v1'
RULE_VERSION = 'g6-local-embedding-relevance-r1'
MAX_EDGES = 500
# These are *candidate triage* thresholds, not calibrated policy accuracy.
DEFAULT_THRESHOLD = 0.72


class EmbeddingUnavailable(RuntimeError):
    """No locally configured embedding runtime; fail closed."""


def cosine(left, right):
    """Bounded, finite, dimension-checked cosine similarity."""
    if not isinstance(left,(list,tuple)) or not isinstance(right,(list,tuple)):
        raise Refused('G6_EMBEDDING_VECTOR_INVALID')
    if len(left)!=len(right) or not 2<=len(left)<=4096:
        raise Refused('G6_EMBEDDING_DIMENSION_INVALID')
    if any(not isinstance(v,(int,float)) or isinstance(v,bool) or not isfinite(v)
           for v in (*left,*right)):
        raise Refused('G6_EMBEDDING_NONFINITE')
    na=sqrt(sum(v*v for v in left));nb=sqrt(sum(v*v for v in right))
    if na<1e-12 or nb<1e-12:raise Refused('G6_ZERO_NORM')
    score=sum(a*b for a,b in zip(left,right))/(na*nb)
    return max(-1.0,min(1.0,score))


def _hypothesis(src, dst, relation):
    cues=RELATION_PHRASES.get(relation)
    if not cues:raise Refused('G6_UNKNOWN_RELATION')
    return f'{src} {cues[0]} {dst}.'


def assess_semantic(g2:dict,g3:dict,g4:dict,g5:dict, embedder=None, *, threshold=DEFAULT_THRESHOLD)->dict:
    """Recompute G5, bind provenance, then optionally screen quote vs declared edge.

    embedder implements .embed(list[str])->list[list[float]] and .identity.
    None means explicit abstention; never silently replace with lexical scores.
    """
    for report in (g2,g3,g4,g5):validate_result(report)
    if (g5.get('schema')!='pi.graph_g5.direction_modality.synthetic.v1' or
        any(r.get('production_admission')!='DENIED' or
            r.get('rights_admission')!='SYNTHETIC_ONLY' for r in (g2,g3,g4,g5))):
        raise Refused('G6_REQUIRES_SYNTHETIC_G5_CHAIN')
    if (g5.get('source_g2_sha256')!=g2['report_sha256'] or
        g5.get('source_g3_sha256')!=g3['report_sha256'] or
        g5.get('source_g4_sha256')!=g4['report_sha256'] or
        assess_direction(g2,g3,g4)['report_sha256']!=g5['report_sha256']):
        raise Refused('G6_RECOMPUTED_G5_OR_LINEAGE_MISMATCH')
    if type(threshold) not in (float,int) or not 0.5<=threshold<=0.95:
        raise Refused('G6_THRESHOLD_OUT_OF_BOUNDS')
    edges=g2['graph']['edges']
    if len(edges)>MAX_EDGES:raise Refused('G6_EDGE_BOUNDARY')
    nodes={n['id']:n for n in g2['graph']['nodes']}
    prior={x['edge_id']:x for x in g5['relationship_assessments']}
    model_id='DISABLED_NO_MODEL' if embedder is None else str(getattr(embedder,'identity','')).strip()
    if not model_id or len(model_id)>160:raise Refused('G6_MODEL_ID_INVALID')
    answers=[];findings=[]
    for edge in edges:
        eid=edge['declaration_id'];old=prior[eid]
        source=g3['edge_verification'][eid].get('source_evidence')
        grounded=(isinstance(source,dict) and
                  source.get('verification_scope')=='EXACT_TEXT_AT_SOURCE_UNIT_ONLY' and
                  g3['edge_verification'][eid].get('status')=='DECLARED_RELATION_EXCERPTS_LOCATED')
        prior_status=old['classification']
        score=None
        if not grounded:
            classification='SOURCE_NOT_VERIFIED';why='G3 source evidence is not verified.'
        elif prior_status in ('EXPLICIT_NEGATION','REVERSED_DIRECTION_CANDIDATE',
                              'MIXED_POLARITY_CANDIDATE'):
            classification='BLOCKED_BY_DIRECTION_OR_NEGATION';why='G5 negation or direction blocks positive relation.'
        elif prior_status=='PROSPECTIVE_RELATION':
            classification='PROSPECTIVE_ONLY';why='G5 says planned/possible, not achieved.'
        elif embedder is None:
            classification='NO_MODEL_ABSTAIN';why='No local model configured. Embedding relevance not evaluated.'
        else:
            quote=source['excerpt']
            phrase=_hypothesis(nodes[edge['from']]['title'], nodes[edge['to']]['title'],edge['relation'])
            if len(quote)>12000:raise Refused('G6_QUOTE_LIMIT')
            vectors=embedder.embed([phrase,quote])
            if not isinstance(vectors,(list,tuple)) or len(vectors)!=2:
                raise Refused('G6_EMBEDDING_OUTPUT_INVALID')
            score=round(cosine(*vectors),6)
            classification=('SEMANTIC_RELEVANCE_CANDIDATE' if score>=threshold else
                            'LOW_EMBEDDING_SIMILARITY')
            why=('Embedding similarity alone cannot establish entailment or causal support. '
                 'Compare source text manually; threshold is not domain-calibrated.')
        item={'edge_id':eid,'relation':edge['relation'],'from_id':edge['from'],
              'to_id':edge['to'],'g5_classification':prior_status,
              'classification':classification,'similarity':score,
              'threshold':float(threshold),'model_id':model_id,
              'review_required':True,'semantic_entailment':'NOT_ESTABLISHED',
              'causal_validity':'NOT_TESTED','legal_compliance':'NOT_TESTED',
              'reason':why,'source_g3_verified':grounded}
        answers.append(item)
        evidence=([{'document_title':source['document_title'],
                    'file_sha256':source['document_sha256'],
                    'locator':source['locator'],'excerpt':source['excerpt'],
                    'source_unit_id':source['source_unit_id'],
                    'unit_fingerprint':source['unit_fingerprint']}]
                  if isinstance(source,dict) else
                  [{'document_title':g2['ledger_filename'],'file_sha256':g2['ledger_sha256'],
                    'locator':f'Edges:{eid}','excerpt':'Verified source unavailable',
                    'source_unit_id':'UNVERIFIED',
                    'unit_fingerprint':_digest([g2['ledger_sha256'],eid])}])
        findings.append({'id':_digest([eid,g5['report_sha256'],RULE_VERSION,model_id])[:24],
                         'indicator':(nodes[edge['from']]['title']+' → '+nodes[edge['to']]['title'])[:500],
                         'year':nodes[edge['from']]['year'],'status':classification,
                         'rationale':why+' Human reviewer required.',
                         'evidence':evidence,'review_status':'PENDING','review_note':''})
    report={'schema':SCHEMA,'analysis_kind':'LOCAL_SEMANTIC_RELEVANCE_TRIAGE',
            'rights_admission':'SYNTHETIC_ONLY','production_admission':'DENIED',
            'review_authority':'NOT_GRANTED','rule_version':RULE_VERSION,
            'source_g2_sha256':g2['report_sha256'],'source_g3_sha256':g3['report_sha256'],
            'source_g4_sha256':g4['report_sha256'],'source_g5_sha256':g5['report_sha256'],
            'model_id':model_id,'threshold':float(threshold),
            'relationship_assessments':answers,
            'assessment_metrics':{'edges':len(edges),
              'status_counts':dict(sorted(Counter(x['classification'] for x in answers).items())),
              'scored':sum(x['similarity'] is not None for x in answers),
              'review_required':len(answers)},
            'findings':findings,'boundary_notice':(
              'Synthetic candidates only. Cosine measures text similarity, NOT semantic entailment, '
              'coherence, factuality, policy or legal validity, or causal impact. Never auto-approve. '
              'Model IDs and thresholds must be benchmarked on governed documents before admission.')}
    report['report_sha256']=_digest(report)
    validate_result(report)
    return report
