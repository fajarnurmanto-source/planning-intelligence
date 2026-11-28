/* F1/SDEA-01: read-only snapshot catalogue. File integrity is not legal admission. */
(() => {
  'use strict'
  const get=id=>document.getElementById(id)
  const ids=['r70b-total','r70b-verified','r70b-anomalies']
  let loading=false
  function add(parent,tag,cls,value){const node=document.createElement(tag);if(cls)node.className=cls;if(value!==undefined)node.textContent=value;parent.appendChild(node);return node}
  function link(parent,label,url,local=false){
    if(typeof url!=='string')return
    if(!local && !/^https:\/\/jdih\.jogjakota\.go\.id\//.test(url))return
    if(local && !/^\/api\/qa-regulatory-r70b\/pdf\?source_id=PER(?:DA|WALI)[0-9]+_[0-9]{4}$/.test(url))return
    const a=add(parent,'a','',label);a.href=url
    if(!local){a.target='_blank';a.rel='noopener noreferrer'}
  }
  function validate(result){
    if(result?.schema!=='PI_R70B_OFFICIAL_SOURCE_SNAPSHOTS_QA_V1'||result.tenant_id!=='ID-DIY-YOG'||result.legal_harmonization!=='NOT_ASSESSED'||result.numeric_comparison!=='BLOCKED'||result.production_admission!=='DENIED')throw Error('CONTRACT_HOLD')
    if(!Array.isArray(result.sources)||result.sources.length!==7||result.total!==result.sources.length)throw Error('CONTRACT_HOLD')
    const seen=new Set()
    for(const item of result.sources){
      if(!item||typeof item.id!=='string'||!/^PER(?:DA|WALI)[0-9]+_[0-9]{4}$/.test(item.id)||seen.has(item.id)
        ||typeof item.title!=='string'||!item.title.trim()||typeof item.has_local_pdf!=='boolean'
        ||!(/^[A-Fa-f0-9]{64}$/.test(item.sha256||''))||item.review_status!=='REVIEW_REQUIRED'
        ||(item.has_local_pdf&&(item.status!=='ACQUIRED_IDENTITY_VERIFIED'||item.identity!=='PDF_SHA256_MATCHES_LEDGER'))
        ||(!item.has_local_pdf&&(item.status!=='ACQUIRED_NEEDS_REVIEW'||item.identity!=='NOT_CHECKED_LOCALLY')))throw Error('CONTRACT_HOLD')
      seen.add(item.id)
    }
    if(result.verified!==result.sources.filter(x=>x.has_local_pdf).length
      ||result.metadata_anomalies!==result.sources.filter(x=>typeof x.metadata_warning==='string'&&x.metadata_warning.trim()).length)throw Error('CONTRACT_HOLD')
    return result
  }
  function clear(){
    ids.forEach(id=>get(id).textContent='—')
    get('r70b-sources').replaceChildren()
  }
  function display(raw){
    const result=validate(raw)
    get('r70b-total').textContent=String(result.total)
    get('r70b-verified').textContent=String(result.verified)
    get('r70b-anomalies').textContent=String(result.metadata_anomalies)
    const area=get('r70b-sources');area.replaceChildren()
    for(const item of result.sources){
      const card=add(area,'article','r70b-item')
      add(card,'span','r70b-kicker',item.sequence===null?'Perda / Anggaran':item.sequence===0?'Perwali / Naskah dasar':'Perubahan ke-'+item.sequence)
      add(card,'h3','',item.title)
      const ready=item.has_local_pdf
      add(card,'span','r70b-status'+(ready?' ready':''),ready?'Salinan lokal cocok hash':'Salinan lokal belum terverifikasi')
      add(card,'p','','Keberlakuan, cakupan norma dan hubungan hukum belum disahkan.')
      if(item.metadata_warning)add(card,'p','r70b-anomaly','Metadata perlu diperiksa: '+item.metadata_warning)
      const actions=add(card,'div','r70b-actions')
      link(actions,'Buka JDIH',item.metadata_url)
      link(actions,'Naskah resmi',item.source_url)
      if(ready)link(actions,'Unduh salinan lokal terverifikasi', '/api/qa-regulatory-r70b/pdf?source_id='+item.id, true)
      else add(card,'small','','Salinan lokal belum dapat dibuka. Periksa sumber resmi atau hubungi pengelola arsip.')
      const detail=add(card,'details','r70b-provenance')
      add(detail,'summary','','Identitas dan riwayat bukti')
      add(detail,'p','','Hash arsip yang diharapkan: '+item.sha256)
      add(detail,'p','',ready?'Berkas lokal telah diperiksa terhadap hash arsip pada pemuatan daftar ini.':'Hash tercatat bukan bukti berkas lokal telah diperiksa.')
      add(detail,'p','','ID sumber: '+item.id)
      add(detail,'p','','Status sumber: '+item.identity+' · '+item.review_status)
      add(detail,'p','','Cakupan: pemeriksaan berkas; bukan pemeriksaan seluruh halaman, hak penggunaan, atau legalitas.')
    }
    get('r70b-state').textContent=result.verified+' dari '+result.total+' salinan lokal cocok dengan hash arsip. Pemeriksaan hukum dan angka tetap ditahan.'
  }
  async function load(){
    if(loading)return
    loading=true
    const area=get('r70b-sources'),button=get('r70b-reload'),state=get('r70b-state')
    clear();area.setAttribute('aria-busy','true');button.disabled=true
    state.textContent='Memeriksa daftar sumber…'
    try{
      const response=await fetch('/api/qa-regulatory-r70b/sources',{credentials:'same-origin',cache:'no-store'})
      if(response.status===401){state.textContent='Sesi masuk berakhir. Masuk kembali.';location.assign('./login.html');return}
      if(response.status===403){state.textContent='Akses daftar sumber tidak diberikan. Hubungi pengelola akun.';return}
      if(!response.ok)throw Error('SOURCE_UNAVAILABLE')
      display(await response.json())
    }catch(error){
      clear()
      state.textContent='Daftar sumber belum dapat diperiksa. Periksa ketersediaan arsip atau pilih Periksa ulang daftar sumber. Belum dapat menyimpulkan hasil.'
      add(area,'p','r70b-anomaly','Tidak ada hasil yang dapat ditampilkan dari pemeriksaan ini.')
    }finally{loading=false;area.setAttribute('aria-busy','false');button.disabled=false}
  }
  document.addEventListener('DOMContentLoaded',()=>{get('r70b-reload').addEventListener('click',load);load()})
})()
