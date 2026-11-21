/* R70A · all data supplied by authenticated, tenant-scoped QA endpoint. */
(() => {
  'use strict'
  const el = id => document.getElementById(id)
  const add = (tag,cls,txt,parent) => {
    const node=document.createElement(tag)
    if(cls)node.className=cls
    if(txt!==undefined)node.textContent=txt
    parent.appendChild(node)
    return node
  }
  const safeLink = (parent,label,value) => {
    if(typeof value!=='string')return
    const isOfficial=/^https:\/\/(?:peraturan\.go\.id|www\.peraturan\.go\.id|peraturan\.bpk\.go\.id|jdih\.jogjakota\.go\.id)\//.test(value)
    const isLocal=/^\.\/(?:sources_r68\/r68_yog_(?:apbd|penjabaran_apbd|renja_dlh)_selected_pages\.pdf|uu12-2011-r70a\.pdf)(?:#page=\d+)?$/.test(value)
    if(!isOfficial&&!isLocal)return
    const a=add('a','',label,parent);a.href=value
    if(isOfficial){a.target='_blank';a.rel='noopener noreferrer'}
  }
  const clean = container => container.replaceChildren()
  function show(data){
    if(data.schema!=='PI_R70A_REGULATORY_LEGAL_TRACE_QA_V1' || data.tenant_id!=='ID-DIY-YOG' || data.decision!=='ABSTAIN')throw Error('Konteks audit atau status pengamanan salah')
    if(!Array.isArray(data.nodes)||!Array.isArray(data.relationships)||!Array.isArray(data.findings)||!Array.isArray(data.amendment_timeline))throw Error('Struktur audit tidak lengkap')
    el('trace-nodes').textContent=String(data.nodes.length).padStart(2,'0')
    el('trace-edges').textContent=String(data.relationships.length).padStart(2,'0')
    el('trace-amends').textContent=String(data.amendment_timeline.length).padStart(2,'0')
    const nodes=new Map(data.nodes.map(node=>[node.id,node]))
    const links=el('trace-relationships');clean(links)
    for(const edge of data.relationships){
      const card=add('article','trace-link',undefined,links)
      add('span','trace-kind',edge.kind.replaceAll('_',' ')+' · '+edge.status,card)
      const route=add('div','trace-route',undefined,card)
      add('span','',nodes.get(edge.source)?.title||'—',route)
      add('span','trace-arrow','→',route)
      add('span','',nodes.get(edge.target)?.title||'—',route)
      add('p','',edge.evidence,card)
      add('small','',edge.caveat,card)
      safeLink(card,'Buka bukti sumber',edge.href)
    }
    const findings=el('trace-findings');clean(findings)
    for(const finding of data.findings){
      const card=add('article','reg-card trace-review',undefined,findings)
      add('span','trace-review-status'+(finding.status==='SUPPORTED'?' is-supported':''),finding.id+' · '+finding.status,card)
      add('strong','',finding.title,card)
      add('p','',finding.detail,card)
      safeLink(card,'Periksa naskah resmi',finding.official_href)
    }
    const timeline=el('trace-timeline');clean(timeline)
    for(const item of data.amendment_timeline){
      const card=add('article','reg-card',undefined,timeline)
      add('span','trace-timeline-seq','PERUBAHAN KE-'+item.sequence+' · BELUM DIREKONSILIASI',card)
      add('strong','',item.title,card)
      safeLink(card,'Telusuri JDIH resmi',item.official_href)
    }
  }
  async function load(){
    const response=await fetch('/api/qa-regulatory-r70a/trace',{credentials:'same-origin',cache:'no-store'})
    if(response.status===401){location.assign('./login.html');return}
    if(!response.ok)throw Error('Sumber atau hak akses tidak memenuhi syarat: HTTP '+response.status)
    show(await response.json())
  }
  document.addEventListener('DOMContentLoaded',()=>load().catch(error=>{
    const area=el('trace-relationships');if(area){clean(area);add('div','trace-error','Telaah ditahan: '+error.message,area)}
  }))
})()
