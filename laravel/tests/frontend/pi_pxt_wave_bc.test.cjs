'use strict'
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm')
const read=n=>fs.readFileSync(path.join(__dirname,'../../public',n),'utf8')
test('PXT shared glossary never treats unknown states or inherited object keys as admitted',()=>{
 const scope={window:{}};vm.runInNewContext(read('pi-glossary.js'),scope)
 const g=scope.window.PIGlossary
 assert.equal(g.status('DENIED'),'Tidak diizinkan')
 assert.equal(g.status('NOT_ASSESSED'),'Belum dinilai')
 assert.equal(g.status('BLOCKED'),'Belum diizinkan')
 assert.equal(g.status('NO_ACTIVE_DECISION'),'Belum ada keputusan pemeriksa')
 assert.equal(g.status('VERIFIED_SOURCE_BYTES'),'Berkas cocok dengan arsip · keabsahan hukum belum dinilai')
 assert.equal(g.status('toString'),'Perlu pemeriksaan lanjutan')
 assert.equal(g.status('SOMETHING_NEW'),'Perlu pemeriksaan lanjutan')
 assert.equal(g.relation('IMPLEMENTS_PROVISION_OF_CANDIDATE'),'Diduga melaksanakan ketentuan')
 assert.equal(g.role('institution_admin'),'Administrator institusi')
 assert.equal(g.role('__proto__'),'Akun lokal')
 assert.ok(Object.isFrozen(g))
})
test('PXT mobile navigation retains links, restores desktop and maintains aria state',()=>{
 const handlers={},button={hidden:true,addEventListener:(n,f)=>handlers[n]=f,setAttribute:(k,v)=>button[k]=v}
 const nav={hidden:false},media={matches:true,addEventListener:(n,f)=>handlers.media=f}
 vm.runInNewContext(read('pi-pxt-shell.js'),{document:{addEventListener:(n,f)=>f(),querySelector:()=>button,getElementById:()=>nav},matchMedia:()=>media})
 assert.equal(nav.hidden,true);assert.equal(button['aria-expanded'],'false');assert.equal(button.hidden,false)
 handlers.click();assert.equal(nav.hidden,false);assert.equal(button['aria-expanded'],'true')
 handlers.click();assert.equal(nav.hidden,true)
 media.matches=false;handlers.media();assert.equal(nav.hidden,false);assert.equal(button.hidden,true)
})
test('PXT case and dashboard share shell and preserve evidence/consent controls',()=>{
 for(const page of ['index.html','development-case-r85.html']){
  const h=read(page)
  for(const file of ['pi-design-tokens.css','pi-pxt-dashboard.css','pi-pxt-shell.js','pi-glossary.js','pi-brand.svg'])assert.ok(h.includes(file),file)
  assert.match(h,/aria-controls="pi-primary-nav"/)
  assert.match(h,/id="pi-primary-nav"/)
 }
 const h=read('development-case-r85.html')
 for(const id of ['r85-filters','r85-questions','r85-graphs','r85-guards','r88-controls','r88-results','r89-preview','r89-data','r89-consent','r89-run','r106-run','r106-rows'])assert.equal((h.match(new RegExp('id="'+id+'"','g'))||[]).length,1,id)
 assert.match(h,/id="r89-consent" type="checkbox" disabled/)
 assert.match(h,/id="r89-run"[^>]*disabled/)
 assert.match(h,/potensi biaya API/)
 assert.match(read('pi-pxt-workspace.css'),/overflow-x:auto/)
})
