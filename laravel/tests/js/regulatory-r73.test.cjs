/* PI R73 browser-contract checks: Node built-ins only, no network or AI calls. */
'use strict'

const { test } = require('node:test')
const assert = require('node:assert/strict')
const { readFileSync } = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')

const script = readFileSync(path.resolve(__dirname, '../../public/regulatory-change-trace-r72.js'), 'utf8')
const hash = symbol => symbol.repeat(64)
const integrity = {
  ledger_sha256: hash('E'),
  scope_sha256: hash('F'),
  scope_links_to_ledger: true
}

class Element {
  constructor(tag) {
    this.tagName = tag
    this.children = []
    this.dataset = {}
    this.handlers = new Map()
    this.textContent = ''
    this.disabled = false
  }
  appendChild(child) {
    this.children.push(child)
    return child
  }
  replaceChildren(...children) { this.children = children }
  setAttribute(name, value) { this[name] = value }
  addEventListener(name, listener) { this.handlers.set(name, listener) }
}

function event(sourceId, title, sequence, pdfHash, evidenceHash, previous) {
  return {
    source_id: sourceId,
    source_pdf_sha256: pdfHash,
    source_title: title,
    sequence,
    target: 'LAMPIRAN I',
    target_item: 'I',
    change_verb_as_extracted: 'diubah',
    start_pdf_page: 1,
    operative_verb_pdf_page: 2,
    previous_target_source_id: previous,
    evidence_refs: [
      { source_id: sourceId, pdf_page: 2, excerpt_sha256: evidenceHash }
    ],
    review_status: 'REVIEW_REQUIRED',
    structural_relation: 'SUCCESSIVE_TARGET_MENTIONS_ONLY'
  }
}

const earlier = event('PERWALI1_2026', 'Perubahan Pertama', 1, hash('A'), hash('C'), null)
const later = event('PERWALI4_2026', 'Perubahan Kedua', 2, hash('B'), hash('D'), earlier.source_id)

function timeline() {
  return {
    schema: 'PI_R72_STRUCTURAL_AMENDMENT_TRACE_V1',
    source_integrity: { ...integrity },
    summary: { distinct_target_count: 1, structural_change_candidate_count: 2 },
    target_groups: [{
      target: 'LAMPIRAN I',
      event_count: 2,
      events: [earlier, later],
      interpretation: 'CHRONOLOGICAL_TARGET_MENTIONS_NOT_CONTENT_DIFF'
    }],
    review_status: 'CANDIDATE_TRACE_ONLY',
    legal_harmonization: 'NOT_ASSESSED',
    annex_content_diff: 'NOT_PERFORMED',
    numeric_comparison: 'BLOCKED',
    reviewer_gate: 'REQUIRED',
    production_admission: 'DENIED'
  }
}

function verified(eventData) {
  return {
    schema: 'PI_R70H_VERIFIED_EVIDENCE_LOOKUP_V1',
    source_id: eventData.source_id,
    source_pdf_sha256: eventData.source_pdf_sha256,
    source_title: eventData.source_title,
    excerpt_sha256: eventData.evidence_refs[0].excerpt_sha256,
    source_integrity: { ...integrity },
    review_status: 'REVIEW_REQUIRED',
    legal_harmonization: 'NOT_ASSESSED',
    numeric_comparison: 'BLOCKED',
    production_admission: 'DENIED',
    matches: [{
      pdf_page: 2,
      excerpt_sha256: eventData.evidence_refs[0].excerpt_sha256,
      excerpt: eventData.sequence === 1
        ? 'Lampiran I diubah melalui peraturan'
        : 'Lampiran I disesuaikan melalui peraturan',
      context: 'operative_target',
      context_detail: 'LAMPIRAN I',
      review_status: 'REVIEW_REQUIRED'
    }]
  }
}

const walk = element => [element, ...(element.children || []).flatMap(walk)]
const settle = () => new Promise(resolve => setImmediate(resolve))

