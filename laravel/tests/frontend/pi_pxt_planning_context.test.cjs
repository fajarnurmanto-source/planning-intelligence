'use strict'
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path')
const read=n=>fs.readFileSync(path.join(__dirname,'../../public',n),'utf8')
const fixture=()=>JSON.parse(fs.readFileSync(path.join(__dirname,'../../../docs/evidence/r88-20261121/crosswalk-real-engine-output.json'),'utf8'))
function setup(fetch){
 const ids={}
 function el(tag){return {tag,textContent:'',children:[],disabled:false,hidden:false,checked:false,value:'',events:{},appendChild(n){this.children.push(n)},replaceChildren(){this.children=[]},setAttribute(k,v){this[k]=v},addEventListener(k,fn){this.events[k]=fn}}}
 for(const id of ['r88-controls','r88-run','r88-results','r88-context','r88-status','r88-export','r89-preview','r89-run','r89-consent','r89-data','r89-results','r89-status','r89-provider'])ids[id]=el(id)
 const filters={year:'2026',as_of:'2026-11-21',code:'2.11.11.2.01.0004'}
 ids['r89-provider'].value='gemini'
 ids['r88-controls'].elements=[ids['r88-run'],ids['r88-export']]
 vm.runInNewContext(read('regulatory-planning-r88.js'),{window:{},document:{getElementById:id=>ids[id],createElement:el},URL,URLSearchParams,FormData:class{constructor(){return Object.entries(filters)}},fetch,location:{href:'http://127.0.0.1:8820/'}})
 return {ids,filters,submit:()=>ids['r88-controls'].events.submit({preventDefault(){},currentTarget:ids['r88-controls']}),change:()=>ids['r88-controls'].events.change(),preview:()=>ids['r89-preview'].events.click(),providerChange:()=>ids['r89-provider'].events.change()}
}
const response=data=>({ok:true,json:async()=>data})
test('F3 real read-model receipt renders context, gaps and controls; input invalidates all results',async()=>{
 const u=setup(async()=>response(fixture()))
 await u.submit()
 assert.match(u.ids['r88-status'].textContent,/Jejak tersedia/)
 assert.match(u.ids['r88-context'].textContent,/2026/)
 assert.equal(u.ids['r88-export'].disabled,false)
 assert.equal(u.ids['r89-preview'].disabled,false)
 assert.ok(u.ids['r88-results'].children.some(n=>n.className==='pi-crosswalk-gaps'))
 u.filters.year='2027';u.change()
 assert.equal(u.ids['r88-results'].children.length,0)
 for(const id of ['r88-export','r89-preview','r89-consent','r89-run'])assert.equal(u.ids[id].disabled,true,id)
 assert.equal(u.ids['r89-data'].hidden,true)
})
test('F3 late crosswalk response cannot overwrite changed filters',async()=>{
 let complete
 const u=setup(()=>new Promise(r=>complete=r))
 const pending=u.submit();u.filters.year='2027';u.change();complete(response(fixture()));await pending
 assert.equal(u.ids['r88-results'].children.length,0);assert.equal(u.ids['r88-export'].disabled,true)
 assert.match(u.ids['r88-status'].textContent,/Pilihan berubah/)
})
test('F3 rejects wrong context, equivalent definitions, duplicate relation and premature admission',async()=>{
 for(const mutate of [d=>d.filters.year=2027,d=>d.continuity.equivalence_asserted=true,d=>d.relations.push(d.relations[0]),d=>d.interpretation.substantive_alignment='PASS',d=>d.hierarchy[0].national_master_verified=true,d=>d.operational_definitions[0].relation='EQUIVALENT']){
  const d=fixture();mutate(d);const u=setup(async()=>response(d));await u.submit()
  assert.equal(u.ids['r88-results'].children.length,0);assert.equal(u.ids['r88-export'].disabled,true);assert.equal(u.ids['r89-preview'].disabled,true)
 }
})
test('F3 provider change while preview is pending cannot enable obsolete consent',async()=>{
 let complete
 const u=setup(async url=>url.includes('planning-crosswalk')?response(fixture()):new Promise(r=>complete=r))
 await u.submit()
 const pending=u.preview()
 u.ids['r89-provider'].value='groq';u.providerChange()
 complete(response({}));await pending
 assert.equal(u.ids['r89-consent'].disabled,true);assert.equal(u.ids['r89-run'].disabled,true);assert.equal(u.ids['r89-data'].hidden,true)
})
