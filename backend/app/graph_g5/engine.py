"""G5: direction, polarity and modality triage for G3-grounded G4 statements.

Only explicit local-text syntax: NOT semantic entailment, legal validity,
causal attribution or automatic correctness. Synthetic candidate only.
"""
from __future__ import annotations
from collections import Counter
import json
import re
import unicodedata
from hashlib import sha256
from app.graph_g4.engine import RELATION_PHRASES, assess_relationships
from app.vs1.canonical_bridge import Refused
from app.vs2.reviews import validate_result

RULE_VERSION='g5-direction-modality-v1'
MAX_EDGES=500
# Blocking statements: a future wish should NOT be interpreted as achieved effect.
PROSPECTIVE={'akan','diharapkan','direncanakan','diperkirakan','diusulkan',
             'berpotensi','mungkin','rencana','diharap','dirancang'}
NEGATIONS={'tidak','bukan','belum','tanpa','mustahil','gagal'}


def _digest(obj):
    return sha256(json.dumps(obj,sort_keys=True,ensure_ascii=False,
                             separators=(',',':')).encode('utf8')).hexdigest()


def _norm(s):
    return ' '.join(re.findall(r'\w+',unicodedata.normalize('NFKC',str(s or '')).casefold()))


def _phrase_spans(text,term):
    if not term:return []
    return [m.span() for m in re.finditer(r'(?<!\w)'+re.escape(term)+r'(?!\w)',text)]


def _clauses(quote):
    # Sentence/clause boundary stops independent statements satisfying an edge.
    return [_norm(s) for s in re.split(r'[.!?;\n]+',str(quote or '')) if _norm(s)]


def orientation_screen(quote,subject,object_,relation):
    """Conservative shallow syntax test; never return automatic policy approval.

    All three spans must occur in SAME clause with a valid orientation. A
    negator or modal marker in the five tokens immediately before predicate
    takes precedence over an assertion.
    """
    subject=_norm(subject);object_=_norm(object_)
    predicates=[_norm(p) for p in RELATION_PHRASES.get(relation,())]
    if not subject or not object_ or subject==object_ or not predicates:
        return 'RELATION_UNRESOLVED','Missing distinct endpoints or supported relation lexicon.'
    candidates=[];reverse_seen=False
    for clause in _clauses(quote):
        sources=_phrase_spans(clause,subject)
        targets=_phrase_spans(clause,object_)
        if not sources or not targets:continue
        for predicate in predicates:
            for pstart,pend in _phrase_spans(clause,predicate):
                direct=any(s_end<=pstart and t_start>=pend for _,s_end in sources for t_start,_ in targets)
                reverse=any(t_end<=pstart and s_start>=pend for _,t_end in targets for s_start,_ in sources)
                if reverse:reverse_seen=True
                if not direct:continue
                # Avoid using distant unrelated endpoint references.
                best_s=max((s_end for _,s_end in sources if s_end<=pstart),default=-1)
                best_t=min((t_start for t_start,_ in targets if t_start>=pend),default=len(clause))
                if best_s<0 or len(clause[best_s:pstart].split())>7 or len(clause[pend:best_t].split())>7:
                    continue
                window=clause[best_s:pstart].split()[-5:]
                if any(t in NEGATIONS for t in window):
                    candidates.append(('EXPLICIT_NEGATION','Explicit relation negated; human examination required.'))
                elif any(t in PROSPECTIVE for t in window):
                    candidates.append(('PROSPECTIVE_RELATION','Relation described as planned/possible, not an achieved result.'))
                elif any(t in PROSPECTIVE for t in clause[pend:best_t].split()):
                    candidates.append(('RELATION_UNCERTAIN','Modality interrupts relation assertion.'))
                else:
                    candidates.append(('DIRECTIONAL_ASSERTION_CANDIDATE','Direct explicit orientation, but no causal or policy validity proven.'))
    # A negative overrides a positive in the same piece of evidence: contradiction.
    statuses={item[0] for item in candidates}
    if 'EXPLICIT_NEGATION' in statuses and 'DIRECTIONAL_ASSERTION_CANDIDATE' in statuses:
        return 'MIXED_POLARITY_CANDIDATE','Both assertion and negation found: investigate claim scope.'
    for priority in ('EXPLICIT_NEGATION','PROSPECTIVE_RELATION','RELATION_UNCERTAIN','DIRECTIONAL_ASSERTION_CANDIDATE'):
        if priority in statuses:return next(x for x in candidates if x[0]==priority)
    if reverse_seen:return 'REVERSED_DIRECTION_CANDIDATE','The quoted relation appears oriented opposite to the declared edge.'
    return 'RELATION_UNRESOLVED','No same-clause explicit subject–predicate–object direction located.'


