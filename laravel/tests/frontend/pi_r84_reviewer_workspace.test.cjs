const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

const source = (name) => fs.readFileSync(path.resolve(__dirname,'../../public',name),'utf8')
const html = source('regulatory-trace-r70a.html')
const js = source('regulatory-evidence-review-r84.js')

test('R84 operational reviewer is on existing regulatory workspace, next to R82 and R83', () => {
  assert.ok(html.includes('id="r84-review-workspace"'))
  assert.ok(html.includes('regulatory-evidence-review-r84.js'))
  for (const id of ['r84-review-status', 'r84-candidate-menu', 'r84-evidence-detail',
    'r84-decision-form', 'r84-review-dimension', 'r84-review-decision',
    'r84-review-reason', 'r84-reviewed-original', 'r84-review-history']) {
    assert.ok(html.includes('id="'+id+'"'), id)
  }
  assert.ok(html.includes('regulatory-trace-acceptance-r79.js'))
  assert.ok(html.includes('regulatory-passage-review-r82.js'))
})

test('R84 only presents seven original immutable national candidates', () => {
  assert.ok(js.includes('const ARTICLES = [11, 16, 20, 22, 24, 25, 28]'))
  assert.ok(js.includes("'/api/qa-regulatory-r82/passage-candidates'"))
  assert.ok(js.includes("report.review_status === 'REVIEW_REQUIRED'"))
  assert.ok(js.includes("report.relation_admission === 'NOT_PERFORMED'"))
  assert.ok(js.includes("report.legal_harmonization === 'NOT_ASSESSED'"))
  assert.ok(js.includes("report.production_admission === 'DENIED'"))
  assert.ok(js.includes("isSha(candidate.review_candidate_sha256)"))
})

test('Reviewer never writes while migration is pending or role is insufficient', () => {
  assert.ok(js.includes('state.ready = false'))
  assert.ok(js.includes("data.review_storage_state === 'READY'"))
  assert.ok(js.includes("REVIEW_ROLES.includes(state.session.role)"))
  assert.ok(js.includes("node.hidden = !allowed"))
  assert.ok(js.includes("if (!canSubmit() || !dom('r84-reviewed-original').checked) return"))
  assert.ok(js.includes("Penyimpanan keputusan belum aktif"))
})

test('Review POST submits server-generated hashes, same-origin session, CSRF and bounded reason', () => {
  assert.ok(js.includes("method:'POST'"))
  assert.ok(js.includes("'X-CSRF-TOKEN':state.session.csrf"))
  assert.ok(js.includes("source_manifest_sha256:state.report.r81_manifest_sha256"))
  assert.ok(js.includes("candidate_sha256:candidate.review_candidate_sha256"))
  assert.ok(js.includes('reason.length < 12 || reason.length > 2000'))
  assert.ok(js.includes("credentials:'same-origin'"))
  assert.ok(js.includes("response.status !== 201"))
  assert.ok(js.includes("result.relation_promotion !== 'NOT_PERFORMED'"))
  assert.ok(js.includes("result.production_admission !== 'DENIED'"))
})

test('Review history, evidence and links are protected from HTML injection and external URL control', () => {
  assert.ok(js.includes("create('blockquote', 'r84-quote', excerpt.excerpt"))
  assert.ok(js.includes("['PP-81-2012', 'UU-18-2008'].includes(sourceId)"))
  assert.ok(js.includes("encodeURIComponent(sourceId) + '#page=' + page"))
  assert.ok(js.includes("link.rel = 'noopener noreferrer'"))
  assert.ok(js.includes("entry.candidate_sha256 !== state.selected.review_candidate_sha256"))
  assert.equal(js.includes('innerHTML'),false)
  assert.equal(js.includes('localStorage'),true) // prohibition explicitly stated in the module header
  assert.equal(/fetch\(\s*['"]https?:\/\//i.test(js),false)
})

test('Frontend does not perform migrations or grant legal or production admission', () => {
  assert.ok(js.includes("state.ready"))
  assert.ok(js.includes("result.production_admission !== 'DENIED'"))
  assert.equal(/artisan\s+migrate|schema::create|relation_promotion\s*:\s*['"]PROMOTED/i.test(js),false)
})
