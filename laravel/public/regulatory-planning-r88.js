(() => {
  'use strict'
  const get = id => document.getElementById(id)
  const add = (parent, tag, text) => {
    const el = document.createElement(tag)
    el.textContent = String(text ?? '')
    parent.appendChild(el)
    return el
  }
  let data = null
  let semanticPreview = null
  let generation=0, previewGeneration=0, crosswalkBusy=false, previewBusy=false, runBusy=false
  // Disabled controls are excluded from FormData; read named values even during a locked run.
  const currentFilters=()=>new URLSearchParams(['code','year','as_of'].map(name=>[name,get('r88-controls').elements.namedItem(name).value]))
  const filterMatches=(filters,query)=>String(filters?.year)===query.get('year')&&filters?.as_of===query.get('as_of')&&filters?.code===query.get('code')
  const labelStatus=code=>window.PIGlossary?.status(code)||'Perlu pemeriksaan lanjutan'
  const labelRelation=code=>window.PIGlossary?.relation(code)||'Hubungan perlu diperiksa'
  const detail=(parent,summary,content)=>{const el=add(parent,'details','');el.className='r85-technical';add(el,'summary',summary);add(el,'p',content);return el}
  const sourceLink=(parent,value)=>{
    if(value==='./overview-r69c.json')return // local projection is not a primary PDF
    try{const url=new URL(value);if(url.protocol!=='https:'||!['peraturan.bpk.go.id','peraturan.go.id','www.peraturan.go.id','jdih.jogjakota.go.id'].includes(url.hostname)||url.username||url.password||url.port)return;const a=add(parent,'a','Buka sumber resmi');a.href=url.href;a.target='_blank';a.rel='noopener noreferrer'}catch(_){}
  }
  const recoveryMessages = {
    PROVIDER_BALANCE:'Saldo layanan AI tidak mencukupi. Periksa saldo atau pilih layanan lain dengan persetujuan baru.',
    PROVIDER_ACCESS:'Akses layanan AI ditolak. Periksa pengaturan API dan izin.',
    PROVIDER_RATE_LIMIT:'Kuota sementara tercapai. Tunggu sebelum mengajukan analisis baru.',
    PROVIDER_TRANSIENT:'Layanan AI sedang terganggu. Telaah bukti tetap dapat digunakan.',
    PROVIDER_OUTPUT_INVALID:'Keluaran AI tidak memenuhi kontrak. Tidak ada hasil yang disahkan.',
    PROVIDER_TRANSPORT_OR_REQUEST:'Koneksi atau permintaan AI belum berhasil. Periksa layanan sebelum meminta analisis baru.',
    PROVIDER_PREFLIGHT:'Konfigurasi permintaan AI belum sesuai.',
    PROVIDER_BUSY:'Analisis lain sedang berlangsung pada layanan ini.',
    PROVIDER_REQUEST_UNCERTAIN:'Status permintaan sebelumnya belum pasti. Tunggu untuk mencegah pemanggilan ganda.',
    RECOVERY_GUARD_UNAVAILABLE:'Pengaman operasional tidak tersedia. Pemanggilan AI ditahan.',
    LOCAL_AI_BUDGET_EXHAUSTED:'Batas penggunaan harian internal PI tercapai. Tunggu pergantian hari UTC atau tinjau kebijakan administrator.',
    LOCAL_AI_BUDGET_UNAVAILABLE:'Pencatatan anggaran AI internal tidak tersedia. Pemanggilan ditahan.'
  }
  const resetSemantic = message => {
    previewGeneration++
    semanticPreview = null
    get('r89-run').disabled = true
    get('r89-consent').checked = false
    get('r89-consent').disabled = true
    get('r89-data').hidden = true
    get('r89-data').textContent = ''
    get('r89-results').replaceChildren()
    get('r89-status').textContent = message
  }
  const fail = message => {
    resetSemantic('Crosswalk belum siap; tidak ada panggilan AI.')
    get('r89-preview').disabled = true
    data = null
    get('r88-export').disabled = true
    get('r88-results').replaceChildren()
    get('r88-status').textContent = `ABSTAIN · ${message}`
    get('r88-context').textContent='Belum ada konteks crosswalk yang diterima.'
  }
  function checked(next,query){
    const gates=next?.interpretation
    if(next?.schema!=='PI_R88_REGULATORY_PLANNING_CROSSWALK_V1'||next.tenant_id!=='ID-DIY-YOG'
      ||!filterMatches(next.filters,query)||![0,1].includes(next.result_count)
      ||gates?.legal_harmonization !== 'NOT_ASSESSED'||gates?.substantive_alignment !== 'NOT_ASSESSED'
      ||gates?.numeric_comparison !== 'BLOCKED'||gates?.review_decision !== 'NO_ACTIVE_DECISION'||gates?.production_admission !== 'DENIED'
      ||next.continuity?.equivalence_asserted!==false||next.continuity?.status!=='ABSTAIN_MISSING_COMPARISON_VERSION'
      ||!['OBSERVED_LOCAL_CODE_REVIEW_REQUIRED','ABSTAIN_UNBOUND_CODE'].includes(next.binding?.status)
      ||next.classification?.national_master_verified!==false||next.classification?.authority!=='NOT_ASSESSED'
      ||next.classification?.legal_applicability!=='NOT_ASSESSED'||next.classification?.production_admission!=='DENIED'
      ||!['source_registry_sha256','definitions_registry_sha256','backbone_registry_sha256'].every(k=>/^[A-F0-9]{64}$/.test(next[k]||''))
      ||!['hierarchy','operational_definitions','nodes','relations','gaps'].every(k=>Array.isArray(next[k])))throw Error('CROSSWALK_CONTRACT_HOLD')
    if(next.result_count===0&&[next.hierarchy,next.operational_definitions,next.nodes,next.relations].some(a=>a.length))throw Error('CROSSWALK_EMPTY_HOLD')
    if(next.result_count===1&&(!next.relations.length||next.binding.status!=='OBSERVED_LOCAL_CODE_REVIEW_REQUIRED'))throw Error('CROSSWALK_BINDING_HOLD')
    const nodes=new Map(),edges=new Set()
    for(const n of next.nodes){if(!n||typeof n.id!=='string'||typeof n.label!=='string'||nodes.has(n.id))throw Error('CROSSWALK_NODE_HOLD');nodes.set(n.id,n)}
    for(const e of next.relations){
      const p=e?.provenance
      if(!e||typeof e.id!=='string'||edges.has(e.id)||!nodes.has(e.from)||!nodes.has(e.to)||e.legal_applicability!=='NOT_ASSESSED'
        ||e.temporal_status!=='OBSERVED_BY_AS_OF_NOT_PROOF_OF_LEGAL_VALIDITY'||!p||typeof p.as_of_date!=='string'||p.as_of_date>next.filters.as_of
        ||typeof p.locator!=='string'||typeof p.version!=='string'||p.jurisdiction!=='ID-DIY-YOG'&&p.jurisdiction!=='ID'&&p.jurisdiction!=='ID-DIY')throw Error('CROSSWALK_EDGE_HOLD')
      edges.add(e.id)
    }
    const codes=new Set()
    for(const row of next.hierarchy){
      if(!row||typeof row.code!=='string'||codes.has(row.code)||row.year!==next.filters.year||row.national_master_verified!==false
        ||!(next.filters.code===row.code||next.filters.code.startsWith(row.code+'.')))throw Error('CROSSWALK_HIERARCHY_HOLD')
      codes.add(row.code)
    }
    for(const item of next.operational_definitions){
      if(!item||item.code!==next.filters.code||item.planning_year!==next.filters.year||item.relation!=='RELATED_CANDIDATE_NOT_IDENTICAL'||item.status!=='CANDIDATE_REVIEW_REQUIRED'||!Array.isArray(item.supporting_edge_ids)||!item.supporting_edge_ids.length||item.supporting_edge_ids.some(id=>!edges.has(id)))throw Error('CROSSWALK_DEFINITION_HOLD')
    }
    return next
  }
  function render(next){
    const root=get('r88-results')
    const context=get('r88-context')
    context.textContent='Konteks crosswalk: Kota Yogyakarta · '+next.filters.year+' · kode '+next.filters.code+' · bukti tercatat hingga '+next.filters.as_of+'. Tanggal acuan bukti bukan penetapan keberlakuan hukum.'
    const summary=add(root,'article','');summary.className='pi-crosswalk-summary'
    add(summary,'h3','Apa yang dapat ditelaah?')
    add(summary,'p',next.result_count?'Jejak dokumen tersedia untuk telaah. Kode yang diamati secara lokal belum membuktikan nomenklatur nasional atau kewenangan OPD.':'Belum ada jejak kasus yang terikat pada pilihan ini.')
    add(summary,'p','Pembanding '+next.continuity.comparison_year+': versi kode, definisi indikator dan target belum terverifikasi. Tidak ada kesetaraan atau perbandingan angka yang disahkan.')
    detail(summary,'Status pengikatan dan versi',next.binding.status+' · '+next.continuity.status+' · '+next.classification.status)
    const levels={BIDANG_URUSAN:'Bidang urusan',PROGRAM:'Program',KEGIATAN:'Kegiatan',SUB_KEGIATAN:'Subkegiatan'}
    if(next.hierarchy.length){
      const card=add(root,'article','');card.className='pi-crosswalk-hierarchy'
      add(card,'h3','Nomenklatur yang diamati pada dokumen')
      for(const item of next.hierarchy){
        const row=add(card,'div','');row.className='pi-crosswalk-row'
        add(row,'small',(levels[item.level]||'Tingkat nomenklatur')+' · '+item.code)
        add(row,'p',item.label)
        add(row,'p',item.status==='PREFIX_DERIVED_REVIEW_REQUIRED'?'Awalan kode · klasifikasi perlu diperiksa':'Pengamatan dokumen lokal · belum verifikasi master nasional')
        const audit=detail(row,'Versi dan lokasi sumber',item.version+' · '+item.locator+' · '+item.status)
        sourceLink(audit,item.source_url)
      }
    }
    for(const item of next.operational_definitions){
      const card=add(root,'article','')
      add(card,'h3','Definisi indikator · kandidat keterkaitan')
      add(card,'p','Istilah subkegiatan: '+item.preferred_term)
      add(card,'p','Istilah yang ditemukan: '+item.observed_term)
      add(card,'p','Kemiripan istilah bukan kesetaraan definisi. Rumus, satuan dan target pembanding masih perlu dibuktikan.')
      detail(card,'Alasan dan referensi teknis',item.relation+' · '+item.reason+' · '+item.supporting_edge_ids.join(', '))
    }
    const gaps=add(root,'article','');gaps.className='pi-crosswalk-gaps'
    add(gaps,'h3','Bukti yang masih diperlukan')
    const names={AUTHORITY:['Kewenangan OPD','Ketentuan kewenangan dan perubahan yang relevan belum selesai diperiksa. Dokumen rencana tidak menetapkan kewenangan dengan sendirinya.'],INDICATOR:['Indikator dan target','Rumus, satuan dan versi target pembanding belum terverifikasi.'],DPA_REALISATION_AUDIT:['DPA, realisasi dan audit','Bukti DPA, realisasi dan temuan BPK belum diterima. Pengawasan APIP berbeda dari audit eksternal BPK.']}
    for(const gap of next.gaps){
      const row=add(gaps,'div','');row.className='pi-crosswalk-row'
      const known=Object.prototype.hasOwnProperty.call(names,gap.id)?names[gap.id]:['Bukti lain perlu diperiksa','Belum dapat menyimpulkan dari bukti yang tersedia.']
      add(row,'strong',known[0]);add(row,'p',known[1]);detail(row,'Rincian kekurangan bukti',gap.id+' · '+gap.status+' · '+gap.detail)
    }
    const traces=add(root,'article','');traces.className='pi-crosswalk-traces'
    add(traces,'h3','Hubungan dokumen dan sumber')
    if(!next.relations.length)add(traces,'p','Tidak ada hubungan terikat bukti pada pilihan ini.')
    const nodes=new Map(next.nodes.map(n=>[n.id,n.label]))
    for(const edge of next.relations){
      const row=add(traces,'div','');row.className='pi-crosswalk-row'
      add(row,'strong',labelRelation(edge.relation))
      add(row,'p',nodes.get(edge.from)+' → '+nodes.get(edge.to))
      const p=edge.provenance
      add(row,'p',labelStatus(p.verification_status)+' · Hubungan belum disahkan')
      const audit=detail(row,'Lokasi, versi dan identitas bukti',p.locator+' · '+p.version+' · '+p.as_of_date+' · '+p.jurisdiction)
      add(audit,'p',edge.id+' · '+edge.relation+' · '+edge.status+' · '+edge.relation_uncertainty+' · '+p.verification_status)
      add(audit,'p',p.source_sha256?'SHA-256 '+p.source_sha256:'Hash sumber belum tersedia')
      if(p.source_url==='./overview-r69c.json')add(audit,'p','Proyeksi lokal, bukan PDF sumber primer.')
      sourceLink(audit,p.source_url)
    }
  }
  get('r88-controls').addEventListener('submit', async event => {
    event.preventDefault()
    if(crosswalkBusy||runBusy)return
    const own=++generation
    fail('Sedang memeriksa bukti…')
    get('r88-status').textContent='Sedang memeriksa bukti…'
    const query=currentFilters()
    crosswalkBusy=true
    get('r88-run').disabled=true
    get('r88-results').setAttribute('aria-busy','true')
    try {
      const response=await fetch('/api/qa-superior-r88/planning-crosswalk?'+query,{credentials:'same-origin',cache:'no-store'})
      if(!response.ok)throw Error('CROSSWALK_REQUEST_HOLD')
      const next=await response.json()
      if(own!==generation)return
      if(query.toString()!==currentFilters().toString()){fail('Pilihan berubah. Periksa kesesuaian untuk konteks baru.');return}
      checked(next,query)
      render(next)
      data=next
      resetSemantic('Crosswalk dimuat. Periksa pratinjau sebelum menyetujui panggilan cloud.')
      get('r89-preview').disabled=next.result_count!==1
      get('r88-export').disabled=false
      get('r88-status').textContent=next.result_count?'Jejak tersedia untuk review · pembanding belum terverifikasi':'ABSTAIN · Tidak ada jejak sesuai filter'
    }catch(error){if(own===generation)fail('Bukti belum dapat diperiksa. Periksa akses atau pilih Periksa kesesuaian kembali.')}
    finally{crosswalkBusy=false;get('r88-run').disabled=false;get('r88-results').setAttribute('aria-busy','false')}
  })
  const approved = get('r89-consent')
  const sameFilters = () => {
    if (!data) return false
    const current = currentFilters()
    return current.get('year') === String(data.filters.year)
      && current.get('as_of') === data.filters.as_of
      && current.get('code') === data.filters.code
  }
  const invalidate=()=>{generation++;fail('Pilihan berubah. Periksa kesesuaian untuk konteks baru.')}
  get('r88-controls').addEventListener('change',invalidate)
  get('r88-controls').addEventListener('input',invalidate)
  get('r89-provider').addEventListener('change', () => {
    resetSemantic('Provider berubah; periksa ulang data yang akan dikirim.')
    get('r89-preview').disabled = !data || data.result_count !== 1 || !sameFilters()
  })
  approved.addEventListener('change', () => {
    get('r89-run').disabled = !approved.checked || !semanticPreview || !sameFilters()
    if (!approved.checked) get('r89-status').textContent = 'Persetujuan belum diberikan; tidak ada panggilan AI.'
  })
  get('r89-preview').addEventListener('click', async () => {
    if (!data || data.result_count !== 1 || !sameFilters() || previewBusy || runBusy) return
    resetSemantic('Memverifikasi bukti untuk pratinjau…')
    const own=previewGeneration, snapshot=data, provider=get('r89-provider').value
    previewBusy=true
    const query = new URLSearchParams(data.filters)
    try {
      const response = await fetch('/api/qa-superior-r89/semantic-preview?' + query, {
        credentials:'same-origin', cache:'no-store', headers:{Accept:'application/json'}
      })
      const payload = await response.json()
      if(own!==previewGeneration)return
      if(data!==snapshot||provider!==get('r89-provider').value||!sameFilters()){resetSemantic('Konteks berubah. Periksa kembali sebelum menyetujui.');return}
      if (!response.ok) throw new Error(payload.code || 'HTTP ' + response.status)
      if (payload.schema !== 'PI_R89_EVIDENCE_BOUND_SEMANTIC_ANALYSIS_V1'
        || payload.tenant_id !== data.tenant_id
        || payload.source_registry_sha256 !== data.source_registry_sha256
        || payload.definitions_registry_sha256 !== data.definitions_registry_sha256
        || payload.semantic_reasoning?.gates?.production_admission !== 'DENIED'
        || payload.preview?.status !== 'READY_FOR_EXPLICIT_CLOUD_APPROVAL'
        || !/^[A-F0-9]{64}$/.test(payload.preview.sha256 || '')
        || !payload.preview.context) {
        throw new Error('Pratinjau atau kontrak bukti tidak valid; ABSTAIN')
      }
      semanticPreview = payload
      get('r89-data').textContent = JSON.stringify(payload.preview.context,null,2)
      get('r89-data').hidden = false
      approved.disabled = false
      get('r89-status').textContent = 'Pratinjau siap. Pengiriman hanya dilakukan setelah persetujuan Anda.'
    } catch(error) { if(own===previewGeneration)resetSemantic('Pratinjau belum tersedia. Periksa bukti dan coba kembali.') }
    finally{previewBusy=false}
  })
  get('r89-run').addEventListener('click', async () => {
    if (!approved.checked || !semanticPreview || !data || !sameFilters()) return
    if(runBusy||crosswalkBusy||previewBusy)return
    const snapshot=data,preview=semanticPreview,provider=get('r89-provider').value,own=generation
    const valid=()=>data===snapshot&&semanticPreview===preview&&generation===own&&get('r89-provider').value===provider&&sameFilters()
    runBusy=true
    const locked=Array.from(get('r88-controls').elements).map(el=>[el,el.disabled])
    locked.forEach(([el])=>el.disabled=true)
    get('r89-run').disabled = true
    // Consume this approval even on timeout or rejected response.
    approved.checked = false
    approved.disabled = true
    get('r89-preview').disabled = true
    get('r89-provider').disabled = true
    get('r89-results').replaceChildren()
    get('r89-status').textContent = 'Memeriksa konfigurasi dan memanggil provider pilihan…'
    try {
      const sessionResponse = await fetch('/api/auth/session',{credentials:'same-origin',cache:'no-store'})
      if (!sessionResponse.ok) throw new Error('Sesi login berakhir')
      const session = await sessionResponse.json()
      if (typeof session.csrf !== 'string' || !session.csrf) throw new Error('Token CSRF tidak tersedia')
      if(!valid()){resetSemantic('Konteks berubah sebelum pengiriman. Tidak ada panggilan AI.');return}
      const response = await fetch('/api/qa-superior-r89/semantic-candidates', {
        method:'POST', credentials:'same-origin', cache:'no-store',
        headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':session.csrf},
        body:JSON.stringify({
          provider:get('r89-provider').value, consent:true,
          approved_cloud_metadata:true, approved_provider_charges:true,
          approved_context_sha256:semanticPreview.preview.sha256,
          year:data.filters.year,as_of:data.filters.as_of,code:data.filters.code
        })
      })
      const payload = await response.json()
      if(!valid()){resetSemantic('Konteks berubah setelah permintaan dikirim. Hasil tidak ditampilkan; periksa status layanan sebelum mengajukan permintaan baru.');return}
      if (!response.ok) throw new Error(payload.semantic_reasoning?.status || payload.code || 'HTTP ' + response.status)
      const gate = payload.semantic_reasoning?.gates
      if (payload.schema !== 'PI_R89_EVIDENCE_BOUND_SEMANTIC_ANALYSIS_V1'
        || payload.tenant_id !== data.tenant_id
        || payload.context_sha256 !== semanticPreview.preview.sha256
        || payload.source_registry_sha256 !== data.source_registry_sha256
        || payload.definitions_registry_sha256 !== data.definitions_registry_sha256
        || gate?.equivalence_asserted !== false
        || gate?.relation_promotion !== 'NOT_PERFORMED'
        || gate?.numeric_comparison !== 'BLOCKED'
        || gate?.production_admission !== 'DENIED'
        || !Array.isArray(payload.semantic_reasoning?.candidates)) {
        throw new Error('Hasil tidak cocok dengan bukti atau pengaman hukum')
      }
      const reasoning = payload.semantic_reasoning
      get('r89-status').textContent = reasoning.status + ' · ' + reasoning.provider_execution + ' · Legal ' + reasoning.legal_validation_status
      const recovery=payload.provider_recovery
      if(recovery && recovery.failure_class){
        const card=add(get('r89-results'),'article','')
        add(card,'h3','Analisis AI ditahan')
        add(card,'p',recoveryMessages[recovery.failure_class] || 'Layanan AI belum dapat digunakan.')
        if(Number.isInteger(recovery.retry_not_before)&&recovery.retry_not_before>0){
          add(card,'p','Batas waktu penahanan: '+new Date(recovery.retry_not_before*1000).toLocaleString('id-ID'))
        }
        add(card,'p','Tidak ada pemanggilan ulang otomatis. Periksa pratinjau dan beri persetujuan baru untuk analisis berikutnya.')
        add(card,'p','Telaah bukti dan fitur non-AI tetap tersedia.')
      }
      const budget=payload.resource_budget
      if(budget?.scope==='PI_LOCAL_TENANT_GEMINI_SEMANTIC_ROUTES_ONLY'){
        const card=add(get('r89-results'),'article','')
        add(card,'h3','Penggunaan Gemini di PI')
        add(card,'p','Reservasi request hari UTC: '+budget.attempt_reservations+' / '+budget.request_limit)
        add(card,'p','Token dilaporkan provider: '+budget.provider_reported_tokens+' ('+budget.usage_reports+' laporan)')
        add(card,'p','Ini batas internal PI, bukan kuota atau tagihan Google.')
      }
      if (reasoning.candidates.length > 0) {
        const card = add(get('r89-results'),'article','')
        const candidate = reasoning.candidates[0]
        add(card,'h3','Usulan semantik: ' + candidate.semantic_relation)
        add(card,'p',candidate.explanation)
        add(card,'p','Model: ' + payload.provider + ' · ' + payload.model)
        add(card,'p','Keyakinan model: ' + candidate.semantic_confidence + ' · Bukti: ' + candidate.evidence_confidence)
        add(card,'p','Jalur bukti: ' + candidate.supporting_relation_ids.join(' → '))
        add(card,'p','Tidak ada keputusan hukum, kesetaraan indikator, atau anggaran yang disahkan.')
      }
      data.semantic_reasoning = reasoning
      data.semantic_analysis = {
        schema:payload.schema,provider:payload.provider,model:payload.model,
        context_sha256:payload.context_sha256,context_class:payload.context_class,
        source_rights:payload.source_rights,provider_recovery:payload.provider_recovery || null,resource_budget:payload.resource_budget || null
      }
    } catch(error) {
      if(valid()){
        get('r89-results').replaceChildren()
        get('r89-status').textContent = 'ABSTAIN · '+(recoveryMessages[error.message]||'Analisis belum dapat diselesaikan. Periksa status layanan sebelum mengajukan persetujuan baru.')
      }
    } finally {
      semanticPreview = null
      approved.checked = false
      approved.disabled = true
      get('r89-run').disabled = true
      get('r89-provider').disabled = false
      runBusy=false
      locked.forEach(([el,was])=>el.disabled=was)
      get('r88-export').disabled=!data||!sameFilters()
      get('r89-preview').disabled = !data || data.result_count !== 1 || !sameFilters()
    }
  })
  get('r88-export').addEventListener('click', () => {
    if (!data || !sameFilters() || runBusy || crosswalkBusy) return
    const url = URL.createObjectURL(new Blob([JSON.stringify(data,null,2)],{type:'application/json;charset=utf-8'}))
    const link = document.createElement('a')
    link.href = url
    link.download = 'PI-R88-crosswalk-review-not-approved.json'
    link.click()
    setTimeout(() => URL.revokeObjectURL(url),1000)
  })
})()
