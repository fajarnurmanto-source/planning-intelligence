/* PI R70K: a source-anchored planning node viewed against the authenticated R70F scope. */
(() => {
  'use strict'
  const $ = id => document.getElementById(id)
  const add = (parent, tag, cls, value) => {
    const item = document.createElement(tag)
    if (cls) item.className = cls
    if (value !== undefined) item.textContent = String(value)
    parent.appendChild(item)
    return item
  }
  const sha = value => typeof value === 'string' && /^[A-F0-9]{64}$/.test(value)
  const source = value => value === 'https://jdih.jogjakota.go.id/asset/naskah/2025pw3405034.pdf'
  function checkedPlanning(data) {
    const node = data?.planning_node || {}
    const doc = data?.document || {}
    const edge = data?.crosswalk || {}
    const guard = data?.interpretation || {}
    if (data?.schema !== 'PI_R70K_TYPED_PLANNING_CROSSWALK_V1' ||
        data.tenant_id !== 'ID-DIY-YOG' || data.review_year !== 2026 ||
        !sha(data.source_registry_sha256) ||
        data.source_integrity !== 'METADATA_REGISTRY_ONLY_UNPINNED_PDF' ||
        data.data_rights !== 'PUBLIC_OFFICIAL_DOCUMENT_METADATA_WITH_SOURCE_LOCATOR_ONLY' ||
        doc.id !== 'ID-DIY-YOG-RKPD-2026-PERWALI-34-2025' || doc.kind !== 'RKPD' ||
        doc.plan_period_from !== 2026 || doc.plan_period_to !== 2026 ||
        doc.issued_on !== '2025-07-07' || !source(doc.source_url) ||
        doc.pdf_sha256 !== null || doc.pdf_snapshot_status !== 'NOT_CAPTURED_NO_HASH' ||
        node.taxonomy_type !== 'PROGRAM_NARRATIVE_WITH_PERFORMANCE_INDICATOR' ||
        node.program_name_as_observed !== 'program penyelenggaraan jalan' ||
        node.official_program_code !== null ||
        node.official_program_code_status !== 'NOT_PRESENT_IN_REVIEWED_EXCERPT' ||
        node.indicator_name !== 'Indeks prasarana umum wilayah' ||
        node.indicator_unit !== 'angka indeks' ||
        node.historical_observation_year !== 2024 ||
        node.historical_value_as_printed !== '92,16' ||
        node.planning_target_year !== 2026 || node.planning_target_value !== null ||
        node.source_printed_page !== 'II-77' || node.source_pdf_page !== null ||
        node.reviewer_status !== 'REVIEW_REQUIRED' ||
        node.relation_type !== 'NARRATIVE_COMPONENT_OF_COMPOSITE_INDICATOR' ||
        node.source_status !== 'PUBLIC_WEB_TEXT_LOCATED_PDF_NOT_HASH_PINNED' ||
        edge.target_regulatory_model !== 'PI_R70F_LARAVEL_REGULATORY_READ_MODEL_V1' ||
        edge.status !== 'ABSTAIN' || edge.admitted_regulatory_relation !== null ||
        edge.program_to_amendment_mapping !== 'NOT_ESTABLISHED' ||
        edge.reason !== 'NO_VERIFIED_2026_PROGRAM_CODE_OR_ROW_LEVEL_REGULATORY_MATCH' ||
        edge.substantive_alignment !== 'NOT_ASSESSED' ||
        guard.review_status !== 'REVIEW_REQUIRED' ||
        guard.legal_harmonization !== 'NOT_ASSESSED' ||
        guard.substantive_alignment !== 'NOT_ASSESSED' ||
        guard.numeric_comparison !== 'BLOCKED' || guard.production_admission !== 'DENIED') {
      throw Error('Kontrak node R70K tidak cocok. Pemetaan ditahan.')
    }
    return data
  }
  function checkedRegulatory(data) {
    if (data?.schema !== 'PI_R70F_LARAVEL_REGULATORY_READ_MODEL_V1' ||
        data.tenant_id !== 'ID-DIY-YOG' ||
        data.summary?.source_count !== 7 ||
        data.summary?.amendment_count !== 5 ||
        data.summary?.scope_targets_first_95_pages !== 10 ||
        !Array.isArray(data.documents) || data.documents.length !== 7 ||
        data.interpretation?.legal_harmonization !== 'NOT_ASSESSED' ||
        data.interpretation?.numeric_comparison !== 'BLOCKED' ||
        data.interpretation?.production_admission !== 'DENIED' ||
        !data.source_integrity?.scope_links_to_ledger ||
        !sha(data.source_integrity?.ledger_sha256) ||
        !sha(data.source_integrity?.scope_sha256)) {
      throw Error('Rantai sumber regulasi R70F tidak valid; perbandingan ditahan.')
    }
    return data
  }
  function render(planning, regulatory) {
    const data = checkedPlanning(planning)
    const sourceModel = checkedRegulatory(regulatory)
    const node = data.planning_node
    const area = $('r70k-results')
    area.replaceChildren()

    const card = add(area, 'article', 'r70k-node')
    add(card, 'span', 'r70j-kind', 'RKPD 2026 · PROGRAM (NARASI) + INDIKATOR · REVIEW_REQUIRED')
    add(card, 'h3', '', node.normalized_program_name)
    add(card, 'p', '', 'Unit dalam RKPD: ' + node.responsible_unit)
    add(card, 'p', '', 'Indikator yang disebut: ' + node.indicator_name + ' (' + node.indicator_unit + ')')
    add(card, 'p', 'r70k-raw', 'Data historis yang tercetak untuk 2024: ' + node.historical_value_as_printed +
      '. Ini BUKAN target RKPD 2026 dan tidak dihitung atau dibandingkan.')
    add(card, 'p', '', 'Bukti: ' + node.source_locator)
    const link = add(card, 'a', 'r70j-link', 'Buka dokumen RKPD resmi; cari Tabel 2.66 ↗')
    link.href = data.document.source_url
    link.target = '_blank'
    link.rel = 'noopener noreferrer'
    add(card, 'p', 'r70j-unpinned', 'Kode program belum terbukti pada fragmen ini; nomor halaman PDF fisik dan hash berkas juga belum ditangkap. Teks rujukan baru terlokalisasi pada halaman tercetak II-77.')

    const review = add(area, 'section', 'r70k-abstain')
    add(review, 'strong', '', 'ABSTAIN · BELUM ADA CROSSWALK REGULASI → PROGRAM')
    add(review, 'p', '', 'Model R70F memiliki ' + sourceModel.summary.amendment_count +
      ' peraturan pengubah dan ' + sourceModel.summary.scope_targets_first_95_pages +
      ' sasaran Lampiran I/II. Namun fragmen RKPD ini tidak memuat kode resmi program, baris sasaran tahun 2026 atau bukti bahwa salah satu sasaran regulasi merupakan program yang sama.')
    add(review, 'p', '', 'Hubungan narasi program dengan indikator dicatat sebagai kandidat komponen, bukan pemetaan legal, angka anggaran, atau konfirmasi indikator/target 2026.')
    add(review, 'small', '', 'NO_VERIFIED_2026_PROGRAM_CODE_OR_ROW_LEVEL_REGULATORY_MATCH · NUMERIC BLOCKED · PRODUCTION DENIED')
    const next = add(review, 'a', 'r70j-link', 'Lihat 10 kandidat regulasi R70I ↗')
    next.href = '#r70i-title'
    add(area, 'p', 'r70j-digest', 'SHA-256 registri metadata R70K (BUKAN PDF): ' + data.source_registry_sha256)
    $('r70k-status').textContent = '1 node program/indikator terlokalisasi · ' +
      sourceModel.summary.scope_targets_first_95_pages + ' sasaran regulasi diperiksa konteksnya · pemetaan ABSTAIN.'
  }
  async function get(url) {
    const response = await fetch(url, {credentials:'same-origin', cache:'no-store'})
    if (response.status === 401) {location.replace('./login.html'); throw Error('Sesi berakhir')}
    if (!response.ok) throw Error(url + ': ' + (response.status === 403 ? 'akses tenant ditolak' :
      response.status === 503 ? 'source read model tidak tersedia' : 'HTTP ' + response.status))
    return response.json()
  }
  document.addEventListener('DOMContentLoaded', () => Promise.all([
    get('/api/qa-planning-r70k/program-candidate'),
    get('/api/qa-regulatory-r70g/laravel-read-model')
  ]).then(([planning, regulatory]) => render(planning, regulatory)).catch(error => {
    $('r70k-results').replaceChildren()
    $('r70k-status').dataset.error = 'true'
    $('r70k-status').textContent = 'R70K ditahan: ' + error.message
  }))
})()
