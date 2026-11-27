#!/usr/bin/env node
// PI R95: dependency-free Chrome DevTools browser acceptance.
// Only GitHub Actions Linux runner, synthetic R93 user and localhost:19893.
// Real browser desktop 1440px + mobile 390px; clicks R88 crosswalk and
// R89 *preview only*, never provider-run/consent/reviewer POST.
// Requires Chrome preinstalled on hosted runner, zero npm packages.
import assert from 'node:assert/strict'
import {spawn, spawnSync} from 'node:child_process'
import {existsSync, mkdtempSync, readFileSync, rmSync} from 'node:fs'
import {join} from 'node:path'
import {setTimeout as delay} from 'node:timers/promises'

assert.equal(process.env.GITHUB_ACTIONS,'true','R95 only runs on CI')
assert.equal(process.env.APP_ENV,'testing','R95 requires testing environment')
assert.equal(process.env.PI_R93_CI_ORIGIN,'http://127.0.0.1:19893','no owner 8820 access')
assert.ok(process.env.RUNNER_TEMP,'R95 runner temporary home required')
let chrome=''
for(const executable of ['google-chrome','google-chrome-stable','chromium','chromium-browser']){
  const result=spawnSync('which',[executable],{encoding:'utf8'})
  if(result.status===0){chrome=result.stdout.trim();break}
}
assert.ok(chrome,'PI_R95_CHROME_NOT_INSTALLED_ON_RUNNER')
let profile,spawned,port
async function startChrome(){
 for(let attempt=1;attempt<=2;attempt++){
  profile=mkdtempSync(join(process.env.RUNNER_TEMP,'pi-r95-cdp-'))
  let stderr=''
  let spawnError=false
  spawned=spawn(chrome,[
  '--headless=new','--no-sandbox','--disable-gpu','--disable-dev-shm-usage',
  '--no-first-run','--disable-background-networking','--disable-extensions',
  '--remote-allow-origins=*','--remote-debugging-port=0',
  '--user-data-dir='+profile,'--window-size=1440,900','about:blank',
],{stdio:['ignore','ignore','pipe']})
  spawned.on('error',()=>{spawnError=true})
  spawned.stderr.on('data',chunk=>{stderr=(stderr+chunk.toString()).slice(-4000)})
  for(let i=0;i<150;i++){
    if(spawnError||spawned.exitCode!==null)break
    const file=join(profile,'DevToolsActivePort')
    if(existsSync(file)){
      const candidate=Number(readFileSync(file,'utf8').split(/\r?\n/)[0])
      if(candidate>0&&candidate<=65535){
        try{
          const response=await fetch('http://127.0.0.1:'+candidate+'/json/version',{signal:AbortSignal.timeout(1000)})
          if(response.ok){port=candidate;return}
        }catch{}
      }
    }
    await delay(200)
  }
  console.error('PI_R95_CHROME_START_ATTEMPT_FAILED='+attempt+' '+stderr)
  try{spawned.kill('SIGKILL')}catch{}
  await delay(500)
  try{rmSync(profile,{recursive:true,force:true})}catch{}
 }
 throw new Error('PI_R95_CHROME_DEBUG_PORT_TIMEOUT_AFTER_TWO_ATTEMPTS')
}
let ws
let send
try{
  await startChrome()
  let targets=await (await fetch('http://127.0.0.1:'+port+'/json')).json()
  let tab=targets.find(t=>t.type==='page')
  if(!tab){
    tab=await (await fetch('http://127.0.0.1:'+port+'/json/new?about:blank',{method:'PUT'})).json()
  }
  assert.ok(tab.webSocketDebuggerUrl,'PI_R95_DEVTOOLS_PAGE_TARGET_NOT_FOUND')
  ws=new WebSocket(tab.webSocketDebuggerUrl)
  await new Promise((resolve,reject)=>{
    ws.addEventListener('open',resolve,{once:true})
    ws.addEventListener('error',reject,{once:true})
  })
  let seq=0
  const pending=new Map()
  let externalRequests=0
  ws.addEventListener('message',event=>{
    const data=JSON.parse(event.data)
    if(data.method==='Network.requestWillBeSent'){
      const url=data.params?.request?.url||''
      if(/^https?:\/\//i.test(url)&&!url.startsWith('http://127.0.0.1:19893/')){
        externalRequests++
      }
    }
    if(typeof data.id!=='number')return
    const p=pending.get(data.id)
    if(!p)return
    pending.delete(data.id)
    if(data.error)p.reject(new Error(data.error.message||'CDP error'))
    else p.resolve(data.result||{})
  })
  send=(method,params={})=>{
    const id=++seq
    return Promise.race([
      new Promise((resolve,reject)=>{
        pending.set(id,{resolve,reject})
        ws.send(JSON.stringify({id,method,params}))
      }),
      delay(12000).then(()=>{throw new Error('PI_R95_CDP_TIMEOUT_'+method)})
    ])
  }
  async function evalPage(expression){
    const data=await send('Runtime.evaluate',{
      expression,awaitPromise:true,returnByValue:true,
    })
    if(data.exceptionDetails)throw new Error('PI_R95_PAGE_JAVASCRIPT_EXCEPTION')
    return data.result?.value
  }
  async function until(expression,label){
    for(let i=0;i<90;i++){
      try{
        if(await evalPage(expression))return
      }catch{ /* page navigation or DOM not yet ready */ }
      await delay(200)
    }
    throw new Error('PI_R95_BROWSER_WAIT_FAILED_'+label)
  }
  async function navigate(path){
    await send('Page.navigate',{url:'http://127.0.0.1:19893'+path})
    await until("document.readyState === 'complete'",'PAGE_LOADED')
  }
  await send('Page.enable')
  await send('Runtime.enable')
  await send('Network.enable')
  await send('Emulation.setDeviceMetricsOverride',{
    width:1440,height:900,deviceScaleFactor:1,mobile:false,
  })
  await navigate('/login.html')
  const logged=await evalPage("(async function(){"+
    "const s=await fetch('/api/auth/setup-status',{credentials:'same-origin'});"+
    "const d=await s.json();"+
    "const r=await fetch('/api/auth/login',{method:'POST',credentials:'same-origin',"+
    "headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':d.csrf},"+
    "body:JSON.stringify({username:'r93_ci_operator',password:'R93-CI-Only-Password-Not-A-User-Secret!'})});"+
    "return {status:r.status,setup:s.status,needs_setup:d.needs_setup};})()")
  assert.equal(logged.setup,200)
  assert.equal(logged.needs_setup,false)
  assert.equal(logged.status,200)
  await navigate('/development-case-r85.html')
  await until("document.querySelector('#r86-status')?.textContent?.includes('Registry R86 siap')","R86_CATALOGUE")
  await until("document.querySelector('#r85-questions')?.querySelectorAll('article').length > 0","R85_CASE")
  const loaded=await evalPage("({"+
    "caseCount:document.querySelector('#r85-questions')?.children.length,"+
    "r86Text:document.querySelector('#r86-status')?.textContent,"+
    "signedIn:document.body?.textContent?.includes('r93_ci_operator'),"+
    "cloudInitiallyDisabled:document.querySelector('#r89-run')?.disabled,"+
    "r88NeedsClick:document.querySelector('#r88-status')?.textContent?.includes('Pilih Periksa')" +
    "})")
  assert.ok(loaded.caseCount>0,'R85 evidence questions must render')
  assert.match(loaded.r86Text,/10\/10/)
  assert.equal(loaded.signedIn,true)
  assert.equal(loaded.cloudInitiallyDisabled,true)
  assert.equal(loaded.r88NeedsClick,true)
  console.log('PI_R95_CHROME_R85_R86_AUTHENTICATED_RENDER=PASS')
  // PER-A authenticated search; do not navigate to external links.
  await until("!!document.querySelector('button[aria-label=\"Cari di PI (Ctrl+K)\"]')",'PER_SEARCH_READY')
  await evalPage("document.querySelector('button[aria-label=\"Cari di PI (Ctrl+K)\"]').click()")
  await until("document.querySelector('dialog[aria-label=\"Pencarian PI\"]')?.open",'PER_SEARCH_OPEN')
  await evalPage("(()=>{const n=document.querySelector('input[aria-label=\"Kata kunci pencarian\"]');n.value='Perwal 74 2025';n.dispatchEvent(new Event('input',{bubbles:true}));})()")
  await until("Array.from(document.querySelectorAll('dialog[aria-label=\"Pencarian PI\"] a')).some(a=>a.textContent.includes('Perwal 74/2025')&&a.textContent.includes('tentang'))",'PER_REGULATORY_SEARCH_RESULT')
  assert.ok(await evalPage("Array.from(document.querySelectorAll('dialog[aria-label=\"Pencarian PI\"] a')).every(a=>a.origin===location.origin||a.hostname==='jdih.jogjakota.go.id')"))
  await evalPage("document.querySelector('dialog[aria-label=\"Pencarian PI\"]').close()")
  console.log('PI_PER01_CHROME_AUTHENTICATED_SEARCH=PASS')
  await until("document.querySelector('#per01-base-title')?.textContent?.includes('tentang')&&document.querySelector('#per01-change-title')?.textContent?.includes('tentang')",'PER_FISCAL_IDENTITY')
  assert.match(await evalPage("document.querySelector('#per01-base-title').textContent"),/Perwal 74\/2025/)
  assert.match(await evalPage("document.querySelector('#per01-change-title').textContent"),/Perwal 12\/2026/)
  console.log('PI_PER01_CHROME_FISCAL_IDENTITIES=PASS')

  // R108: actual browser navigation confirms progressive disclosure and
  // task labels while all legacy evidence DOM remains mounted for audit.
  const ux=await evalPage("({"+
    "tabs:document.querySelectorAll('.pi-r108-tab').length,"+
    "title:document.querySelector('#pi-r108-workflow-title')?.textContent,"+
    "current:document.querySelector('.pi-r108-tab[aria-current=page]')?.dataset.view,"+
    "annexHidden:document.querySelector('#r106-annex-review')?.hidden,"+
    "regulatoryHidden:document.querySelector('#r86-lifecycle')?.hidden,"+
    "menu:Array.from(document.querySelectorAll('.pi-app-group-name')).map(e=>e.textContent)"+
    "})")
  assert.equal(ux.tabs,6,'PI_R108_SIX_WORK_STAGES_MISSING')
  assert.match(ux.title,/ingin Anda periksa/i)
  assert.equal(ux.current,'ringkasan')
  assert.equal(ux.annexHidden,true)
  assert.equal(ux.regulatoryHidden,true)
  assert.ok(ux.menu.includes('Telaah perencanaan'))
  assert.ok(ux.menu.includes('Anggaran dan pendanaan'))
  await evalPage("location.hash='pi-view=anggaran'")
  await until("document.querySelector('#r106-annex-review')?.hidden === false",'R108_ANNEX_SELECTED')
  const fiscal=await evalPage("({"+
    "code:document.querySelector('#r106-run')?.textContent,"+
    "current:document.querySelector('.pi-r108-tab[aria-current=page]')?.dataset.view,"+
    "other:document.querySelector('#r86-lifecycle')?.hidden"+
    "})")
  assert.match(fiscal.code,/Periksa lampiran/)
  assert.equal(fiscal.current,'anggaran')
  assert.equal(fiscal.other,true)
  await evalPage("location.hash='pi-view=dokumen'")
  await until("document.querySelector('#r100-official-sources')?.hidden === false",'R108_DOCUMENT_SELECTED')
  assert.equal(await evalPage("document.querySelector('#r106-annex-review')?.hidden"),true)
  await evalPage("location.hash='pi-view=ringkasan'")
  await until("document.querySelector('#r85-questions')?.closest('section.panel')?.hidden === false",'R108_SUMMARY_SELECTED')
  console.log('PI_R108_CHROME_PLANNER_WORKFLOW_NAVIGATION=PASS')

  // Wave3: the 18 evidence relations remain, but start in 4 closed groups.
  await evalPage("location.hash='pi-view=dokumen'")
  await until("document.querySelector('#r85-evidence .r85-evidence-group')!==null",
    'R108_W3_EVIDENCE_READY')
  const grouped=await evalPage("({"+
    "groups:document.querySelectorAll('#r85-evidence .r85-evidence-group').length,"+
    "rows:document.querySelectorAll('#r85-evidence article.r85-evidence-item').length,"+
    "allClosed:Array.from(document.querySelectorAll('#r85-evidence .r85-evidence-group')).every(n=>!n.open),"+
    "catalogClosed:!document.querySelector('.pi-r108-catalog-details')?.open"+
    "})")
  assert.equal(grouped.groups,4,'R108_W3_GRAPH_GROUPS_MISSING')
  assert.equal(grouped.rows,18,'R108_W3_EVIDENCE_MISSING')
  assert.equal(grouped.allClosed,true,'R108_W3_GROUPS_SHOULD_BEGIN_CLOSED')
  assert.equal(grouped.catalogClosed,true,'R108_W3_CATALOG_SHOULD_BEGIN_CLOSED')
  await evalPage("document.querySelector('#r85-evidence .r85-evidence-group').open=true")
  const record=await evalPage("({"+
    "label:document.querySelector('#r85-evidence .r85-evidence-group article strong')?.textContent,"+
    "source:document.querySelector('#r85-evidence .r85-evidence-group article a')?.href,"+
    "rawClosed:!document.querySelector('#r85-evidence .r85-evidence-group article details')?.open"+
    "})")
  assert.match(record.label,/→/)
  assert.ok(record.source.startsWith('https://'))
  assert.equal(record.rawClosed,true)
  await evalPage("location.hash='pi-view=ringkasan'")
  console.log('PI_R108_W3_GROUPED_DOCUMENTS_READONLY=PASS')


  // R108 Wave2: live async case context, form reload, and semantic
  // status presentation, without changing any authority decisions.
  await until("document.querySelector('.pi-r108-context')?.textContent?.includes('Tahun: 2026')",'R108_W2_CONTEXT_ASYNC')
  const plannerStatus=await evalPage("({"+
    "context:document.querySelector('.pi-r108-context')?.textContent,"+
    "banner:document.querySelector('#r85-case-status')?.textContent,"+
    "guards:document.querySelector('#r85-guards')?.textContent,"+
    "badges:Array.from(document.querySelectorAll('#r85-questions .r85-status')).map(x=>x.textContent),"+
    "raw:document.querySelector('#r85-questions details code')?.textContent"+
    "})")
  assert.match(plannerStatus.context,/Wilayah: ID-DIY-YOG/)
  assert.match(plannerStatus.context,/Tahun: 2026/)
  assert.match(plannerStatus.context,/Topik: Persampahan/)
  assert.match(plannerStatus.banner,/Siap ditelaah|Perlu pemeriksaan/)
  assert.match(plannerStatus.guards,/Belum dinilai/)
  assert.match(plannerStatus.guards,/Tidak diizinkan/)
  assert.ok(plannerStatus.badges.every(x=>!x.includes('_')),'R108_UI_BADGE_ENUM_LEAK')
  assert.match(plannerStatus.raw,/Relasi:/)
  await evalPage("document.querySelector('#r85-year').dispatchEvent(new Event('change',{bubbles:true}))")
  await until("document.querySelector('.pi-r108-context')?.textContent?.includes('Pilihan diubah')",'R108_W2_CONTEXT_CHANGED')
  await evalPage("document.querySelector('#r85-filters').requestSubmit()")
  await until("document.querySelector('.pi-r108-context')?.textContent?.includes('Tahun: 2026')",'R108_W2_CONTEXT_RELOADED')
  console.log('PI_R108_W2_OWNER_CASE_CONTEXT_AND_PLAIN_STATUS=PASS')



  await until("document.querySelectorAll('#pi-performance-evidence .pi-performance-card').length===4",'W3C_PERFORMANCE_EVIDENCE')
  const performanceEvidence=await evalPage("document.getElementById('pi-performance-evidence').textContent")
  assert.match(performanceEvidence,/Indikator keluaran/)
  assert.match(performanceEvidence,/Target dan satuan: belum tersedia/)
  assert.match(performanceEvidence,/Realisasi: belum tersedia/)
  assert.match(performanceEvidence,/Keluaran tidak membuktikan outcome/)
  console.log('PI_R108_W3C_EVIDENCE_AND_MISSING_VALUES=PASS')
  await evalPage("document.querySelector('#r88-controls').requestSubmit()")
  await until("document.querySelector('#r88-status')?.textContent?.includes('Jejak tersedia')","R88_CROSSWALK")
  const crosswalk=await evalPage("({"+
    "status:document.querySelector('#r88-status')?.textContent,"+
    "count:document.querySelector('#r88-results')?.children.length,"+
    "previewEnabled:!document.querySelector('#r89-preview')?.disabled})")
  assert.ok(crosswalk.count>0,'R88 must render at least one crosswalk card')
  assert.equal(crosswalk.previewEnabled,true)
  console.log('PI_R95_CHROME_R88_REAL_DOM_INTERACTION=PASS')

  await evalPage("document.querySelector('#r89-preview').click()")
  await until("document.querySelector('#r89-status')?.textContent?.includes('Pratinjau siap')","R89_PREVIEW")
  const preview=await evalPage("({"+
    "visible:!document.querySelector('#r89-data')?.hidden,"+
    "length:document.querySelector('#r89-data')?.textContent?.length,"+
    "runDisabled:document.querySelector('#r89-run')?.disabled,"+
    "consentChecked:document.querySelector('#r89-consent')?.checked})")
  assert.equal(preview.visible,true)
  assert.ok(preview.length>20)
  assert.equal(preview.runDisabled,true)
  assert.equal(preview.consentChecked,false)
  console.log('PI_R95_CHROME_R89_PREVIEW_WITHOUT_PAID_PROVIDER=PASS')

  await until("document.querySelector('#r100-count-metadata')?.textContent === '18'",'R100_INDEX_READY')
  const sourceState=await evalPage("({"+
    "cards:document.querySelector('#r100-list')?.children.length,"+
    "bytes:document.querySelector('#r100-count-hash')?.textContent,"+
    "amounts:document.querySelector('#r100-count-amount')?.textContent,"+
    "detail:document.querySelector('#r100-state')?.textContent,"+
    "firstLink:document.querySelector('#r100-list a')?.href})")
  assert.equal(sourceState.cards,6)
  assert.equal(sourceState.bytes,'0')
  assert.equal(sourceState.amounts,'0')
  assert.match(sourceState.detail,/18 rekaman metadata JDIH/)
  assert.ok(sourceState.firstLink.startsWith('https://jdih.jogjakota.go.id/'))
  console.log('PI_R100_CHROME_REAL_OFFICIAL_METADATA_AND_EVIDENCE_GATES=PASS')
  const filtering=await evalPage("(()=>{"+
    "const selector=document.querySelector('#r100-lane');"+
    "selector.value='SECTOR';selector.dispatchEvent(new Event('change',{bubbles:true}));"+
    "const sector=document.querySelector('#r100-list').children.length;"+
    "selector.value='FISCAL';selector.dispatchEvent(new Event('change',{bubbles:true}));"+
    "const fiscalFirst=document.querySelector('#r100-list').children.length;"+
    "document.querySelector('#r100-more').click();"+
    "const fiscalAll=document.querySelector('#r100-list').children.length;"+
    "selector.value='ALL';selector.dispatchEvent(new Event('change',{bubbles:true}));"+
    "return {sector,fiscalFirst,fiscalAll,reset:document.querySelector('#r100-list').children.length};"+
    "})()")
  assert.deepEqual(filtering,{sector:1,fiscalFirst:6,fiscalAll:7,reset:6})
  console.log('PI_R100_CHROME_CATEGORY_FILTER_AND_EXPAND=PASS')


  async function viewport(width,height,mobile){
    await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile})
    await delay(500)
    return evalPage("({"+
      "viewport:window.innerWidth,"+
      "visual:window.visualViewport?.width,"+
      "screenWidth:window.screen.width,"+
      "docWidth:document.documentElement.scrollWidth,"+
      "bodyWidth:document.body.scrollWidth,"+
      "overflow:Array.from(document.body.querySelectorAll('*')).map(e=>({e,r:e.getBoundingClientRect()})).filter(x=>x.r.right>390.5&&x.r.width>3).slice(0,16).map(x=>({tag:x.e.tagName,id:x.e.id,cls:String(x.e.className).slice(0,48),right:Math.round(x.r.right),width:Math.round(x.r.width)})),"+
      "r85Visible:document.querySelector('#r85-filters')?.getBoundingClientRect().width>0,"+
      "r86Visible:document.querySelector('#r86-lifecycle')?.getBoundingClientRect().width>0,"+
      "r88Visible:document.querySelector('#r88-crosswalk')?.getBoundingClientRect().width>0,"+
      "r89Visible:document.querySelector('#r89-panel')?.getBoundingClientRect().width>0,"+
      "r100Visible:document.querySelector('#r100-official-sources')?.getBoundingClientRect().width>0})")
  }
  const desktop=await viewport(1440,900,false)
  assert.ok(desktop.viewport>=1300&&desktop.viewport<=1440)
  assert.ok(desktop.docWidth<=desktop.viewport+3,'PI_R95_DESKTOP_HORIZONTAL_OVERFLOW_'+JSON.stringify(desktop))
  assert.ok(desktop.r85Visible&&!desktop.r86Visible&&!desktop.r88Visible&&!desktop.r89Visible,
    'PI_R108_DEFAULT_DETAIL_MUST_BE_COLLAPSED_'+JSON.stringify(desktop))
  console.log('PI_R95_CHROME_DESKTOP_1440PX_NO_OVERFLOW=PASS')
  // Chrome needs a reload to apply the document viewport meta to a
  // desktop->mobile emulation switch (without it innerWidth may remain 980).
  await send('Page.reload',{ignoreCache:true})
  await until("document.readyState === 'complete'",'MOBILE_PAGE_RELOAD')
  await until("document.querySelector('#r86-status')?.textContent?.includes('Registry R86 siap')",'MOBILE_R86_RELOADED')
  const phone=await viewport(390,844,true)
  console.log('PI_R95_MOBILE_METRICS='+JSON.stringify(phone))
  assert.ok(phone.viewport>=385&&phone.viewport<=400,'PI_R95_MOBILE_VIEWPORT_NOT_390_'+JSON.stringify(phone))
  assert.ok(phone.docWidth<=phone.viewport+3,'PI_R95_MOBILE_390PX_HORIZONTAL_OVERFLOW_'+JSON.stringify(phone))
  assert.ok(phone.r85Visible&&!phone.r86Visible&&!phone.r88Visible&&!phone.r89Visible&&!phone.r100Visible,
    'PI_R108_MOBILE_WORKFLOW_DETAILS_MUST_BE_COLLAPSED_'+JSON.stringify(phone))
  console.log('PI_R95_CHROME_MOBILE_390PX_NO_OVERFLOW=PASS')

  // Re-select 2027 and observe ABSTAIN; do not consent or call paid provider.
  await evalPage("(()=>{const y=document.querySelector('#r88-controls select[name=year]');"+
    "y.value='2027';y.dispatchEvent(new Event('change',{bubbles:true}));"+
    "document.querySelector('#r88-controls').requestSubmit()})()")
  await until("document.querySelector('#r88-status')?.textContent?.includes('ABSTAIN')","R88_2027_ABSTAIN")
  const abstain=await evalPage("({"+
    "runDisabled:document.querySelector('#r89-run')?.disabled,"+
    "previewDisabled:document.querySelector('#r89-preview')?.disabled,"+
    "consentChecked:document.querySelector('#r89-consent')?.checked})")
  assert.equal(abstain.runDisabled,true)
  assert.equal(abstain.previewDisabled,true)
  assert.equal(abstain.consentChecked,false)
  assert.equal(externalRequests,0,'no external HTTP / provider egress during browser QA')
  console.log('PI_R95_CHROME_2027_ABSTAIN_AND_CLOUD_OFF=PASS')
  // Run the EXACT browser acceptance file that the owner can paste into
  // their existing Edge tab. Same code, real authenticated Chrome DOM.
  const r97=readFileSync(new URL('./pi-r97-owner-browser-readonly.js',import.meta.url),'utf8')
  const r97result=await evalPage(r97)
  assert.equal(r97result?.status,'PASS','PI_R97_OWNER_SCRIPT_CI_HOLD_'+JSON.stringify(r97result))
  for (const expected of [
    'PI_R97_ORIGINAL_AUTH_TENANT_ROLE=PASS',
    'PI_R97_R88_CROSSWALK_BROWSER_CLICK=PASS',
    'PI_R97_R89_PREVIEW_NO_PROVIDER_CALL=PASS',
    'PI_R97_R88_2027_ABSTAIN_CLOUD_BLOCK=PASS',
    'PI_R97_EDGE_MOBILE_390_NO_OVERFLOW=PASS',
    'PI_R97_BROWSER_READ_ONLY_ACCEPTANCE=PASS',
  ]) {
    assert.ok(r97result.checks.includes(expected),'PI_R97_CI_BROWSER_MISSING_'+expected)
  }
  assert.equal(externalRequests,0,'PI_R97_BROWSER_NETWORK_EGRESS_DETECTED')
  console.log('PI_R97_OWNER_EDGE_SNIPPET_REAL_CHROME_CI=PASS')

  // R108 W3B: authenticated DOM, native disclosures and old direct links.
  await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false})
  await navigate('/regulatory-laravel-r70g.html')
  await until("document.querySelectorAll('details[data-pi-regulatory-task]').length===4",'W3B_GROUPS')
  assert.equal(await evalPage("document.querySelectorAll('details[data-pi-regulatory-task][open]').length"),0)
  await evalPage("Array.from(document.querySelectorAll('nav a')).find(a=>a.hash==='#r70l-title').click()")
  await until("document.getElementById('r70l-title').closest('details').open",'W3B_AUTHORITY')
  await evalPage("location.hash='#r75-title'")
  await until("document.getElementById('r75-title').closest('details').open",'W3B_REVIEW')
  assert.equal(await evalPage("document.querySelectorAll('details[data-pi-regulatory-task][open]').length"),1)
  await navigate('/regulatory-laravel-r70g.html#r74-title')
  await until("document.getElementById('r74-title').closest('details').open",'W3B_DIRECT_GENEALOGY')
  assert.equal(await evalPage("!!document.getElementById('r74-form')&&!!document.getElementById('r70o-form')"),true)
  await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true})
  await navigate('/regulatory-laravel-r70g.html#r72-title')
  await until("document.getElementById('r72-title').closest('details').open",'W3B_MOBILE_HISTORY')
  assert.ok(await evalPage("document.documentElement.scrollWidth<=innerWidth+3"),'W3B mobile overflow')
  assert.equal(externalRequests,0,'W3B external request')
  console.log('PI_R108_W3B_CHROME_TASK_GROUPS_AND_DEEP_LINKS=PASS')
  console.log('PI_R95_CHROME_RESPONSIVE_AUTHENTICATED_ACCEPTANCE=PASS')
} finally {
  try{ws?.close()}catch{}
  try{spawned?.kill('SIGTERM')}catch{}
  try{rmSync(profile,{recursive:true,force:true})}catch{}
}
