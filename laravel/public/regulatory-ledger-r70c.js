/* Read-only tenant-scoped amendment ledger; user-visible provenance only. */
(() => {
  'use strict'
  const $=id=>document.getElementById(id)
  const add=(parent,tag,cls,value)=>{const node=document.createElement(tag);if(cls)node.className=cls;if(value!==undefined)node.textContent=String(value);parent.appendChild(node);return node}
  const jdih=url=>typeof url==='string' && /^https:\/\/jdih\.jogjakota\.go\.id\//.test(url)
  const pdf=(id,page)=>/^(PERDA|PERWALI)\d+_\d{4}$/.test(id)?'/api/qa-regulatory-r70b/pdf?source_id='+id+(page?'#page='+page:''):''
  function external(parent,title,url){if(!jdih(url))return;const link=add(parent,'a','',title);link.href=url;link.target='_blank';link.rel='noopener noreferrer'}
  function local(parent,title,id,page){const path=pdf(id,page);if(!path)return;const link=add(parent,'a','',title);link.href=path;link.target='_blank';link.rel='noopener noreferrer'}
  function sourceCard(item){
    const card=add($('r70c-ledger'),'article','r70c-card')
    card.setAttribute('aria-label',item.document_role==='AMENDING_REGULATION'?'Peraturan pengubah':'Peraturan dasar')
    const head=add(card,'div','r70c-top');const title=add(head,'div','')
    const label=item.document_role==='AMENDING_REGULATION'?'PERUBAHAN KE-'+item.sequence:item.document_role==='BASE_PENJABARAN_APBD'?'NASKAH DASAR':'PERDA PENDUKUNG'
    add(title,'span','r70c-kicker',label)
    add(title,'h3','',item.title)
    const badge=add(head,'span','r70c-pill',item.parse_status==='OPERATIVE_CLAUSE_LOCATED_FOR_REVIEW'?'PASAL I DITEMUKAN':'PERLU TELAAH')
    if(item.document_role!=='AMENDING_REGULATION')badge.textContent='SUMBER DASAR'
    const pageDetails=[]
    if(item.operative_pasal_i_page)pageDetails.push('Pasal I: halaman PDF '+item.operative_pasal_i_page)
    if(item.enactment_pasal_ii_page)pageDetails.push('Pasal II: halaman PDF '+item.enactment_pasal_ii_page)
    add(card,'p','r70c-meta',pageDetails.length?pageDetails.join(' · '):'Dokumen sumber; tidak ada konsolidasi numerik')
    if(item.explicit_targets?.length){const list=add(card,'div','r70c-target-list');for(const target of item.explicit_targets)add(list,'span','r70c-target',target)}
    else if(item.document_role==='AMENDING_REGULATION')add(card,'p','r70c-blocker','Belum ada rumusan perubahan eksplisit yang lolos pencarian teks terbatas; lihat pasal aslinya.')
    if(item.metadata_warning)add(card,'p','r70c-anomaly','METADATA JDIH PERLU DITELAAH: '+item.metadata_warning)
    const actions=add(card,'div','r70c-actions')
    local(actions,'PDF snapshot',item.id,item.operative_pasal_i_page||1)
    external(actions,'Metadata JDIH',item.metadata_url)
    if(item.evidence?.length){
      const details=add(card,'details','','')
      add(details,'summary','','Lihat '+item.evidence.length+' jejak bukti per halaman')
      const area=add(details,'div','r70c-evidence')
      for(const proof of item.evidence){
        const article=add(area,'article','r70c-proof')
        add(article,'small','',proof.evidence_type+' · PDF halaman '+proof.pdf_page)
        add(article,'p','',proof.excerpt)
        local(article,'Buka halaman sumber',item.id,proof.pdf_page)
      }
    }
  }
  function display(payload){
    if(payload.schema!=='PI_R70C_AMENDMENT_LEDGER_QA_V1'||payload.tenant_id!=='ID-DIY-YOG'||payload.production_admission!=='DENIED'||payload.legal_harmonization!=='NOT_ASSESSED')throw Error('Cakupan atau bukti QA tidak sesuai')
    if(payload.identity_verified!==7||payload.amendment_count!==5||!Array.isArray(payload.source_chain)||payload.source_chain.length!==7)throw Error('Rantai sumber resmi belum lengkap')
    $('r70c-verified').textContent='07/07'
    $('r70c-amendments').textContent=String(payload.amendment_count).padStart(2,'0')
    $('r70c-operative').textContent=String(payload.amendments_with_pasal_i).padStart(2,'0')
    $('r70c-targets').textContent=String(payload.targets_evidenced).padStart(2,'0')
    $('r70c-ledger').replaceChildren()
    payload.source_chain.forEach(sourceCard)
  }
  async function load(){
    const response=await fetch('/api/qa-regulatory-r70c/ledger',{credentials:'same-origin',cache:'no-store'})
    if(response.status===401){location.assign('./login.html');return}
    if(!response.ok)throw Error(response.status===503?'Jejak R70C belum dibangun di Windows. Jalankan BANGUN-JEJAK-PERUBAHAN-PI-R70C.cmd.':'HTTP '+response.status)
    display(await response.json())
  }
  document.addEventListener('DOMContentLoaded',()=>load().catch(error=>{
    const area=$('r70c-ledger');if(area){area.replaceChildren();add(area,'p','r70c-anomaly','Tidak dapat memuat jejak perubahan: '+error.message)}
  }))
})()
