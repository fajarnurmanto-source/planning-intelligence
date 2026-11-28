'use strict'
const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const script = fs.readFileSync(path.join(__dirname, '../../public/login.js'), 'utf8')

function browserHarness({loginOk = true, sessionOk = true, setupOk = true} = {}) {
  const requests = []
  const navigation = []
  const on = {}
  const ids = ['login-form', 'toggle-password', 'heading', 'description',
    'password-hint', 'username', 'password', 'confirm-label',
    'confirm-password', 'submit', 'feedback']
  const elements = Object.fromEntries(ids.map(id => [id, {
    value: '', type: id === 'password' ? 'password' : 'text',
    textContent: '', hidden: false, required: false, disabled: true,
    autocomplete: '', callbacks: {}, attributes: {},
    addEventListener(name, fn) { this.callbacks[name] = fn },
    setAttribute(name, value) { this.attributes[name] = value },
    focus() { this.focused = true },
  }]))
  const doc = {
    getElementById(id) {
      assert.ok(elements[id], 'Missing expected DOM element ' + id)
      return elements[id]
    },
    addEventListener(name, handler) { on[name] = handler },
  }
  async function fetch(url, options) {
    requests.push({url, options})
    if (url === '/api/auth/setup-status') {
      return {ok: setupOk, json: async () => ({needs_setup: false, csrf: 'ci-only-csrf'})}
    }
    if (url === '/api/auth/login') {
      return {ok: loginOk, json: async () => loginOk
        ? {status: 'AUTHENTICATED'}
        : {code: 'INVALID_CREDENTIALS'}}
    }
    if (url === '/api/auth/session') {
      return {ok: sessionOk, json: async () => ({
        status: sessionOk ? 'AUTHENTICATED' : 'REJECTED',
        username: 'fajar.nurmanto',
      })}
    }
    throw new Error('Unexpected request ' + url)
  }
  vm.runInNewContext(script, {document: doc, fetch, location: {
    replace: url => navigation.push(url),
  }}, {filename: 'login.js'})
  assert.equal(typeof on.DOMContentLoaded, 'function')
  on.DOMContentLoaded()
  return {elements, requests, navigation, wait: () => new Promise(setImmediate),
    async submit() {
      await elements['login-form'].callbacks.submit({preventDefault() {}})
    }}
}

test('R74 existing login sends exact typed password and verifies authenticated cookie before redirect', async () => {
  const app = browserHarness()
  await app.wait()
  assert.equal(app.elements.submit.disabled, false)
  assert.equal(app.elements.password.autocomplete, 'current-password')
  app.elements.username.value = 'fajar.nurmanto'
  const typed = 'CI-only_Üñìcødë-Pássw0rd!'
  app.elements.password.value = typed
  await app.submit()
  assert.equal(app.navigation[0], './index.html')
  assert.deepEqual(app.requests.map(r => r.url), [
    '/api/auth/setup-status', '/api/auth/login', '/api/auth/session',
  ])
  const sent = JSON.parse(app.requests[1].options.body)
  assert.equal(sent.username, 'fajar.nurmanto')
  assert.equal(sent.password, typed)
  assert.equal(app.requests[1].options.credentials, 'same-origin')
  assert.equal(app.requests[2].options.credentials, 'same-origin')
  assert.equal(app.requests[1].options.headers['X-CSRF-TOKEN'], 'ci-only-csrf')
})

test('R74 incorrect login clears stale browser password and does not navigate', async () => {
  const app = browserHarness({loginOk: false})
  await app.wait()
  app.elements.username.value = 'fajar.nurmanto'
  app.elements.password.value = 'ci-incorrect-secret'
  await app.submit()
  assert.equal(app.elements.password.value, '')
  assert.equal(app.elements.password.focused, true)
  assert.equal(app.navigation.length, 0)
  assert.match(app.elements.feedback.textContent, /Ketik ulang/)
  assert.equal(app.requests.filter(r => r.url === '/api/auth/login').length, 1)
})

test('R74 session verification failure stops navigation after successful POST', async () => {
  const app = browserHarness({sessionOk: false})
  await app.wait()
  app.elements.username.value = 'fajar.nurmanto'
  app.elements.password.value = 'ci-password-not-real'
  await app.submit()
  assert.equal(app.navigation.length, 0)
  assert.match(app.elements.feedback.textContent, /sesi browser belum aktif/)
})

test('R74 readiness and local reveal control have no credential logging or network side effects', async () => {
  const app = browserHarness({setupOk: false})
  await app.wait()
  assert.equal(app.elements.submit.disabled, true)
  assert.match(app.elements.feedback.textContent, /Status autentikasi tidak tersedia/)
  const priorCount = app.requests.length
  app.elements['toggle-password'].callbacks.click()
  assert.equal(app.elements.password.type, 'text')
  app.elements['toggle-password'].callbacks.click()
  assert.equal(app.elements.password.type, 'password')
  assert.equal(app.requests.length, priorCount)
})
