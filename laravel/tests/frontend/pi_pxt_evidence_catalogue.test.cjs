'use strict'
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path')
const read=n=>fs.readFileSync(path.join(__dirname,'../../public',n),'utf8')
function data(){
 const sources=Array.from({length:7},(_,i)=>({id:'PERWALI'+(i+1)+'_2026',title:'Dokumen uji '+i,sequence:i,source_url:'https://jdih.jogjakota.go.id/asset/naskah/uji.pdf',metadata_url:null,has_local_pdf:i===0,status:i===0?'ACQUIRED_IDENTITY_VERIFIED':'ACQUIRED_NEEDS_REVIEW',identity:i===0?'PDF_SHA256_MATCHES_LEDGER':'NOT_CHECKED_LOCALLY',review_status:'REVIEW_REQUIRED',sha256:'A'.repeat(64),metadata_warning:null}))
 return {schema:'PI_R70B_OFFICIAL_SOURCE_SNAPSHOTS_QA_V1',tenant_id:'ID-DIY-YOG',legal_harmonization:'NOT_ASSESSED',numeric_comparison:'BLOCKED',production_admission:'DENIED',total:7,verified:1,metadata_anomalies:0,sources}
}
async function mount(responses){
 const ids={},events={};let calls=0,redirect=null
 const el=tag=>({tag,children:[],textContent:'',appendChild(n){this.children.push(n)},replaceChildren(){this.children=[]},setAttribute(k,v){this[k]=v},addEventListener(k,fn){this[k]=fn}})
 for(const id of ['r70b-total','r70b-verified','r70b-anomalies','r70b-sources','r70b-state','r70b-reload'])ids[id]=el(id)
 vm.runInNewContext(read('regulatory-sources-r70b.js'),{document:{getElementById:id=>ids[id],createElement:el,addEventListener:(k,fn)=>events[k]=fn},location:{assign:x=>redirect=x},fetch:async(url,options)=>{assert.equal(url,'/api/qa-regulatory-r70b/sources');assert.equal(options.credentials,'same-origin');const r=responses[calls++];return {ok:r.status===200,status:r.status,json:async()=>r.data}}})
 events.DOMContentLoaded();await new Promise(r=>setImmediate(r))
 const all=()=>{const flat=n=>[n,...n.children.flatMap(flat)];return flat(ids['r70b-sources'])}
 return {ids,all,click:()=>ids['r70b-reload'].click(),calls:()=>calls,redirect:()=>redirect}
}
test('F1 source state distinguishes expected hash from verified local PDF',async()=>{
 const u=await mount([{status:200,data:data()}])
 assert.equal(u.ids['r70b-verified'].textContent,'1')
 assert.equal(u.ids['r70b-sources'].children.length,7)
 assert.equal(u.all().filter(e=>e.href?.startsWith('/api/qa-regulatory-r70b/pdf')).length,1)
 assert.ok(u.all().some(e=>e.textContent.includes('Hash tercatat bukan bukti')))
 assert.ok(u.all().some(e=>e.textContent.includes('bukan pemeriksaan seluruh halaman')))
 assert.match(u.ids['r70b-state'].textContent,/hukum dan angka tetap ditahan/)
})
test('F1 explicit retry clears stale evidence and recovers on valid response',async()=>{
 const u=await mount([{status:200,data:data()},{status:503},{status:200,data:data()}])
 await u.click();assert.equal(u.ids['r70b-verified'].textContent,'—')
 assert.equal(u.all().filter(e=>e.href).length,0);assert.equal(u.calls(),2)
 await u.click();assert.equal(u.ids['r70b-verified'].textContent,'1');assert.equal(u.calls(),3)
})
test('F1 rejects contradictory states, duplicate IDs, counts, tenant and numeric gate',async()=>{
 for(const change of [d=>d.sources[1].has_local_pdf=true,d=>d.sources[1].id=d.sources[0].id,d=>d.verified=7,d=>d.tenant_id='OTHER',d=>d.numeric_comparison='PASS',d=>d.sources[0].review_status='APPROVED']){
  const d=data();change(d);const u=await mount([{status:200,data:d}])
  assert.equal(u.ids['r70b-total'].textContent,'—');assert.equal(u.all().filter(e=>e.href).length,0);assert.match(u.ids['r70b-state'].textContent,/belum dapat diperiksa/)
 }
})
test('F1 authentication failure does not display evidence or auto retry',async()=>{
 const a=await mount([{status:401}]);assert.equal(a.redirect(),'./login.html');assert.equal(a.calls(),1)
 const b=await mount([{status:403}]);assert.match(b.ids['r70b-state'].textContent,/Akses daftar sumber tidak diberikan/);assert.equal(b.calls(),1)
})
test('F1 screen preserves provenance, navigation and distinct root review',()=>{
 const h=read('regulatory-sources-r70b.html')
 assert.match(h,/Salinan lokal cocok hash/);assert.match(h,/bukan versi hukum terkonsolidasi/)
 assert.match(h,/regulatory-trace-r70a\.html/);assert.match(h,/development-case-r85\.html#pi-view=dokumen/)
 assert.match(h,/pi-design-tokens\.css/);assert.match(h,/pi-primary-nav/);assert.match(h,/r70b-reload/)
})
