/* PI R70G/R70H QA: authenticated Laravel projection with on-demand, hash-pinned evidence. */
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
  const validSha = value => typeof value === 'string' && /^[A-Fa-f0-9]{64}$/.test(value)
  const validPage = value => Number.isInteger(value) && value > 0 && value <= 100000
  const jdihPdfUrls = new Map()
  function href(parent, label, value, external = false) {
    if (!value) return
    const link = node(parent, 'a', '', label)
    link.href = value
    if (external) {link.target = '_blank'; link.rel = 'noopener noreferrer'}
  }
  function pdf(parent, id, page, label) {
    if (!validId(id) || !validPage(page)) return
    const sourceUrl = jdihPdfUrls.get(id)
    if (!sourceUrl) return
    href(parent, label, sourceUrl + '#page=' + page, true)
  }
  function official(parent, url, label) {
    if (typeof url !== 'string' || !/^https:\/\/jdih\.jogjakota\.go\.id\//.test(url)) return
    href(parent, label, url, true)
  }
  function evidenceCount(item) {
    return (Array.isArray(item.source_evidence) ? item.source_evidence.length : 0) +
      (Array.isArray(item.scope_targets) ? item.scope_targets.reduce((n, t) => n + (Array.isArray(t.evidence) ? t.evidence.length : 0), 0) : 0)
  }
  function evidenceFailure(status) {
    if (status === 403) return 'Akses sumber ini ditolak untuk institusi atau sesi Anda.'
    if (status === 404) return 'Kutipan tidak ditemukan pada sumber QA yang terverifikasi.'
    if (status === 503) return 'Sumber bukti belum tersedia atau integritasnya tidak cocok. Tidak ada kutipan yang ditampilkan.'
    return 'Endpoint bukti gagal (HTTP ' + status + ').'
  }
  function checkedEvidence(data, sourceId, pdfHash, requested, integrity) {
    if (!data || data.schema !== 'PI_R70H_VERIFIED_EVIDENCE_LOOKUP_V1' ||
        data.source_id !== sourceId || data.excerpt_sha256 !== requested.excerpt_sha256 ||
        data.source_pdf_sha256 !== pdfHash || data.review_status !== 'REVIEW_REQUIRED' ||
        data.legal_harmonization !== 'NOT_ASSESSED' || data.numeric_comparison !== 'BLOCKED' ||
        data.production_admission !== 'DENIED' ||
        data.source_integrity?.ledger_sha256 !== integrity.ledger_sha256 ||
        data.source_integrity?.scope_sha256 !== integrity.scope_sha256 ||
        !data.source_integrity?.scope_links_to_ledger || !Array.isArray(data.matches) ||
        data.matches.length < 1 || data.matches.length > 20) {
      throw Error('Kontrak bukti Laravel tidak cocok. Kutipan ditahan.')
    }
    for (const match of data.matches) {
      if (!validPage(match.pdf_page) || !validSha(match.excerpt_sha256) ||
          match.excerpt_sha256 !== requested.excerpt_sha256 ||
          typeof match.excerpt !== 'string' || !match.excerpt ||
          match.review_status === 'APPROVED') {
        throw Error('Anchor atau status kutipan tidak sah. Kutipan ditahan.')
      }
    }
    if (!data.matches.some(match => match.pdf_page === requested.pdf_page)) {
      throw Error('Kutipan tidak memiliki halaman PDF yang diminta.')
    }
    return data.matches
  }
  function evidenceGroup(parent, sourceId, pdfHash, refs, integrity, label) {
    if (!Array.isArray(refs) || refs.length === 0) return
    const seen = new Set()
    const unique = refs.filter(ref => {
      if (!ref || ref.source_id !== sourceId || ref.pdf_sha256 !== pdfHash ||
          !validSha(ref.excerpt_sha256) || !validPage(ref.pdf_page)) {
        throw Error('Rujukan kutipan pada read model tidak lengkap.')
      }
      const key = ref.excerpt_sha256 + ':' + ref.pdf_page
      if (seen.has(key)) return false
      seen.add(key)
      return true
    })
    const details = node(parent, 'details', 'r70h-evidence')
    node(details, 'summary', '', label + ' (' + unique.length + ')')
    const list = node(details, 'div', 'r70h-evidence-list')
    for (const ref of unique) {
      const row = node(list, 'div', 'r70h-evidence-row')
      node(row, 'span', 'r70h-evidence-meta', 'PDF halaman ' + ref.pdf_page + ' · SHA-256 ' + ref.excerpt_sha256.slice(0, 12) + '…')
      const button = node(row, 'button', 'r70h-evidence-button', 'Buka kutipan terverifikasi')
      button.type = 'button'
      const output = node(row, 'div', 'r70h-evidence-output')
      output.setAttribute('role', 'status')
      output.setAttribute('aria-live', 'polite')
      button.addEventListener('click', async () => {
        button.disabled = true
        output.replaceChildren()
        node(output, 'p', 'r70h-loading', 'Memeriksa kutipan melalui Laravel…')
        try {
          const url = '/api/qa-regulatory-r70h/evidence/' +
            encodeURIComponent(sourceId) + '/' + encodeURIComponent(ref.excerpt_sha256)
          const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store'})
          if (response.status === 401) { location.replace('./login.html'); return }
          if (!response.ok) throw Error(evidenceFailure(response.status))
          const data = await response.json()
          const matches = checkedEvidence(data, sourceId, pdfHash, ref, integrity)
          output.replaceChildren()
          node(output, 'p', 'r70h-state', 'BUKTI TERIKAT SUMBER · REVIEW MANUSIA DIPERLUKAN')
          for (const match of matches) {
            const proof = node(output, 'section', 'r70h-proof')
            node(proof, 'small', '', 'PDF hal. ' + match.pdf_page + ' · ' + (match.context || 'sumber') +
              (match.context_detail ? ' · ' + match.context_detail : ''))
            node(proof, 'blockquote', '', match.excerpt)
            pdf(proof, sourceId, match.pdf_page, 'Buka PDF halaman sumber ↗')
          }
          const digests = node(output, 'dl', 'r70h-digests')
          node(digests, 'dt', '', 'SHA-256 kutipan')
          node(digests, 'dd', '', ref.excerpt_sha256)
          node(digests, 'dt', '', 'SHA-256 PDF sumber')
          node(digests, 'dd', '', pdfHash)
          node(digests, 'dt', '', 'SHA-256 ledger / scope')
          node(digests, 'dd', '', integrity.ledger_sha256 + ' / ' + integrity.scope_sha256)
          node(output, 'p', 'r70h-caveat', 'Kutipan telah ditautkan ke berkas QA; keberlakuan, harmonisasi, angka dan keputusan hukum belum dinilai.')
          button.textContent = 'Periksa ulang kutipan'
        } catch (error) {
          output.replaceChildren()
          node(output, 'p', 'r70h-error', 'Kutipan belum dapat dibuka: ' + (error?.message || 'kesalahan tidak diketahui'))
        } finally {
          button.disabled = false
        }
      })
    }
  }
  function sourceCard(area, item, integrity) {
    const card = node(area, 'article', 'r70g-card')
    const top = node(card, 'div', 'r70g-top')
    const title = node(top, 'div')
    const role = item.document_role === 'AMENDING_REGULATION' ? 'PERUBAHAN KE-' + item.sequence : item.document_role === 'BASE_PENJABARAN_APBD' ? 'NASKAH DASAR' : 'PERATURAN PENDUKUNG'
    node(title, 'small', '', role)
    node(title, 'h3', '', item.title)
    node(top, 'span', 'r70g-pill', item.review_status === 'TWO_CLAUSES_TRACED_REVIEW_REQUIRED' ? 'DUA KLAUSUL TERLACAK' : 'REVIEW SUMBER')
    node(card, 'p', 'r70g-note', 'Bukti terikat SHA-256: ' + evidenceCount(item) + ' kutipan · Status hukum: ' + item.legal_status + ' · Tidak ada angka konsolidasi.')
    evidenceGroup(card, item.id, item.pdf_sha256, item.source_evidence, integrity, 'Buka kutipan naskah')
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
        evidenceGroup(box, item.id, item.pdf_sha256, target.evidence, integrity, 'Lihat kutipan klausul')
      }
    }
    if (Array.isArray(item.date_candidates)) {
      const refs = item.date_candidates.flatMap(candidate => Array.isArray(candidate.evidence) ? candidate.evidence : [])
      evidenceGroup(card, item.id, item.pdf_sha256, refs, integrity, 'Periksa kutipan tanggal')
    }
    const actions = node(card, 'div', 'r70g-actions')
    pdf(actions, item.id, 1, 'PDF daring JDIH ↗')
    official(actions, item.metadata_url, 'Metadata JDIH ↗')
    node(card, 'p', 'r70g-caveat', 'PDF daring berasal dari JDIH. SHA-256 di atas mengikat snapshot QA; berkas daring saat dibuka belum diverifikasi ulang.')
    if (item.metadata_warning) node(card, 'p', 'r70g-warning', 'ANOMALI METADATA — ' + item.metadata_warning)
  }
  function render(data) {
    const guard = data.interpretation || {}
    if (data.schema !== 'PI_R70F_LARAVEL_REGULATORY_READ_MODEL_V1' || data.tenant_id !== 'ID-DIY-YOG' ||
        guard.production_admission !== 'DENIED' || guard.numeric_comparison !== 'BLOCKED' ||
        guard.legal_harmonization !== 'NOT_ASSESSED' || guard.reviewer_gate !== 'REQUIRED' ||
        !data.source_integrity?.scope_links_to_ledger ||
        !validSha(data.source_integrity.ledger_sha256) || !validSha(data.source_integrity.scope_sha256) ||
        !Array.isArray(data.documents) || data.documents.length !== 7 ||
        data.summary?.source_count !== 7 || data.summary?.amendment_count !== 5 ||
        data.summary?.evidence_excerpt_sha256_verified !== 47) throw Error('Kontrak keamanan dan sumber Laravel tidak cocok')
    jdihPdfUrls.clear()
    for (const item of data.documents) {
      if (validId(item.id) && typeof item.source_url === 'string' &&
          /^https:\/\/jdih\.jogjakota\.go\.id\/asset\/naskah\/[a-z0-9_-]+\.pdf$/i.test(item.source_url)) {
        jdihPdfUrls.set(item.id, item.source_url)
      }
    }
    $('r70g-sources').textContent = '07/07'
    $('r70g-amendments').textContent = '05/05'
    $('r70g-targets').textContent = data.summary.scope_targets_first_95_pages + '/10'
    $('r70g-hashes').textContent = String(data.summary.evidence_excerpt_sha256_verified)
    $('r70g-ledger-sha').textContent = data.source_integrity.ledger_sha256
    $('r70g-scope-sha').textContent = data.source_integrity.scope_sha256
    const area = $('r70g-documents')
    area.replaceChildren()
    data.documents.forEach(item => sourceCard(area, item, data.source_integrity))
    $('r70g-status').textContent = 'R70F Laravel read model berhasil diverifikasi. Buka kutipan R70H sesuai kebutuhan; reviewer tetap diperlukan.'
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
