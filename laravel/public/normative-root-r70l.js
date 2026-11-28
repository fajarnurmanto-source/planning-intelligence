/* PI R70L: typed national normative ancestry, without invented legal or fiscal admission. */
(() => {
  'use strict'
  const $ = id => document.getElementById(id)
  const add = (parent, tag, cls, value) => {
    const node = document.createElement(tag)
    if (cls) node.className = cls
    if (value !== undefined) node.textContent = String(value)
    parent.appendChild(node)
    return node
  }
  const sha = value => typeof value === 'string' && /^[A-F0-9]{64}$/.test(value)
  const instruments = {
    'IDN-PERMENDAGRI-86-2017': [
      'https://peraturan.bpk.go.id/Details/311927/permendagri-no-86-tahun-2017',
      'https://peraturan.go.id/files/bn1312-2017bt.pdf'
    ],
    'IDN-PERMENDAGRI-10-2025': [
      'https://peraturan.bpk.go.id/Details/321470/permendagri-no-10-tahun-2025',
      'https://peraturan.go.id/files/permendagri-no-10-tahun-2025.pdf'
    ]
  }
  const provisions = {
    'ROOT-86-2017-ART76': ['IDN-PERMENDAGRI-86-2017', 'Pasal 76; halaman cetak -40-', 'DELEGATION_FOR_ANNUAL_RKPD_GUIDELINE'],
    'ROOT-10-2025-PREAMBLE-B': ['IDN-PERMENDAGRI-10-2025', 'Menimbang huruf b; halaman cetak 1', 'EXPLICIT_LEGAL_BASIS_REFERENCE'],
    'ROOT-10-2025-ART2-3': ['IDN-PERMENDAGRI-10-2025', 'Pasal 2 ayat (3); halaman cetak -4-', 'SIMULTANEOUS_DRAFTING_REQUIREMENT'],
    'ROOT-10-2025-ART3-5': ['IDN-PERMENDAGRI-10-2025', 'Pasal 3 ayat (5); halaman cetak -5-', 'MUNICIPAL_RKPD_NATIONAL_AND_PROVINCIAL_REFERENCE'],
    'ROOT-10-2025-ART2-6': ['IDN-PERMENDAGRI-10-2025', 'Pasal 2 ayat (6); halaman cetak -5-', 'PROVINCIAL_DIY_EXCEPTION'],
    'ROOT-10-2025-ART12-2': ['IDN-PERMENDAGRI-10-2025', 'Pasal 12 ayat (2) huruf a–c; halaman cetak -8-', 'MUNICIPAL_RKPD_TO_BUDGET_PROCESS']
  }

  function checked(data) {
    if (data?.schema !== 'PI_R70L_NORMATIVE_ROOT_READ_MODEL_V1' ||
        data.tenant_id !== 'ID-DIY-YOG' || data.review_year !== 2026 ||
        data.as_of_date !== '2026-11-08' ||
        data.access_policy !== 'PUBLIC_OFFICIAL_NORMATIVE_METADATA_NO_PDF_REPUBLICATION' ||
        data.source_integrity !== 'OFFICIAL_LIVE_PDF_LINKS_NOT_HASH_PINNED' ||
        !sha(data.source_registry_sha256) ||
        !Array.isArray(data.instruments) || data.instruments.length !== 2 ||
        !Array.isArray(data.provisions) || data.provisions.length !== 6) {
      throw Error('Identitas, tahun atau integritas registri norma tidak cocok.')
    }
    const seenDocs = new Set()
    for (const doc of data.instruments) {
      const urls = instruments[doc.id]
      if (!urls || seenDocs.has(doc.id) || doc.kind !== 'PERMENDAGRI' ||
          doc.official_metadata_url !== urls[0] || doc.official_pdf_url !== urls[1] ||
          doc.pdf_sha256 !== null || doc.pdf_snapshot_status !== 'NOT_CAPTURED_NO_HASH' ||
          doc.amendment_census !== 'NOT_EXHAUSTIVELY_VERIFIED' ||
          (doc.id === 'IDN-PERMENDAGRI-10-2025' && doc.effective_date !== '2025-06-23') ||
          (doc.id === 'IDN-PERMENDAGRI-86-2017' && doc.effective_date !== '2017-09-25')) {
        throw Error('Sumber/versi peraturan tidak terverifikasi.')
      }
      seenDocs.add(doc.id)
    }
    const seenNorms = new Set()
    for (const norm of data.provisions) {
      const expected = provisions[norm.id]
      if (!expected || seenNorms.has(norm.id) || norm.instrument_id !== expected[0] ||
          norm.locator !== expected[1] || norm.role !== expected[2] ||
          typeof norm.summary !== 'string' || norm.summary.length < 20 || norm.summary.length > 350) {
        throw Error('Rujukan ketentuan hukum tidak cocok.')
      }
      seenNorms.add(norm.id)
    }
    const census = data.census_snapshot || {}
    if (census.schema !== 'PI_R70N_OFFICIAL_RELATION_METADATA_CENSUS_V1' ||
        census.as_of_date !== '2026-11-08' ||
        census.scope !== 'TWO_SCOPED_PARENT_PERMENDAGRI_ONLY' ||
        census.exhaustiveness !== 'NOT_EXHAUSTIVELY_VERIFIED' ||
        census.result !== 'AMENDMENT_CHAIN_INCOMPLETE' ||
        census.official_pdf_bytes_sha256 !== 'NOT_CAPTURED' ||
        census.empty_event_list_is_not_no_amendment_claim !== true ||
        !Array.isArray(census.admitted_direct_amendment_events) || census.admitted_direct_amendment_events.length !== 0 ||
        !Array.isArray(census.observations) || census.observations.length !== 2 ||
        census.observations[0].instrument_id !== 'IDN-PERMENDAGRI-86-2017' ||
        census.observations[0].official_effective_date !== '2017-09-25' ||
        census.observations[0].official_current_status !== 'BERLAKU' ||
        census.observations[0].relation_catalogue !== 'MENCABUT_PERMENDAGRI_54_2010' ||
        census.observations[0].direct_amendment_record !== 'NONE_LISTED_NOT_PROOF_OF_NONE' ||
        census.observations[0].related_source_url !== 'https://peraturan.go.id/files/bn517-2010.pdf' ||
        census.observations[1].instrument_id !== 'IDN-PERMENDAGRI-10-2025' ||
        census.observations[1].official_effective_date !== '2025-06-23' ||
        census.observations[1].official_current_status !== 'BERLAKU' ||
        census.observations[1].relation_catalogue !== 'BELUM_TERSEDIA' ||
        census.observations[1].direct_amendment_record !== 'UNDETERMINED') {
      throw Error('Sensus status peraturan resmi belum dapat dipercaya.')
    }
    const d = data.delegation || {}
    const c = data.case || {}
    const g = data.gates || {}
    const timeline = data.version_timeline || {}
    if (timeline.as_of_date !== '2026-11-08' || timeline.status !== 'AMENDMENT_CHAIN_INCOMPLETE' ||
        timeline.verified_change_event_count !== 0 || timeline.no_events_means_no_changes !== false ||
        timeline.legal_conclusion !== 'NOT_ASSESSED' || timeline.review_status !== 'REVIEW_REQUIRED' ||
        !Array.isArray(timeline.instrument_states) || timeline.instrument_states.length !== 2 ||
        !Array.isArray(timeline.change_events) || timeline.change_events.length !== 0 ||
        timeline.instrument_states.some(x => x.amendment_census !== 'NOT_EXHAUSTIVELY_VERIFIED' ||
          x.provision_events_effective !== 0 || x.provision_events_future !== 0)) {
      throw Error('Hasil kalkulasi versi peraturan belum dapat dipercaya.')
    }
    if (d.from_provision_id !== 'ROOT-86-2017-ART76' ||
        d.to_instrument_id !== 'IDN-PERMENDAGRI-10-2025' ||
        d.support_provision_id !== 'ROOT-10-2025-PREAMBLE-B' ||
        d.type !== 'EXPLICIT_NORMATIVE_DELEGATION_REFERENCE' ||
        d.evidence_status !== 'SOURCE_CONFIRMED_FOR_TEXTUAL_REFERENCE' ||
        d.legal_validation !== 'REVIEW_REQUIRED' ||
        c.planning_document_id !== 'ID-DIY-YOG-RKPD-2026-PERWALI-34-2025' ||
        c.planning_document_kind !== 'RKPD' || c.planning_year !== 2026 ||
        c.planning_document_source !== 'https://jdih.jogjakota.go.id/asset/naskah/2025pw3405034.pdf' ||
        c.municipal_relevance_provision_id !== 'ROOT-10-2025-ART3-5' ||
        c.simultaneous_drafting_provision_id !== 'ROOT-10-2025-ART2-3' ||
        c.regional_exception_provision_id !== 'ROOT-10-2025-ART2-6' ||
        c.regional_exception_applicability !== 'PROVINCE_DIY_ONLY_NOT_CITY_YOGYAKARTA' ||
        c.normative_applicability !== 'SOURCE_BOUND_RELEVANCE_CANDIDATE' ||
        c.actual_compliance !== 'NOT_ASSESSED' || c.admitted_alignment !== null ||
        g.normative_root_trace !== 'PARTIAL_PRIMARY_PROVISIONS_FOUND' ||
        g.regulatory_version !== 'AMENDMENT_CHAIN_INCOMPLETE' ||
        g.planning_constraint_crosswalk !== 'MAPPING_CANDIDATE' ||
        g.fiscal_legal_basis !== 'NOT_ASSESSED' ||
        g.source_pdf_hash !== 'NOT_CAPTURED' || g.result !== 'ABSTAIN' ||
        g.legal_harmonization !== 'NOT_ASSESSED' ||
        g.substantive_alignment !== 'NOT_ASSESSED' ||
        g.numeric_comparison !== 'BLOCKED' ||
        g.production_admission !== 'DENIED' ||
        g.review_status !== 'REVIEW_REQUIRED') {
      throw Error('Gerbang norma induk tidak dapat dibuka.')
    }
    return data
  }

  function link(parent, label, url) {
    const anchor = add(parent, 'a', 'r70j-link', label)
    anchor.href = url
    anchor.target = '_blank'
    anchor.rel = 'noopener noreferrer'
  }

  function render(payload) {
    const data = checked(payload)
    const root = $('r70l-results')
    root.replaceChildren()

    const trace = add(root, 'section', 'r70l-trace')
    add(trace, 'strong', '', 'Jejak rujukan norma dan pedoman tahunan — belum lengkap')
    add(trace, 'p', '', 'Permendagri 86/2017 Pasal 76 memberi dasar penetapan pedoman RKPD melalui peraturan menteri. Konsiderans Permendagri 10/2025 merujuk pasal tersebut. Rantai rujukan ini ditemukan dalam naskah resmi; kesimpulan pemenuhan RKPD belum dinilai.')
    add(trace, 'small', '', 'Rujukan tekstual ditemukan; delegasi dan penerapannya masih memerlukan telaah hukum.')

    const timeline = data.version_timeline
    const version = add(root, 'section', 'r70l-version')
    add(version, 'strong', '', 'Tanggal acuan registri: ' + timeline.as_of_date + ' · Rantai perubahan belum lengkap')
    for (const record of timeline.instrument_states) {
      add(version, 'p', '', record.instrument_id + ': status tanggal tercatat dalam registri; keberlakuan substantif belum disimpulkan.')
      const detail=add(version,'details','pi-reg-audit');add(detail,'summary','','Lihat status teknis versi');add(detail,'code','',record.date_state+' · '+record.amendment_census)
    }
    add(version, 'small', '', 'Peristiwa amendemen terverifikasi dalam registry: ' +
      timeline.verified_change_event_count + '. Angka nol hanya berarti belum ada event yang diadmit, BUKAN bukti bahwa tak pernah ada perubahan.')

    const census = data.census_snapshot
    const censusPanel = add(root, 'section', 'r70l-version')
    add(censusPanel, 'strong', '', 'SENSUS REVISI RESMI · PARSIAL · ' + census.as_of_date)
    add(censusPanel, 'p', '', '86/2017 berlaku sejak 25 September 2017 dan MENCABUT 54/2010. Ini relasi pencabutan aturan pendahulu, bukan perubahan atas 86/2017.')
    add(censusPanel, 'p', '', '10/2025 berlaku sejak 23 Juni 2025. Riwayat hubungan dalam metadata JDIH BPK masih BELUM TERSEDIA; tidak boleh disimpulkan tidak pernah diubah.')
    add(censusPanel, 'small', '', 'Cakupan: dua Permendagri. Sensus perubahan belum terbukti menyeluruh; PDF resmi belum di-hash. Hasil tetap AMENDMENT_CHAIN_INCOMPLETE / ABSTAIN.')
    link(censusPanel, 'Lihat Permendagri 54/2010 historis ↗', census.observations[0].related_source_url)

    const grid = add(root, 'div', 'r70l-grid')
    for (const doc of data.instruments) {
      const card = add(grid, 'article', 'r70l-source')
      add(card, 'span', 'r70j-kind', 'PERMENDAGRI ' + doc.number + '/' + doc.year + ' · NASIONAL')
      add(card, 'h3', '', doc.title)
      if (doc.effective_date) add(card, 'p', '', 'Tanggal mulai berlaku menurut metadata resmi: ' + doc.effective_date)
      add(card, 'p', 'r70j-unpinned', 'Status perubahan belum disensus menyeluruh; PDF sumber daring belum memiliki SHA-256 snapshot QA.')
      const actions = add(card, 'div', 'r70j-actions')
      link(actions, 'Lihat metadata resmi ↗', doc.official_metadata_url)
      link(actions, 'Buka PDF peraturan ↗', doc.official_pdf_url)
    }

    const normList = add(root, 'section', 'r70l-norms')
    add(normList, 'h3', '', '6 ketentuan relevan beserta locator')
    for (const norm of data.provisions) {
      const card = add(normList, 'article', 'r70l-norm')
      add(card, 'strong', '', norm.locator)
      const roleLabels={DELEGATION_FOR_ANNUAL_RKPD_GUIDELINE:'Rujukan delegasi pedoman tahunan',EXPLICIT_LEGAL_BASIS_REFERENCE:'Rujukan eksplisit dasar hukum',SIMULTANEOUS_DRAFTING_REQUIREMENT:'Ketentuan penyusunan simultan',MUNICIPAL_RKPD_NATIONAL_AND_PROVINCIAL_REFERENCE:'Rujukan nasional dan provinsi untuk RKPD kota',PROVINCIAL_DIY_EXCEPTION:'Pengecualian untuk Provinsi DIY',MUNICIPAL_RKPD_TO_BUDGET_PROCESS:'Rujukan proses RKPD ke anggaran'}
      add(card, 'span', 'r70l-role', roleLabels[norm.role]||'Perlu telaah ketentuan')
      const doc=data.instruments.find(i=>i.id===norm.instrument_id)
      link(card,'Buka naskah resmi ketentuan ini',doc.official_pdf_url)
      const detail=add(card,'details','pi-reg-audit');add(detail,'summary','','Lihat identitas ketentuan');add(detail,'code','',norm.id+' · '+norm.role)
      add(card, 'p', '', norm.summary)
    }

    const applicability = add(root, 'section', 'r70l-applicability')
    add(applicability, 'strong', '', 'PENERAPAN TERBATAS: KOTA YOGYAKARTA · RKPD 2026')
    add(applicability, 'p', '', 'Pasal 3 ayat (5) menempatkan RKPD kabupaten/kota dalam hubungan dengan RKP 2026, program strategis nasional dan RKPD provinsi. Pasal 2 ayat (3) menerangkan penyusunan yang simultan dengan RPJMD. Ini menjelaskan konteks versi R70J tetapi bukan pembuktian keselarasan isi.')
    add(applicability, 'p', '', 'Pengecualian Pasal 2 ayat (6) secara tekstual ditujukan kepada PROVINSI DIY; tidak boleh otomatis dialihkan kepada KOTA Yogyakarta. Pasal 12 ayat (2) membuka jalur pemeriksaan KUA–PPAS/APBD, tanpa mengesahkan angka.')
    link(applicability, 'Lihat RKPD Kota Yogyakarta 2026 ↗', data.case.planning_document_source)

    const guard = add(root, 'section', 'r70l-stop')
    add(guard, 'strong', '', 'Belum dapat menyimpulkan norma induk dan keberlakuan')
    add(guard, 'p', '', 'Rantai amendemen/pencabutan belum diperiksa secara menyeluruh; hash PDF belum ditangkap; keselarasan nyata RKPD dengan ketentuan induk dan dokumen nasional/provinsi belum diuji. Tampilkan rujukan sebagai bukti awal saja.')
    add(guard, 'small', '', 'Hubungan masih kandidat. Perbandingan angka dan keputusan produksi belum diizinkan.')
    const audit=add(root,'details','pi-reg-audit');add(audit,'summary','','Identitas registri dan batas teknis');add(audit,'code','','AMENDMENT_CHAIN_INCOMPLETE · MAPPING_CANDIDATE · NUMERIC BLOCKED · PRODUCTION DENIED');add(audit, 'p', 'r70j-digest', 'SHA-256 registri metadata R70L (BUKAN HASH PDF): ' + data.source_registry_sha256)
    $('r70l-status').textContent = data.instruments.length+' peraturan · '+data.provisions.length+' ketentuan kandidat · rantai perubahan belum lengkap.'
  }

  async function get(url) {
    const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store'})
    if (response.status === 401) {
      location.replace('./login.html')
      throw Error('Sesi berakhir')
    }
    if (!response.ok) throw Error(response.status === 403 ? 'Akses tenant ditolak.' :
      response.status === 503 ? 'Sumber norma induk gagal diverifikasi.' : 'HTTP ' + response.status)
    return response.json()
  }
  let loading=false
  async function reload(){
    if(loading)return
    loading=true
    const target=$('r70l-results'),status=$('r70l-status'),button=$('r70l-reload')
    target.replaceChildren();target.setAttribute('aria-busy','true')
    status.dataset.error='false';status.textContent='Memeriksa rujukan norma…'
    if(button)button.disabled=true
    try{render(await get('/api/qa-regulatory-r70l/normative-root'))}
    catch(error){
      target.replaceChildren();status.dataset.error='true'
      status.textContent=error?.message==='Sesi berakhir'?'Sesi masuk berakhir. Masuk kembali.':error?.message==='Akses tenant ditolak.'?'Akses dasar hukum tidak diberikan.':'Dasar hukum belum dapat diperiksa. Periksa ketersediaan sumber atau pilih Periksa ulang dasar hukum.'
    }finally{loading=false;target.setAttribute('aria-busy','false');if(button)button.disabled=false}
  }
  document.addEventListener('DOMContentLoaded',()=>{$('r70l-reload')?.addEventListener('click',reload);void reload()})
})()
