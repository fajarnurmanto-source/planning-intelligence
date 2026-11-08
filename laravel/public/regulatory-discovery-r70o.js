/* PI R70O: on-demand official regulatory source discovery; provisional, non-normative. */
(() => {
  'use strict'
  const form = document.getElementById('r70o-form')
  if (!form) return
  const queryInput = document.getElementById('r70o-query')
  const sourceInput = document.getElementById('r70o-source')
  const submit = document.getElementById('r70o-submit')
  const status = document.getElementById('r70o-status')
  const resultArea = document.getElementById('r70o-results')
  const node = (parent, tag, text, className) => {
    const el = document.createElement(tag)
    if (className) el.className = className
    if (text !== undefined) el.textContent = text
    parent.appendChild(el)
    return el
  }
  const officialUrl = value => {
    try {
      const url = new URL(value)
      return url.protocol === 'https:' && url.hostname === 'peraturan.bpk.go.id' &&
        /^\/Details\/[0-9]+(?:\/[a-zA-Z0-9-]+)?\/?$/.test(url.pathname)
    } catch (_) { return false }
  }
  function present(data) {
    if (!data || data.schema !== 'PI_R70O_OFFICIAL_DISCOVERY_V1' ||
        data.result_status !== 'DISCOVERY_ONLY' || data.legal_status !== 'NOT_VERIFIED' ||
        data.amendment_census !== 'NOT_EXHAUSTIVELY_VERIFIED' ||
        data.normative_alignment !== 'ABSTAIN' || data.numeric_comparison !== 'BLOCKED' ||
        data.production_admission !== 'DENIED' || !Array.isArray(data.items) || data.items.length > 15) {
      throw Error('Kontrak keamanan penelusuran resmi tidak cocok. Hasil ditahan.')
    }
    resultArea.replaceChildren()
    if (data.items.length === 0) {
      node(resultArea, 'p', 'Tidak ada kandidat yang dapat dipastikan cocok dari cuplikan laman. Gunakan tautan pencarian resmi untuk menelusuri manual; ini bukan bukti bahwa peraturan tidak ada.')
    }
    data.items.forEach(item => {
      if (!officialUrl(item.official_metadata_url) || item.classification !== 'SEARCH_RESULT_CANDIDATE' ||
          item.legal_status !== 'NOT_VERIFIED' || item.source_pdf_sha256 !== null ||
          typeof item.title !== 'string' || item.title.length < 10) {
        throw Error('Rujukan bukan tautan resmi yang diizinkan. Hasil ditahan.')
      }
      const card = node(resultArea, 'article', undefined, 'r70g-card')
      const link = node(card, 'a', item.title)
      link.href = item.official_metadata_url
      link.target = '_blank'
      link.rel = 'noopener noreferrer'
      node(card, 'p', 'KANDIDAT · Status berlaku dan rantai revisi belum diverifikasi.')
      if (item.amendment_or_repeal_candidate === true) {
        node(card, 'small', 'Judul menyebut perubahan/pencabutan — telusuri naskah dan hubungan resmi.')
      }
    })
    if (typeof data.official_search_url === 'string' && /^https:\/\/peraturan\.bpk\.go\.id\/Search\?/.test(data.official_search_url)) {
      const link = node(resultArea, 'a', 'Buka pencarian langsung di JDIH BPK')
      link.href = data.official_search_url
      link.target = '_blank'
      link.rel = 'noopener noreferrer'
    }
    const precision = data.search_mode === 'STRUCTURED_IDENTITY'
      ? 'Filter resmi jenis–nomor–tahun digunakan; identitas katalog bukan bukti keberlakuan.'
      : 'Pencarian kata kunci luas; beberapa hasil dapat berbeda jenis atau nomor.'
    status.textContent = `${data.items.length} kandidat dari ${data.source_name}. ${precision} Semua memerlukan sensus amendemen, verifikasi naskah resmi, dan reviewer. ABSTAIN tetap berlaku.`
  }
  form.addEventListener('submit', async event => {
    event.preventDefault()
    const query = queryInput.value.trim()
    if (query.length < 3 || query.length > 100 || sourceInput.value !== 'jdih_bpk') {
      status.textContent = 'Masukkan kata kunci 3–100 karakter dan pilih sumber resmi yang diizinkan.'
      return
    }
    submit.disabled = true
    status.textContent = 'Menghubungi JDIH resmi…'
    resultArea.replaceChildren()
    try {
      const params = new URLSearchParams({q:query, source:sourceInput.value})
      const response = await fetch('/api/qa-regulatory-r70o/discover?' + params.toString(), {credentials:'same-origin', cache:'no-store'})
      if (response.status === 401) { window.location.replace('./login.html'); return }
      if (!response.ok) {
        throw Error(response.status === 403 ? 'Tenant atau sesi tidak berwenang.' :
          response.status === 422 ? 'Kata kunci tidak valid.' :
          response.status === 429 ? 'Batas frekuensi tercapai. Coba lagi nanti.' :
          'Situs hukum resmi belum dapat diakses (HTTP ' + response.status + '). Tidak ada data yang direka.')
      }
      present(await response.json())
    } catch (error) {
      resultArea.replaceChildren()
      status.textContent = String(error.message || error)
    } finally { submit.disabled = false }
  })
})()
