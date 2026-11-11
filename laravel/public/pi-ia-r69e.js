/* R69E: canonical product information architecture, 1 Superior + 5 Specialized.
 * Runs on QA HTML home and inherited React workspace, preserving underlying
 * compiled feature controls. Navigation labels are NOT authorization gates.
 */
(() => {
  'use strict'
  const section = new URLSearchParams(location.search).get('pi_section')
  const onWorkspace = location.pathname.endsWith('/workspace.html')
  const onRegulatory = location.pathname.endsWith('/regulatory-r69e.html')
  const onTrace = location.pathname.endsWith('/regulatory-trace-r70a.html')
  const onFullSources = location.pathname.endsWith('/regulatory-sources-r70b.html')
  const onLedger = location.pathname.endsWith('/regulatory-ledger-r70c.html')
  const onScope = location.pathname.endsWith('/regulatory-scope-r70d.html')
  const onLaravel = location.pathname.endsWith('/regulatory-laravel-r70g.html')
  const onFreeHome = location.pathname.endsWith("/free-home-r69e.html")
  let modulesAllowed = new Set(["regulatory"]) // fail-closed until server confirms
  let planName = "FREE"
  const spec = [
    {id:'superior',label:'PI Superior',subtitle:'Pusat keputusan lintas aplikasi',items:[
      {label:'Beranda',href:'./index.html',key:'home'},
      {label:'Agenda review',href:'./index.html#actions-title'},
      {label:'Analisis AI',href:'./ai-gateway.html'},
    ]},
    {id:'evidence',label:'Evidence Intelligence',subtitle:'Dokumen, bukti, kode dan provenance',items:[
      {label:'Dokumen & bukti',href:'./index.html#sources-title'},
      {label:'Kode & taksonomi',href:'./workspace.html?pi_section=taksonomi',key:'taksonomi'},
    ]},
    {id:'regulatory',label:'Regulatory Intelligence',subtitle:'Dasar hukum dan harmonisasi',items:[
      {label:'Telaah regulasi',href:'./regulatory-r69e.html',key:'regulasi'},
      {label:'Jejak norma & pasal',href:'./regulatory-trace-r70a.html',key:'norma'},
      {label:'Naskah lengkap & perubahan',href:'./regulatory-sources-r70b.html',key:'naskah'},
      {label:'Jejak perubahan regulasi',href:'./regulatory-ledger-r70c.html',key:'perubahan'},
      {label:'Cakupan perubahan Pasal I',href:'./regulatory-scope-r70d.html',key:'cakupan'},
      {label:'Genealogi Regulasi (R74)',href:'./regulatory-laravel-r70g.html#r74-title',key:'genealogi'},
      {label:'Meja Review Terpadu (R75)',href:'./regulatory-laravel-r70g.html#r75-title',key:'laravel'},
    ]},
    {id:'performance',label:'Performance Intelligence',subtitle:'Sasaran, indikator dan target',items:[
      {label:'Perencanaan',href:'./workspace.html?pi_section=perencanaan',key:'perencanaan'},
      {label:'Renstra & Renja',href:'./workspace.html?pi_section=renstra',key:'renstra'},
      {label:'Pohon kinerja',href:'./workspace.html?pi_section=kinerja',key:'kinerja'},
    ]},
    {id:'fiscal',label:'Fiscal Intelligence',subtitle:'Alokasi, APBD dan belanja',items:[
      {label:'Penganggaran & APBD',href:'./workspace.html?pi_section=penganggaran',key:'penganggaran'},
      {label:'RKA & perubahan anggaran',status:'Dalam pengembangan'},
    ]},
    {id:'evaluation',label:'Evaluation Intelligence',subtitle:'Realisasi, hasil dan efektivitas',items:[
      {label:'Evaluasi hasil',status:'Dalam pengembangan'},
      {label:'Temuan evaluasi terverifikasi',status:'Dalam pengembangan'},
    ]},
  ]
  function create(tag,cls,text,parent) {
    const el=document.createElement(tag)
    if(cls) el.className=cls
    if(text!=null) el.textContent=text
    if(parent) parent.appendChild(el)
    return el
  }
  function draw(nav) {
    if(nav.dataset.piHierarchyReady==='true') return
    nav.dataset.piHierarchyReady='true'
    nav.classList.add('pi-app-menu')
    nav.replaceChildren()
    for(let i=0;i<spec.length;i++) {
      const group=spec[i]
      const locked=group.id!=="superior" && !modulesAllowed.has(group.id)
      if(i===0) create('div','pi-app-group-label','APLIKASI UTAMA',nav)
      if(i===1) create('div','pi-app-group-label','5 APLIKASI SPESIALIS',nav)
      const details=create('details','pi-app-group',null,nav)
      details.dataset.piApp=group.id
      const hasActive=group.items.some(x=>(onWorkspace&&x.key===section)||(onRegulatory&&x.key==='regulasi')||(onTrace&&x.key==='norma')||(onFullSources&&x.key==='naskah')||(onLedger&&x.key==='perubahan')||(onScope&&x.key==='cakupan')||(onLaravel&&((x.key==='genealogi'&&location.hash==='#r74-title')||(x.key==='laravel'&&location.hash!=='#r74-title'))))
      details.open=!locked && (hasActive || (!onWorkspace&&i===0))
      const summary=create('summary','pi-app-group-summary',null,details)
      create('span','pi-app-group-name',group.label,summary)
      create('span','pi-app-group-chevron','⌄',summary)
      create('small','pi-app-subtitle',group.subtitle,details)
      if(locked){
        details.classList.add("is-locked")
        const upsell=create("a","pi-app-upgrade","Perlu berlangganan · lihat katalog",details)
        upsell.href="./subscription-r69e.html"
        continue
      }
      const list=create('div','pi-app-submenu',null,details)
      const displayItems=group.id==="superior"&&planName==="FREE"
        ? [...group.items.filter(item=>item.key==="home"),{label:"Langganan aplikasi",href:"./subscription-r69e.html"}]
        : group.items
      for(const item of displayItems) {
        if(item.href) {
          const a=create('a','pi-app-item',item.label,list)
          a.href=item.href
          if((onWorkspace&&item.key===section) || (onRegulatory&&item.key==='regulasi') || (onTrace&&item.key==='norma') || (onFullSources&&item.key==='naskah') || (onLedger&&item.key==='perubahan') || (onScope&&item.key==='cakupan') || (onLaravel&&((item.key==='genealogi'&&location.hash==='#r74-title')||(item.key==='laravel'&&location.hash!=='#r74-title'))) || (!onWorkspace&&!onRegulatory&&!onTrace&&!onFullSources&&!onLedger&&!onScope&&!onLaravel&&item.key==='home'&&location.hash==='')) {
            a.classList.add('active')
            a.setAttribute('aria-current','page')
          }
        } else {
          const row=create('div','pi-app-item pi-app-unavailable',null,list)
          create('span','',item.label,row)
          create('small','',item.status||'Belum tersedia',row)
          row.title='Fitur belum tersedia di QA ini'
        }
      }
      details.addEventListener('toggle',() => {
        if(!details.open)return
        for(const other of nav.querySelectorAll('details.pi-app-group'))if(other!==details)other.open=false
      })
    }
    const route=create('div','pi-app-route',null,nav)
    create('strong','','ALUR ANTAR-APLIKASI',route)
    create("span","",planName==="FREE"
      ? "FREE · Regulasi mandiri. Tambahkan modul untuk audit lintas aplikasi."
      : "Audit lintas modul sesuai langganan · temuan perlu verifikasi",route)
  }
  function updateLegacyAside(aside) {
    if(!aside)return
    aside.classList.add('pi-r69e-grouped-sidebar')
    let nav=aside.querySelector(':scope > nav[data-pi-app-nav]')
    if(!nav) {
      nav=document.createElement('nav')
      nav.setAttribute('aria-label','Hierarki aplikasi Planning Intelligence')
      nav.setAttribute('data-pi-app-nav','')
      aside.appendChild(nav)
    }
    draw(nav)
    const disclaimer=aside.querySelector('.pi-tenant-disclaimer')
    if(disclaimer&&disclaimer.textContent!=='Autentikasi server QA aktif · SSO produksi belum aktif')
      disclaimer.textContent='Autentikasi server QA aktif · SSO produksi belum aktif'
    const caveat=aside.querySelector('.pi-ux-caveat')
    if(caveat&&caveat.textContent!=='R69E · Login QA aktif; tenant produksi dan SSO belum diaktifkan')
      caveat.textContent='R69E · Login QA aktif; tenant produksi dan SSO belum diaktifkan'
  }
  let scheduled=false
  function patch() {
    scheduled=false
    const home=document.querySelector('nav[data-pi-app-nav]')
    if(home)draw(home)
    const legacy=document.querySelector('.shell > aside')
    if(legacy)updateLegacyAside(legacy)
  }
  function schedule(){if(scheduled)return;scheduled=true;requestAnimationFrame(patch)}
  patch()
  fetch("/api/qa-subscription-r69e/entitlements",{credentials:"same-origin",cache:"no-store"})
    .then(r=>{if(!r.ok)throw Error("ENTITLEMENT_UNAVAILABLE");return r.json()})
    .then(data=>{
      if(!Array.isArray(data.modules))throw Error("INVALID_ENTITLEMENTS")
      modulesAllowed=new Set(data.modules)
      planName=data.plan
      for(const el of document.querySelectorAll("nav[data-pi-app-nav]")){el.dataset.piHierarchyReady="";draw(el)}
    }).catch(()=>{ /* default Free; server rejects unauthorized routes */ })
  const root=document.getElementById('root')
  if(root)new MutationObserver(schedule).observe(root,{subtree:true,childList:true,characterData:true})
  window.addEventListener('hashchange', () => {
    // R74 and R75 share one HTML document: a hash-only navigation must
    // refresh the active sidebar item without reloading the page.
    for (const nav of document.querySelectorAll('nav[data-pi-app-nav]')) {
      nav.dataset.piHierarchyReady = ''
      draw(nav)
    }
  })
  document.addEventListener('DOMContentLoaded',schedule)
})()
