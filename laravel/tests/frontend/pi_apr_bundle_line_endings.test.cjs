const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),cp=require('node:child_process');
test('APR bundle accepts CRLF checkouts and still rejects content drift',()=>{
 const root=path.resolve(__dirname,'../../..'),tmp=fs.mkdtempSync(path.join(os.tmpdir(),'pi-apr-bundle-'));
 const publicDir=path.join(tmp,'laravel/public');fs.mkdirSync(publicDir,{recursive:true});fs.mkdirSync(path.join(tmp,'scripts'));
 const script=path.join(tmp,'scripts/pi-build-regulatory-assets.mjs');fs.copyFileSync(path.join(root,'scripts/pi-build-regulatory-assets.mjs'),script);
 for(const file of fs.readdirSync(path.join(root,'laravel/public')).filter(f=>/\.(js|css|html)$/.test(f))){
  const source=fs.readFileSync(path.join(root,'laravel/public',file),'utf8').replace(/\r\n/g,'\n').replace(/\n/g,'\r\n');fs.writeFileSync(path.join(publicDir,file),source);
 }
 assert.match(cp.execFileSync(process.execPath,[script,'--check'],{encoding:'utf8'}),/CHECK_PASS/);
 fs.appendFileSync(path.join(publicDir,'regulatory-laravel-r70g.js'),'\r\n// APR synthetic unbundled content change\r\n');
 const failed=cp.spawnSync(process.execPath,[script,'--check'],{encoding:'utf8'});
 assert.notEqual(failed.status,0);assert.match(failed.stderr,/BUNDLE_DRIFT/);
});
