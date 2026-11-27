const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const source=fs.readFileSync(path.resolve(__dirname,'../../public/native-annex-r106.js'),'utf8');
for(const code of [401,403,419,422,429,503])test(`R106 reports HTTP ${code} without stale evidence or automatic retry`,async()=>{
 const elements=new Map();let action,calls=0,cleared=0;
 for(const id of ['r106-run','r106-status','r106-summary','r106-rows','r106-source-links'])elements.set(id,{textContent:'stale',disabled:false,replaceChildren(){cleared++},addEventListener(event,fn){if(id==='r106-run')action=fn}});
 vm.runInNewContext(source,{document:{getElementById:id=>elements.get(id),addEventListener(){}},fetch:async()=>{calls++;return{ok:false,status:code,headers:{get:()=>code===429?'60':null}}},Intl,window:{}});
 await action();assert.equal(calls,1);assert.equal(cleared,2);assert.equal(elements.get('r106-run').disabled,false);
 assert.match(elements.get('r106-status').textContent,new RegExp('HTTP '+code));
 if(code===429)assert.match(elements.get('r106-status').textContent,/60 detik/);
 assert.equal(elements.get('r106-summary').textContent,'');
});
