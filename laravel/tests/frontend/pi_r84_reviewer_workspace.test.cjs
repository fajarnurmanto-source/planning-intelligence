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
  assert.equal(/\blocalStorage\.(getItem|setItem|removeItem|clear)\s*\(/.test(js),false)
  assert.equal(/fetch\(\s*['"]https?:\/\//i.test(js),false)
})

test('Frontend does not perform migrations or grant legal or production admission', () => {
  assert.ok(js.includes("state.ready"))
  assert.ok(js.includes("result.production_admission !== 'DENIED'"))
  assert.equal(/artisan\s+migrate|schema::create|relation_promotion\s*:\s*['"]PROMOTED/i.test(js),false)
})


// Real UI interaction test: launch the shipped IIFE against a minimal DOM and
// exercise a user click. No Laravel DB or legal decision is written.
const vm = require('node:vm')

async function citationFixture({clipboardWrite, pdfHash = 'B'.repeat(64)} = {}) {
  class Node {
    constructor(tag='div') {
      this.tagName = tag
      this.children = []
      this.handlers = {}
      this.dataset = {}
      this.classList = {toggle:()=>{}}
      this.style = {}
      this.textContent = ''
      this.value = ''
      this.checked = false
      this.hidden = false
      this.disabled = false
    }
    appendChild(node) { this.children.push(node); return node }
    replaceChildren(...children) { this.children = children }
    addEventListener(name, listener) { this.handlers[name] = listener }
    setAttribute(name, value) { this[name] = value }
    reset() { this.checked = false }
    select() {}
    remove() {}
  }

  const nodes = new Map()
  const requests = []
  const document = {
    body:new Node('body'),
    getElementById(id) {
      if (!nodes.has(id)) nodes.set(id,new Node())
      return nodes.get(id)
    },
    createElement:tag => new Node(tag),
    addEventListener(name,listener) {
      if (name === 'DOMContentLoaded') queueMicrotask(listener)
    },
    execCommand:() => false,
  }
  const sha = letter => letter.repeat(64)
  const excerpt = {pdf_page:4,excerpt_sha256:sha('D'),excerpt:'Pasal bersumber PDF nyata yang perlu diperiksa reviewer.',truncated:false}
  const candidates = [11,16,20,22,24,25,28].map(article=>({
    article,target_provision_id:'UU-18-2008:PASAL-'+article,paragraphs_requested:[1],
    review_status:'REVIEW_REQUIRED',relation_admission:'NOT_PERFORMED',legal_effect:'NOT_ASSESSED',
    evidence_status:'TEXT_LAYER_CANDIDATE_REVIEW_REQUIRED',review_candidate_sha256:sha('C'),
    pp_reference:null,uu_passages:[excerpt],
  }))
  const report = {
    schema:'PI_R82_NATIONAL_PASSAGE_CANDIDATES_QA_V1',tenant_id:'ID-DIY-YOG',
    r80_candidate_article_count:7,passage_verification:'UNREVIEWED_TEXT_LAYER_CANDIDATES_ONLY',
    review_status:'REVIEW_REQUIRED',relation_admission:'NOT_PERFORMED',
    legal_harmonization:'NOT_ASSESSED',production_admission:'DENIED',
    source_snapshot_integrity:'R81_TWO_INDEPENDENT_SHA256_PINS_MATCH',
    text_candidate_article_count:7,unresolved_article_count:0,
    r81_manifest_sha256:sha('A'),candidates,
    sources:[{instrument_id:'UU-18-2008',pdf_sha256:pdfHash,
      acquisition_url:'https://www.peraturan.go.id/files/UU%2B18%2BTahun%2B2008.pdf'}],
  }
  const fetch = async (url,options={}) => {
    requests.push({url,method:options.method||'GET'})
    let payload
    if (url === '/api/auth/session') payload = {
      status:'AUTHENTICATED',tenant_id:'ID-DIY-YOG',role:'institution_admin',
      csrf:'an-intentionally-fake-test-csrf',
    }
    else if (url.includes('passage-candidates')) payload=report
    else payload={schema:'PI_R84_CONTROLLED_RELATION_REVIEW_V1',
      review_storage_state:'READY',tenant_id:'ID-DIY-YOG',relation_promotion:'NOT_PERFORMED',
      production_admission:'DENIED',history:[]}
    return {ok:true,status:200,json:async()=>payload}
  }
  vm.runInNewContext(js,{document,fetch,navigator:{
    clipboard:{writeText:clipboardWrite || (async()=>{})},
  },queueMicrotask}, {filename:'regulatory-evidence-review-r84.js'})
  await new Promise(resolve=>setTimeout(resolve,15))
  const find = (node,predicate) => {
    if (predicate(node)) return node
    for (const child of node.children) {
      const match = find(child,predicate)
      if (match) return match
    }
    return null
  }
  const root = nodes.get('r84-evidence-detail')
  return {
    button:find(root,node=>node.className?.includes('r84-copy-citation')),
    feedback:find(root,node=>node.className === 'r84-copy-status'),
    requests,
  }
}

test('copied evidence citation includes original excerpt, locator, pinned SHA and pending-review warning', async () => {
  const writes=[]
  const fixture=await citationFixture({clipboardWrite:async text=>writes.push(text)})
  assert.ok(fixture.button,'A copy action must appear on a hash-verified excerpt')
  await fixture.button.handlers.click()
  assert.equal(writes.length,1)
  for (const fragment of [
    'BELUM DISAHKAN REVIEWER',
    'Kandidat: UU-18-2008:PASAL-11',
    'Dokumen: UU-18-2008',
    'Halaman PDF: 4',
    'Pasal bersumber PDF nyata yang perlu diperiksa reviewer.',
    'https://www.peraturan.go.id/files/UU%2B18%2BTahun%2B2008.pdf',
    'SHA-256 PDF: '+ 'B'.repeat(64),
    'SHA-256 kutipan: '+ 'D'.repeat(64),
    'SHA-256 manifest R81: '+ 'A'.repeat(64),
    'Fingerprint kandidat R84: '+ 'C'.repeat(64),
    'RELATION_ADMISSION=NOT_PERFORMED',
  ]) assert.ok(writes[0].includes(fragment),fragment)
  assert.match(fixture.feedback.textContent,/tersalin/i)
  assert.equal(fixture.button.disabled,false)
  assert.ok(fixture.requests.every(req=>req.method==='GET'),'Copy must never submit a legal decision')
})

test('clipboard permission failure is reported and does not mutate review records', async () => {
  const fixture=await citationFixture({clipboardWrite:async()=>{throw Error('not allowed')}})
  await fixture.button.handlers.click()
  assert.match(fixture.feedback.textContent,/gagal menyalin/i)
  assert.equal(fixture.button.disabled,false)
  assert.ok(fixture.requests.every(req=>req.method==='GET'))
})

test('citation handoff is withheld when source PDF hash is invalid', async () => {
  const fixture=await citationFixture({pdfHash:'invalid'})
  assert.equal(fixture.button,null)
})
