const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const browserSource = fs.readFileSync(path.resolve(__dirname, '../../public/regulatory-trace-acceptance-r79.js'), 'utf8')
const moduleShim = {exports: {}}
vm.runInNewContext(browserSource, {module: moduleShim}, {filename:'regulatory-trace-acceptance-r79.js'})
const {evaluate} = moduleShim.exports

const ids = ['PERDA11_2025','PERWALI74_2025','PERWALI1_2026','PERWALI4_2026','PERWALI12_2026','PERWALI13_2026','PERWALI15_2026']
const sha = id => id.padEnd(64,'A').slice(0,64).replace(/[^A-F0-9]/g,'A')

function fixtures() {
  const trace = {
    schema:'PI_R70A_REGULATORY_LEGAL_TRACE_QA_V1',
    tenant_id:'ID-DIY-YOG',decision:'ABSTAIN',
    legacy_feature_parity:'PARTIAL_NOT_EQUIVALENT',
    legal_harmonization:'NOT_ASSESSED',numeric_comparison:'BLOCKED',
    production_admission:'DENIED',version_reconciliation:'NOT_PERFORMED',
    nodes:ids.map(id=>({id,node_type:'LOCAL_SOURCE_SNAPSHOT',pdf_sha256:sha(id)})),
    amendment_timeline:Array.from({length:5},(_,i)=>({sequence:i+1,status:'ORDER_ONLY_NOT_VERSION_RECONCILED'})),
    relationships:Array.from({length:5},(_,i)=>({
      source:i===0?'PP-81-2012':'UU-15-2019',
      target:i===0?'UU-18-2008:PASAL-16':'UU-12-2011',
      kind:i===0?'IMPLEMENTS_PROVISION_OF':'AMENDS',
      status:'PRIMARY_TEXT_LOCATOR_NOT_HASH_PINNED',
      evidence:'Lokasi sumber resmi',href:'https://peraturan.go.id/files/example.pdf',
    })),
  }
  const catalog = {
    schema:'PI_R70B_OFFICIAL_SOURCE_SNAPSHOTS_QA_V1',
    tenant_id:'ID-DIY-YOG',total:7,verified:7,legal_harmonization:'NOT_ASSESSED',
    production_admission:'DENIED',
    sources:ids.map(id=>({id,sha256:sha(id),has_local_pdf:true,identity:'PDF_SHA256_MATCHES_LEDGER'})),
  }
  const graph = nodes => ({
    schema:'PI_R74_BIDIRECTIONAL_GENEALOGY_TRACE_V1',tenant_id:'ID-DIY-YOG',
    legal_harmonization:'NOT_ASSESSED',production_admission:'DENIED',
    result_status:'PARTIAL_EVIDENCE_GRAPH_NOT_LEGAL_FINDING',
    nodes:nodes.map(id=>({id})),
  })
  const parents=graph(['PP-81-2012','UU-18-2008:PASAL-16','UU-18-2008'])
  const children=graph(['UU-18-2008','UU-18-2008:PASAL-16','PP-81-2012'])
  const thematic=graph(['TOPIC:PERSAMPAHAN','UU-18-2008','PP-81-2012'])
  return {trace,catalog,parents,children,thematic}
}
const evalFixture = f => evaluate(f.trace,f.catalog,f.parents,f.children,f.thematic)

test('R79 in-page browser QA has exactly eight passing source/lineage checks',()=>{
  const result=evalFixture(fixtures())
  assert.equal(result.pass,true)
  assert.equal(result.checks.length,8)
  assert.equal(result.checks.every(x=>x.pass),true)
})

test('PDF snapshot drift fails QA independently of a successful R74 graph',()=>{
  const f=fixtures()
  f.catalog.sources[0].sha256='B'.repeat(64)
  const result=evalFixture(f)
  assert.equal(result.pass,false)
  assert.equal(result.checks.find(x=>x.label.includes('identitas dan SHA'))?.pass,false)
  assert.equal(result.checks.find(x=>x.label.includes('tema Persampahan'))?.pass,true)
})

test('R74 missing parent/descendant/thematic nodes fails all relevant checks',()=>{
  const f=fixtures()
  f.parents.nodes=f.parents.nodes.filter(x=>x.id!=='UU-18-2008:PASAL-16')
  f.children.nodes=f.children.nodes.filter(x=>x.id!=='PP-81-2012')
  f.thematic.nodes=f.thematic.nodes.filter(x=>x.id!=='UU-18-2008')
  const result=evalFixture(f)
  assert.equal(result.pass,false)
  assert.equal(result.checks.slice(5).filter(x=>x.pass).length,0)
})

test('Legal certification or production admission invalidates QA',()=>{
  const f=fixtures()
  f.trace.production_admission='GRANTED'
  f.trace.relationships[0].status='CERTIFIED_HARMONIZATION'
  const result=evalFixture(f)
  assert.equal(result.pass,false)
  assert.equal(result.checks[0].pass,false)
  assert.equal(result.checks[4].pass,false)
})

test('The R79 page contains the user-triggered acceptance panel and script',()=>{
  const html=fs.readFileSync(path.resolve(__dirname,'../../public/regulatory-trace-r70a.html'),'utf8')
  assert.ok(html.includes('id="r79-acceptance-run"'))
  assert.ok(html.includes('id="r79-acceptance-result"'))
  assert.ok(html.includes('regulatory-trace-acceptance-r79.js'))
  assert.ok(html.includes('bukan</strong> validasi hukum'))
})
