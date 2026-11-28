const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const read=n=>fs.readFileSync(path.join(__dirname,'../../public',n),'utf8');
test('PER01 shared search and registry use authenticated existing catalogue',()=>{const s=read('pi-auth-shell.js');assert.match(s,/Cari di PI/);assert.match(s,/qa-superior-r100\/official-sources/);assert.match(s,/credentials:'same-origin'/);assert.match(s,/a\.pi-app-item\[href\]/);assert.doesNotMatch(s,/innerHTML|localStorage/);});
test('PER01 five stages retain missing evidence',()=>{assert.match(read('index.html'),/5 TAHAP/);const s=read('institution-home.js');assert.match(s,/KUA-PPAS 2026/);assert.match(s,/Bukti RKA-SKPD dan RAPBD belum tersedia/);assert.match(s,/Bukti DPA belum tersedia/);});
test('PER01 fiscal source names use shared identity',()=>{const s=read('native-annex-r106.js');assert.match(s,/PIRegulatoryIdentity\.resolve/);assert.match(s,/YOG-APBD-DETAIL-CHANGE3-2026/);assert.match(s,/judul belum terverifikasi/);assert.match(read('development-case-r85.html'),/per01-base-title/);});

test('PER-B shows Indonesian graph labels while retaining raw technical audit',()=>{
 const s=read('development-case-r85.js');
 assert.match(s,/Versi regulasi/);assert.match(s,/Kandidat penganggaran/);
 assert.match(s,/nodeLabels\[from\.type\]/);assert.match(s,/relationLabels\[edge\.relation\]/);
 assert.match(s,/technical\(row,edge\.status,/);
 assert.doesNotMatch(s,/add\(row, 'code', 'r85-relation'/);
});
test('PER-A failed catalogue promise is not cached; explicit retry reuses validated result',async()=>{
 const vm=require('node:vm');let calls=0;
 const scope={window:{},document:{addEventListener(){}},fetch:async url=>{if(url==='/api/auth/session')return {ok:true,json:async()=>({tenant_id:'ID-DIY-YOG'})};calls++;return calls===1?{ok:false}:{ok:true,json:async()=>({schema:'PI_R100_OFFICIAL_SOURCE_METADATA_READ_MODEL_V1',tenant_id:'ID-DIY-YOG',interpretation:{production_admission:'DENIED'},documents:[{id:'fixture',instrument:'PERWAL',number:74,year:2025,title:'Fixture',official_url:'https://jdih.jogjakota.go.id/home/produk_hukum/detail/1'}]})}}};
 vm.runInNewContext(read('pi-auth-shell.js'),scope);
 const catalog=scope.window.PIRegulatoryIdentity;
 await assert.rejects(catalog.load(),/Katalog tidak tersedia/);
 const a=await catalog.load();assert.equal(a[0].id,'fixture');
 assert.equal(await catalog.load(),a);assert.equal(calls,2);
});

const validCatalog=()=>({schema:'PI_R100_OFFICIAL_SOURCE_METADATA_READ_MODEL_V1',tenant_id:'ID-DIY-YOG',interpretation:{production_admission:'DENIED'},documents:[{id:'fixture',instrument:'PERWAL',number:74,year:2025,title:'Fixture',official_url:'https://jdih.jogjakota.go.id/home/produk_hukum/detail/1'}]});
const catalogScope=(catalogResponse)=>{
 const vm=require('node:vm');let count=0;const redirects=[];
 const scope={window:{},document:{addEventListener(){}},location:{replace:u=>redirects.push(u)},fetch:async u=>{if(u==='/api/auth/session')return {ok:true,json:async()=>({tenant_id:'ID-DIY-YOG'})};count++;return catalogResponse(count)}};
 vm.runInNewContext(read('pi-auth-shell.js'),scope);return {load:()=>scope.window.PIRegulatoryIdentity.load(),redirects,count:()=>count};
};
test('PER-A rejects cross-tenant HTTP200 and permits only a fresh validated retry',async()=>{
 const s=catalogScope(n=>{const d=validCatalog();if(n===1)d.tenant_id='OTHER';return {ok:true,json:async()=>d}});
 await assert.rejects(s.load(),/Kontrak/);assert.equal((await s.load())[0].id,'fixture');assert.equal(s.count(),2);
});
test('PER-A malformed row cannot poison shared identity resolver',async()=>{
 const s=catalogScope(()=>{const d=validCatalog();d.documents=[null];return {ok:true,json:async()=>d}});
 await assert.rejects(s.load(),/Kontrak/);
});
test('PER-A catalog401 clears cached identity and redirects to existing login',async()=>{
 const s=catalogScope(()=>({ok:false,status:401}));await assert.rejects(s.load(),/Katalog/);assert.deepEqual(s.redirects,['./login.html']);
});

test('PER-B mobile rotated arrow has bounded hitbox and audit spans its row',()=>{const s=read('development-case-r85.css');assert.match(s,/\.r85-flow-row > \.r85-technical \{ grid-column:1 \/ -1; min-width:0;/);assert.match(s,/transform:rotate\(90deg\); justify-self:center; width:1\.5em; height:1\.5em;/);});
