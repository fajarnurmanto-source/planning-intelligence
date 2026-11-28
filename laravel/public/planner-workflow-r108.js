/* R108 first-wave task-first UI layer.
 * All existing screens/services/IDs stay mounted; UI changes only grouping,
 * visible workflow panes and labels. Does not bypass auth, tenant or entitlement.
 * No storage, no cloud calls, no new backend or persistence.
 */
(() => {
'use strict'
const page=location.pathname.split('/').pop()||'index.html'
const isCase=page==='development-case-r85.html'
const isHome=page==='index.html'||page===''
if(!isCase&&!isHome)return
const make=(tag,cls,text,parent)=>{
 const n=document.createElement(tag)
 if(cls)n.className=cls
 if(text!=null)n.textContent=text
 if(parent)parent.append(n)
 return n
}
const safeSections=['ringkasan','kewenangan','rencana','anggaran','dokumen','rinci']
const config=[
 {id:'ringkasan',title:'Ringkasan dan hal yang perlu ditelaah',short:'Ringkasan',desc:'Pertanyaan inti, kebutuhan bukti dan batas kesimpulan'},
 {id:'kewenangan',title:'Norma induk dan kewenangan',short:'Kewenangan',desc:'Periksa norma induk yang relevan, delegasi, perubahan, transisi dan keberlakuan pada tanggal kasus. Daftar sumber bukan bukti seluruh norma telah ditemukan.'},
 {id:'rencana',title:'Rencana, program dan subkegiatan',short:'Perencanaan',desc:'Telusuri dokumen, OPD, periode, program dan definisi indikator. Keselarasan belum dapat diputuskan tanpa dasar hukum yang terverifikasi.'},
 {id:'anggaran',title:'Anggaran dan perubahan',short:'Anggaran',desc:'Lampiran APBD, rekening dan sumber perbandingan'},
 {id:'dokumen',title:'Dokumen dan bukti',short:'Dokumen',desc:'Katalog resmi, halaman dan jejak sumber'},
 {id:'rinci',title:'Rincian hubungan bukti',short:'Rincian',desc:'Graf hubungan yang bisa diperiksa saat diperlukan'}
]
const activateSourceLinks=()=>{
 const status=document.querySelector('#r106-status')
 return status?.textContent||'Pilih pemeriksaan lampiran untuk membaca dokumen terverifikasi.'
}
function initCase(){
 const main=document.querySelector('.content-wrap')
 const filters=document.getElementById('r85-filters')
 if(!main||!filters)return
 const byId=id=>document.getElementById(id)
 const assignments={
  ringkasan:[byId('r85-questions')?.closest('section.panel')],
  kewenangan:[byId('r86-lifecycle')],
  rencana:[byId('r88-crosswalk')],
  anggaran:[byId('r106-annex-review')],
  dokumen:[byId('r100-official-sources'),byId('r85-evidence')?.closest('section.panel')],
  rinci:[byId('r85-graphs')?.closest('section.panel')]
 }
 const guardPanel=byId('r85-guards')?.closest('section.panel')
 const expected=[...Object.values(assignments).flat(),guardPanel]
 if(expected.some(x=>!x)||new Set(expected).size!==expected.length){
  // Never hide any original work if the source structure changed.
  return
 }
 for(const [key,list] of Object.entries(assignments)){
  for(const n of list){n.dataset.piPlannerView=key}
 }
 const shell=make('section','pi-r108-workflow',null)
 shell.id='pi-r108-workflow'
 shell.setAttribute('aria-labelledby','pi-r108-workflow-title')
 make('p','pi-r108-overline','RUANG KERJA TELAAH',shell)
 make('h2','', 'Apa yang ingin Anda periksa?',shell).id='pi-r108-workflow-title'
 make('p','pi-r108-intro','Mulai dari konteks dan bukti, lalu norma induk, rencana dan anggaran. Tahap lain tetap dapat dibuka untuk mengumpulkan bukti. Urutan ini bukan tanda bahwa pemeriksaan sebelumnya telah lulus.',shell)
 const context=make('p','pi-r108-context','Menyiapkan konteks kasus…',shell)
 const contextHint=make('p','pi-r108-hint',
  'Kasus contoh: Pengelolaan Persampahan Kota Yogyakarta, tahun 2026. Data belum mencakup semua kasus daerah.',shell)
 contextHint.setAttribute('role','note')
 context.setAttribute('aria-live','polite')
 let caseReady=false
 const updateContext=()=>{
  const fields=[
   ['Wilayah','r85-jurisdiction'],['Tahun','r85-year'],
   ['Topik','r85-topic'],['Kasus','r85-case']
  ]
  const values=fields.map(([label,id])=>{
   const selected=byId(id)?.selectedOptions?.[0]
   return selected ? label+': '+(selected.textContent?.trim()||selected.value||'') : ''
  }).filter(Boolean)
  context.textContent=caseReady&&values.length===fields.length
   ? values.join(' · ')
   : 'Menyiapkan pilihan wilayah, tahun, topik, dan kasus…'
 }
 const syncContext=event=>{
  caseReady=event.detail?.ready===true
  if(!caseReady)context.textContent='Kasus belum tersedia. Periksa kembali akses atau sumber data.'
  else updateContext()
 }
 document.addEventListener('pi:case-state',syncContext)
 filters.addEventListener('change',()=>{
  // A changed filter does not imply the server has accepted a different case.
  context.textContent='Pilihan diubah. Tekan "Muat kasus" untuk memeriksa konteks.'
 })
 window.addEventListener('pageshow',()=>{if(caseReady)updateContext()})
 updateContext()
 const nav=make('nav','pi-r108-tabs',null,shell)
 nav.setAttribute('aria-label','Bagian pekerjaan telaah')
 const buttons={}
 for(const section of ['ringkasan','dokumen','kewenangan','rencana','anggaran','rinci'].map(id=>config.find(s=>s.id===id))){
  const btn=make('a','pi-r108-tab',section.short,nav)
  btn.href='#pi-view='+section.id
  btn.dataset.view=section.id
  btn.setAttribute('aria-label',section.title)
  buttons[section.id]=btn
 }
 const info=make('div','pi-r108-current',null,shell)
 const heading=make('h3','pi-r108-current-heading','',info)
 const sub=make('p','','',info)
 const action=make('a','pi-r108-next-step','',info)
 const nextSteps={
  ringkasan:['Lanjut: Periksa dokumen dan bukti','dokumen'],
  kewenangan:['Lanjut: Telaah perencanaan','rencana'],
  rencana:['Lanjut: Telaah anggaran','anggaran'],
  anggaran:['Lanjut: Telusuri hubungan bukti dan batas evaluasi','rinci'],
  dokumen:['Lanjut: Telaah kewenangan','kewenangan'],
  rinci:['Kembali ke ringkasan untuk telaah manusia','ringkasan']
 }
 document.body.classList.add('pi-r108-case')
 const first=byId('r88-crosswalk')
 first.parentNode.insertBefore(shell,first)
 // Material limitations must remain visible on every view, not just the summary.
 first.parentNode.insertBefore(guardPanel,first)
 const shortcuts={
  'r106-annex-review':'anggaran','r88-crosswalk':'rencana',
  'r86-lifecycle':'kewenangan','r100-official-sources':'dokumen',
  'r85-evidence-title':'dokumen','r85-graphs-title':'rinci',
  'r85-questions-title':'ringkasan'
 }
 const choose=()=>{
  const h=decodeURIComponent(location.hash.slice(1))
  const v=h.startsWith('pi-view=')?h.slice(8):shortcuts[h]
  return safeSections.includes(v)?v:'ringkasan'
 }
 const show=(requested,focus)=>{
  const selected=safeSections.includes(requested)?requested:'ringkasan'
  for(const [key,list] of Object.entries(assignments)){
   for(const el of list)el.hidden=key!==selected
  }
  for(const [key,btn] of Object.entries(buttons)){
   if(key===selected){
    btn.setAttribute('aria-current','page');btn.classList.add('is-active')
   }else{btn.removeAttribute('aria-current');btn.classList.remove('is-active')}
  }
  const definition=config.find(v=>v.id===selected)
  heading.textContent=definition.title;sub.textContent=definition.desc
  const next=nextSteps[selected]
  action.hidden=!next
  if(next){action.textContent=next[0];action.href='#pi-view='+next[1]}
  if(selected==='anggaran'){
   sub.textContent='Periksa rincian rekening dan sumbernya di bawah. Tekan "Periksa lampiran dari arsip lokal" untuk menjalankan pemeriksaan; hasil tetap kandidat.'
  }
  document.title=definition.short+' · Telaah pembangunan · PI'
  if(focus){shell.scrollIntoView({block:'start',behavior:'auto'})}
 }
 const onHash=()=>show(choose(),true)
 window.addEventListener('hashchange',onHash)
 show(choose(),false)
 // Browser deep links (including existing #r106-annex-review) must select pane
 // before scrolling. No forced navigation or form submission.
 if(location.hash && !location.hash.startsWith('#pi-view=')){
  const target=byId(decodeURIComponent(location.hash.slice(1)))
  if(target)setTimeout(()=>target.scrollIntoView({block:'start'}),0)
 }
}
function initHome(){
 const wrap=document.querySelector('.content-wrap')
 const hero=wrap?.querySelector('.hero')
 if(!wrap||!hero)return
 const section=make('section','pi-r108-home-tasks',null)
 section.setAttribute('aria-labelledby','pi-r108-home-title')
 make('p','pi-r108-overline','PILIH PEKERJAAN',section)
 make('h2','', 'Saya ingin…',section).id='pi-r108-home-title'
 const choices=make('div','pi-r108-task-grid',null,section)
 document.body.classList.add('pi-r108-home')
 make('p','pi-r108-hint','Pilihan di bawah membuka kasus contoh Persampahan Kota Yogyakarta 2026. Belum tersedia daftar pekerjaan lintas perangkat daerah.',section)
 const tasks=[
  ['Mencari dokumen dan bukti','Evidence: periksa sumber resmi, halaman, versi dan status bukti.','./development-case-r85.html#pi-view=dokumen'],
  ['Menelaah norma induk dan kewenangan','Regulatory: telusuri dasar kewenangan, delegasi, perubahan dan masa berlaku.','./development-case-r85.html#pi-view=kewenangan'],
  ['Memeriksa program dan subkegiatan','Performance: telusuri sasaran, indikator, OPD dan dokumen perencanaan.','./development-case-r85.html#pi-view=rencana'],
  ['Memeriksa anggaran dan perubahan','Fiscal: baca rekening dan lampiran. Hubungan serta angka masih perlu verifikasi.','./development-case-r85.html#pi-view=anggaran'],
  ['Melihat temuan dan batas evaluasi','Evaluation: tinjau temuan yang tersedia. Evaluasi realisasi dan outcome belum lengkap pada kasus ini.','./index.html#actions-title']
 ]
 for(const [label,desc,href] of tasks){
  const a=make('a','pi-r108-task-card',null,choices)
  a.href=href
  make('strong','',label,a);make('span','',desc,a)
 }
 hero.insertAdjacentElement('afterend',section)
 const heroActions=hero.querySelector('.hero-actions')
 if(heroActions){
  const options=make('details','pi-r108-hero-extras',null)
  make('summary','', 'Pintasan lain dan bantuan AI',options)
  heroActions.parentElement?.append(options)
  options.append(heroActions)
 }
 const kpis=wrap.querySelector('.kpis'),grid=wrap.querySelector('.content-grid')
 if(kpis&&grid){
  const details=make('details','pi-r108-home-details',null)
  const summary=make('summary','', 'Lihat daftar bukti, temuan dan rincian lain',details)
  summary.setAttribute('aria-label','Buka rincian dokumen dan temuan')
  kpis.parentNode.insertBefore(details,kpis)
  details.append(kpis,grid)
  if(location.hash==='#actions-title'){
   details.open=true
   setTimeout(()=>document.getElementById('actions-title')?.scrollIntoView({block:'start'}),0)
  }
  window.addEventListener('hashchange',()=>{
   if(location.hash==='#actions-title')details.open=true
  })
 }
}
if(document.readyState==='loading'){
 document.addEventListener('DOMContentLoaded',()=>isCase?initCase():initHome(),{once:true})
}else{isCase?initCase():initHome()}
})();
