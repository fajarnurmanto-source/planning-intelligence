'use strict'
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path')
const read=n=>fs.readFileSync(path.join(__dirname,'../../public',n),'utf8')
function response(d){return {ok:true,status:200,json:async()=>d}}
function result(lens='legal',direction='ancestors'){
 return {schema:'PI_R74_BIDIRECTIONAL_GENEALOGY_TRACE_V1',tenant_id:'ID-DIY-YOG',root:'PERMENDAGRI-10-2025',direction,lens,max_depth:4,node_count:2,edge_count:1,nodes:[{id:'PERMENDAGRI-10-2025',node_type:'INSTRUMENT',label:'Peraturan contoh',distance:0,article_group:'ARTICLE_8',formal_rank:null},{id:'PARENT',node_type:'INSTRUMENT',label:'Rujukan contoh',distance:1}],relations:[{id:'E1',from:'PERMENDAGRI-10-2025',to:'PARENT',type:'CITES_AS_BASIS',legal_effect:'NOT_ASSESSED',status:'CANDIDATE',source_locator:'Pasal uji'}],legal_harmonization:'NOT_ASSESSED',temporal_applicability:'NOT_ASSESSED',numeric_comparison:'BLOCKED',review_status:'REVIEW_REQUIRED',result_status:'PARTIAL_EVIDENCE_GRAPH_NOT_LEGAL_FINDING',production_admission:'DENIED'}
}
async function setup(fetch,script='regulatory-genealogy-r74.js'){
 const ids={},events={}
 function el(tag){return {tag,value:'',textContent:'',children:[],dataset:{},handlers:{},appendChild(n){this.children.push(n)},replaceChildren(){this.children=[]},setAttribute(k,v){this[k]=v},addEventListener(k,f){this.handlers[k]=f}}}
 const names=script.includes('genealogy')?['r74-form','r74-root','r74-direction','r74-lens','r74-status','r74-results','r74-run','r74-taxonomy','r74-lens-note']:['r70l-results','r70l-status','r70l-reload']
 for(const id of names)ids[id]=el(id)
 if(ids['r74-direction']){ids['r74-direction'].value='ancestors';ids['r74-lens'].value='legal'}
 vm.runInNewContext(read(script),{URLSearchParams,document:{getElementById:id=>ids[id],createElement:el,addEventListener:(k,f)=>events[k]=f},location:{replace(){}},fetch})
 events.DOMContentLoaded();await new Promise(r=>setImmediate(r))
 return {ids,settle:()=>new Promise(r=>setImmediate(r)),all:()=>{const flat=e=>[e,...e.children.flatMap(flat)];return flat(ids['r74-results'])}}
}
const taxonomy={schema:'PI_R74_REGULATORY_TAXONOMY_V1',production_admission:'DENIED',types:[],instruments:[{id:'PERMENDAGRI-10-2025',title:'Peraturan contoh'}],topics:[]}
test('F2 preserves directional evidence, distinct lens and non-universal Article 8 rank',async()=>{
 const u=await setup(async url=>response(url.includes('taxonomy')?taxonomy:result()))
 assert.match(u.ids['r74-status'].textContent,/Jejak parsial/)
 const text=u.all().map(e=>e.textContent).join(' ')
 assert.match(text,/Pasal 8 · tidak memiliki peringkat universal/)
 assert.match(text,/Rujukan dasar hukum/)
 assert.match(text,/Peraturan contoh → Rujukan contoh/)
 assert.match(text,/Akibat hukum belum dinilai/)
 assert.match(text,/Lokasi bukti: Pasal uji/)
})
test('F2 rejects wrong context, duplicate node, dangling edge, wrong lens or admitted legal effect',async()=>{
 for(const mutate of [x=>x.root='OTHER',x=>x.tenant_id='OTHER',x=>x.nodes[1].id=x.nodes[0].id,x=>x.relations[0].to='MISSING',x=>x.relations[0].type='BROADER_TOPIC',x=>x.relations[0].legal_effect='APPROVED',x=>x.temporal_applicability='VERIFIED']){
  const data=result();mutate(data)
  const u=await setup(async url=>response(url.includes('taxonomy')?taxonomy:data))
  assert.equal(u.ids['r74-results'].children.length,0);assert.match(u.ids['r74-status'].textContent,/belum dapat diperiksa/)
 }
})
test('F2 discards stale in-flight result after lens change and retries current context only',async()=>{
 let complete,queries=[]
 const u=await setup(async url=>{if(url.includes('taxonomy'))return response(taxonomy);queries.push(url);if(queries.length===1)return new Promise(r=>complete=r);const d=result('thematic','both');d.relations[0].type='REGULATES_TOPIC';return response(d)})
 u.ids['r74-lens'].value='thematic';u.ids['r74-lens'].handlers.change()
 assert.equal(u.ids['r74-direction'].disabled,true)
 complete(response(result()));await u.settle()
 assert.equal(u.ids['r74-results'].children.length,0);assert.match(u.ids['r74-status'].textContent,/Pilihan berubah/)
 u.ids['r74-form'].handlers.submit({preventDefault(){}});await u.settle()
 assert.match(queries[1],/direction=both/)
 assert.match(u.all().map(e=>e.textContent).join(' '),/Tematik · bukan hubungan hukum/)
})
test('F2 normative root failure is held, retry bounded and no raw error exposed',async()=>{
 let calls=0
 const u=await setup(async()=>{calls++;return {ok:false,status:503}},'normative-root-r70l.js')
 assert.equal(calls,1);assert.match(u.ids['r70l-status'].textContent,/belum dapat diperiksa/);assert.equal(u.ids['r70l-results'].children.length,0)
 await u.ids['r70l-reload'].handlers.click();assert.equal(calls,2);assert.equal(u.ids['r70l-results'].children.length,0)
 assert.doesNotMatch(u.ids['r70l-status'].textContent,/R70L|HTTP|503/)
})
test('F2 preserves persistent limits, direct source route and existing graph controls',()=>{
 const h=read('regulatory-laravel-r70g.html')
 assert.match(h,/pi-normative-boundary/)
 assert.match(h,/regulatory-sources-r70b\.html/)
 assert.match(h,/r70l-reload/)
 assert.match(h,/r74-lens-note/)
 assert.match(h,/Jarak graf bukan tingkat hierarki hukum/)
})