function harness({ mutateTimeline, mutateEvidence, httpStatus } = {}) {
  const roots = Object.fromEntries(['r72-status', 'r72-groups', 'r72-summary']
    .map(name => [name, new Element('div')]))
  const document = {
    getElementById: name => roots[name],
    createElement: tag => new Element(tag),
    createTextNode: text => ({ tagName: '#text', textContent: text })
  }
  const requests = []
  const location = { redirected: null, replace(url) { this.redirected = url } }
  async function fetch(url, options) {
    requests.push({ url, options })
    assert.deepEqual({ ...options }, { credentials: 'same-origin', cache: 'no-store' })
    if (url === '/api/qa-regulatory-r72/change-trace') {
      const payload = timeline()
      mutateTimeline?.(payload)
      return { ok: true, status: 200, json: async () => payload }
    }
    const selected = url.includes(earlier.source_id) ? earlier : later
    const payload = verified(selected)
    mutateEvidence?.(payload, selected)
    if (httpStatus) return { ok: false, status: httpStatus }
    return { ok: true, status: 200, json: async () => payload }
  }
  vm.runInNewContext(script, { document, fetch, location }, { timeout: 2500 })
  return {
    roots,
    requests,
    location,
    buttons: () => walk(roots['r72-groups']).filter(node => node.tagName === 'button'),
    nodes: () => walk(roots['r72-groups'])
  }
}

test('R73 never requests evidence before reviewer clicks', async () => {
  const h = harness()
  await settle()
  assert.equal(h.requests.length, 1)
  assert.equal(h.requests[0].url, '/api/qa-regulatory-r72/change-trace')
  assert.equal(h.buttons().length, 1)
  assert.equal(h.nodes().filter(x => x.tagName === 'blockquote').length, 0)
})

test('R73 reviewer click loads exactly two hash-pinned, same-origin excerpts', async () => {
  const h = harness()
  await settle()
  await h.buttons()[0].handlers.get('click')()
  assert.equal(h.requests.length, 3)
  assert.ok(h.requests.slice(1).every(x => x.url.startsWith('/api/qa-regulatory-r70h/evidence/')))
  assert.deepEqual(h.nodes().filter(x => x.tagName === 'del').map(x => x.textContent), ['diubah'])
  assert.deepEqual(h.nodes().filter(x => x.tagName === 'ins').map(x => x.textContent), ['disesuaikan'])
  assert.equal(h.nodes().filter(x => x.tagName === 'blockquote').length, 2)
  assert.equal(h.nodes().filter(x => x.className === 'r73-guard').length, 1)
  assert.equal(h.buttons()[0].disabled, false)
})

test('R73 holds all excerpts when one source-integrity hash is changed', async () => {
  const h = harness({ mutateEvidence: (payload, selected) => {
    if (selected.source_id === later.source_id) payload.source_integrity.ledger_sha256 = hash('0')
  } })
  await settle()
  await h.buttons()[0].handlers.get('click')()
  assert.equal(h.nodes().filter(x => x.className === 'r73-error').length, 1)
  assert.equal(h.nodes().filter(x => x.tagName === 'blockquote').length, 0)
  assert.equal(h.nodes().filter(x => x.tagName === 'ins').length, 0)
})

test('R73 rejects evidence from a different operative target', async () => {
  const h = harness({ mutateEvidence: (payload, selected) => {
    if (selected.source_id === later.source_id) payload.matches[0].context_detail = 'LAMPIRAN II'
  } })
  await settle()
  await h.buttons()[0].handlers.get('click')()
  assert.equal(h.nodes().filter(x => x.className === 'r73-error').length, 1)
  assert.equal(h.nodes().filter(x => x.tagName === 'blockquote').length, 0)
})

test('R73 rejects an unapproved R72 reviewer gate before showing controls', async () => {
  const h = harness({ mutateTimeline: payload => { payload.reviewer_gate = 'OPTIONAL' } })
  await settle()
  assert.equal(h.buttons().length, 0)
  assert.equal(h.roots['r72-status'].dataset.error, 'true')
  assert.equal(h.requests.length, 1)
})

test('R73 handles expired sessions without exposing partial evidence', async () => {
  const h = harness({ httpStatus: 401 })
  await settle()
  await h.buttons()[0].handlers.get('click')()
  assert.equal(h.location.redirected, './login.html')
  assert.equal(h.nodes().filter(x => x.tagName === 'blockquote').length, 0)
})
