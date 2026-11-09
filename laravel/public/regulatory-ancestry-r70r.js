/* PI R70R — automatic, source-cited legal ancestry (candidate graph; no legal admission). */
(() => {
  'use strict'
  const status = document.getElementById('r70r-status')
  const root = document.getElementById('r70r-results')
  if (!status || !root) return

  const put = (parent, tag, value, className = '') => {
    const element = document.createElement(tag)
    if (className) element.className = className
    if (value !== undefined) element.textContent = String(value)
    parent.appendChild(element)
    return element
  }
  const permitted = (value, kind) => {
    try {
      const url = new URL(value)
      if (url.protocol !== 'https:' || url.hostname !== 'peraturan.bpk.go.id' ||
          url.username || url.password || url.port || url.hash) return false
      return kind === 'details'
        ? /^\/Details\/[0-9]+(?:\/[a-zA-Z0-9-]+)?\/?$/.test(url.pathname)
        : kind === 'search' && url.pathname === '/Search'
    } catch (_) { return false }
  }
  const link = (parent, title, href, kind) => {
    if (!permitted(href, kind)) throw Error('Alamat sumber hukum tidak diizinkan.')
    const anchor = put(parent, 'a', title, 'r70j-link')
    anchor.href = href
    anchor.target = '_blank'
    anchor.rel = 'noopener noreferrer'
  }
  const valid = data => {
    if (data?.schema !== 'PI_R70R_AUTOMATED_ANCESTRY_V1' ||
        data.tenant_id !== 'ID-DIY-YOG' ||
        data.source !== 'JDIH_BPK_OFFICIAL_METADATA' ||
        data.resolved_seed_count !== 2 ||
        data.exhaustiveness !== 'NOT_EXHAUSTIVELY_VERIFIED' ||
        data.result_status !== 'AUTOMATED_ANCESTRY_CANDIDATES_ONLY' ||
        data.legal_harmonization !== 'NOT_ASSESSED' ||
        data.numeric_comparison !== 'BLOCKED' ||
        data.production_admission !== 'DENIED' ||
        !Number.isInteger(data.unresolved_parent_count) ||
        !Array.isArray(data.seed_documents) || data.seed_documents.length !== 2 ||
        !Array.isArray(data.nodes) || data.nodes.length > 40 ||
        !Array.isArray(data.edges) || data.edges.length > 80) {
      throw Error('Kontrak keselamatan penelusuran induk tidak sesuai; hasil ditahan.')
    }
    const ids = new Map()
    for (const item of data.nodes) {
      if (typeof item.id !== 'string' || !/^(?:UUD-1945|(?:UU|PERPRES|PERMENDAGRI)-[0-9]{1,4}-[0-9]{4})$/.test(item.id) ||
          ids.has(item.id) || !['UUD', 'UU', 'PERPRES', 'PERMENDAGRI'].includes(item.kind) ||
          !['PINNED_METADATA_FETCHED', 'CITED_IDENTITY_NOT_YET_RESOLVED', 'OFFICIAL_CATALOG_IDENTITY_CANDIDATE'].includes(item.official_resolution)) {
        throw Error('Node hukum tidak sesuai kontrak.')
      }
      if (item.metadata_url !== null && !permitted(item.metadata_url, 'details')) throw Error('Sumber induk tidak sah.')
      if (item.search_url !== null && !permitted(item.search_url, 'search')) throw Error('Pencarian induk tidak sah.')
      if (item.official_resolution === 'OFFICIAL_CATALOG_IDENTITY_CANDIDATE' &&
          !permitted(item.metadata_url, 'details')) throw Error('Bukti identitas katalog tidak sah.')
      ids.set(item.id, item)
    }
    for (const item of data.seed_documents) {
      if (!['PERMENDAGRI-10-2025', 'PERMENDAGRI-86-2017'].includes(item.id) ||
          !permitted(item.official_url, 'details') ||
          !/^[A-F0-9]{64}$/.test(item.source_html_sha256) ||
          !Array.isArray(item.citations)) throw Error('Dokumen awal tidak sesuai kontrak.')
    }
    for (const edge of data.edges) {
      if (!ids.has(edge.from) || !ids.has(edge.to) ||
          !['OFFICIAL_ABSTRACT_LEGAL_BASIS_CITATION', 'OFFICIAL_ABSTRACT_CONSTITUTIONAL_REFERENCE'].includes(edge.evidence_kind) ||
          edge.relation_status !== 'CANDIDATE_NOT_LEGAL_FINDING' ||
          !permitted(edge.evidence_url, 'details')) throw Error('Hubungan norma tidak sah.')
    }
    if (data.parent_evidence_expansion) {
      const expanded = data.parent_evidence_expansion
      if (expanded.schema !== 'PI_R70T_PARENT_OFFICIAL_EVIDENCE_V1' ||
          expanded.status !== 'PARTIAL_OFFICIAL_METADATA_READING' ||
          expanded.legal_status !== 'ABSTAIN' ||
          expanded.amendment_census !== 'NOT_EXHAUSTIVELY_VERIFIED' ||
          !Array.isArray(data.version_candidates) || data.version_candidates.length > 25 ||
          expanded.total_version_candidates !== data.version_candidates.length) {
        throw Error('Kontrak bukti resmi induk tidak sesuai.')
      }
      for (const change of data.version_candidates) {
        if (!/^UU-[0-9]{1,4}-[0-9]{4}::UU-[0-9]{1,4}-[0-9]{4}$/.test(change.id) ||
            !ids.has(change.instrument_id) ||
            !/^UU-[0-9]{1,4}-[0-9]{4}$/.test(change.amending_candidate_id) ||
            change.relation !== 'OFFICIAL_STATUS_LISTED_AS_AMENDING_CANDIDATE' ||
            change.legal_effect !== 'NOT_ASSESSED' ||
            !/^[A-F0-9]{64}$/.test(change.source_html_sha256) ||
            !permitted(change.evidence_url, 'details')) {
          throw Error('Kandidat revisi tidak memiliki bukti katalog resmi.')
        }
      }
    }
    return data
  }

  function show(payload) {
    const data = valid(payload)
    root.replaceChildren()
    const intro = put(root, 'div', undefined, 'r70l-version')
    put(intro, 'strong', 'PENELUSURAN OTOMATIS · SUMBER RESMI · CALON HUBUNGAN')
    put(intro, 'p', data.resolved_seed_count + ' dokumen awal dibaca sistem tanpa pencarian manual; ' +
      data.edges.length + ' kutipan dasar hukum ditemukan; ' + data.unresolved_parent_count +
      ' identitas induk masih memerlukan penautan otomatis ke naskah resmi.')
    put(intro, 'small', 'Ini bukan inventaris regulasi menyeluruh. Penyebutan di bagian dasar hukum bukan otomatis hubungan delegasi, tingkat hierarki, atau kepastian hukum.')
    const batch = data.parent_resolution_batch
    if (batch) {
      if (batch.schema !== 'PI_R70S_OFFICIAL_PARENT_RESOLVER_V1' ||
          batch.traversal_status !== 'PARTIAL_METADATA_IDENTITY_ONLY' ||
          batch.legal_status !== 'ABSTAIN' ||
          batch.amendment_census !== 'NOT_EXHAUSTIVELY_VERIFIED' ||
          !Number.isInteger(batch.catalog_matched_count) ||
          batch.remaining_unresolved_count !== data.unresolved_parent_count) {
        throw Error('Kontrak pencocokan induk tidak sesuai.')
      }
      put(intro, 'p', 'R70S · Penautan katalog otomatis: ' + batch.catalog_matched_count +
        ' identitas memiliki calon halaman metadata resmi, ' +
        batch.remaining_unresolved_count + ' masih belum ditemukan secara tepat.')
    }

    const nodeById = new Map(data.nodes.map(n => [n.id, n]))
    for (const doc of data.seed_documents) {
      const card = put(root, 'article', undefined, 'r70g-card')
      put(card, 'h3', doc.id.replaceAll('-', ' · '))
      link(card, 'Metadata sumber resmi ↗', doc.official_url, 'details')
      put(card, 'small', 'Sidik jari HTML sumber: SHA-256 ' + doc.source_html_sha256.slice(0, 16) + '… (bukan hash PDF)')
      const parents = data.edges.filter(edge => edge.from === doc.id)
      if (parents.length === 0) put(card, 'p', 'Belum ada kutipan dasar hukum yang dapat dibaca.')
      for (const edge of parents) {
        const parent = nodeById.get(edge.to)
        const line = put(card, 'p', parent.id + ' · ' +
          (parent.official_resolution === 'PINNED_METADATA_FETCHED'
            ? 'metadata sumber awal berhasil diambil'
            : parent.official_resolution === 'OFFICIAL_CATALOG_IDENTITY_CANDIDATE'
              ? 'identitas katalog ditemukan; bukti abstrak tambahan (jika ada) ditampilkan di panel R70T'
              : 'identitas tercantum, tetapi halaman induknya belum terverifikasi'))
        if (parent.metadata_url) link(line, ' Buka aturan ↗', parent.metadata_url, 'details')
        else if (parent.search_url) link(line, ' Pencarian sumber resmi ↗', parent.search_url, 'search')
      }
    }
    if (data.parent_evidence_expansion) {
      const evidence = data.parent_evidence_expansion
      const detail = put(root, 'section', undefined, 'r70l-version')
      put(detail, 'strong', 'R70T · BUKTI ABSTRAK ATURAN INDUK · MASIH KANDIDAT')
      put(detail, 'p', 'Metadata induk yang berhasil dibaca pada putaran ini: ' +
        evidence.read_this_run + '. Kandidat perubahan yang tercatat: ' +
        evidence.total_version_candidates + '.')
      for (const edge of data.edges.filter(item => item.evidence_kind ===
          'OFFICIAL_ABSTRACT_CONSTITUTIONAL_REFERENCE')) {
        const line = put(detail, 'p', edge.from +
          ' → UUD-1945 · kutipan konstitusi ditemukan dalam abstrak resmi; bukan pembuktian seluruh hubungan hukum.')
        link(line, ' Buka bukti resmi ↗', edge.evidence_url, 'details')
      }
      for (const change of data.version_candidates) {
        const line = put(detail, 'p', change.instrument_id + ' · perubahan yang tercantum di katalog: ' +
          change.amending_candidate_id + ' · tanggal efektif dan materi perubahan belum diverifikasi.')
        link(line, ' Buka sumber status ↗', change.evidence_url, 'details')
      }
    }
    const foot = put(root, 'p', undefined, 'r70h-caveat')
    foot.textContent = 'Rantai menuju semua induk tertinggi, seluruh perubahan/pencabutan, dan keberlakuan pada tanggal kasus belum lengkap. Sistem wajib melanjutkan penelusuran otomatis. Sementara itu keputusan harmonisasi dan perhitungan tetap ABSTAIN / BLOCKED.'
    status.textContent = 'Pembacaan otomatis selesai (' + data.retrieved_at +
      '). Tidak diperlukan input kata kunci; hasil awal belum menjadi opini hukum.'
  }

  async function load() {
    status.textContent = 'Sistem sedang mengambil dasar hukum dari JDIH resmi tanpa input pengguna…'
    try {
      const response = await fetch('/api/qa-regulatory-r70r/auto-ancestry', {
        credentials: 'same-origin', cache: 'no-store', headers: {Accept: 'application/json'}
      })
      if (response.status === 401) { window.location.replace('./login.html'); return }
      if (!response.ok) throw Error(response.status === 403
        ? 'Sesi tidak berwenang untuk institusi ini.'
        : 'Sumber hukum resmi/layanan otomatis belum dapat diakses (HTTP ' + response.status + '). ABSTAIN.')
      show(await response.json())
    } catch (error) {
      root.replaceChildren()
      status.textContent = String(error.message || error)
    }
  }
  load()
})()
