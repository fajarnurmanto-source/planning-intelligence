"""Offline, synthetic-only reviewer preview and evidence export.

Review button status is not authenticated institutional approval.
Never use this report as production evidence or official finding.
"""
from __future__ import annotations
import html
import json
from pathlib import Path


def render_synthetic_review(result:dict) -> str:
    if result.get('production_admission')!='DENIED' or result.get('rights_admission')!='SYNTHETIC_ONLY':
        raise ValueError('REVIEW_PREVIEW_REQUIRES_SYNTHETIC_CANDIDATE')
    payload=json.dumps(result,ensure_ascii=False).replace('<','\\u003c').replace('>','\\u003e').replace('&','\\u0026')
    title=html.escape(result['left']['title']+' vs '+result['right']['title'])
    return '''<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>PI VS1 Review</title><style>
body{font:16px system-ui;margin:auto;max-width:980px;padding:28px;color:#19202b;background:#f7f9fc}h1{font-size:26px}section{border:1px solid #d9e2f0;padding:20px;background:#fff;margin:12px 0;border-radius:12px}button,select{font:inherit;padding:8px;margin:8px 8px 0 0}.warning{border-left:5px solid #d08417}.meta{color:#546275;font-size:13px}.evidence{border-left:3px solid #adbdd3;margin:10px 0;padding-left:10px;white-space:pre-wrap;overflow-wrap:anywhere}.status{font-weight:650}
</style></head><body><h1>Planning Intelligence · VS1 synthetic review</h1><p class="meta">NOT PRODUCTION · NOT LEGAL/CAUSAL FINDING · REVIEW IS TEST-ONLY</p><h2>'''+title+'''</h2><div id="findings"></div><button id="download">Ekspor hasil review JSON</button>
<script>const data='''+payload+''';const container=document.getElementById('findings');
for(const finding of data.findings){const section=document.createElement('section');section.className='warning';const title=document.createElement('h3');title.textContent=finding.indicator+' ('+finding.year+')';section.append(title);const reason=document.createElement('p');reason.textContent=finding.status+' — '+finding.rationale;section.append(reason);
for(const ev of finding.evidence){const e=document.createElement('div');e.className='evidence';e.textContent=ev.document_title+' | '+ev.locator+' | SourceUnit '+ev.source_unit_id+'\n'+ev.excerpt;section.append(e)}
const label=document.createElement('label');label.textContent='Keputusan simulasi: ';const select=document.createElement('select');for(const v of ['PENDING','CONFIRMED_SYNTHETIC','REJECTED_SYNTHETIC','NEEDS_EVIDENCE']){const opt=document.createElement('option');opt.value=v;opt.textContent=v;select.append(opt)}select.addEventListener('change',()=>finding.review_status=select.value);label.append(select);section.append(label);container.append(section)}
document.getElementById('download').onclick=()=>{const output=JSON.stringify({...data,review_authority:'NOT_GRANTED',review_origin:'LOCAL_SYNTHETIC_SIMULATION'},null,2);const blob=new Blob([output],{type:'application/json'});const link=document.createElement('a');link.href=URL.createObjectURL(blob);link.download='PI_VS1_SYNTHETIC_REVIEW.json';link.click();URL.revokeObjectURL(link.href)};
</script></body></html>'''


def export_report(result:dict, directory:Path):
    directory.mkdir(parents=True,exist_ok=True)
    json_path=directory/'PI_VS1_SYNTHETIC_ANALYSIS.json'
    html_path=directory/'PI_VS1_SYNTHETIC_REVIEW.html'
    json_path.write_text(json.dumps(result,ensure_ascii=False,indent=2,sort_keys=True)+'\n',encoding='utf-8')
    html_path.write_text(render_synthetic_review(result),encoding='utf-8')
    return json_path,html_path
