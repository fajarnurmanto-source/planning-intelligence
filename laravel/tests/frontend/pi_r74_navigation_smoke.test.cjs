/* Dependency-free Node.js smoke tests for the shared PI sidebar. */
const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')
const path = require('node:path')

function mount(pathname, hash) {
  class El {
    constructor(tag) {
      this.tagName = tag
      this.children = []
      this.dataset = {}
      this.attributes = {}
      this.names = new Set()
      this.classList = {
        add: name => this.names.add(name),
        contains: name => this.names.has(name)
      }
    }
    appendChild(child) { this.children.push(child); return child }
    replaceChildren() { this.children = [] }
    setAttribute(name, value) { this.attributes[name] = value }
    addEventListener() {}
    querySelectorAll(selector) {
      return selector === 'details.pi-app-group'
        ? descendants(this).filter(el => el.tagName === 'details' && el.names.has('pi-app-group'))
        : []
    }
  }
  const descendants = root => [root, ...root.children.flatMap(descendants)]
  const nav = new El('nav')
  const events = {}
  const location = {pathname, hash, search: ''}
  const document = {
    createElement: tag => new El(tag),
    querySelector: selector => selector === 'nav[data-pi-app-nav]' ? nav : null,
    querySelectorAll: selector => selector === 'nav[data-pi-app-nav]' ? [nav] : [],
    getElementById: () => null,
    addEventListener: () => {}
  }
  const context = {
    document, location, URLSearchParams, Set,
    window: {addEventListener: (event, cb) => {events[event] = cb}},
    requestAnimationFrame: cb => cb(),
    fetch: () => Promise.reject(new Error('Offline navigation smoke test')),
    MutationObserver: class {observe() {}}
  }
  const source = fs.readFileSync(path.resolve(__dirname, '../../public/pi-ia-r69e.js'), 'utf8')
  vm.runInNewContext(source, context, {filename: 'pi-ia-r69e.js'})
  const links = () => descendants(nav).filter(el => el.tagName === 'a')
  const item = label => links().find(el => el.textContent === label)
  return {nav, events, location, links, item}
}

test('Genealogy and Review have separate direct links', () => {
  const ui = mount('/index.html', '')
  assert.equal(ui.item('Genealogi Regulasi (R74)')?.href, './regulatory-laravel-r70g.html#r74-title')
  assert.equal(ui.item('Meja Review Terpadu (R75)')?.href, './regulatory-laravel-r70g.html#r75-title')
})

test('Direct genealogy link activates R74, not R75', () => {
  const ui = mount('/regulatory-laravel-r70g.html', '#r74-title')
  assert.equal(ui.item('Genealogi Regulasi (R74)')?.classList.contains('active'), true)
  assert.equal(ui.item('Meja Review Terpadu (R75)')?.classList.contains('active'), false)
})

test('Direct review link activates R75, not R74', () => {
  const ui = mount('/regulatory-laravel-r70g.html', '#r75-title')
  assert.equal(ui.item('Genealogi Regulasi (R74)')?.classList.contains('active'), false)
  assert.equal(ui.item('Meja Review Terpadu (R75)')?.classList.contains('active'), true)
})

test('Switching hash updates active item and never duplicates navigation', () => {
  const ui = mount('/regulatory-laravel-r70g.html', '#r75-title')
  assert.equal(typeof ui.events.hashchange, 'function')
  ui.location.hash = '#r74-title'
  ui.events.hashchange()
  assert.equal(ui.item('Genealogi Regulasi (R74)')?.classList.contains('active'), true)
  assert.equal(ui.item('Meja Review Terpadu (R75)')?.classList.contains('active'), false)
  assert.equal(ui.links().filter(x => x.textContent === 'Genealogi Regulasi (R74)').length, 1)
  ui.location.hash = '#r75-title'
  ui.events.hashchange()
  assert.equal(ui.item('Genealogi Regulasi (R74)')?.classList.contains('active'), false)
  assert.equal(ui.item('Meja Review Terpadu (R75)')?.classList.contains('active'), true)
})

test('Retired R70A-D menu links resolve to honest Laravel equivalents', () => {
  const ui = mount('/index.html', '')
  const html = fs.readFileSync(path.resolve(__dirname, '../../public/regulatory-laravel-r70g.html'), 'utf8')
  const mappings = [
    
    ['Garis waktu (Laravel R72)', 'r72-title'],
    ['Sasaran Lampiran I/II (R70F)', 'r70g-documents']
  ]
  for (const [label, anchor] of mappings) {
    const target = ui.item(label)?.href
    assert.equal(target, './regulatory-laravel-r70g.html#' + anchor)
    assert.ok(html.includes('id="' + anchor + '"'), 'Missing target HTML anchor ' + anchor)
  }
  for (const link of ui.links()) {
    assert.ok(!/regulatory-(?:ledger-r70c|scope-r70d)\.html/.test(link.href || ''),
      'Menu still points to retired API page: ' + link.href)
  }
})

test('Every Laravel consolidation menu section has one active state', () => {
  const labels = [
    ['#r72-title', 'Garis waktu (Laravel R72)'],
    ['#r70g-documents', 'Sasaran Lampiran I/II (R70F)'],
    ['#r74-title', 'Genealogi Regulasi (R74)'],
    ['#r75-title', 'Meja Review Terpadu (R75)']
  ]
  for (const [hash, label] of labels) {
    const ui = mount('/regulatory-laravel-r70g.html', hash)
    const active = ui.links().filter(item => item.classList.contains('active'))
    assert.equal(active.length, 1, 'Expected exactly one active menu at ' + hash)
    assert.equal(active[0].textContent, label)
  }
})

test('New navigation refuses to represent unavailable legacy parity as complete', () => {
  const html = fs.readFileSync(path.resolve(__dirname, '../../public/regulatory-laravel-r70g.html'), 'utf8')
  assert.ok(html.includes('bukan kesetaraan seluruh fungsi legacy'))
  assert.ok(!html.includes('href="./regulatory-scope-r70d.html"'))
  assert.ok(!html.includes('href="./regulatory-ledger-r70c.html"'))
})


test('R78 full-source page is selected from the canonical sidebar', () => {
  const home = mount('/index.html', '')
  assert.equal(home.item('Naskah & PDF (Laravel R78)')?.href, './regulatory-sources-r70b.html')
  const snapshot = mount('/regulatory-sources-r70b.html', '')
  assert.equal(snapshot.item('Naskah & PDF (Laravel R78)')?.classList.contains('active'), true)
  assert.equal(snapshot.links().filter(x => x.classList.contains('active')).length, 1)
})


test('R79 Laravel native trace appears in Regulatory sidebar and has honest caution', () => {
  const home = mount('/index.html', '')
  assert.equal(home.item('Jejak norma (Laravel R79)')?.href, './regulatory-trace-r70a.html')
  const trace = mount('/regulatory-trace-r70a.html', '')
  assert.equal(trace.item('Jejak norma (Laravel R79)')?.classList.contains('active'), true)
  assert.equal(trace.links().filter(x => x.classList.contains('active')).length, 1)
  const html = fs.readFileSync(path.resolve(__dirname, '../../public/regulatory-trace-r70a.html'), 'utf8')
  assert.ok(html.includes('kesetaraan parsial'))
  assert.ok(html.includes('belum diverifikasi hash pasalnya'))
})
