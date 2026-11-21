/* R70B · tenant-gated read-only snapshot catalog; never infer legal validity. */
(() => {
  'use strict'
  const get=id=>document.getElementById(id)
  function add(parent,tag,cls,value){const node=document.createElement(tag);if(cls)node.className=cls;if(value!==undefined)node.textContent=value;parent.appendChild(node);return node}
  function link(parent,label,url,local=false){
    if(!local && !/^https:\/\/jdih\.jogjakota\.go\.id\//.test(url))return
    if(local && !/^\/api\/qa-regulatory-r70b\/pdf\?source_id=PER(?:DA|WALI)[0-9]+_[0-9]{4}$/.test(url))return
    const a=add(parent,'a','',label);a.href=url
    if(!local){a.target='_blank';a.rel='noopener noreferrer'}
  }
  function display(result){
    if(result.schema!=='PI_R70B_OFFICIAL_SOURCE_SNAPSHOTS_QA_V1'||result.tenant_id!=='ID-DIY-YOG'||result.legal_harmonization!=='NOT_ASSESSED'||result.production_admission!=='DENIED')throw Error('Konteks QA sumber tidak sesuai')
    if(!Array.isArray(result.sources)||result.sources.length!==7)throw Error('Daftar sumber tidak lengkap')
    get('r70b-total').textContent=String(result.total).padStart(2,'0')
    get('r70b-verified').textContent=String(result.verified).padStart(2,'0')
    get('r70b-anomalies').textContent=String(result.metadata_anomalies).padStart(2,'0')
    const area=get('r70b-sources');area.replaceChildren()
    for(const item of result.sources){
      const card=add(area,'article','r70b-item')
      add(card,'span','r70b-kicker',(item.sequence===null?'PERDA / ANGGARAN':(item.sequence===0?'PERWALI / NASKAH DASAR':'PERUBAHAN KE-'+item.sequence)))
      add(card,'strong','',item.title)
      const ready=item.status==='ACQUIRED_IDENTITY_VERIFIED'
      const state=ready?'SNAPSHOT TERINDEKS':item.status==='NOT_YET_ACQUIRED'?'BELUM DIUNDUH':item.status==='ACQUIRED_NEEDS_REVIEW'?'TERUNDUH · PERLU VERIFIKASI':'REKAMAN SUMBER PERLU DITELAAH'
      add(card,'span','r70b-status'+(ready?' ready':''),state)
      if(typeof item.pdf_pages==='number')add(card,'small','',item.text_pages+' dari '+item.pdf_pages+' halaman memiliki teks · identitas: '+item.identity)
      if(item.identity_method){
        const method=item.identity_method==='PDFIUM_RENDER_TESSERACT_OCR'?'OCR VISUAL PDF':item.identity_method==='PDFIUM_NATIVE_TEXT'?'TEKS PDFIUM':item.identity_method==='PYPDF_NATIVE_TEXT'?'TEKS PDF':'METODE TERCATAT'
        add(card,'small','', 'Bukti identitas: '+method+(item.identity_evidence_page?' · halaman '+item.identity_evidence_page:''))
      }
      if(item.sha256)add(card,'small','','SHA-256: '+item.sha256)
      if(item.observed_at_utc)add(card,'small','','Diambil: '+item.observed_at_utc+' (UTC)')
      if(item.metadata_warning)add(card,'p','r70b-anomaly','METADATA PERLU DICEK: '+item.metadata_warning)
      const actions=add(card,'div','r70b-actions')
      link(actions,'Buka JDIH',item.metadata_url)
      link(actions,'Naskah resmi',item.source_url)
      if(item.has_local_pdf)link(actions,'Snapshot lokal', '/api/qa-regulatory-r70b/pdf?source_id='+item.id, true)
      if(!item.has_local_pdf)add(card,'small','','Jalankan sinkronisasi di Windows untuk menyimpan dan mengindeks sumber.')
    }
  }
  async function load(){
    const response=await fetch('/api/qa-regulatory-r70b/sources',{credentials:'same-origin',cache:'no-store'})
    if(response.status===401){location.assign('./login.html');return}
    if(!response.ok)throw Error('Akses atau sumber tidak tersedia: HTTP '+response.status)
    display(await response.json())
  }
  document.addEventListener('DOMContentLoaded',()=>load().catch(error=>{
    const target=get('r70b-sources');if(target){target.replaceChildren();add(target,'p','r70b-anomaly','Data QA tidak dapat disajikan: '+error.message)}
  }))
})()
