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
 const scope={window:{},document:{addEventListener(){}},fetch:async()=>{calls++;return calls===1?{ok:false}:{ok:true,json:async()=>({schema:'PI_R100_OFFICIAL_SOURCE_METADATA_READ_MODEL_V1',interpretation:{production_admission:'DENIED'},documents:[{id:'fixture'}]})}}};
 vm.runInNewContext(read('pi-auth-shell.js'),scope);
 const catalog=scope.window.PIRegulatoryIdentity;
 await assert.rejects(catalog.load(),/Katalog tidak tersedia/);
 const a=await catalog.load();assert.equal(a[0].id,'fixture');
 assert.equal(await catalog.load(),a);assert.equal(calls,2);
});
