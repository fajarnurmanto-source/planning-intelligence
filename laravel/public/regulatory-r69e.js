/* PI R76: R69E entry page reads the canonical R70F Laravel API. No legacy 404 route. */
(() => {
  'use strict'
  const $ = id => document.getElementById(id)
  function make(parent, tag, value, className = '') {
    const el = document.createElement(tag)
    if (className) el.className = className
    if (value !== undefined) el.textContent = String(value)
    parent.appendChild(el)
    return el
  }
  function officialUrl(value) {
    try {
      const url = new URL(value)
      return url.protocol === 'https:' &&
        url.hostname === 'jdih.jogjakota.go.id' &&
        /^\/asset\/naskah\/[a-z0-9_-]+\.pdf$/i.test(url.pathname) &&
        !url.port && !url.username && !url.password
    } catch (_) { return false }
  }
  function sourceLink(parent, url) {
    if (!officialUrl(url)) return
    const a = make(parent, 'a', 'Buka PDF sumber resmi ↗')
    a.href = url
    a.target = '_blank'
    a.rel = 'noopener noreferrer'
  }
  function validate(data) {
    if (data?.schema !== 'PI_R70F_LARAVEL_REGULATORY_READ_MODEL_V1' ||
        data.tenant_id !== 'ID-DIY-YOG' ||
        data.interpretation?.numeric_comparison !== 'BLOCKED' ||
        data.interpretation?.legal_harmonization !== 'NOT_ASSESSED' ||
        data.interpretation?.production_admission !== 'DENIED' ||
        data.source_integrity?.scope_links_to_ledger !== true ||
        !Array.isArray(data.documents) || data.documents.length !== 7 ||
        data.summary?.source_count !== 7 ||
        data.summary?.amendment_count !== 5) {
      throw Error('Model sumber terverifikasi tidak cocok; hasil ditahan.')
    }
    for (const item of data.documents) {
      if (typeof item.id !== 'string' || typeof item.title !== 'string' ||
          !/^[A-Fa-f0-9]{64}$/.test(item.pdf_sha256) ||
          !officialUrl(item.source_url)) {
        throw Error('Identitas atau URL sumber tidak sesuai registri.')
      }
    }
    return data
  }
  function render(data) {
    const docs = $('reg-docs')
    const changes = $('reg-amendments')
    const upper = $('reg-upper')
    docs.replaceChildren()
    changes.replaceChildren()
    upper.replaceChildren()
    $('reg-doc-count').textContent = String(data.summary.source_count).padStart(2,'0')
    $('reg-amend-count').textContent = String(data.summary.amendment_count).padStart(2,'0')
    for (const item of data.documents) {
      const isAmendment = item.document_role === 'AMENDING_REGULATION'
      const target = isAmendment ? changes : docs
      const card = make(target, 'article', undefined, 'reg-card')
      make(card, 'span', isAmendment ? 'KANDIDAT PERUBAHAN · REVIEW_REQUIRED' : 'SUMBER REGULASI RESMI', 'reg-label')
      make(card, 'strong', item.title)
      make(card, 'small', 'PDF snapshot QA SHA-256: ' + item.pdf_sha256.slice(0,16) + '…')
      sourceLink(make(card, 'div', undefined, 'reg-links'), item.source_url)
    }
    make(upper, 'p', 'Jejak norma tingkat atas tersedia di meja review terpadu R75. Penilaian keberlakuan dan dampak belum dilakukan.')
    const link = make(upper, 'a', 'Buka Integrated Regulatory Review ↗')
    link.href = './regulatory-laravel-r70g.html#r75-title'
    $('reg-amend-warning').textContent = 'Versi perubahan masih kandidat; konsolidasi hukum dan angka belum dilakukan. ABSTAIN.'
  }
  async function load() {
    const r = await fetch('/api/qa-regulatory-r70g/laravel-read-model', {
      credentials:'same-origin', cache:'no-store', headers:{Accept:'application/json'}
    })
    if (r.status === 401) {location.replace('./login.html');return}
    if (!r.ok) throw Error(r.status === 403 ? 'Akses institusi ditolak.' :
      'Model sumber Laravel belum tersedia (HTTP ' + r.status + ').')
    render(validate(await r.json()))
  }
  document.addEventListener('DOMContentLoaded', () => load().catch(error => {
    $('reg-docs').textContent = 'ABSTAIN — ' + (error.message || String(error))
    $('reg-amendments').textContent = 'Tidak ada kandidat yang boleh dianggap lengkap.'
    $('reg-upper').textContent = 'Telaah regulasi menunggu sumber resmi.'
    $('reg-amend-warning').textContent = 'REVIEW_REQUIRED · NUMERIC BLOCKED · PRODUCTION DENIED'
  }))
})()
