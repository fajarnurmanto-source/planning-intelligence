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
  const onDevelopmentCase = location.pathname.endsWith('/development-case-r85.html')
  const onFreeHome = location.pathname.endsWith("/free-home-r69e.html")
  let modulesAllowed = new Set(["regulatory"]) // fail-closed until server confirms
  let planName = "FREE"
  // Canonical Laravel equivalents, not claims of complete R70A-D feature parity.
  const laravelHashes = {perubahan:'#r72-title',cakupan:'#r70g-documents',genealogi:'#r74-title',laravel:'#r75-title'}
  const isLaravelActive = key => onLaravel && (location.hash ? laravelHashes[key] === location.hash : key === 'laravel')
  // R108 work-oriented navigation. "Kasus pembangunan terpadu" is the
  // unchanged legacy route name, not a user-facing developer milestone.
  // Internal module entitlements remain enforced by the existing API.
  const spec = [
    {id:'superior',label:'Beranda kerja',subtitle:'Pekerjaan dan konteks yang sedang ditelaah',items:[
      {label:'Beranda',href:'./index.html',key:'home'},
      {label:'Lanjutkan telaah kasus',href:'./development-case-r85.html',key:'development-case'},
    ]},
    {id:'planning',label:'Telaah perencanaan',subtitle:'Permasalahan, kewenangan, sasaran dan program',items:[
      {label:'Ringkasan kasus',href:'./development-case-r85.html#pi-view=ringkasan',key:'ringkasan'},
      {label:'Permasalahan & kewenangan',href:'./development-case-r85.html#pi-view=kewenangan',key:'kewenangan'},
      {label:'Program & subkegiatan',href:'./development-case-r85.html#pi-view=rencana',key:'rencana'},
      {label:'Telaah dasar hukum lanjutan',href:'./regulatory-r69e.html',key:'regulasi',module:'regulatory'},
    ]},
    {id:'fiscal',label:'Anggaran dan pendanaan',subtitle:'Kesesuaian, rekening dan perubahan APBD',items:[
      {label:'Lampiran APBD & rekening',href:'./development-case-r85.html#pi-view=anggaran',key:'anggaran'},
      {label:'Telaah anggaran lanjutan',href:'./workspace.html?pi_section=penganggaran',key:'penganggaran',module:'fiscal'},
    ]},
    {id:'evaluation',label:'Pelaksanaan dan evaluasi',subtitle:'Capaian, kendala dan perbaikan',items:[
      {label:'Temuan yang perlu ditelaah',href:'./index.html#actions-title',key:'temuan'},
      {label:'Evaluasi hasil lanjutan',status:'Belum tersedia untuk data kasus ini'},
    ]},
    {id:'evidence',label:'Dokumen dan dasar hukum',subtitle:'Berkas, sumber dan riwayat bukti',items:[
      {label:'Katalog dokumen',href:'./development-case-r85.html#pi-view=dokumen',key:'dokumen'},
      {label:'Lampiran APBD',href:'./development-case-r85.html#pi-view=anggaran',key:'lampiran'},
      {label:'Pemeriksaan hukum lanjutan',href:'./regulatory-laravel-r70g.html',key:'regulatory-detail',module:'regulatory'},
    ]},
    {id:'review',label:'Temuan dan tindak lanjut',subtitle:'Pemeriksaan, rekomendasi dan keputusan manusia',items:[
      {label:'Ringkasan temuan',href:'./index.html#actions-title',key:'reviews'},
      {label:'Meja pemeriksa',href:'./regulatory-trace-r70a.html#r84-review-workspace',key:'reviewer',module:'regulatory'},
      {label:'Rincian hubungan bukti',href:'./development-case-r85.html#pi-view=rinci',key:'rinci'},
    ]},
    {id:'specialist',label:'Telaah regulasi lanjutan',subtitle:'Riwayat perubahan dan hubungan ketentuan hukum',items:[
      {label:'Jejak ketentuan',href:'./regulatory-trace-r70a.html',key:'norma',module:'regulatory'},
      {label:'Naskah dan PDF regulasi',href:'./regulatory-sources-r70b.html',key:'naskah',module:'regulatory'},
      {label:'Riwayat perubahan peraturan',href:'./regulatory-laravel-r70g.html#r72-title',key:'perubahan',module:'regulatory'},
      {label:'Sasaran dan ruang lingkup lampiran',href:'./regulatory-laravel-r70g.html#r70g-documents',key:'cakupan',module:'regulatory'},
      {label:'Hubungan antar peraturan',href:'./regulatory-laravel-r70g.html#r74-title',key:'genealogi',module:'regulatory'},
      {label:'Meja telaah terpadu',href:'./regulatory-laravel-r70g.html#r75-title',key:'laravel',module:'regulatory'},
    ]},
    {id:'settings',label:'Pengaturan dan bantuan',subtitle:'Layanan AI opsional dan pengaturan lanjutan',items:[
      {label:'Bantuan analisis AI',href:'./ai-gateway.html',key:'ai'},
      {label:'Pengaturan AI',href:'./ai-settings.html',key:'ai-settings'},
      {label:'Paket dan akses layanan',href:'./subscription-r69e.html',key:'subscription'},
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
      const locked=false // task navigation itself never grants domain access
      if(i===0) create('div','pi-app-group-label','MULAI PEKERJAAN',nav)
      if(i===1) create('div','pi-app-group-label','PILIH TAHAP TELAAH',nav)
      const details=create('details','pi-app-group',null,nav)
      details.dataset.piApp=group.id
      const hasActive=group.items.some(x=>(onDevelopmentCase&&x.href?.includes('#pi-view=')&&location.hash===x.href.slice(x.href.indexOf('#')))||(onWorkspace&&x.key===section)||(onRegulatory&&x.key==='regulasi')||(onTrace&&x.key==='norma')||(onFullSources&&x.key==='naskah')||(onLedger&&x.key==='perubahan')||(onScope&&x.key==='cakupan')||(onDevelopmentCase&&x.key==='development-case')||isLaravelActive(x.key))
      details.open=!locked && (hasActive || (!onWorkspace&&i===0))
      const summary=create('summary','pi-app-group-summary',null,details)
      create('span','pi-app-group-name',group.label,summary)
      const chevron=create('span','pi-app-group-chevron','⌄',summary)
      chevron.setAttribute('aria-hidden','true')
      create('small','pi-app-subtitle',group.subtitle,details)
      if(locked){
        details.classList.add("is-locked")
        const upsell=create("a","pi-app-upgrade","Perlu berlangganan · lihat katalog",details)
        upsell.href="./subscription-r69e.html"
        continue
      }
      const list=create('div','pi-app-submenu',null,details)
      const displayItems=group.id==="superior"&&planName==="FREE"
        ? group.items.filter(item=>item.key==="home"||item.key==="development-case")
        : group.items
      for(const item of displayItems) {
        if(item.href && (!item.module || modulesAllowed.has(item.module))) {
          const a=create('a','pi-app-item',item.label,list)
          a.href=item.href
          if((onDevelopmentCase&&item.href?.includes('#pi-view=')&&location.hash===item.href.slice(item.href.indexOf('#'))) || (onWorkspace&&item.key===section) || (onRegulatory&&item.key==='regulasi') || (onTrace&&item.key==='norma') || (onFullSources&&item.key==='naskah') || (onLedger&&item.key==='perubahan') || (onScope&&item.key==='cakupan') || (onDevelopmentCase&&item.key==='development-case') || isLaravelActive(item.key) || (!onWorkspace&&!onRegulatory&&!onTrace&&!onFullSources&&!onLedger&&!onScope&&!onLaravel&&!onDevelopmentCase&&item.key==='home'&&location.hash==='')) {
            a.classList.add('active')
            a.setAttribute('aria-current','page')
          }
        } else {
          const row=create('div','pi-app-item pi-app-unavailable',null,list)
          create('span','',item.label,row)
          create('small','',item.status||(item.module?'Memerlukan akses modul terkait':'Belum tersedia'),row)
          row.title='Fitur belum tersedia atau akses modul belum diberikan'
        }
      }
      details.addEventListener('toggle',() => {
        if(!details.open)return
        for(const other of nav.querySelectorAll('details.pi-app-group'))if(other!==details)other.open=false
      })
    }
    const route=create('div','pi-app-route',null,nav)
    create('strong','','CARA MENGGUNAKAN PI',route)
    create("span","","Pilih pekerjaan, periksa dokumen dan bukti, lalu catat hal yang perlu ditindaklanjuti. Data belum lengkap tidak berarti disetujui.",route)
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
      caveat.textContent='Login lokal aktif; SSO dan layanan produksi belum disahkan'
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
