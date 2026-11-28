'use strict'
const test = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')

const publicPath = path.resolve(__dirname, '../../public')
const shell = fs.readFileSync(path.join(publicPath, 'pi-auth-shell.js'), 'utf8')
const crosswalk = fs.readFileSync(path.join(publicPath, 'regulatory-planning-r88.js'), 'utf8')

test('R92 logout sends Laravel CSRF header and session cookies without exposing token', async () => {
  let domReady = null
  const calls = []
  const navigation = []
  const header = {
    children: [],
    appendChild(child) {
      this.children.push(child)
      child.parentNode = this
    },
  }
  const document = {
    body: {append() {}},
    addEventListener(name, fn) {
      if (name === 'DOMContentLoaded') domReady = fn
    },
    createElement(tag) {
      return {
        tag,
        children: [],
        style: {},
        setAttribute() {},
        addEventListener() {},
        append(...children) { this.children.push(...children) },
      }
    },
    querySelector() { return header },
  }
  const fetch = async (url, options = {}) => {
    calls.push({url, options})
    if (url === '/api/auth/session') {
      return {ok:true, json:async()=>({
        username:'ci-user', tenant_id:'ID-DIY-YOG', role:'institution_admin', csrf:'ci-only-csrf-token',
      })}
    }
    if (url === '/api/auth/logout') {
      return {ok:true}
    }
    throw new Error('Unexpected fetch: '+url)
  }
  vm.runInNewContext(shell, {
    window:{}, document, fetch, location:{replace(url){navigation.push(url)}},
    MutationObserver:class {},
  }, {filename:'pi-auth-shell.js'})
  assert.equal(typeof domReady,'function')
  domReady()
  await new Promise(setImmediate)
  assert.equal(header.children.length,1)
  const menu=header.children[0]
  const button=menu.children.find(child => child.tag === 'button' && child.textContent === 'Keluar')
  assert.ok(button)
  await button.onclick()
  const logout=calls.find(call=>call.url==='/api/auth/logout')
  assert.ok(logout)
  assert.equal(logout.options.method,'POST')
  assert.equal(logout.options.credentials,'same-origin')
  assert.equal(logout.options.headers['X-CSRF-TOKEN'],'ci-only-csrf-token')
  assert.equal(Object.hasOwn(logout.options.headers,'X-PI-CSRF'),false)
  assert.deepEqual(navigation,['./login.html'])
})

test('R92 semantic POST requires deliberate approval and the Laravel CSRF header', () => {
  assert.doesNotThrow(()=>new vm.Script(crosswalk,{filename:'regulatory-planning-r88.js'}))
  assert.equal(crosswalk.includes("'X-PI-CSRF'"),false)
  assert.match(crosswalk, /['"]X-CSRF-TOKEN['"]\s*:\s*session\.csrf/)
  assert.match(crosswalk, /if\s*\(!approved\.checked\s*\|\|\s*!semanticPreview/)
  assert.match(crosswalk, /approved_context_sha256\s*:\s*semanticPreview\.preview\.sha256/)
  assert.match(crosswalk, /approved_provider_charges\s*:\s*true/)
  assert.match(crosswalk, /credentials\s*:\s*'same-origin'/)
  assert.match(crosswalk, /fetch\('\/api\/qa-superior-r89\/semantic-candidates'/)
})
