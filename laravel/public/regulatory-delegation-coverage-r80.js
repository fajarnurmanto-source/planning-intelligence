/* PI R80 / read-only delegation coverage. Not passage verification. */
(() => {
  'use strict'
  const el = id => document.getElementById(id)
  const row = (parent, text, kind = '') => {
    const div = document.createElement('div')
    div.className = 'r80-coverage-row ' + kind
    div.textContent = text
    parent.appendChild(div)
  }
  async function load() {
    const box = el('r80-coverage-results')
    if (!box) return
    const response = await fetch('/api/qa-regulatory-r80/delegation-coverage', {
      credentials: 'same-origin', cache: 'no-store', headers: {Accept: 'application/json'}
    })
    if (!response.ok) throw new Error('R80 API HTTP ' + response.status)
    const data = await response.json()
    if (data.schema !== 'PI_R80_DELEGATION_COVERAGE_QA_V1' ||
      data.tenant_id !== 'ID-DIY-YOG' ||
      data.result_status !== 'DOCUMENT_LOCATOR_CANDIDATES_NOT_VERIFIED_PRIMARY_EXCERPTS' ||
      data.production_admission !== 'DENIED' ||
      data.legal_harmonization !== 'NOT_ASSESSED' ||
      !Array.isArray(data.sources)) {
      throw new Error('R80 contract or guard mismatch')
    }
    box.replaceChildren()
    const total = document.createElement('p')
    total.className = 'r80-coverage-summary'
    total.textContent = data.candidate_article_count + ' pasal rujukan kandidat · ' +
      data.curated_locator_article_count + ' sudah tercatat sebagai locator R74 · ' +
      data.missing_curated_article_count + ' belum terpetakan'
    box.appendChild(total)
    for (const source of data.sources) {
      if (source.source_evidence_state !== 'OFFICIAL_PDF_LINK_ONLY_NOT_HASH_PINNED' ||
        source.source_pdf_sha256 !== null || !Array.isArray(source.candidates)) {
        throw new Error('R80 source is not clearly marked unpinned')
      }
      row(box, source.source_instrument_id + ' → ' + source.target_instrument_id,
        'r80-coverage-heading')
      for (const entry of source.candidates) {
        const paragraphs = entry.paragraphs.length
          ? ' ayat (' + entry.paragraphs.join('), (') + ')' : ''
        row(box, 'Pasal ' + entry.article + paragraphs + ' · ' +
          (entry.curated_r74_relation_present
            ? 'Locator R74 ada, teks pasal belum diverifikasi'
            : 'Kandidat belum masuk graf R74'), 'r80-coverage-candidate')
      }
    }
    row(box, 'R80 hanya audit cakupan dokumen. Hash PDF nasional, bunyi pasal, berlaku waktu, dan akibat hukum masih memerlukan bukti serta telaah reviewer.',
      'r80-coverage-warning')
  }
  if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
      load().catch(error => {
        const area = el('r80-coverage-results')
        if (area) area.textContent = 'R80 belum siap: ' + (error?.message || 'unknown')
      })
    })
  }
})()
