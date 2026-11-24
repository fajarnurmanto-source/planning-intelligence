'use strict'
const test=require('node:test')
const assert=require('node:assert/strict')
const fs=require('node:fs')
const path=require('node:path')
const dir=path.resolve(__dirname,'../../public')
const read=name=>fs.readFileSync(path.join(dir,name),'utf8')
test('R108 navigation uses practitioner task hierarchy across shared pages',()=>{
 const nav=read('pi-ia-r69e.js')
 for(const label of ['Beranda kerja','Telaah perencanaan','Anggaran dan pendanaan','Pelaksanaan dan evaluasi','Dokumen dan dasar hukum','Temuan dan tindak lanjut']){
  assert.ok(nav.includes(label),label)
 }
 assert.match(nav,/module:'regulatory'/)
 assert.match(nav,/modulesAllowed\.has\(item\.module\)/)
 assert.doesNotMatch(nav,/APLIKASI UTAMA|5 APLIKASI SPESIALIS/)
})
test('R108 protects existing IDs and uses non-destructive task views',()=>{
 const app=read('planner-workflow-r108.js')
 const page=read('development-case-r85.html')
 const home=read('index.html')
 for(const id of ['r88-crosswalk','r86-lifecycle','r106-annex-review','r100-official-sources','r85-graphs','r85-guards']){
  assert.match(app,new RegExp(id))
  assert.match(page,new RegExp(id))
 }
 assert.match(app,/el\.hidden=key!==selected/)
 assert.match(app,/hashchange/)
 assert.match(app,/#r106-annex-review/)
 assert.match(app,/pi-view=anggaran/)
 assert.match(home,/planner-workflow-r108\.js/)
 assert.match(page,/planner-workflow-r108\.js/)
 assert.doesNotMatch(app,/innerHTML|document\.write|localStorage|sessionStorage|method:'POST'|fetch\(/)
})
test('R108 one case flow preserves original R106, auth and anti-admission UI',()=>{
 const caseHtml=read('development-case-r85.html')
 const r106=read('native-annex-r106.js')
 assert.match(caseHtml,/id="r106-run"/)
 assert.match(caseHtml,/id="r106-source-links"/)
 assert.match(caseHtml,/id="r106-rows"/)
 assert.match(r106,/numeric_admission!==0/)
 assert.match(r106,/production_admission!=='DENIED'/)
 assert.match(caseHtml,/pi-auth-shell\.js/)
 assert.match(read('planner-workflow-r108.css'),/\[data-pi-planner-view\]\[hidden\]/)
})
