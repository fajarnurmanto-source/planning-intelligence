#!/usr/bin/env node
/* PI R93 live HTTP acceptance on a disposable GitHub Actions Laravel server.
 * This is never an owner Windows script and never uses real credentials.
 * Exercises REAL CSRF, encrypted cookie roundtrip, login/logout, tenant paths,
 * R85/R86/R88/R89 preview, invalid consent and reviewer no-write boundaries.
 */
import assert from 'node:assert/strict'

assert.equal(process.env.GITHUB_ACTIONS, 'true', 'CI-only HTTP smoke')
assert.equal(process.env.APP_ENV, 'testing', 'CI-only app')
const origin = process.env.PI_R93_CI_ORIGIN
assert.equal(origin, 'http://127.0.0.1:19893', 'never query public or owner server')
const bag = new Map()

function cookies() {
  return [...bag].map(([name, value]) => name + '=' + value).join('; ')
}

async function request(path, {method='GET', body, csrf, legacyCsrf=false}={}) {
  assert.match(path, /^\//)
  const headers = {'Accept':'application/json'}
  if (bag.size) headers.Cookie = cookies()
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (csrf) headers[legacyCsrf ? 'X-PI-CSRF' : 'X-CSRF-TOKEN'] = csrf
  const response = await fetch(origin+path,{
    method, headers, redirect:'manual', signal:AbortSignal.timeout(10000),
    ...(body !== undefined ? {body:JSON.stringify(body)} : {}),
  })
  const setCookies = response.headers.getSetCookie()
  for (const c of setCookies) {
    const part=c.split(';')[0]
    const eq=part.indexOf('=')
    if(eq<=0)continue
    const name=part.slice(0,eq)
    const value=part.slice(eq+1)
    if(value)bag.set(name,value)
    else bag.delete(name)
  }
  const raw=await response.text()
  let data={}
  if(raw) {
    try { data=JSON.parse(raw) }
    catch { throw new Error('PI_R93_UNEXPECTED_NONJSON_HTTP='+response.status+' '+path) }
  }
  return {status:response.status,data}
}
function status(actual, expected, label) {
  assert.equal(actual.status,expected, label+': HTTP '+actual.status+' instead of '+expected+
    (actual.data.code ? ' ('+actual.data.code+')':''))
}
const routes=[
  '/api/auth/session',
  '/api/qa-superior-r85/development-case',
  '/api/qa-superior-r86/regulatory-lifecycle',
  '/api/qa-superior-r88/planning-crosswalk',
  '/api/qa-superior-r89/semantic-preview',
  '/api/qa-regulatory-r84/evidence-reviews',
]
const setup=await request('/api/auth/setup-status')
status(setup,200,'setup status')
assert.equal(setup.data.needs_setup,false)
assert.ok(typeof setup.data.csrf==='string' && setup.data.csrf.length>24)
assert.ok(bag.has('laravel_session'),'Laravel cookie is present')
for(const path of routes){
  const res=await request(path)
  status(res,401,'anonymous protected route '+path)
}
console.log('PI_R93_LIVE_HTTP_ANONYMOUS_API=401_PASS')

const credential={
  username:'r93_ci_operator',
  password:'R93-CI-Only-Password-Not-A-User-Secret!',
}
const wrongHeader=await request('/api/auth/login',{
  method:'POST',body:credential,csrf:setup.data.csrf,legacyCsrf:true,
})
status(wrongHeader,419,'legacy custom CSRF header must fail')
console.log('PI_R93_LIVE_HTTP_NONSTANDARD_CSRF=419_REJECTED')

const login=await request('/api/auth/login',{
  method:'POST',body:credential,csrf:setup.data.csrf,
})
status(login,200,'real CSRF login')
assert.equal(login.data.status,'AUTHENTICATED')
const session=await request('/api/auth/session')
status(session,200,'encrypted cookie session')
assert.equal(session.data.status,'AUTHENTICATED')
assert.equal(session.data.username,'r93_ci_operator')
assert.equal(session.data.role,'institution_admin')
assert.equal(session.data.tenant_id,'ID-DIY-YOG')
assert.ok(typeof session.data.csrf==='string'&&session.data.csrf.length>24)
console.log('PI_R93_LIVE_HTTP_CSRF_COOKIE_AUTH=PASS')

const r85=await request('/api/qa-superior-r85/development-case')
status(r85,200,'R85 case')
assert.equal(r85.data.schema,'PI_R85_GOVERNMENT_DEVELOPMENT_CASE_READ_MODEL_V1')
assert.equal(r85.data.interpretation.production_admission,'DENIED')
const r86=await request('/api/qa-superior-r86/regulatory-lifecycle')
status(r86,200,'R86 lifecycle')
assert.equal(r86.data.schema,'PI_R86_REGULATORY_DEVELOPMENT_LIFECYCLE_V1')
assert.equal(r86.data.case.lifecycle.stage_count,10)
const r88=await request('/api/qa-superior-r88/planning-crosswalk')
status(r88,200,'R88 crosswalk')
assert.equal(r88.data.schema,'PI_R88_REGULATORY_PLANNING_CROSSWALK_V1')
assert.equal(r88.data.interpretation.numeric_comparison,'BLOCKED')
assert.equal(r88.data.semantic_reasoning.provider_execution,'NOT_RUN')
const q=new URLSearchParams({year:'2026',as_of:'2026-11-21',code:'2.11.11.2.01.0004'})
const r89=await request('/api/qa-superior-r89/semantic-preview?'+q)
status(r89,200,'R89 metadata preview')
assert.equal(r89.data.schema,'PI_R89_EVIDENCE_BOUND_SEMANTIC_ANALYSIS_V1')
assert.equal(r89.data.semantic_reasoning.provider_execution,'NOT_RUN')
assert.equal(r89.data.semantic_reasoning.gates.production_admission,'DENIED')
console.log('PI_R93_LIVE_HTTP_R85_R86_R88_R89_GET=PASS')

const forbiddenCloud=await request('/api/qa-superior-r89/semantic-candidates',{
  method:'POST', csrf:session.data.csrf,
  body:{
    year:2026, as_of:'2026-11-21',code:'2.11.11.2.01.0004',
    provider:'deepseek',
    approved_context_sha256:'A'.repeat(64),
    consent:false,approved_cloud_metadata:false,approved_provider_charges:false,
  },
})
status(forbiddenCloud,422,'R89 without approval must reject')
for (const name of ['consent','approved_cloud_metadata','approved_provider_charges']){
  assert.ok(Object.hasOwn(forbiddenCloud.data.errors||{},name),'missing rejected field '+name)
}
console.log('PI_R93_LIVE_HTTP_R89_UNCONSENTED_POST=422_PASS')

const review=await request('/api/qa-regulatory-r84/evidence-reviews')
status(review,200,'R84 reviewer read-only')
assert.equal(review.data.schema,'PI_R84_CONTROLLED_RELATION_REVIEW_V1')
assert.equal(review.data.history.length,0)
assert.equal(review.data.relation_promotion,'NOT_PERFORMED')
const pdf=await request('/api/qa-regulatory-r70b/pdf?source_id=../')
status(pdf,400,'reject unsafe PDF source id')
console.log('PI_R93_LIVE_HTTP_REVIEW_READ_AND_PDF_DENY=PASS')

const logout=await request('/api/auth/logout',{
  method:'POST',csrf:session.data.csrf,
})
status(logout,200,'CSRF-protected actual logout')
assert.equal(logout.data.status,'SIGNED_OUT')
status(await request('/api/auth/session'),401,'session must be gone after logout')
status(await request('/api/qa-superior-r88/planning-crosswalk'),401,'crosswalk must revoke on logout')
console.log('PI_R93_LIVE_HTTP_LOGOUT_REVOKED=PASS')

const setupViewer=await request('/api/auth/setup-status')
status(setupViewer,200,'viewer csrf refresh')
const viewer=await request('/api/auth/login',{
  method:'POST', csrf:setupViewer.data.csrf,
  body:{username:'r93_ci_viewer',password:'R93-CI-Viewer-Password-Not-A-Secret!'},
})
status(viewer,200,'synthetic viewer login')
const viewSession=await request('/api/auth/session')
status(viewSession,200,'viewer authenticated')
assert.equal(viewSession.data.role,'viewer')
const forbiddenReviewer=await request('/api/qa-regulatory-r84/evidence-reviews',{
  method:'POST',csrf:viewSession.data.csrf,body:{
    candidate_id:'UU-18-2008:PASAL-16',
    review_dimension:'CITATION_ACCURACY', decision:'ACCEPTED',
    reason:'R93 synthetic forbidden reviewer request.',
    source_manifest_sha256:'A'.repeat(64),
    candidate_sha256:'B'.repeat(64),
  },
})
status(forbiddenReviewer,403,'viewer may not approve regulatory evidence')
status(await request('/api/auth/logout',{method:'POST',csrf:viewSession.data.csrf}),200,'viewer logout')
console.log('PI_R93_LIVE_HTTP_VIEWER_REVIEWER_POST=403_PASS')
console.log('PI_R93_LIVE_HTTP_ISOLATED_ACCEPTANCE=PASS')
