/* PI R70J QA: official planning-document lineage, temporal context and explicit review boundaries. */
(() => {
  'use strict'
  const $ = id => document.getElementById(id)
  const element = (parent, tag, className, value) => {
    const item = document.createElement(tag)
    if (className) item.className = className
    if (value !== undefined) item.textContent = String(value)
    parent.appendChild(item)
    return item
  }
  const sourceUrl = value => {
    if (typeof value !== 'string') return null
    try {
      const url = new URL(value)
      if (url.protocol !== 'https:' || !['jdih.jogjakota.go.id', 'peraturan.bpk.go.id'].includes(url.hostname) ||
          url.username || url.password || url.port) return null
      return url.href
    } catch { return null }
  }
  const sha = value => typeof value === 'string' && /^[A-F0-9]{64}$/.test(value)
  const fixedDocuments = new Map([
    ['RPJMD', 'ID-DIY-YOG-RPJMD-2025-2029-PERDA-2-2025'],
    ['RKPD', 'ID-DIY-YOG-RKPD-2026-PERWALI-34-2025']
  ])
  function checked(data) {
    const guard = data?.interpretation || {}
    if (data?.schema !== 'PI_R70J_DOCUMENT_LINEAGE_READ_MODEL_V1' || data.tenant_id !== 'ID-DIY-YOG' ||
        data.planning_year !== 2026 || !sha(data.source_registry_sha256) ||
        data.source_integrity !== 'REGISTRY_SHA256_ONLY_PDF_NOT_PINNED' ||
        data.source_access_policy !== 'PUBLIC_OFFICIAL_METADATA_READ_ONLY_NO_PDF_REPUBLICATION' ||
        guard.mapping_status !== 'MAPPING_CANDIDATE' || guard.review_status !== 'REVIEW_REQUIRED' ||
        guard.legal_harmonization !== 'NOT_ASSESSED' || guard.substantive_alignment !== 'NOT_ASSESSED' ||
        guard.numeric_comparison !== 'BLOCKED' || guard.production_admission !== 'DENIED' ||
        data.chronology?.rkpd_issued_on !== '2025-07-07' ||
        data.chronology?.rpjmd_final_issued_on !== '2025-08-15' ||
        data.chronology?.rkpd_predates_rpjmd_final !== true ||
        data.chronology?.reference_type_in_rkpd !== 'DRAFT_RPJMD' ||
        data.chronology?.interpretation !== 'VERSION_REVIEW_REQUIRED_NOT_VIOLATION' ||
        !Array.isArray(data.documents) || data.documents.length !== 2 ||
        !Array.isArray(data.relations) || data.relations.length !== 2) {
      throw Error('Kontrak atau status batas analisis R70J tidak cocok; sumber tidak ditampilkan.')
    }
    const seen = new Set()
    for (const doc of data.documents) {
      if (!fixedDocuments.has(doc.kind) || fixedDocuments.get(doc.kind) !== doc.id || seen.has(doc.kind) ||
          (doc.kind === 'RKPD' && doc.issued_on !== '2025-07-07') ||
          (doc.kind === 'RPJMD' && doc.issued_on !== '2025-08-15') ||
          doc.pdf_sha256 !== null || doc.pdf_snapshot_state !== 'NOT_CAPTURED_NO_HASH' ||
          !sourceUrl(doc.metadata_url) || !sourceUrl(doc.pdf_url) ||
          typeof doc.verified_locator !== 'string' || !doc.verified_locator.trim() ||
          typeof doc.source_observation !== 'string' || !doc.source_observation.trim()) {
        throw Error('Dokumen perencanaan tidak lolos pembatasan sumber resmi R70J.')
      }
      seen.add(doc.kind)
    }
    if (seen.size !== 2) throw Error('Pasangan RPJMD dan RKPD tidak lengkap.')
    const required = new Set(['PLANNING_DOCUMENT_NORMATIVE_REFERENCE', 'DRAFT_VERSION_REFERENCE_REQUIRES_RECONCILIATION'])
    for (const relation of data.relations) {
      if (!required.has(relation.type) || ![...fixedDocuments.values()].includes(relation.from) ||
          ![...fixedDocuments.values()].includes(relation.to) || relation.from === relation.to ||
          relation.mapping_status !== 'MAPPING_CANDIDATE' ||
          relation.substantive_alignment !== 'NOT_ASSESSED' ||
          typeof relation.source_locator !== 'string' || !relation.source_locator.trim()) {
        throw Error('Relasi perencanaan tidak memenuhi kontrak kandidat.')
      }
      required.delete(relation.type)
    }
    if (required.size) throw Error('Relasi periode atau versi belum lengkap.')
    return data
  }
  function link(parent, label, url) {
    const valid = sourceUrl(url)
    if (!valid) return
    const a = element(parent, 'a', 'r70j-link', label)
    a.href = valid
    a.target = '_blank'
    a.rel = 'noopener noreferrer'
  }
  function render(raw) {
    const data = checked(raw)
    const output = $('r70j-results')
    output.replaceChildren()
    const chronology = element(output, 'section', 'r70j-version')
    element(chronology, 'strong', '', 'PERINGATAN VERSI · BUKAN TEMUAN PELANGGARAN')
    element(chronology, 'p', '', 'Perwali RKPD 2026 terbit 7 Juli 2025; Perda RPJMD final 2025–2029 terbit 15 Agustus 2025. Naskah RKPD masih menyebut rancangan RPJMD. Pemeriksaan kesesuaian versi dokumen diperlukan; urutan tanggal saja bukan bukti disharmonisasi.')
    element(chronology, 'small', '', 'VERSION_REVIEW_REQUIRED_NOT_VIOLATION · MAPPING_CANDIDATE')
    const cards = element(output, 'div', 'r70j-grid')
    for (const doc of data.documents) {
      const card = element(cards, 'article', 'r70j-card')
      element(card, 'span', 'r70j-kind', doc.kind + ' · ' + doc.issued_on)
      element(card, 'h3', '', doc.title)
      element(card, 'p', '', doc.source_observation)
      element(card, 'p', 'r70j-locator', 'Anchor rujukan: ' + doc.verified_locator)
      element(card, 'p', 'r70j-unpinned', 'PDF daring belum disimpan/di-hash sebagai snapshot QA. Status: NOT_CAPTURED_NO_HASH.')
      const actions = element(card, 'div', 'r70j-actions')
      link(actions, 'Metadata resmi ↗', doc.metadata_url)
      link(actions, 'Baca PDF resmi ↗', doc.pdf_url)
    }
    const relations = element(output, 'section', 'r70j-relations')
    element(relations, 'h3', '', 'Dua hubungan dokumen untuk telaah')
    for (const relation of data.relations) {
      const item = element(relations, 'div', 'r70j-relation')
      element(item, 'strong', '', relation.type === 'PLANNING_DOCUMENT_NORMATIVE_REFERENCE' ?
        'RPJMD → RKPD · acuan normatif' : 'RKPD → rancangan RPJMD · cek perubahan versi')
      element(item, 'p', '', relation.evidence_summary)
      element(item, 'small', '', relation.source_locator + ' · MAPPING_CANDIDATE · NOT_ASSESSED')
    }
    element(output, 'p', 'r70j-digest', 'SHA-256 registri metadata (BUKAN PDF): ' + data.source_registry_sha256)
    $('r70j-status').textContent = '2 dokumen resmi · 2 hubungan dokumen · perlu review versi; angka dan penilaian hukum tetap diblokir.'
  }
  async function load() {
    const response = await fetch('/api/qa-planning-r70j/lineage-candidates', {
      credentials: 'same-origin', cache: 'no-store'
    })
    if (response.status === 401) { location.replace('./login.html'); return }
    if (!response.ok) throw Error(response.status === 403 ? 'Tenant tidak diizinkan.' :
      response.status === 503 ? 'Registri sumber ditolak atau belum tersedia di Laravel lokal.' :
      'HTTP ' + response.status)
    render(await response.json())
  }
  document.addEventListener('DOMContentLoaded', () => load().catch(error => {
    $('r70j-status').dataset.error = 'true'
    $('r70j-status').textContent = 'Lineage R70J tidak ditampilkan: ' + error.message
    $('r70j-results').replaceChildren()
  }))
})()