def assess_direction(g2:dict,g3:dict,g4:dict)->dict:
    for item in (g2,g3,g4):validate_result(item)
    if g4.get('schema')!='pi.graph_g4.literal_relation_screen.synthetic.v1' or \
       g2.get('schema')!='pi.graph_g2.substantive.synthetic.v1' or \
       g3.get('schema')!='pi.graph_g3.document_grounded.synthetic.v1':
        raise Refused('G5_REQUIRES_G2_G3_G4_ARCHIVES')
    if any(item.get('production_admission')!='DENIED' or item.get('rights_admission')!='SYNTHETIC_ONLY' for item in (g2,g3,g4)):
        raise Refused('G5_SYNTHETIC_ONLY')
    if g4.get('source_g2_sha256')!=g2['report_sha256'] or g4.get('source_g3_sha256')!=g3['report_sha256']:
        raise Refused('G5_LINEAGE_MISMATCH')
    expected=assess_relationships(g2,g3)
    if expected['report_sha256']!=g4['report_sha256']:
        raise Refused('G5_RECOMPUTED_G4_MISMATCH')
    edges=g2['graph']['edges']
    if len(edges)>MAX_EDGES:raise Refused('G5_EDGE_BOUNDARY')
    idx={a['edge_id']:a for a in g4['relationship_assessments']}
    node={n['id']:n for n in g2['graph']['nodes']}
    results=[];findings=[]
    for edge in edges:
        eid=edge['declaration_id'];old=idx[eid]
        source=g3['edge_verification'][eid]
        anchor=source.get('source_evidence')
        if (old['classification']=='SOURCE_GROUNDING_GAP' or not isinstance(anchor,dict)
            or anchor.get('verification_scope')!='EXACT_TEXT_AT_SOURCE_UNIT_ONLY'):
            status='UNVERIFIED_SOURCE';explanation='Missing G3 verified original SourceUnit.'
        else:
            src=node[edge['from']]['title'];dst=node[edge['to']]['title']
            status,explanation=orientation_screen(anchor['excerpt'],src,dst,edge['relation'])
        flags=list(old.get('context_flags') or [])
        priority=('HIGH' if status in ('EXPLICIT_NEGATION','MIXED_POLARITY_CANDIDATE','REVERSED_DIRECTION_CANDIDATE','UNVERIFIED_SOURCE') or flags else
                  'NORMAL' if status in ('DIRECTIONAL_ASSERTION_CANDIDATE','PROSPECTIVE_RELATION') else 'FOLLOW_UP')
        item={'edge_id':eid,'relation':edge['relation'],
              'from_id':edge['from'],'to_id':edge['to'],
              'g4_classification':old['classification'],'classification':status,
              'context_flags':flags,'review_priority':priority,'rationale':explanation,
              'evidence_kind':'SOURCEUNIT_EXACT_TEXT_ONLY',
              'semantic_entailment':'NOT_TESTED','causal_validity':'NOT_TESTED',
              'legal_compliance':'NOT_TESTED','review_required':True}
        results.append(item)
        if isinstance(anchor,dict):
            evidence=[{'document_title':anchor['document_title'],
                       'file_sha256':anchor['document_sha256'],
                       'locator':anchor['locator'],'excerpt':anchor['excerpt'],
                       'source_unit_id':anchor['source_unit_id'],
                       'unit_fingerprint':anchor['unit_fingerprint']}]
        else:
            evidence=[{'document_title':g2['ledger_filename'],
                       'file_sha256':g2['ledger_sha256'],
                       'locator':f'Edges:{eid}',
                       'excerpt':'Original SourceUnit not verified',
                       'source_unit_id':'UNVERIFIED',
                       'unit_fingerprint':_digest([g2['ledger_sha256'],eid])}]
        title=f"{node[edge['from']]['title']} → {node[edge['to']]['title']}"
        findings.append({'id':_digest([eid,g4['report_sha256'],RULE_VERSION])[:24],
                         'indicator':title[:500],'year':node[edge['from']]['year'],
                         'status':status,
                         'rationale':f"{explanation} Review priority {priority}. "
                                     'This is a textual candidate only; reviewer decision required.',
                         'evidence':evidence,'review_status':'PENDING','review_note':''})
    report={'schema':'pi.graph_g5.direction_modality.synthetic.v1',
            'analysis_kind':'DIRECTIONAL_MODALITY_EVIDENCE_TRIAGE',
            'production_admission':'DENIED','rights_admission':'SYNTHETIC_ONLY',
            'review_authority':'NOT_GRANTED',
            'source_g2_sha256':g2['report_sha256'],
            'source_g3_sha256':g3['report_sha256'],
            'source_g4_sha256':g4['report_sha256'],
            'rule_version':RULE_VERSION,'relationship_assessments':results,
            'assessment_metrics':{'edges':len(results),'status_counts':dict(sorted(Counter(x['classification'] for x in results).items())),
                                  'high_priority':sum(x['review_priority']=='HIGH' for x in results),
                                  'review_required':len(results)},
            'findings':findings,
            'boundary_notice':('G5 only triages explicit subject-predicate-object order, negation, '
                'and prospective language within G3-verified synthetic quotes. It is not semantic '
                'entailment, causal proof, legal compliance, or authority to approve policy. '
                'No production use, human review mandatory.')}
    report['report_sha256']=_digest(report)
    validate_result(report)
    return report
