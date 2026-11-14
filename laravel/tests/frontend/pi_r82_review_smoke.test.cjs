const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

const page = fs.readFileSync(path.resolve(__dirname,'../../public/regulatory-trace-r70a.html'),'utf8')
const js = fs.readFileSync(path.resolve(__dirname,'../../public/regulatory-passage-review-r82.js'),'utf8')

test('R82 read-only evidence panel is embedded, distinct from R79/R80', () => {
  assert.ok(page.includes('id="r82-passage-results"'))
  assert.ok(page.includes('id="r82-passage-refresh"'))
  assert.ok(page.includes('regulatory-passage-review-r82.js'))
  assert.ok(page.includes('r79-acceptance-run'))
  assert.ok(page.includes('r80-coverage-results'))
})

test('R82 denies legal conclusions, and uses same-origin read only GET', () => {
  assert.ok(js.includes('/api/qa-regulatory-r82/passage-candidates'))
  assert.ok(js.includes("credentials: 'same-origin'"))
  assert.ok(js.includes("cache: 'no-store'"))
  assert.ok(js.includes("relation_admission !== 'NOT_PERFORMED'"))
  assert.ok(js.includes("production_admission !== 'DENIED'"))
  assert.ok(js.includes("review_status"))
  assert.equal(/method:\s*['"]POST['"]|localStorage|fetch\(\s*['"]https?:/i.test(js), false)
})

test('R82 reviewer quotes render with textContent (no HTML injection)', () => {
  assert.ok(js.includes('prose.textContent = excerpt.excerpt'))
  assert.equal(js.includes('innerHTML'), false)
  assert.ok(js.includes('excerpt.pdf_page'))
  assert.ok(js.includes('excerpt.excerpt_sha256'))
  assert.ok(js.includes('Laporan belum disiapkan'))
})
