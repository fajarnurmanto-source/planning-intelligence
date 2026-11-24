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
 {id:'kewenangan',title:'Permasalahan dan kewenangan',short:'Kewenangan',desc:'Dasar kewenangan serta tahapan yang berkaitan'},
 {id:'rencana',title:'Rencana, program dan subkegiatan',short:'Perencanaan',desc:'Kesesuaian program, indikator dan hubungan antar dokumen'},
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
  ringkasan:[byId('r85-questions')?.closest('section.panel'),byId('r85-guards')?.closest('section.panel')],
  kewenangan:[byId('r86-lifecycle')],
  rencana:[byId('r88-crosswalk')],
  anggaran:[byId('r106-annex-review')],
  dokumen:[byId('r100-official-sources'),byId('r85-evidence')?.closest('section.panel')],
  rinci:[byId('r85-graphs')?.closest('section.panel')]
 }
 const expected=Object.values(assignments).flat()
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
 make('p','pi-r108-intro','Pilih pekerjaan. Informasi lanjutan dibuka saat dibutuhkan, tanpa kehilangan sumber dan konteks kasus.',shell)
 const context=make('p','pi-r108-context','Konteks mengikuti wilayah, tahun, topik dan kasus yang Anda pilih.',shell)
 context.setAttribute('aria-live','polite')
 const updateContext=()=>{
  const names=['r85-jurisdiction','r85-year','r85-topic','r85-case']
  const values=names.map(name=>{
   const sel=byId(name)
   return sel?.selectedOptions?.[0]?.textContent?.trim()||sel?.value||''
  }).filter(Boolean)
  context.textContent=values.length?'Konteks: '+values.join(' · '):'Konteks dipilih melalui formulir di atas.'
 }
 filters.addEventListener('change',updateContext)
 filters.addEventListener('submit',()=>setTimeout(updateContext,0))
 updateContext()
 const nav=make('nav','pi-r108-tabs',null,shell)
 nav.setAttribute('aria-label','Bagian pekerjaan telaah')
 const buttons={}
 for(const section of config){
  const btn=make('a','pi-r108-tab',section.short,nav)
  btn.href='#pi-view='+section.id
  btn.dataset.view=section.id
  btn.setAttribute('aria-label',section.title)
  buttons[section.id]=btn
 }
 const info=make('div','pi-r108-current',null,shell)
 const heading=make('h3','pi-r108-current-heading','',info)
 const sub=make('p','','',info)
 const action=make('a','pi-r108-source-jump','',info)
 action.hidden=true
 action.href='#r106-annex-review'
 action.textContent='Periksa lampiran APBD'
 const first=byId('r88-crosswalk')
 first.parentNode.insertBefore(shell,first)
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
  action.hidden=selected!=='anggaran'
  if(selected==='anggaran'){
   sub.textContent+=' · '+activateSourceLinks()
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
 const tasks=[
  ['Menelaah permasalahan dan kewenangan','Memeriksa persoalan, aturan dan perangkat daerah terkait.','./development-case-r85.html#pi-view=kewenangan'],
  ['Memeriksa program dan subkegiatan','Menelusuri hubungan sasaran, program, kegiatan dan indikator.','./development-case-r85.html#pi-view=rencana'],
  ['Memeriksa anggaran dan perubahan','Membaca rekening, lampiran APBD dan sumbernya.','./development-case-r85.html#pi-view=anggaran'],
  ['Mencari dokumen dan bukti','Membuka katalog, sumber dan halaman rujukan.','./development-case-r85.html#pi-view=dokumen'],
  ['Melihat temuan untuk ditindaklanjuti','Meninjau temuan dan batas kesimpulan yang tersedia.','./index.html#actions-title']
 ]
 for(const [label,desc,href] of tasks){
  const a=make('a','pi-r108-task-card',null,choices)
  a.href=href
  make('strong','',label,a);make('span','',desc,a)
 }
 hero.insertAdjacentElement('afterend',section)
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
