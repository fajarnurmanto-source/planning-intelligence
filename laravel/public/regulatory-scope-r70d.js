/* Tenant-gated, source-page linked operative clause candidates; no adjudication. */
(() => {
 'use strict'
 const $=id=>document.getElementById(id)
 const add=(parent,tag,cls,value)=>{const e=document.createElement(tag);if(cls)e.className=cls;if(value!==undefined)e.textContent=String(value);parent.appendChild(e);return e}
 const pdf=(id,page)=>/^(PERDA|PERWALI)\d+_\d{4}$/.test(id)&&Number.isInteger(page)&&page>0?'/api/qa-regulatory-r70b/pdf?source_id='+id+'#page='+page:''
 function link(parent,id,page,label){const url=pdf(id,page);if(!url)return;const a=add(parent,'a','r70d-source-link',label);a.href=url;a.target='_blank';a.rel='noopener noreferrer'}
 function proof(parent,sourceId,p){const box=add(parent,'div','r70d-proof');add(box,'small','',p.evidence_type+' · PDF halaman '+p.pdf_page);add(box,'p','',p.excerpt);link(box,sourceId,p.pdf_page,'Buka halaman bukti ↗')}
 function source(item){if(item.sequence===null||item.sequence===0)return
  const card=add($('r70d-timeline'),'article','r70d-amendment')
  const head=add(card,'div','r70d-amendment-head');const left=add(head,'div','');add(left,'small','r70d-kicker','PERUBAHAN KE-'+item.sequence);add(left,'h3','',item.title)
  add(head,'span','r70d-status '+(item.status==='TWO_CLAUSES_TRACED_REVIEW_REQUIRED'?'r70d-good':'r70d-alert'),item.status==='TWO_CLAUSES_TRACED_REVIEW_REQUIRED'?'DUA LAMPIRAN TERLACAK':'TELAAH DIPERLUKAN')
  if(item.pasal_i_pdf_page){const p=add(card,'p','r70d-mini','Pasal I: halaman '+item.pasal_i_pdf_page+' · Pasal II: halaman '+item.pasal_ii_pdf_page);link(p,item.id,item.pasal_i_pdf_page,'Buka Pasal I ↗')}
  const grid=add(card,'div','r70d-grid')
  for(const target of item.targets||[]){const t=add(grid,'section','r70d-target');add(t,'small','r70d-kicker','BUTIR '+target.item+' · '+target.target);add(t,'h4','',target.detected_change_verb?'Kata kerja '+target.detected_change_verb.toLowerCase()+' terdeteksi':'Kata kerja perubahan belum terbaca')
   add(t,'p','r70d-mini','Awal: PDF hal. '+target.start_pdf_page+' · Penutup: '+(target.verb_pdf_page?'hal. '+target.verb_pdf_page:'belum ditemukan')+' · Rentang: '+(target.operative_page_span||'—')+' halaman')
   if(target.target==='LAMPIRAN II')add(t,'p','r70d-mini','Kandidat kode organisasi: '+target.organizational_code_count)
   if(target.organizational_codes?.length){const details=add(t,'details','');add(details,'summary','','Lihat kode organisasi yang terindeks');add(details,'p','r70d-codes',target.organizational_codes.join(' · '))}
   const details=add(t,'details','');add(details,'summary','','Lihat bukti pembuka dan penutup');for(const p of target.evidence||[])proof(details,item.id,p)
  }
  if(!item.targets?.length)add(card,'p','r70d-error','Klausul operatif tidak lengkap pada indeks. Buka naskah untuk telaah manual.')
  if(item.dates?.length){const d=add(card,'p','r70d-mini','Tanggal yang terbaca dari naskah: ');for(const date of item.dates){add(d,'span','',date.type+': '+date.date_as_printed+' · ');link(d,item.id,date.evidence.pdf_page,'PDF hal. '+date.evidence.pdf_page+' ↗')}}
  if(item.metadata_warning)add(card,'p','r70d-error','ANOMALI METADATA: '+item.metadata_warning)
 }
 function display(data){if(data.schema!=='PI_R70D_OPERATIVE_SCOPE_QA_V1'||data.tenant_id!=='ID-DIY-YOG'||data.production_admission!=='DENIED'||data.numeric_comparison!=='BLOCKED')throw Error('Konteks QA tidak sah')
  if(data.source_count!==7||data.amendment_count!==5||!Array.isArray(data.timeline)||data.timeline.length!==7)throw Error('Rantai sumber belum lengkap')
  $('r70d-sources').textContent='07/07';$('r70d-amendments').textContent='05/05';$('r70d-both').textContent=String(data.amendments_with_both_annex_targets).padStart(2,'0')+'/05';$('r70d-clauses').textContent=String(data.explicit_target_clauses).padStart(2,'0')
  $('r70d-timeline').replaceChildren();data.timeline.forEach(source)
 }
 async function init(){const r=await fetch('/api/qa-regulatory-r70d/scope',{credentials:'same-origin',cache:'no-store'});if(r.status===401){location.assign('./login.html');return}if(!r.ok)throw Error(r.status===503?'Peta R70D belum selesai dibangun. Jalankan BANGUN-CAKUPAN-REGULASI-PI-R70D.cmd.':'HTTP '+r.status);display(await r.json())}
 document.addEventListener('DOMContentLoaded',()=>init().catch(err=>{const area=$('r70d-timeline');if(area){area.replaceChildren();add(area,'p','r70d-error','Bukti belum dapat dimuat: '+err.message)}}))
})()
