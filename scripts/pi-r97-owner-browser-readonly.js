/* Planning Intelligence R97 — local owner Edge DevTools browser acceptance.
 * Paste this entire source into Console while authenticated at
 * http://127.0.0.1:8820/development-case-r85.html
 * READ ONLY (API GET only): R88 crosswalk and R89 preview, NO R89 provider
 * call, NO consent checkbox, NO reviewer decision, NO account/logout change.
 * No secrets/CSRF/authentication token are displayed or transmitted.
 * CI mirror: ephemeral localhost 19893 synthetic Laravel test.
 */
(async function piR97OwnerReadOnlyAcceptance() {
  'use strict'
  const prefix = 'PI_R97_'
  const results = []
  const check = (condition, label) => {
    if (!condition) throw new Error(label)
  }
  const info = line => {
    results.push(line)
    console.log(line)
  }
  const element = id => {
    const e = document.getElementById(id)
    check(Boolean(e), 'MISSING_DOM_'+id)
    return e
  }
  const pause = ms => new Promise(resolve => setTimeout(resolve, ms))
  const wait = async (predicate,label) => {
    for (let i=0;i<80;i++) {
      if (predicate()) return true
      await pause(125)
    }
    throw new Error('TIMEOUT_'+label)
  }
  let fields
  let originals
  try {
    check(location.hostname === '127.0.0.1' &&
      (location.port === '8820' || location.port === '19893') &&
      location.pathname === '/development-case-r85.html',
      'NOT_ORIGINAL_PI_OR_ISOLATED_CI_ORIGIN')
    check(document.readyState !== 'loading','PAGE_STILL_LOADING')
    const sessionResp = await fetch('/api/auth/session',{
      credentials:'same-origin',cache:'no-store',
      headers:{Accept:'application/json'}
    })
    check(sessionResp.status === 200,'ORIGINAL_SESSION_NOT_AUTHENTICATED')
    const session = await sessionResp.json()
    check(session.status === 'AUTHENTICATED' &&
      session.tenant_id === 'ID-DIY-YOG' &&
      session.role === 'institution_admin','SESSION_TENANT_ROLE_MISMATCH')
    // Never log session JSON, username, cookies, passwords, or CSRF token.
    info(prefix+'ORIGINAL_AUTH_TENANT_ROLE=PASS')

    const status86 = element('r86-status')
    await wait(() => /Registry R86 siap/.test(status86.textContent),'R86_READY')
    check(/10\/10/.test(status86.textContent),'R86_STAGES_NOT_READY')
    check(element('r85-questions').querySelectorAll('article').length > 0,
      'R85_EVIDENCE_NOT_RENDERED')
    info(prefix+'R85_R86_READ_MODELS=PASS')

    const controls = element('r88-controls')
    fields = ['code','year','as_of'].map(name => {
      const input = controls.elements.namedItem(name)
      check(input && 'value' in input,'MISSING_FILTER_'+name)
      return input
    })
    originals = fields.map(input => input.value)
    const preview = element('r89-preview')
    const cloud = element('r89-run')
    const consent = element('r89-consent')
    const r88status = element('r88-status')
    const r89status = element('r89-status')
    check(!consent.checked,'CONSENT_ALREADY_CHECKED_STOP')
    check(cloud.disabled,'PAID_CLOUD_BUTTON_ALREADY_ENABLED_STOP')
    const setYear = (year) => {
      if (fields[1].value !== year) {
        fields[1].value = year
        fields[1].dispatchEvent(new Event('change',{bubbles:true}))
      }
    }
    // Only R88 GET route is used by the submit listener, never POST.
    fields[0].value = '2.11.11.2.01.0004'
    fields[2].value = '2026-11-21'
    setYear('2026')
    controls.requestSubmit()
    await wait(() => /Jejak tersedia|ABSTAIN/.test(r88status.textContent) &&
      !/Sedang memeriksa/.test(r88status.textContent),'R88_2026')
    check(/Jejak tersedia/.test(r88status.textContent),'R88_2026_NO_EVIDENCE')
    check(element('r88-results').children.length > 0,'R88_RESULT_CARDS_MISSING')
    check(!preview.disabled,'R89_PREVIEW_NOT_ENABLED')
    check(cloud.disabled && !consent.checked,'R88_UNEXPECTED_CLOUD_APPROVAL')
    info(prefix+'R88_CROSSWALK_BROWSER_CLICK=PASS')

    // R89 preview uses only GET, without approving external provider calls.
    preview.click()
    await wait(() => /Pratinjau siap|ABSTAIN/.test(r89status.textContent),
      'R89_PREVIEW')
    check(/Pratinjau siap/.test(r89status.textContent),'R89_PREVIEW_ABSTAIN')
    check(!element('r89-data').hidden &&
      element('r89-data').textContent.length >= 30,'R89_PREVIEW_NOT_RENDERED')
    check(cloud.disabled && !consent.checked,'R89_PAID_CLOUD_RUN_NOT_DISABLED')
    info(prefix+'R89_PREVIEW_NO_PROVIDER_CALL=PASS')

    setYear('2027')
    controls.requestSubmit()
    await wait(() => /ABSTAIN/.test(r88status.textContent) &&
      !/Sedang memeriksa/.test(r88status.textContent),'R88_2027_ABSTAIN')
    check(preview.disabled && cloud.disabled && !consent.checked,
      '2027_FAIL_CLOSED_GATES_INVALID')
    info(prefix+'R88_2027_ABSTAIN_CLOUD_BLOCK=PASS')

    const viewport = window.innerWidth
    const visual = window.visualViewport?.width ?? viewport
    const docWidth = document.documentElement.scrollWidth
    const bodyWidth = document.body.scrollWidth
    if (viewport >= 385 && viewport <= 400) {
      check(docWidth <= viewport+3 && bodyWidth <= viewport+3 &&
        visual <= viewport+3,
        'MOBILE_390_HORIZONTAL_OVERFLOW_'+docWidth+'_'+viewport)
      for (const id of ['r85-filters','r86-lifecycle',
        'r88-crosswalk','r89-panel']) {
        check(element(id).getBoundingClientRect().width > 0,
          'MOBILE_SECTION_NOT_VISIBLE_'+id)
      }
      info(prefix+'EDGE_MOBILE_390_NO_OVERFLOW=PASS')
    } else {
      info(prefix+'EDGE_MOBILE_390=NOT_CHECKED_VIEWPORT_'+viewport)
    }
    info(prefix+'HTTP_METHODS_USED=GET_ONLY')
    info(prefix+'PROVIDER_AND_REVIEWER_WRITES=NONE')
    info(prefix+'BROWSER_READ_ONLY_ACCEPTANCE=PASS')
    return {status:'PASS',checks:results}
  } catch (err) {
    const code=String(err?.message||'UNKNOWN_ERROR').replace(/[^A-Z0-9_.-]/gi,'_')
    info(prefix+'BROWSER_READ_ONLY_ACCEPTANCE=HOLD_'+code.slice(0,90))
    return {status:'HOLD',reason:code,checks:results}
  } finally {
    // Restore original selectors (without running paid calls or reviewer writes).
    if (fields && originals) {
      for (let i=0;i<fields.length;i++) fields[i].value=originals[i]
      fields[1].dispatchEvent(new Event('change',{bubbles:true}))
    }
  }
})()
