'use strict'
const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path')
const read=n=>fs.readFileSync(path.join(__dirname,'../../public',n),'utf8')
test('PXT migrated pages share local SVG and tokens, preserve functional DOM',()=>{
 for(const [file,ids] of [['login.html',['login-form','username','password','confirm-password','toggle-password','submit','feedback']],['index.html',['metric-docs','metric-pdfs','metric-findings','metric-decisions','findings-list','chain-list','documents-list']]]){
  const h=read(file)
  assert.match(h,/pi-design-tokens\.css/);assert.match(h,/pi-brand\.svg/);assert.match(h,/pi-skip/)
  for(const id of ids)assert.equal((h.match(new RegExp('id="'+id+'"','g'))||[]).length,1,id)
  assert.doesNotMatch(h,/<(?:script|link|img)[^>]+(?:href|src)="https?:\/\//)
 }
 assert.match(read('index.html'),/id="metric-decisions">—</)
 assert.match(read('login.html'),/login\.js/)
 assert.match(read('index.html'),/pi-auth-shell\.js/)
 assert.match(read('pi-brand.svg'),/viewBox="0 0 64 64"/)
})
test('PXT semantic foreground colors meet basic WCAG AA text contrast on white and base surface',()=>{
 const t=read('pi-design-tokens.css')
 const color=k=>t.match(new RegExp('--pi-'+k+':(#(?:[0-9a-fA-F]{6}))'))[1]
 const lum=h=>{const v=h.slice(1).match(/../g).map(x=>parseInt(x,16)/255).map(x=>x<=.04045?x/12.92:((x+.055)/1.055)**2.4);return v[0]*.2126+v[1]*.7152+v[2]*.0722}
 const ratio=(a,b)=>(Math.max(lum(a),lum(b))+.05)/(Math.min(lum(a),lum(b))+.05)
 for(const k of ['text','secondary','success','warning','error','action'])for(const bg of ['surface','bg'])assert.ok(ratio(color(k),color(bg))>=4.5,k+' on '+bg)
 assert.ok(ratio('#FFFFFF',color('action'))>=4.5)
 assert.match(t,/prefers-reduced-motion/);assert.match(t,/@supports/);assert.match(t,/focus-visible/)
})
test('PXT login never exposes unknown server response code or raw exception',()=>{
 const s=read('login.js')
 assert.match(s,/safeMessages\.has\(message\)/)
 assert.doesNotMatch(s,/new Error\(result\.code|textContent = String\(error/)
 assert.match(s,/\/api\/auth\/session/)
 assert.match(s,/X-CSRF-TOKEN/)
})
