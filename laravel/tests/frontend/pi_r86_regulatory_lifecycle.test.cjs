const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

const root = path.resolve(__dirname, '../..')
const html = fs.readFileSync(path.join(root, 'public/regulatory-lifecycle-r86.js'), 'utf8')
const page = fs.readFileSync(path.join(root, 'public/development-case-r85.html'), 'utf8')
const css = fs.readFileSync(path.join(root, 'public/regulatory-lifecycle-r86.css'), 'utf8')
const r85Css = fs.readFileSync(path.join(root, 'public/development-case-r85.css'), 'utf8')

test('R86 is integrated into the existing R85 PI Superior workspace', () => {
  assert.match(page, /id="r86-lifecycle"/)
  assert.match(page, /DASAR KEWENANGAN/i)
  assert.match(page, /regulatory-lifecycle-r86\.js/)
  assert.match(page, /regulatory-lifecycle-r86\.css/)
  assert.match(page, /regulatory-trace-r70a\.html#r84-review-workspace/)
  assert.doesNotMatch(page, /iframe/)
  assert.equal(fs.existsSync(path.join(root, 'public/development-case-r86.html')), false)
})

test('R86 uses one authenticated read model, five shared specialist views, and fail-closed gates', () => {
  assert.match(html, /fetch\(`\/api\/qa-superior-r86\/regulatory-lifecycle/)
  assert.match(html, /credentials:'same-origin'/)
  assert.match(html, /specialized_views\?\.length !== 5/)
  assert.match(html, /entry_count !== 38/)
  assert.match(html, /legal_harmonization !== 'NOT_ASSESSED'/)
  assert.match(html, /numeric_comparison !== 'BLOCKED'/)
  assert.match(html, /review_decision !== 'NO_ACTIVE_DECISION'/)
  assert.match(html, /production_admission !== 'DENIED'/)
  assert.doesNotMatch(html, /innerHTML|insertAdjacentHTML|document\.write/)
  assert.doesNotMatch(html, /fetch\(['"]https?:/)
})

test('R86 provides inspectable official evidence, stage filtering, JSON and Markdown export', () => {
  assert.match(page, /id="r86-stage"/)
  assert.match(page, /id="r86-as-of"/)
  assert.match(page, /id="r86-export-json"/)
  assert.match(page, /id="r86-export-md"/)
  assert.match(html, /allowedHosts/)
  assert.match(html, /noopener noreferrer/)
  assert.match(html, /application\/json;charset=utf-8/)
  assert.match(html, /text\/markdown;charset=utf-8/)
  assert.match(html, /NOT_APPROVED/)
})

test('R86 temporal labels state their metadata-only scope', () => {
  assert.match(html, /BERLAKU SECARA TEMPORAL \(METADATA\)/)
  assert.match(html, /bukan sertifikasi penerapan hukum, harmonisasi, atau keputusan reviewer/i)
  assert.match(css, /\.r86-temporal-note/)
})

test('R86 layout has explicit desktop and mobile rules', () => {
  assert.match(css, /grid-template-columns:repeat\(5,minmax\(0,1fr\)\)/)
  assert.match(css, /@media \(max-width:720px\)/)
  assert.match(css, /\.r86-controls,.r86-metrics,.r86-temporal,.r86-stages,.r86-apps,.r86-brief \{ grid-template-columns:1fr; \}/)
  assert.match(r85Css, /\.r85-evidence-item > \* \{ min-width:0; overflow-wrap:anywhere; \}/)
  assert.match(r85Css, /\.r85-evidence-item \{ grid-template-columns:minmax\(0,1fr\); \}/)
})

