#!/usr/bin/env node
// PI R87 browser benchmark. No credentials, cookies, source snapshots, or secrets are written.
// Requires: npm install --no-save playwright-core; Microsoft Edge installed on Windows.
import { performance } from 'node:perf_hooks';
import fs from 'node:fs';
import path from 'node:path';

const argv = process.argv.slice(2);
const opt = (flag, fallback = null) => {
  const i = argv.indexOf(flag);
  return i < 0 ? fallback : argv[i + 1];
};
const before = opt('--before');
const after = opt('--after');
const output = opt('--out', path.resolve('r87-benchmark-results'));
const iterations = Number(opt('--iterations', '10'));
const username = opt('--username', 'fajar.nurmanto');
const headless = !argv.includes('--headed');
const readySelector = '#r70g-status';

function validateUrl(raw, name) {
  if (!raw) throw new Error(`Missing ${name} URL. Provide --${name} http://127.0.0.1:<port>/regulatory-laravel-r70g.html`);
  const u = new URL(raw);
  if (u.protocol !== 'http:' || !['127.0.0.1', 'localhost'].includes(u.hostname)) {
    throw new Error(`${name} must be a local HTTP URL (127.0.0.1 or localhost).`);
  }
  if (!u.pathname.endsWith('/regulatory-laravel-r70g.html')) {
    throw new Error(`${name} must target regulatory-laravel-r70g.html.`);
  }
  return u;
}

const round = (n) => Number.isFinite(n) ? Math.round(n * 100) / 100 : null;
const quantile = (a, p) => {
  const b = a.filter(Number.isFinite).sort((x,y)=>x-y);
  if (!b.length) return null;
  const at = (b.length - 1) * p; const lo = Math.floor(at); const hi = Math.ceil(at);
  return round(b[lo] + (b[hi] - b[lo]) * (at - lo));
};
const stats = a => ({samples:a.filter(Number.isFinite).length, median:quantile(a,0.5), p95:quantile(a,0.95), min:quantile(a,0), max:quantile(a,1)});
const measureNames = ['JSHeapUsedSize','JSHeapTotalSize','TaskDuration','ScriptDuration','LayoutDuration','RecalcStyleDuration'];
function metricMap(raw) { return Object.fromEntries((raw.metrics || []).filter(x=>measureNames.includes(x.name)).map(x=>[x.name,x.value])); }

