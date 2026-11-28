'use strict'

const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const publicDir = path.join(__dirname, '../../public')
const page = fs.readFileSync(path.join(publicDir,'development-case-r85.html'),'utf8')
const app = fs.readFileSync(path.join(publicDir,'regulatory-planning-r88.js'),'utf8')

test('R89 is a provider-selectable semantic panel inside PI Superior, no local-model requirement', () => {
  assert.match(page,/id="r88-crosswalk"/)
  assert.match(page,/id="r89-panel"/)
  assert.match(page,/id="r89-provider"/)
  for (const p of ['deepseek','openai','gemini','groq','openrouter']) {
    assert.match(page,new RegExp('option value="' + p + '"'))
  }
  assert.match(page,/href="\/ai-settings.html"/)
  assert.match(page,/id="r89-preview"[^>]*disabled/)
  assert.match(page,/id="r89-consent" type="checkbox" disabled/)
  assert.match(page,/id="r89-run"[^>]*disabled/)
  assert.doesNotMatch(page, /Ollama|GPU lokal diperlukan/)
})

test('GET preview is read-only and visible before any consented cloud request', () => {
  assert.match(app,/\/api\/qa-superior-r89\/semantic-preview/)
  assert.match(app,/get\('r89-data'\)\.textContent = JSON\.stringify\(payload\.preview\.context,null,2\)/)
  assert.match(app,/get\('r89-data'\)\.hidden = false/)
  assert.match(app,/approved\.disabled = false/)
  assert.match(app,/get\('r89-run'\)\.addEventListener\('click'/)
  assert.match(app,/if \(!approved\.checked \|\| !semanticPreview \|\| !data \|\| !sameFilters\(\)\) return/)
})

test('cloud inference requires selected provider, exact context hash, consent and authenticated CSRF', () => {
  assert.match(app,/method:'POST'/)
  assert.match(app,/\/api\/auth\/session/)
  assert.match(app,/'X-CSRF-TOKEN':session\.csrf/)
  assert.doesNotMatch(app,/X-PI-CSRF/)
  assert.match(app,/provider:get\('r89-provider'\)\.value/)
  assert.match(app,/approved_cloud_metadata:true/)
  assert.match(app,/approved_provider_charges:true/)
  assert.match(app,/approved_context_sha256:semanticPreview\.preview\.sha256/)
  assert.match(app,/payload\.context_sha256 !== semanticPreview\.preview\.sha256/)
})

test('R89 output must retain legal/financial gates and be exported as R88 read-model supplement', () => {
  assert.match(app,/gate\?\.equivalence_asserted !== false/)
  assert.match(app,/gate\?\.relation_promotion !== 'NOT_PERFORMED'/)
  assert.match(app,/gate\?\.numeric_comparison !== 'BLOCKED'/)
  assert.match(app,/gate\?\.production_admission !== 'DENIED'/)
  assert.match(app,/data\.semantic_reasoning = reasoning/)
  assert.match(app,/data\.semantic_analysis =/)
  assert.match(app,/JSON\.stringify\(data,null,2\)/)
  assert.match(app,/textContent =/)
})

test('consent and previously previewed context are reset on changed provider or R88 filters', () => {
  assert.match(app,/get\('r89-provider'\)\.addEventListener\('change'/)
  assert.match(app,/get\('r88-controls'\)\.addEventListener\('change'/)
  assert.match(app,/semanticPreview = null/)
  assert.match(app,/get\('r89-consent'\)\.checked = false/)
})

test('APR shows safe escalation and consumes one approval after any attempt', () => {
  assert.match(app,/PROVIDER_BALANCE:'Saldo/)
  assert.match(app,/PROVIDER_REQUEST_UNCERTAIN/)
  assert.match(app,/Tidak ada pemanggilan ulang otomatis/)
  assert.match(app,/provider_recovery:payload.provider_recovery/)
  assert.match(app,/Consume this approval/)
  assert.match(app,/finally \{\s*semanticPreview = null\s*approved.checked = false/)
})
