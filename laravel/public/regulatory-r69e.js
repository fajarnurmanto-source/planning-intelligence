/* Institution-scoped R69E Regulatory: authenticated API result, no embedded examples. */
(() => {
  'use strict'
  const root = document
  function putText(id, value) {const el=root.getElementById(id);if(el)el.textContent=String(value)}
  function make(tag, cls, txt, parent) {const el=root.createElement(tag);if(cls)el.className=cls;if(txt!==undefined)el.textContent=txt;parent.appendChild(el);return el}
  function safeLink(parent,label,url){
    // Source URLs only come from an allowlisted server registry or scoped PDF paths.
    const external=url.startsWith('https://')
    const local=url.startsWith('sources_r68/')
    if(!external&&!local)return
    const a=make('a','',label,parent);a.href=url
    if(external){a.target='_blank';a.rel='noopener noreferrer'}
  }
  function renderList(id, records, builder){
    const parent=root.getElementById(id);parent.replaceChildren()
    for(const record of records){const card=make('article','reg-card',undefined,parent);builder(card,record)}
  }
  function show(data){
    if(data.schema!=='PI_R69E_INSTITUTION_REGULATORY_CONTEXT_V1'||data.institution?.tenant_id!=='ID-DIY-YOG')throw Error('Konteks sumber tidak cocok')
    putText('reg-doc-count',data.local_regulations.length.toString().padStart(2,'0'))
    putText('reg-amend-count',data.amendments.length.toString().padStart(2,'0'))
    renderList('reg-docs',data.local_regulations,(card,item)=>{
      make('span','reg-label',item.id+' · SUMBER LOKAL',card)
      make('strong','',item.title,card)
      make('small','',item.evidence_status+' · SHA-256 asli: '+item.original_sha256.slice(0,16)+'…',card)
      const links=make('div','reg-links',undefined,card)
      safeLink(links,'Buka cuplikan PDF',item.excerpt_href)
      safeLink(links,'JDIH resmi',item.issuer_url)
    })
    renderList('reg-amendments',data.amendments,(card,item)=>{
      make('span','reg-label','PERUBAHAN · BELUM DIREKONSILIASI',card)
      make('strong','',item.label,card)
      const links=make('div','reg-links',undefined,card)
      safeLink(links,'Periksa sumber resmi',item.issuer_url)
    })
    putText('reg-amend-warning',data.amendment_warning)
    renderList('reg-upper',data.upper_law_candidates,(card,item)=>{
      make('span','reg-label','KANDIDAT DASAR UMUM · STATUS BELUM DIUJI',card)
      make('strong','',item.title,card)
      const links=make('div','reg-links',undefined,card)
      safeLink(links,'Baca sumber resmi',item.url)
    })
  }
  async function load(){
    const response=await fetch('/api/qa-regulatory-r69e/context',{credentials:'same-origin',cache:'no-store'})
    if(response.status===401){location.replace('./login.html');return}
    if(!response.ok)throw Error('Server menolak konteks regulasi: HTTP '+response.status)
    show(await response.json())
  }
  root.addEventListener('DOMContentLoaded',()=>load().catch(err=>{
    const list=root.getElementById('reg-docs');if(list)list.textContent='Telaah regulasi tidak tersedia: '+err.message
  }))
})()
