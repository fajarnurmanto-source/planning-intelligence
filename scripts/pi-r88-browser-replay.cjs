// Browser render replay of real Laravel output; authentication tested separately in Feature tests.
const {chromium}=require(process.env.PI_PLAYWRIGHT_MODULE || '../qualification/benchmark-kit/node_modules/playwright-core')
const fs=require('fs'),path=require('path'),http=require('http'),assert=require('assert/strict')
const root=path.resolve(__dirname,'../laravel/public')
const evidenceDir=path.resolve(process.env.PI_R88_EVIDENCE_DIR || 'qualification/r88')
;(async()=>{
 const server=http.createServer((req,res)=>{
  const file=path.join(root,new URL(req.url,'http://localhost').pathname)
  if(!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);return res.end()}
  res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':'text/html')
  res.end(fs.readFileSync(file))
 })
 await new Promise(r=>server.listen(0,'127.0.0.1',r))
 const origin=`http://127.0.0.1:${server.address().port}`
 const browser=await chromium.launch({channel:'msedge',headless:true,chromiumSandbox:true})
 const results=[]
 try{
  for(const width of [1440,390]){
   const page=await browser.newPage({viewport:{width,height:1000},acceptDownloads:true})
   await page.route('**/api/**',async route=>{
    const url=new URL(route.request().url())
    if(url.pathname.endsWith('planning-crosswalk')){
     const key=url.searchParams.get('year')==='2027'?'2027':'2026'
     return route.fulfill({contentType:'application/json',body:fs.readFileSync(path.join(evidenceDir,`model-${key}.json`),'utf8')})
    }
    // Explicit browser fixture identity; never a real account or authentication bypass in Laravel.
    if(url.pathname==='/api/auth/session') return route.fulfill({json:{username:'RENDER_FIXTURE',role:'viewer'}})
    return route.fulfill({status:503,json:{status:'ABSTAIN',code:'UNRELATED_API_NOT_IN_RENDER_REPLAY'}})
   })
   await page.goto(origin+'/development-case-r85.html')
   await page.locator('#r88-controls button[type=submit]').click()
   await page.waitForFunction(()=>document.querySelector('#r88-results article'))
   assert.match(await page.locator('#r88-status').innerText(),/Jejak tersedia/)
   assert.match(await page.locator('#r88-results').innerText(),/MISSING_EVIDENCE/)
   const download=page.waitForEvent('download')
   await page.locator('#r88-export').click()
   const artifact=await download
   const target=path.join(evidenceDir,`export-${width}.json`)
   await artifact.saveAs(target)
   const exported=JSON.parse(fs.readFileSync(target))
   assert.equal(exported.interpretation.numeric_comparison,'BLOCKED')
   assert.equal(exported.continuity.equivalence_asserted,false)
   const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)
   assert.equal(overflow,false,'page must not overflow')
   await page.screenshot({path:path.join(evidenceDir,`desktop-mobile-${width}.png`),fullPage:true})
   await page.locator('#r88-controls select').selectOption('2027')
   await page.locator('#r88-controls button[type=submit]').click()
   await page.waitForFunction(()=>document.querySelector('#r88-status').textContent.includes('Tidak ada jejak'))
   await page.route('**/api/qa-superior-r88/**',r=>r.fulfill({status:401,json:{message:'Unauthenticated'}}))
   await page.locator('#r88-controls button[type=submit]').click()
   await page.waitForFunction(()=>document.querySelector('#r88-status').textContent.includes('(401)'))
   assert.equal(await page.locator('#r88-export').isDisabled(),true)
   assert.equal(await page.locator('#r88-results article').count(),0)
   results.push({width,render:'PASS',export:'PASS',year_2027_abstain:'PASS',expired_session_clear:'PASS',overflow:false,scope:'REAL_ENGINE_OUTPUT_REPLAY_NOT_LIVE_AUTHENTICATED_E2E'})
   await page.close()
  }
  fs.writeFileSync(path.join(evidenceDir,'browser-results.json'),JSON.stringify(results,null,2))
  console.log(JSON.stringify(results))
 }finally{await browser.close();server.close()}
})().catch(e=>{console.error(e);process.exit(1)})