let password;
try {
  const beforeUrl = validateUrl(before,'before');
  const afterUrl = validateUrl(after,'after');
  if (beforeUrl.origin === afterUrl.origin) {
    throw new Error('BEFORE and AFTER must use different local origins/ports, serving distinct Git snapshots.');
  }
  if (!Number.isInteger(iterations) || iterations < 1 || iterations > 30) {
    throw new Error('Use --iterations from 3 to 30 (recommended: 10).');
  }
  const chunks = [];
  for await (const chunk of process.stdin) chunks.push(chunk);
  password = Buffer.concat(chunks).toString('utf8').replace(/\r?\n$/, '');
  if (!password) throw new Error('Password missing from STDIN. Supply it locally through PowerShell secure prompt.');

  let chromium;
  try { ({ chromium } = await import('playwright-core')); }
  catch { try { ({ chromium } = await import('playwright')); }
  catch { throw new Error('Playwright missing. In this kit directory run: npm install --no-save playwright-core'); } }

  fs.mkdirSync(output,{recursive:true});
  const samplesFile = path.join(output,'r87-browser-samples.jsonl');
  const summaryFile = path.join(output,'r87-summary.json');
  fs.writeFileSync(samplesFile,'');
  const browser = await chromium.launch({channel:'msedge',headless, chromiumSandbox:true, args:[]});
  const all = [];
  const apiSamples = [];
  const configs = {BEFORE:beforeUrl,AFTER:afterUrl};
  const directRefs = {};
  const authenticatedContexts = {};

  async function authenticatedContext(base) {
    const context = await browser.newContext({viewport:{width:1440,height:900},locale:'id-ID',serviceWorkers:'block'});
    const status = await context.request.get(new URL('/api/auth/setup-status',base).href, {timeout:15000});
    if (!status.ok()) throw new Error(`Login setup not available HTTP ${status.status()} on ${base.origin}`);
    const {csrf,needs_setup} = await status.json();
    if (needs_setup) throw new Error(`No account in isolated QA database for ${base.origin}; do not bootstrap accounts during benchmark.`);
    const login = await context.request.post(new URL('/api/auth/login',base).href, {
      headers:{'X-CSRF-TOKEN':String(csrf),'Accept':'application/json'},
      data:{username,password},timeout:20000
    });
    if (!login.ok()) {
      throw new Error(`Authentication failed on ${base.origin} (HTTP ${login.status()}). Verify QA clone identity and password; do not change active DB.`);
    }
    const session = await context.request.get(new URL('/api/auth/session',base).href,{timeout:15000});
    if (!session.ok()) throw new Error(`Authenticated session not established on ${base.origin} (${session.status()}).`);
    const details = await session.json();
    if (details.username !== username || details.tenant_id !== 'ID-DIY-YOG') {
      throw new Error(`Unexpected QA account/tenant on ${base.origin}; refuse incomparable run.`);
    }
    return context;
  }
  // Authenticating here is required for measurement. Password is never sent to the filesystem.
  async function measure(label, kind, sampleIndex) {
    const base=configs[label];
    for (const target of Object.values(configs)) {
      const health = await fetch(new URL('/up',target),{signal:AbortSignal.timeout(30000)});
      await health.text();
      if (!(typeof health.ok === "function" ? health.ok() : health.ok === true)) throw new Error('BENCHMARK_SERVER_NOT_READY '+target.origin+' HTTP '+health.status);
    }
    const context=authenticatedContexts[label] ||= await authenticatedContext(base);
    const page=await context.newPage();
    const cdp=await context.newCDPSession(page);
    await cdp.send('Network.enable');
    await cdp.send('Network.clearBrowserCache');
    await cdp.send('Performance.enable');
    await page.addInitScript(() => {
      window.__r87lcp = null;
      try {new PerformanceObserver(list=>{const a=list.getEntries();if(a.length)window.__r87lcp=a[a.length-1].startTime;}).observe({type:'largest-contentful-paint',buffered:true});}catch{}
    });
    // Distinguish actual CDP transfer from decoded bytes.
    let active=false; let requests=0; let resourceErrors=0; let failedRequests=0;let encodedBytes=0;let cacheHits=0;
    let scenario='unmeasured';
    const requestPaths=new Map();
    const safePath=url=>{try{return new URL(url).pathname}catch{return '[invalid-url]'}};
    const recordFailure=row=>fs.appendFileSync(path.join(output,'r87-resource-errors.jsonl'),JSON.stringify({version:label,scenario,iteration:sampleIndex,...row})+'\n');
    const typeCounts={};
    cdp.on('Network.requestWillBeSent',e=>{if(!active)return;requests++;requestPaths.set(e.requestId,{pathname:safePath(e.request.url),type:e.type});});
    cdp.on('Network.responseReceived',e=>{if(!active)return;typeCounts[e.type]=(typeCounts[e.type]||0)+1;if(e.response?.status>=400){resourceErrors++;recordFailure({pathname:safePath(e.response.url),type:e.type,http_status:e.response.status,event:'http_error'});}if(e.response?.fromDiskCache)cacheHits++;});
    cdp.on('Network.requestServedFromCache',()=>{if(active)cacheHits++;});
    cdp.on('Network.loadingFailed',e=>{if(active){failedRequests++;recordFailure({...requestPaths.get(e.requestId),type:e.type,http_status:null,event:'loading_failed',canceled:!!e.canceled,error_code:/^net::[A-Z_]+$/.test(e.errorText||'')?e.errorText:'NETWORK_FAILURE'});}});
    cdp.on('Network.loadingFinished',e=>{if(active)encodedBytes+=e.encodedDataLength||0;});

    async function capture(stage) {
      scenario=stage;
      const start=await cdp.send('Performance.getMetrics');
      requests=0;resourceErrors=0;failedRequests=0;encodedBytes=0;cacheHits=0;
      for(const key of Object.keys(typeCounts))delete typeCounts[key];
      active=true;
      const wallStart=performance.now();
      const response=stage==='cold' ? await page.goto(base.href,{waitUntil:'load',timeout:45000}) : await page.reload({waitUntil:'load',timeout:45000});
      // A stable DOM status rather than merely load event is used for application readiness.
      let uiReady=true;
      try {
        await page.waitForFunction((s)=>{
          const e=document.querySelector(s);
          return !!e && !/meminta read model|loading|sedang memuat/i.test(e.textContent||'');
        },readySelector,{timeout:15000});
      } catch {uiReady=false;}
      const readyMs=performance.now()-wallStart;
      await page.waitForTimeout(1200); // permit lazy requests and LCP to settle
      active=false;
      const metrics=metricMap(await cdp.send('Performance.getMetrics'));
      const baseline=metricMap(start);
      const browserTiming=await page.evaluate(()=>{
        const nav=performance.getEntriesByType('navigation')[0];
        const paint=performance.getEntriesByType('paint');
        const fcp=paint.find(x=>x.name==='first-contentful-paint');
        const status=document.querySelector('#r70g-status')?.textContent?.trim()||null;
        const scripts=[...document.querySelectorAll('script[src]')].length;
        const css=[...document.querySelectorAll('link[rel="stylesheet"]')].length;
        return {
          dcl_ms:nav?.domContentLoadedEventEnd??null,
          load_ms:nav?.loadEventEnd??null,
          fcp_ms:fcp?.startTime??null,
          lcp_ms:window.__r87lcp,
          static_script_refs:scripts,
          static_css_refs:css,
          r70g_status:status?.slice(0,200)||null,
          document_path:location.pathname,
          has_model_elements:document.querySelectorAll('#r70g-documents > *').length>0,
        };
      });
      const row={version:label,cache_scenario:stage,iteration:sampleIndex,time:new Date().toISOString(),
        http_status:response?.status()??null,ui_ready:uiReady,ready_ms:round(readyMs),
        network_requests:requests,resource_http_errors:resourceErrors,failed_requests:failedRequests,
        encoded_transfer_bytes:encodedBytes,cache_hits:cacheHits,resource_types:typeCounts,
        js_heap_used_bytes:round(metrics.JSHeapUsedSize),js_heap_total_bytes:round(metrics.JSHeapTotalSize),
        task_duration_ms:round(metrics.TaskDuration*1000),
        script_duration_ms:round(metrics.ScriptDuration*1000),
        ...browserTiming};
      if(!directRefs[label])directRefs[label]=browserTiming.static_script_refs+browserTiming.static_css_refs;
      fs.appendFileSync(samplesFile,JSON.stringify(row)+'\n'); all.push(row);
      process.stdout.write(`${label} ${stage} ${sampleIndex}/${iterations}: ready=${row.ready_ms}ms DCL=${round(row.dcl_ms)}ms FCP=${round(row.fcp_ms)}ms requests=${row.network_requests} bytes=${row.encoded_transfer_bytes} status=${row.http_status}\n`);
      return row;
    }
    try {
      await capture('cold');
      await capture('warm');
      const apiStart=performance.now();
      const apiResponse=await context.request.get(new URL('/api/qa-regulatory-r70g/laravel-read-model',base).href,{timeout:30000,headers:{Accept:'application/json'}});
      const apiRow={version:label,iteration:sampleIndex,api:'R70F',elapsed_ms:round(performance.now()-apiStart),http_status:apiResponse.status()};
      apiSamples.push(apiRow);
      fs.appendFileSync(samplesFile,JSON.stringify({api_sample:apiRow})+'\n');
    } finally {await page.close();}
  }

  try {
    // Alternate AB / BA to reduce temporal ordering bias; each cold sample has a new browser context.
    for(let i=1;i<=iterations;i++) {
      const order=i%2===1?['BEFORE','AFTER']:['AFTER','BEFORE'];
      for(const label of order) {
        await measure(label,'cold',i);
        await new Promise(resolve=>setTimeout(resolve,16000));
      }
    }
  } finally {await browser.close();}
  const numericKeys=['ready_ms','dcl_ms','load_ms','fcp_ms','lcp_ms','network_requests','encoded_transfer_bytes','js_heap_used_bytes','task_duration_ms'];
  const summary={generated:new Date().toISOString(),headless,iterations,scope:'Real Chromium/Edge authenticated page load. Cold=isolated browser HTTP cache context; warm=reload. Not OS cold. Auth API setup/login excluded from page request totals.',
    variants:{BEFORE:beforeUrl.origin,AFTER:afterUrl.origin},direct_refs:directRefs,
    scenarios:{},failures:all.filter(r=>r.http_status!==200||!r.ui_ready||r.resource_http_errors||r.failed_requests).length};
  for(const label of ['BEFORE','AFTER'])for(const stage of ['cold','warm']){
    const rows=all.filter(r=>r.version===label&&r.cache_scenario===stage);
    summary.scenarios[`${label}_${stage}`]={count:rows.length,metrics:Object.fromEntries(numericKeys.map(key=>[key,stats(rows.map(r=>r[key]))])),http_statuses:[...new Set(rows.map(r=>r.http_status))],ui_ready_pass:rows.filter(r=>r.ui_ready).length,resource_error_samples:rows.filter(r=>r.resource_http_errors||r.failed_requests).length};
  }
  summary.authenticated_apis={R70F:Object.fromEntries(['BEFORE','AFTER'].map(label=>{
    const rows=apiSamples.filter(r=>r.version===label);
    return [label,{http_statuses:[...new Set(rows.map(r=>r.http_status))],timing_ms:stats(rows.filter(r=>r.http_status===200).map(r=>r.elapsed_ms)),pass:rows.length===iterations&&rows.every(r=>r.http_status===200)}];
  }))};
  const beforeRefs=directRefs.BEFORE,afterRefs=directRefs.AFTER;
  summary.comparability={
    expected_before_refs:21,observed_before_refs:beforeRefs,
    expected_after_refs:6,observed_after_refs:afterRefs,
    comparable_asset_layout:beforeRefs===21&&afterRefs===6,
    note:'URLs and source snapshots must be independently verified by the operator. R70G readiness and corresponding data/tenant must match. No legal validation or production admission.'
  };
  fs.writeFileSync(summaryFile,JSON.stringify(summary,null,2)+'\n');
  process.stdout.write(`RESULT=${summaryFile}\nSAMPLES=${samplesFile}\n`);
  if(summary.failures||!summary.comparability.comparable_asset_layout||Object.values(summary.authenticated_apis.R70F).some(x=>!x.pass)) {
    process.stderr.write('WARNING: failed/unauthenticated samples or asset layout mismatch; do not claim acceptance until examined.\n');
    process.exitCode=3;
  }
} catch(error) {
  fs.mkdirSync(output,{recursive:true});
  fs.writeFileSync(path.join(output,'benchmark-error.txt'),'BENCHMARK_BLOCKED='+String(error?.message||error)+'\n');
  process.stderr.write('BENCHMARK_BLOCKED='+String(error?.message||error)+'\n');
  process.exitCode=2;
} finally {password=undefined;}
