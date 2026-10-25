"""G4: evaluate explicit relationship-language adequacy on archived G2+G3 evidence.

Not an NLI/semantic entailment classifier. A verified SourceUnit excerpt remains
just a located statement. G4 checks literal endpoint references and a small,
versioned, relation-specific set of words, including explicit negations.
When uncertain, returns NEEDS_EVIDENCE; never decides legal/causal truth.
No file, network, model or database access in this pure engine.
"""
from __future__ import annotations
from collections import Counter
from hashlib import sha256
import json
import re
import unicodedata

from app.vs1.canonical_bridge import Refused
from app.vs2.reviews import validate_result

LEXICON_VERSION = 'g4-id-linkage-lexicon-v1'
# Ordered phrase patterns: intentionally minimal, conservative and domain-specific.
RELATION_PHRASES = {
    'DECOMPOSES_TO': ('dijabarkan menjadi', 'diturunkan menjadi'),
    'SUPPORTS': ('mendukung', 'menunjang'),
    'IMPLEMENTS': ('melaksanakan', 'merupakan pelaksanaan'),
    'DELIVERED_BY': ('dihasilkan oleh', 'dihasilkan melalui'),
    'CONTRIBUTES_TO': ('berkontribusi terhadap', 'berkontribusi pada'),
    'ALIGNS_WITH': ('selaras dengan', 'sejalan dengan'),
}
NEGATORS = ('tidak', 'bukan', 'belum', 'tidak dapat', 'tidak terbukti')
MAX_EDGES = 500


def _digest(value):
    return sha256(json.dumps(value, ensure_ascii=False, sort_keys=True,
                             separators=(',', ':')).encode('utf-8')).hexdigest()


def _norm(text):
    text=unicodedata.normalize('NFKC', str(text or '')).casefold()
    return ' '.join(re.findall(r'\w+', text, flags=re.UNICODE))


def _contains(haystack, phrase):
    # no substring match inside a longer word
    return bool(phrase) and (' '+phrase+' ') in (' '+haystack+' ')


def lexical_screen(quote, from_title, to_title, relation):
    """Only classifies *expressly written phrases*; absence != relation false."""
    sentence=_norm(quote)
    left=_norm(from_title)
    right=_norm(to_title)
    if not left or not right or not (_contains(sentence,left) and _contains(sentence,right)):
        return 'RELATION_ENTITY_REFERENCE_GAP', 'One/both full entity names absent from grounded quote.'
    cues=[_norm(cue) for cue in RELATION_PHRASES.get(relation,())]
    found=[cue for cue in cues if _contains(sentence,cue)]
    if not found:
        return 'RELATION_PREDICATE_GAP', 'No accepted explicit predicate for declared edge type.'
    negated=False
    for cue in found:
        # Restrict negation to a short window *before* the predicate rather
        # than considering a negation elsewhere in the excerpt.
        for match in re.finditer(r'(?<!\w)'+re.escape(cue)+r'(?!\w)',sentence):
            preceding=sentence[max(0,match.start()-40):match.start()]
            tokens=preceding.split()
            if any(neg == ' '.join(tokens[-len(neg.split()):]) for neg in NEGATORS):
                negated=True
    if negated:
        return 'EXPLICIT_RELATION_NEGATION_CANDIDATE', 'Verified quote explicitly negates its declared relation.'
    return 'EXPLICIT_RELATION_ASSERTION_CANDIDATE', 'Both entities and a relation-specific assertion occur in one verified source quote.'


