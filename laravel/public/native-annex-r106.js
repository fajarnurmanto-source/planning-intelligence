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
button.addEventListener('click',async()=>{
 button.disabled=true;status.textContent='Memeriksa bukti PDF lokal...';
 summary.textContent='';body.replaceChildren();links.replaceChildren();
 try{
  const response=await fetch('/api/qa-superior-r106/annex-fiscal-review',
   {credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
  if(!response.ok)throw Error(response.status===401?'Silakan login di PI.':'ABSTAIN: PDF belum tersedia atau fitur belum diaktifkan.');
  const data=await response.json();
  if(data.schema!=='PI_R106_NATIVE_PDF_ANNEX_REVIEW_V1'||data.production_admission!=='DENIED'
    ||data.numeric_admission!==0||data.row_count!==6||!Array.isArray(data.rows))
   throw Error('Kontrak bukti R106 tidak sesuai.');
  status.textContent='Kandidat ekstraksi lokal berhasil. Perlu review sumber dan hukum.';
  summary.textContent='Enam rekening: Rp'+money(data.derived_leaf_total_idr)+' (kandidat; bukan angka yang sudah disahkan).';
  for(const [source,page,label,identityId] of [
   ['base',data.source_documents.base.source_pdf_page,'Perwal 74/2025','YOG-APBD-DETAIL-2026'],
   ['change3',data.source_documents.third_amendment.source_pdf_page,'Perwal 12/2026','YOG-APBD-DETAIL-CHANGE3-2026']]){
   const link=document.createElement('a');link.href='/api/qa-superior-r106/annex-pdf?source='+source+'#page='+encodeURIComponent(page);
   link.target='_blank';link.rel='noopener noreferrer';link.textContent=label+' · judul belum terverifikasi · PDF halaman '+page;
   if(window.PIRegulatoryIdentity)window.PIRegulatoryIdentity.resolve(identityId,label).then(title=>{link.textContent=title+' · PDF halaman '+page;});
   links.append(link);
  }
  for(const r of data.rows){
   const tr=document.createElement('tr');
   for(const value of [r.account_code,money(r.base_idr),money(r.third_amendment_after_idr),
     money(r.third_amendment_delta_idr),'PDF '+r.base_page+' / '+r.change_page,'Perlu review']){
    const td=document.createElement('td');td.textContent=value;tr.append(td);
   }
   body.append(tr);
  }
 }catch(error){status.textContent=error.message||'R106 ABSTAIN';}
 finally{button.disabled=false;}
});
})();