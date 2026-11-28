(() => {
'use strict';
const button=document.getElementById('r106-run'),status=document.getElementById('r106-status'),
summary=document.getElementById('r106-summary'),body=document.getElementById('r106-rows'),
links=document.getElementById('r106-source-links');
if(!button||!status||!summary||!body||!links)return;
document.addEventListener('DOMContentLoaded',()=>{
 for(const [id,key,fallback] of [['per01-base-title','YOG-APBD-DETAIL-2026','Perwal 74/2025'],['per01-change-title','YOG-APBD-DETAIL-CHANGE3-2026','Perwal 12/2026']]){
  const el=document.getElementById(id);if(el&&window.PIRegulatoryIdentity)window.PIRegulatoryIdentity.resolve(key,fallback).then(title=>{el.textContent=title;});
 }
});
const money=n=>new Intl.NumberFormat('id-ID').format(n);
const sources=[
 ['base','PERWAL-74-2025','E4AAA45F33B7254FDB7BBE3A0BC456999FD2CE3E19D8C07B874C640A5E1DEE24','Perwal 74/2025','YOG-APBD-DETAIL-2026'],
 ['third_amendment','PERWAL-12-2026','4E5194E6987468E30406911B277BACCA73506B3BF93BE413C0C19784AE3825EA','Perwal 12/2026','YOG-APBD-DETAIL-CHANGE3-2026']
];
const pageOK=(n,d)=>Number.isInteger(n)&&n>=1&&n<=d.pdf_page_count;
const amountOK=n=>Number.isSafeInteger(n)&&n>=0;
const textOK=s=>typeof s==='string'&&s.trim().length>0&&s.length<=2000;
const hold=()=>{throw Error('Kontrak bukti R106 tidak sesuai. Seluruh hasil ditahan.')};
function checked(data){
 if(data?.schema!=='PI_R106_NATIVE_PDF_ANNEX_REVIEW_V1'||data.production_admission!=='DENIED'
   ||data.numeric_admission!==0||data.row_count!==6||!Array.isArray(data.rows)||data.rows.length!==6
   ||data.case_id!=='YOG-2026-PERSAMPAHAN'||data.subactivity_code!=='2.11.11.2.01.0004'
   ||data.reconciliation!=='SIX_LEAF_ACCOUNTS_AND_NUMBERS_MATCH_ONLY'
   ||data.origin_signature_and_complete_annex!=='REVIEW_REQUIRED'||data.later_amendments_13_15_2026!=='NOT_RECONCILED'
   ||data.rka_dpa_realization!=='NOT_VERIFIED'||data.human_review!=='NOT_REQUESTED'
   ||data['2027_indicator_equivalence']!=='ABSTAIN'||data.writes_performed!==false)hold();
 for(const [key,instrument,sha] of sources){
  const d=data.source_documents?.[key];
  if(!d||d.instrument!==instrument||d.sha256!==sha||d.subactivity_code!==data.subactivity_code
    ||!Number.isInteger(d.pdf_page_count)||d.pdf_page_count<2||d.pdf_page_count>2200||!pageOK(d.source_pdf_page,d)
    ||d.text_layer!=='AVAILABLE_LOCAL'||d.legal_identity!=='HEADER_TEXT_MATCH_ONLY_NOT_AUTHENTICATED'
    ||!d.rows||typeof d.rows!=='object'||Array.isArray(d.rows)||Object.keys(d.rows).length!==6)hold();
 }
 const a=data.source_documents.base,b=data.source_documents.third_amendment;
 const seen=new Set();let total=0;
 for(const r of data.rows){
  if(!r||typeof r.account_code!=='string'||!/^5(?:\.\d+){4}\.\d{5}$/.test(r.account_code)||seen.has(r.account_code)
    ||![r.base_idr,r.third_amendment_before_idr,r.third_amendment_after_idr].every(amountOK)
    ||r.base_idr!==r.third_amendment_before_idr||r.third_amendment_before_idr!==r.third_amendment_after_idr
    ||r.third_amendment_delta_idr!==0||r.row_review!=='TEXT_LAYER_CANDIDATE_NEEDS_VISUAL_REVIEW'
    ||!pageOK(r.base_page,a)||!pageOK(r.change_page,b)||r.base_page<a.source_pdf_page||r.base_page>a.source_pdf_page+1
    ||r.change_page<b.source_pdf_page||r.change_page>b.source_pdf_page+1||!textOK(r.base_excerpt)||!textOK(r.change_excerpt))hold();
  const ar=a.rows[r.account_code],br=b.rows[r.account_code];
  if(!ar||!br||ar.code!==r.account_code||br.code!==r.account_code||ar.amount_idr!==r.base_idr
    ||br.before_idr!==r.third_amendment_before_idr||br.after_idr!==r.third_amendment_after_idr||br.delta_idr!==r.third_amendment_delta_idr
    ||ar.page!==r.base_page||br.page!==r.change_page||ar.excerpt!==r.base_excerpt||br.excerpt!==r.change_excerpt)hold();
  seen.add(r.account_code);total+=r.third_amendment_after_idr;
 }
 if(!Number.isSafeInteger(total)||total!==data.derived_leaf_total_idr||total!==1020366250)hold();
 return data;
}
const element=(tag,text)=>{const el=document.createElement(tag);if(text!==undefined)el.textContent=text;return el};
function pdfLink(source,page,label){
 const a=element('a',label+' · PDF halaman '+page);
 a.href='/api/qa-superior-r106/annex-pdf?source='+source+'#page='+encodeURIComponent(page);
 a.target='_blank';a.rel='noopener noreferrer';return a;
}
let busy=false;
button.addEventListener('click',async()=>{
 if(busy)return;
 busy=true;let committing=false;button.disabled=true;status.textContent='Memeriksa bukti PDF lokal...';
 summary.textContent='';body.replaceChildren();links.replaceChildren();
 try{
  const response=await fetch('/api/qa-superior-r106/annex-fiscal-review',
   {credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
  if(!response.ok){
   const messages={401:'Silakan login di PI',403:'Akses pemeriksaan ditolak',419:'Sesi pemeriksaan berakhir',
    422:'Parameter pemeriksaan tidak valid',429:'Batas pemeriksaan sementara tercapai',
    503:'Bukti PDF lokal atau pemeriksa belum tersedia. Hasil ditahan'};
   const retry=response.headers?.get('Retry-After');
   const delay=response.status===429&&/^[0-9]{1,4}$/.test(retry||'')&&Number(retry)>0&&Number(retry)<=3600
    ? ' Coba lagi setelah '+Number(retry)+' detik.' : '';
   throw Error((messages[response.status]||'Pemeriksaan tidak dapat diselesaikan')+' (HTTP '+response.status+').'+delay);
  }
  const data=checked(await response.json());
  // Stage every row before displaying any evidence. No partial output on rejection.
  const stagedLinks=[],stagedRows=[];
  for(const [key,instrument,sha,label,identityId] of sources){
   const d=data.source_documents[key],source=key==='base'?'base':'change3';
   const card=element('article');card.className='pi-fiscal-source';
   const a=pdfLink(source,d.source_pdf_page,label+' · judul belum terverifikasi');
   card.append(a,element('p','Salinan cocok dengan pin SHA-256; keaslian hukum dan kelengkapan lampiran belum disahkan.'));
   const audit=element('details');audit.append(element('summary','Identitas berkas dan halaman'),element('p',instrument+' · SHA-256 '+sha),element('p','Halaman fisik '+d.source_pdf_page+' dari '+d.pdf_page_count+'; halaman cetak '+(d.printed_appendix_page??'belum diketahui')));
   card.append(audit);
   if(window.PIRegulatoryIdentity)window.PIRegulatoryIdentity.resolve(identityId,label).then(title=>{a.textContent=title+' · PDF halaman '+d.source_pdf_page;}).catch(()=>{});
   stagedLinks.push(card);
  }
  for(const r of data.rows){
   const tr=element('tr');
   for(const value of [r.account_code,money(r.base_idr),money(r.third_amendment_before_idr),money(r.third_amendment_after_idr),money(r.third_amendment_delta_idr)])tr.append(element('td',value));
   const proof=element('td');proof.className='pi-fiscal-row-proof';
   proof.append(pdfLink('base',r.base_page,'Naskah awal'),pdfLink('change3',r.change_page,'Perubahan III'));
   const detail=element('details');detail.append(element('summary','Kutipan ekstraksi'),element('p','Awal: '+r.base_excerpt),element('p','Perubahan III: '+r.change_excerpt));proof.append(detail);
   tr.append(proof,element('td','Kandidat · perlu pemeriksaan visual'));
   stagedRows.push(tr);
  }
  committing=true;links.append(...stagedLinks);body.append(...stagedRows);
  summary.textContent='Jumlah enam rekening kandidat: Rp'+money(data.derived_leaf_total_idr)+'. Hasil rekonsiliasi ekstraksi pada dua naskah; bukan total anggaran yang berlaku atau angka realisasi.';
  status.textContent='Enam rekening kandidat ditemukan. Perubahan lanjutan, kewenangan dan angka belum disahkan.';
 }catch(error){
  if(committing){summary.textContent='';body.replaceChildren();links.replaceChildren();}
  const msg=error?.message||'';
  status.textContent=/^(Silakan login di PI|Akses pemeriksaan ditolak|Sesi pemeriksaan berakhir|Parameter pemeriksaan tidak valid|Batas pemeriksaan sementara tercapai|Bukti PDF lokal atau pemeriksa belum tersedia|Pemeriksaan tidak dapat diselesaikan|Kontrak bukti R106 tidak sesuai)/.test(msg)?msg:'Bukti belum dapat diperiksa. Periksa arsip atau jalankan pemeriksaan kembali. Tidak ada hasil yang disahkan.';
 }
 finally{busy=false;button.disabled=false;}
});
})();