def assess_relationships(g2:dict, g3:dict)->dict:
    validate_result(g2);validate_result(g3)
    if g2.get('schema')!='pi.graph_g2.substantive.synthetic.v1' or \
       g3.get('schema')!='pi.graph_g3.document_grounded.synthetic.v1':
        raise Refused('G4_EXPECTS_G2_AND_G3_ARCHIVES')
    if g3.get('source_g2_sha256')!=g2['report_sha256'] or \
       g3.get('source_g1_sha256')!=g2['source_g1_sha256'] or \
       g3.get('ledger_sha256')!=g2['ledger_sha256']:
        raise Refused('G4_ARCHIVED_LINEAGE_MISMATCH')
    if g2.get('production_admission')!='DENIED' or g3.get('production_admission')!='DENIED':
        raise Refused('G4_PRODUCTION_BARRIER')
    for role,doc in g3['documents'].items():
        if role not in g2['documents'] or doc['sha256']!=g2['documents'][role]['sha256']:
            raise Refused('G4_SOURCE_DOCUMENT_LINEAGE_MISMATCH')
    nodes={n['id']:n for n in g2['graph']['nodes']}
    edges=g2['graph']['edges']
    if len(edges)>MAX_EDGES:raise Refused('G4_EDGE_LIMIT')
    if {e['declaration_id'] for e in edges}!=set(g3['edge_verification']):
        raise Refused('G4_DECLARATION_SET_MISMATCH')
    assessments=[];findings=[]
    for edge in edges:
        eid=edge['declaration_id']
        src,dst=nodes[edge['from']],nodes[edge['to']]
        grounding=g3['edge_verification'][eid]
        source_ev=grounding.get('source_evidence')
        left_ev=grounding.get('from_node_evidence')
        right_ev=grounding.get('to_node_evidence')
        grounded=(grounding['status']=='DECLARED_RELATION_EXCERPTS_LOCATED' and
                  grounding['quote_status']=='SOURCE_QUOTE_LOCATED' and
                  all(isinstance(v,dict) and v.get('source_unit_id') and v.get('unit_fingerprint')
                      for v in [source_ev,left_ev,right_ev]))
        if not grounded:
            classification='SOURCE_GROUNDING_GAP'
            rationale='G3 has not located the edge and both endpoint excerpts at verified SourceUnits.'
        else:
            classification,rationale=lexical_screen(source_ev['excerpt'],src['title'],dst['title'],edge['relation'])
        mismatch=[]
        if src['scope'].casefold()!=dst['scope'].casefold():mismatch.append('SCOPE')
        if src['year']!=dst['year']:mismatch.append('YEAR')
        if mismatch:rationale+=' Declared context differs: '+', '.join(mismatch)+'.'
        # All classes are *candidates*, not a verified positive/negative truth decision.
        entry={'edge_id':eid,'from_id':edge['from'],'to_id':edge['to'],
               'relation':edge['relation'],'classification':classification,
               'context_flags':mismatch,'rationale':rationale,
               'assertion_scope':'VERIFIED_QUOTE_LITERAL_SCREEN_ONLY',
               'semantic_entailment':'NOT_VERIFIED','causal_validity':'NOT_TESTED',
               'review_required':True,'source_unit_id':(source_ev or {}).get('source_unit_id')}
        assessments.append(entry)
        evidences=[]
        for ev in [source_ev,left_ev,right_ev]:
            if not isinstance(ev,dict):continue
            evidences.append({'document_title':ev['document_title'],
                              'file_sha256':ev['document_sha256'],
                              'locator':ev['locator'],'excerpt':ev['excerpt'],
                              'source_unit_id':ev['source_unit_id'],
                              'unit_fingerprint':ev['unit_fingerprint']})
        if not evidences:
            evidences=[{'document_title':g2['ledger_filename'],
                        'file_sha256':g2['ledger_sha256'],
                        'locator':'Edges:'+eid,'excerpt':'No grounded quote available',
                        'source_unit_id':'UNVERIFIED',
                        'unit_fingerprint':_digest([g2['ledger_sha256'],eid])}]
        findings.append({'id':_digest([eid,g3['report_sha256'],LEXICON_VERSION])[:24],
                         'indicator':f"{src['title']} → {dst['title']}"[:500],
                         'year':src['year'],'status':classification,
                         'rationale':rationale+' Human review required; no policy validity inferred.',
                         'evidence':evidences,'review_status':'PENDING','review_note':''})
    counts=dict(sorted(Counter(a['classification'] for a in assessments).items()))
    result={'schema':'pi.graph_g4.literal_relation_screen.synthetic.v1',
            'production_admission':'DENIED','rights_admission':'SYNTHETIC_ONLY',
            'review_authority':'NOT_GRANTED','source_g2_sha256':g2['report_sha256'],
            'source_g3_sha256':g3['report_sha256'],
            'lexicon_version':LEXICON_VERSION,
            'analysis_kind':'BOUNDED_LITERAL_RELATION_EVIDENCE_SCREEN',
            'relationship_assessments':assessments,
            'assessment_metrics':{'edges':len(edges),'status_counts':counts,
                'review_required':len(assessments)},'findings':findings,
            'boundary_notice':('G4 only checks literal references, relation-specific phrases, and '
                'explicit negation in G3-verified synthetic SourceUnit quotations. '
                'A located statement is not semantic entailment, policy alignment, '
                'causal impact or legal compliance. Every result requires human review. '
                'No production authority.')}
    result['report_sha256']=_digest(result)
    validate_result(result)
    return result
