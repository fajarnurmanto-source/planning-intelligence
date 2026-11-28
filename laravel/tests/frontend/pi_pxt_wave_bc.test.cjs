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

test('PXT roadmap: preserve 1+5, normative first, direct evidence and persistent material limits',()=>{
 const f=read('planner-workflow-r108.js'),h=read('development-case-r85.html'),home=read('index.html'),j=read('development-case-r85.js')
 assert.match(f,/ringkasan:\['Lanjut: Periksa dokumen dan bukti','dokumen'\]/)
 assert.match(f,/dokumen:\['Lanjut: Telaah kewenangan','kewenangan'\]/)
 assert.match(f,/kewenangan:\['Lanjut: Telaah perencanaan','rencana'\]/)
 assert.match(f,/first\.parentNode\.insertBefore\(guardPanel,first\)/)
 assert.doesNotMatch(f,/ringkasan:\[[^\n]*r85-guards/)
 assert.match(h,/RPJPD dan RPJMD bukan norma hukum tertinggi/)
 assert.match(h,/belum membuktikan kelengkapan penelusuran norma induk/)
 assert.match(home,/bukan hierarki norma hukum/)
 assert.match(home,/PI Superior menyatukan/)
 for(const domain of ['Evidence','Regulatory','Performance','Fiscal','Evaluation'])assert.ok(f.includes(domain+':'))
 assert.match(f,/Evaluasi realisasi dan outcome belum lengkap/)
 assert.match(j,/substantive_alignment !== 'NOT_ASSESSED'/)
 assert.match(j,/numeric_comparison !== 'BLOCKED'/)
 assert.match(j,/review_decision !== 'NO_ACTIVE_DECISION'/)
})
