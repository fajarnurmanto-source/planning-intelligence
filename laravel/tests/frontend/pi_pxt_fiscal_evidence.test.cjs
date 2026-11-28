const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const source=fs.readFileSync(path.resolve(__dirname,'../../public/native-annex-r106.js'),'utf8');
function receipt(){
 const code='2.11.11.2.01.0004',codes=['5.1.02.01.001.00025','5.1.02.01.001.00026','5.1.02.01.001.00052','5.1.02.02.001.00086','5.1.02.02.009.00013','5.1.02.05.002.00001'],values=[18000000,48432250,27060000,48324000,75000000,803550000];
 // Synthetic contract fixture using existing PHP test amounts, not an original PDF receipt.
 const make=(instrument,sha,page)=>({instrument,sha256:sha,subactivity_code:code,pdf_page_count:1000,source_pdf_page:page,text_layer:'AVAILABLE_LOCAL',legal_identity:'HEADER_TEXT_MATCH_ONLY_NOT_AUTHENTICATED',rows:{}});
 const base=make('PERWAL-74-2025','E4AAA45F33B7254FDB7BBE3A0BC456999FD2CE3E19D8C07B874C640A5E1DEE24',384),change=make('PERWAL-12-2026','4E5194E6987468E30406911B277BACCA73506B3BF93BE413C0C19784AE3825EA',561);
 const rows=codes.map((c,i)=>{const a=values[i],p=i===5?385:384,b='Fixture awal '+c,e='Fixture perubahan '+c;base.rows[c]={code:c,amount_idr:a,page:p,excerpt:b};change.rows[c]={code:c,before_idr:a,after_idr:a,delta_idr:0,page:561,excerpt:e};return {account_code:c,base_idr:a,third_amendment_before_idr:a,third_amendment_after_idr:a,third_amendment_delta_idr:0,base_page:p,change_page:561,base_excerpt:b,change_excerpt:e,row_review:'TEXT_LAYER_CANDIDATE_NEEDS_VISUAL_REVIEW'}});
 return {schema:'PI_R106_NATIVE_PDF_ANNEX_REVIEW_V1',case_id:'YOG-2026-PERSAMPAHAN',subactivity_code:code,source_documents:{base,third_amendment:change},rows,row_count:6,derived_leaf_total_idr:1020366250,reconciliation:'SIX_LEAF_ACCOUNTS_AND_NUMBERS_MATCH_ONLY',origin_signature_and_complete_annex:'REVIEW_REQUIRED',later_amendments_13_15_2026:'NOT_RECONCILED',rka_dpa_realization:'NOT_VERIFIED',human_review:'NOT_REQUESTED','2027_indicator_equivalence':'ABSTAIN',numeric_admission:0,production_admission:'DENIED',writes_performed:false};
}
function setup(fetch){
 let action;const ids={};const el=tag=>({tag,children:[],textContent:'',disabled:false,append(...n){this.children.push(...n)},replaceChildren(){this.children=[]},addEventListener(_,fn){action=fn}});
 for(const id of ['r106-run','r106-status','r106-summary','r106-rows','r106-source-links'])ids[id]=el(id);
 vm.runInNewContext(source,{document:{getElementById:id=>ids[id],createElement:el,addEventListener(){}},fetch,Intl,window:{}});
 return {ids,run:()=>action()};
}
const ok=d=>({ok:true,json:async()=>d});
test('F4 displays exact version columns and row-specific source locators, without admission',async()=>{
 const u=setup(async()=>ok(receipt()));await u.run();
 const rows=u.ids['r106-rows'].children;assert.equal(rows.length,6);assert.equal(rows[5].children.length,7);
 assert.match(rows[5].children[5].children[0].href,/source=base#page=385$/);
 assert.match(rows[5].children[5].children[1].href,/source=change3#page=561$/);
 assert.match(rows[0].children[6].textContent,/perlu pemeriksaan visual/);
 assert.match(u.ids['r106-summary'].textContent,/1\.020\.366\.250/);assert.match(u.ids['r106-summary'].textContent,/bukan total anggaran yang berlaku/);
 assert.equal(u.ids['r106-source-links'].children.length,2);
});
test('F4 rejects wrong scope, pin, gates, totals, duplicate codes, page and source-row drift',async()=>{
 for(const change of [d=>d.case_id='OTHER',d=>d.subactivity_code='OTHER',d=>d.numeric_admission=1,d=>d.source_documents.base.sha256='A'.repeat(64),d=>d.rows.pop(),d=>d.rows[1].account_code=d.rows[0].account_code,d=>d.rows[0].base_page=1001,d=>d.rows[0].third_amendment_delta_idr=1,d=>d.derived_leaf_total_idr++,d=>d.rows[0].base_excerpt='mismatch',d=>d.rows[0].base_idr='18000000',d=>d.later_amendments_13_15_2026='VERIFIED']){
  const d=receipt();change(d);const u=setup(async()=>ok(d));await u.run();
  assert.equal(u.ids['r106-rows'].children.length,0);assert.equal(u.ids['r106-source-links'].children.length,0);assert.equal(u.ids['r106-summary'].textContent,'');assert.match(u.ids['r106-status'].textContent,/ditahan/);
 }
});
test('F4 failed reload clears previous candidates and explicit retry recovers',async()=>{
 let count=0;const u=setup(async()=>{count++;return count===2?{ok:false,status:503}:ok(receipt())});
 await u.run();assert.equal(u.ids['r106-rows'].children.length,6);
 await u.run();assert.equal(u.ids['r106-rows'].children.length,0);assert.equal(u.ids['r106-summary'].textContent,'');assert.equal(count,2);
 await u.run();assert.equal(u.ids['r106-rows'].children.length,6);assert.equal(count,3);
});
test('F4 coalesces repeated click during same request',async()=>{
 let resolve,calls=0;const u=setup(()=>{calls++;return new Promise(r=>resolve=r)});
 const pending=u.run();await u.run();assert.equal(calls,1);resolve(ok(receipt()));await pending;assert.equal(u.ids['r106-rows'].children.length,6);
});
