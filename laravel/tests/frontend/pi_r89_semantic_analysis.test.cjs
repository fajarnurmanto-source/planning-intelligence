'use strict'

const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

const publicDir = path.join(__dirname, '../../public')
const page = fs.readFileSync(path.join(publicDir, 'development-case-r85.html'), 'utf8')
const app = fs.readFileSync(path.join(publicDir, 'regulatory-planning-r88.js'), 'utf8')

test('R89 controls are embedded inside existing R88 case without chatbot or auto inference', () => {
  assert.match(page, /id="r88-crosswalk"/)
  assert.match(page, /id="r89-panel"/)
  assert.match(page, /id="r89-consent" type="checkbox"/)
  assert.match(page, /id="r89-run"[^>]*disabled/)
  assert.match(page, /model lokal/)
  assert.doesNotMatch(page, /<iframe[^>]+ollama/i)
})

test('R89 local model can only be invoked on consented click and authenticated CSRF session', () => {
  assert.match(app, /approved\.addEventListener\('change'/)
  assert.match(app, /get\('r89-run'\)\.addEventListener\('click'/)
  assert.match(app, /if \(!approved\.checked \|\| !data \|\| data\.result_count !== 1\) return/)
  assert.match(app, /\/api\/auth\/session/)
  assert.match(app, /'X-PI-CSRF':session\.csrf/)
  assert.match(app, /method:'POST'/)
  assert.match(app, /\/api\/qa-superior-r89\/semantic-candidates/)
})

test('R89 result is fail-closed and retained inside R88 JSON export only', () => {
  assert.match(app, /output\.source_registry_sha256 !== data\.source_registry_sha256/)
  assert.match(app, /output\.definitions_registry_sha256 !== data\.definitions_registry_sha256/)
  assert.match(app, /gate\?\.relation_promotion !== 'NOT_PERFORMED'/)
  assert.match(app, /gate\?\.production_admission !== 'DENIED'/)
  assert.match(app, /data\.semantic_reasoning = reasoning/)
  assert.match(app, /data\.semantic_analysis =/)
  assert.match(app, /JSON\.stringify\(data,null,2\)/)
  assert.match(app, /textContent =/)
})
