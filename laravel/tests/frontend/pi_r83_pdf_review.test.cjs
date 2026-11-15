const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

const js = fs.readFileSync(path.resolve(__dirname, '../../public/regulatory-passage-review-r82.js'), 'utf8')
const html = fs.readFileSync(path.resolve(__dirname, '../../public/regulatory-trace-r70a.html'), 'utf8')

test('R83 only links to same-origin, verified two-source Laravel PDF endpoint', () => {
  assert.ok(js.includes("'/api/qa-regulatory-r83/national-pdf?source_id='"))
  assert.ok(js.includes("encodeURIComponent(excerpt.source_id) + '#page=' + excerpt.pdf_page"))
  assert.ok(js.includes("source_id: 'PP-81-2012'"))
  assert.ok(js.includes("source_id: 'UU-18-2008'"))
  assert.ok(js.includes("['PP-81-2012', 'UU-18-2008'].includes(excerpt.source_id)"))
  assert.ok(js.includes('Number.isInteger(excerpt.pdf_page)'))
  assert.ok(js.includes("pdfLink.rel = 'noopener noreferrer'"))
  assert.equal(js.includes('innerHTML'), false)
})

test('R83 does not replace R82 reviewer guard or accepted R79 acceptance panel', () => {
  assert.ok(js.includes("report.review_status !== 'REVIEW_REQUIRED'"))
  assert.ok(js.includes("record.relation_admission !== 'NOT_PERFORMED'"))
  assert.ok(html.includes('id="r82-passage-results"'))
  assert.ok(html.includes('regulatory-trace-acceptance-r79.js'))
  assert.ok(html.includes('r80-coverage-results'))
})
