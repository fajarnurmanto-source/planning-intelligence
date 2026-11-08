/* PI R70G QA: render the authenticated Laravel R70F projection, not raw legal conclusions. */
(() => {
  'use strict'
  const $ = id => document.getElementById(id)
  const node = (parent, tag, cls, content) => {
    const element = document.createElement(tag)
    if (cls) element.className = cls
    if (content !== undefined) element.textContent = String(content)
    parent.appendChild(element)
    return element
  }
  const validId = value => typeof value === 'string' && /^(PERDA|PERWALI)[0-9]+_[0-9]{4}$/.test(value)
  const validPage = value => Number.isInteger(value) && value > 0 && value <= 100000
  function href(parent, label, value, external = false) {
    if (!value) return
    const link = node(parent, 'a', '', label)
    link.href = value
    if (external) {link.target = '_blank'; link.rel = 'noopener noreferrer'}
  }
  function pdf(parent, id, page, label) {
    if (!validId(id) || !validPage(page)) return
    href(parent, label, '/api/qa-regulatory-r70b/pdf?source_id=' + encodeURIComponent(id) + '#page=' + page, true)
  }
  function official(parent, url, label) {
    if (typeof url !== 'string' || !/^https:\/\/jdih\.jogjakota\.go\.id\//.test(url)) return
    href(parent, label, url, true)
  }
  function evidenceCount(item) {
    return (Array.isArray(item.source_evidence) ? item.source_evidence.length : 0) +
      (Array.isArray(item.scope_targets) ? item.scope_targets.reduce((n, t) => n + (Array.isArray(t.evidence) ? t.evidence.length : 0), 0) : 0)
  }
  function sourceCard(area, item) {
    const card = node(area, 'article', 'r70g-card')
    const top = node(card, 'div', 'r70g-top')
    const title = node(top, 'div')
    const role = item.document_role === 'AMENDING_REGULATION' ? 'PERUBAHAN KE-' + item.sequence : item.document_role === 'BASE_PENJABARAN_APBD' ? 'NASKAH DASAR' : 'PERATURAN PENDUKUNG'
    node(title, 'small', '', role)
    node(title, 'h3', '', item.title)
    node(top, 'span', 'r70g-pill', item.review_status === 'TWO_CLAUSES_TRACED_REVIEW_REQUIRED' ? 'DUA KLAUSUL TERLACAK' : 'REVIEW SUMBER')
    node(card, 'p', 'r70g-note', 'Bukti terikat SHA-256: ' + evidenceCount(item) + ' kutipan · Status hukum: ' + item.legal_status + ' · Tidak ada angka konsolidasi.')
    if (Array.isArray(item.scope_targets) && item.scope_targets.length) {
      const grid = node(card, 'div', 'r70g-targets')
      for (const target of item.scope_targets) {
        const box = node(grid, 'section', 'r70g-target')
        node(box, 'small', '', 'BUTIR ' + target.item + ' · ' + target.target)
        node(box, 'h4', '', target.change_verb ? 'Kata kerja: ' + target.change_verb.toLowerCase() : 'Kata kerja belum diverifikasi')
        node(box, 'p', '', 'PDF awal: hal. ' + target.start_pdf_page + ' · Kata kerja: hal. ' + target.verb_pdf_page + ' · Rentang: ' + (target.operative_page_span || '—') + ' halaman')
        if (target.target === 'LAMPIRAN II') node(box, 'p', '', 'Kandidat kode organisasi: ' + target.organizational_code_count)
        pdf(box, item.id, target.start_pdf_page, 'Buka PDF halaman pembuka ↗')
        if (target.verb_pdf_page !== target.start_pdf_page) pdf(box, item.id, target.verb_pdf_page, 'Buka PDF kata kerja ↗')
      }
    }
    const actions = node(card, 'div', 'r70g-actions')
    pdf(actions, item.id, 1, 'PDF snapshot lokal ↗')
    official(actions, item.metadata_url, 'Metadata JDIH ↗')
    if (item.metadata_warning) node(card, 'p', 'r70g-warning', 'ANOMALI METADATA — ' + item.metadata_warning)
  }
  function render(data) {
    const guard = data.interpretation || {}
    if (data.schema !== 'PI_R70F_LARAVEL_REGULATORY_READ_MODEL_V1' || data.tenant_id !== 'ID-DIY-YOG' ||
        guard.production_admission !== 'DENIED' || guard.numeric_comparison !== 'BLOCKED' ||
        guard.legal_harmonization !== 'NOT_ASSESSED' || guard.reviewer_gate !== 'REQUIRED' ||
        !data.source_integrity?.scope_links_to_ledger ||
        !Array.isArray(data.documents) || data.documents.length !== 7 ||
        data.summary?.source_count !== 7 || data.summary?.amendment_count !== 5 ||
        data.summary?.evidence_excerpt_sha256_verified !== 47) throw Error('Kontrak keamanan dan sumber Laravel tidak cocok')
    $('r70g-sources').textContent = '07/07'
    $('r70g-amendments').textContent = '05/05'
    $('r70g-targets').textContent = data.summary.scope_targets_first_95_pages + '/10'
    $('r70g-hashes').textContent = String(data.summary.evidence_excerpt_sha256_verified)
    $('r70g-ledger-sha').textContent = data.source_integrity.ledger_sha256
    $('r70g-scope-sha').textContent = data.source_integrity.scope_sha256
    const area = $('r70g-documents')
    area.replaceChildren()
    data.documents.forEach(item => sourceCard(area, item))
    $('r70g-status').textContent = 'R70F Laravel read model berhasil diverifikasi. Hanya sumber dan cakupan yang ditampilkan; reviewer tetap diperlukan.'
  }
  async function load() {
    const response = await fetch('/api/qa-regulatory-r70g/laravel-read-model', {credentials:'same-origin', cache:'no-store'})
    if (response.status === 401) {location.replace('./login.html'); return}
    if (!response.ok) throw Error(response.status === 503 ? 'Laravel staging atau sumber R70C/R70D belum tersedia. Periksa laporan QA R70G.' : 'HTTP ' + response.status)
    render(await response.json())
  }
  document.addEventListener('DOMContentLoaded', () => load().catch(error => {
    $('r70g-status').dataset.error = 'true'
    $('r70g-status').textContent = 'Read model belum dapat dimuat: ' + error.message
  }))
})()
