/* Dependency-free R87 asset packaging tests; browser performance is separately verified. */
const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const {execFileSync} = require('node:child_process')
const dir = path.resolve(__dirname, '../../public')
const generator = path.resolve(__dirname, '../../../scripts/pi-build-regulatory-assets.mjs')
const js = [
  'regulatory-laravel-r70g.js','regulatory-change-trace-r72.js',
  'regulatory-genealogy-r74.js','planning-lineage-r70j.js',
  'planning-program-r70k.js','normative-root-r70l.js',
  'regulatory-discovery-r70o.js','regulatory-ancestry-r70r.js',
  'regulatory-review-r75.js'
]
const css = [
  'regulatory-r69e.css','regulatory-trace-r70a.css',
  'regulatory-scope-r70d.css','regulatory-laravel-r70g.css',
  'regulatory-change-trace-r72.css','regulatory-genealogy-r74.css',
  'planning-lineage-r70j.css','regulatory-review-r75.css'
]
const read = name => fs.readFileSync(path.join(dir,name),'utf8')

test('generated bundles are deterministic and have no source drift', () => {
  const report = execFileSync(process.execPath,[generator,'--check'],{encoding:'utf8'})
  assert.match(report,/CHECK_PASS/)
})

test('R70G retains only six direct static JS/CSS references', () => {
  const html=read('regulatory-laravel-r70g.html')
  assert.equal((html.match(/<script\b[^>]*\bsrc="/g)||[]).length,3)
  assert.equal((html.match(/<link\b[^>]*rel="stylesheet"/g)||[]).length,3)
  for(const f of js) assert.ok(!html.includes('src="./'+f), 'Old JS still loaded: '+f)
  for(const f of css) assert.ok(!html.includes('href="./'+f), 'Old CSS still loaded: '+f)
  for(const f of ['pi-auth-shell.js','pi-ia-r69e.js','institution-home.css','pi-ia-r69e.css'])
    assert.ok(html.includes('./'+f), 'Shared/cached asset missing: '+f)
})

test('all existing feature scripts and styles are preserved in original order', () => {
  for(const [items,file] of [[js,'regulatory-workbench-r87.bundle.js'],
    [css,'regulatory-workbench-r87.bundle.css']]){
    const bundle=read(file)
    let previous=-1
    for(const name of items){
      const pos=bundle.indexOf('/* BEGIN '+name+' */')
      assert.ok(pos>previous,'Incorrect source order for '+name)
      assert.ok(bundle.includes(read(name).trimEnd(),pos),'Missing original source '+name)
      previous=pos
    }
  }
})

test('bundled CSS has no relative asset URLs or imports', () => {
  for(const file of css)
    assert.doesNotMatch(read(file),/@import\b|url\s*\(/i,'Asset references found in '+file)
})

test('bundled JavaScript parses successfully under Node', () => {
  execFileSync(process.execPath,['--check',path.join(dir,'regulatory-workbench-r87.bundle.js')])
})
