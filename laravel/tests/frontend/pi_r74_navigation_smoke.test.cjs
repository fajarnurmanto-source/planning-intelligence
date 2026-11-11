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