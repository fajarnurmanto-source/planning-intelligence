/* PI R75 — integrated, read-only review workbench. All values are text-only. */
(() => {
  'use strict'
  const root = document.getElementById('r75-workspace')
  if (!root) return
  const status = document.getElementById('r75-status')
  const refresh = document.getElementById('r75-refresh')
  const exportButton = document.getElementById('r75-export')
  const filter = document.getElementById('r75-filter')
  const counts = document.getElementById('r75-counts')
  let current = null

  function add(parent, tag, value, className = '') {
    const element = document.createElement(tag)
    if (className) element.className = className
    if (value !== undefined) element.textContent = String(value)
    parent.appendChild(element)
    return element
  }
  function allowedOfficialUrl(value) {
    try {
      const url = new URL(value)
      return url.protocol === 'https:' && ['peraturan.bpk.go.id', 'jdih.jogjakota.go.id'].includes(url.hostname) &&
        !url.username && !url.password && !url.port && !url.hash
    } catch (_) { return false }
  }
  function officialLink(parent, value, title) {
    if (!allowedOfficialUrl(value)) throw Error('Tautan sumber bukan domain resmi yang diperbolehkan.')
    const anchor = add(parent, 'a', title)
    anchor.href = value
    anchor.target = '_blank'
    anchor.rel = 'noopener noreferrer'
  }
  const sha = value => typeof value === 'string' && /^[a-f0-9]{64}$/i.test(value)
  const plainId = value => typeof value === 'string' && /^[A-Za-z0-9_:-]{1,120}$/.test(value)
  function validate(data) {
    if (data?.schema !== 'PI_R71_NORMATIVE_PLANNING_READINESS_V1' ||
        typeof data.tenant_id !== 'string' || !plainId(data.tenant_id) ||
        !sha(data.planning_source_sha256) ||
        data.readiness !== 'ABSTAIN' ||
        data.legal_harmonization !== 'NOT_ASSESSED' ||
        data.numeric_comparison !== 'BLOCKED' ||
        data.production_admission !== 'DENIED' ||
        data.crosswalk_status !== 'NO_VERIFIED_PROVISION_TO_PLANNING_EDGE' ||
        data.review_required !== true ||
        !Array.isArray(data.verified_provision_to_planning_edges) ||
        data.verified_provision_to_planning_edges.length !== 0 ||
        !Array.isArray(data.documents) || data.documents.length > 30 ||
        !Array.isArray(data.candidate_provision_to_planning_edges) ||
        data.candidate_provision_to_planning_edges.length > 100 ||
        !Array.isArray(data.upstream_amendment_watch) || data.upstream_amendment_watch.length > 25 ||
        data.candidate_edge_count !== data.candidate_provision_to_planning_edges.length ||
        data.upstream_amendment_watch_count !== data.upstream_amendment_watch.length ||
        !Array.isArray(data.blockers) || !data.blockers.includes('NORMATIVE_APPLICABILITY_NOT_PROVEN')) {
      throw Error('Kontrak kesiapan regulasi belum memenuhi gerbang aman.')
    }
    const documentIds = new Set()
    for (const doc of data.documents) {
      if (!plainId(doc.id) || documentIds.has(doc.id) ||
          !/^\d{4}-\d{2}-\d{2}$/.test(doc.issued_on) ||
          !allowedOfficialUrl(doc.metadata_url)) {
        throw Error('Identitas atau sumber dokumen tidak dapat dipercaya.')
      }
      documentIds.add(doc.id)
    }
    for (const edge of data.candidate_provision_to_planning_edges) {
      if (!plainId(edge.id) || !documentIds.has(edge.source_document_id) ||
          !documentIds.has(edge.to_planning_document_id) ||
          !allowedOfficialUrl(edge.source_metadata_url) ||
          edge.mapping_status !== 'MAPPING_CANDIDATE' ||
          edge.review_status !== 'REVIEW_REQUIRED' ||
          edge.legal_effect !== 'NOT_ASSESSED' ||
          edge.evidence_status !== 'METADATA_LOCATOR_ONLY_PDF_NOT_PINNED' ||
          !['FINAL_INSTRUMENT_POSTDATES_TARGET_REVIEW_DRAFT_REFERENCE',
            'NO_POSTDATING_OBSERVED_NOT_LEGAL_VALIDATION'].includes(edge.temporal_review_status) ||
          typeof edge.provision_locator !== 'string' || edge.provision_locator.length > 300) {
        throw Error('Bukti crosswalk tidak memenuhi kontrak review.')
      }
    }
    for (const change of data.upstream_amendment_watch) {
      if (!plainId(change.instrument_id) || !plainId(change.amending_candidate_id) ||
          !allowedOfficialUrl(change.official_evidence_url) ||
          !sha(change.source_html_sha256) ||
          change.status !== 'UNLINKED_AMENDMENT_CANDIDATE_REVIEW_REQUIRED' ||
          change.planning_impact !== 'NOT_ASSESSED_NO_VERIFIED_PROVISION_EDGE') {
        throw Error('Kandidat amendemen tidak memiliki bukti resmi yang utuh.')
      }
    }
    return data
  }
  function card(label, detail, source, hash, flagged) {
    const article = add(root, 'article', undefined, 'r75-item' + (flagged ? ' r75-flagged' : ''))
    add(article, 'span', label, 'r75-tag')
    add(article, 'h3', detail)
    if (source) officialLink(article, source, 'Periksa metadata resmi ↗')
    if (hash) add(article, 'small', 'SHA-256 HTML sumber: ' + hash, 'r75-hash')
    return article
  }
  function render() {
    if (!current) return
    root.replaceChildren()
    const group = filter.value
    const edges = current.candidate_provision_to_planning_edges
    const changes = current.upstream_amendment_watch
    const temporal = edges.filter(edge => edge.source_postdates_target)
    counts.textContent = edges.length + ' kandidat rujukan · ' + temporal.length +
      ' anomali kronologi · ' + changes.length + ' kandidat perubahan regulasi · 0 hubungan pasal terverifikasi'
    if (group === 'all' || group === 'chronology') {
      const title = add(root, 'h3', 'Kronologi dan kandidat rujukan')
      title.className = 'r75-group-title'
      if (!edges.length) add(root, 'p', 'Belum ada kandidat rujukan lintas dokumen.')
      for (const edge of edges) {
        const flagged = edge.source_postdates_target === true
        const item = card(flagged ? 'TINJAU URUTAN WAKTU' : 'RUJUKAN PERLU DITELAAH',
          edge.source_document_id + ' → ' + edge.to_planning_document_id,
          edge.source_metadata_url, null, flagged)
        add(item, 'p', 'Rujukan: ' + edge.provision_locator)
        add(item, 'p', 'Tanggal sumber: ' + edge.source_issued_on +
          ' · tanggal dokumen tujuan: ' + edge.target_issued_on)
        add(item, 'p', flagged
          ? 'Instrumen final terbit sesudah dokumen tujuan. Periksa apakah rujukan saat penyusunan menggunakan rancangan.'
          : 'Tidak ditemukan urutan terbalik; keberlakuan belum dibuktikan.')
        add(item, 'small', 'PDF belum diikat hash · REVIEW_REQUIRED · NOT_ASSESSED')
      }
    }
    if (group === 'all' || group === 'amendments') {
      add(root, 'h3', 'Pemantauan calon perubahan aturan', 'r75-group-title')
      if (!changes.length) add(root, 'p', 'Tidak ada kandidat perubahan dalam cache saat ini; ini bukan bukti tidak ada perubahan.')
      for (const change of changes) {
        const item = card('PERUBAHAN BELUM DIVERIFIKASI',
          change.instrument_id + ' → ' + change.amending_candidate_id,
          change.official_evidence_url, change.source_html_sha256, false)
        add(item, 'p', 'Hubungan terhadap pasal dan dampak pada dokumen perencanaan belum dinilai.')
        add(item, 'small', 'NOT_ASSESSED · REVIEW_REQUIRED')
      }
    }
    add(root, 'p',
      'Tidak ada penetapan keselarasan atau keberlakuan hukum. Pemakaian untuk keputusan anggaran/produksi tetap dilarang.',
      'r75-caveat')
  }
  async function load() {
    refresh.disabled = true
    exportButton.disabled = true
    current = null
    root.replaceChildren()
    status.textContent = 'Mengambil bukti crosswalk dan pemantauan regulasi melalui sesi aktif…'
    try {
      const response = await fetch('/api/qa-planning-r71/normative-readiness', {
        credentials: 'same-origin', cache: 'no-store', headers: {Accept: 'application/json'}
      })
      if (response.status === 401) { location.replace('./login.html'); return }
      if (!response.ok) throw Error(response.status === 403
        ? 'Akses institusi ditolak.'
        : 'Cache ancestry atau lineage belum siap (HTTP ' + response.status + ').')
      current = validate(await response.json())
      status.textContent = 'Data kandidat diterima · tenant ' + current.tenant_id +
        ' · reviewer wajib memeriksa bukti · ABSTAIN'
      exportButton.disabled = false
      render()
    } catch (error) {
      counts.textContent = 'Tidak ada kandidat yang dapat digunakan.'
      status.textContent = 'ABSTAIN — ' + (error.message || String(error))
    } finally {
      refresh.disabled = false
    }
  }
  function download() {
    if (!current) return
    const packet = {
      schema: 'PI_R75_REVIEW_WORKSPACE_EXPORT_V1',
      exported_at_utc: new Date().toISOString(),
      tenant_id: current.tenant_id,
      planning_source_sha256: current.planning_source_sha256,
      documents: current.documents,
      candidate_provision_to_planning_edges: current.candidate_provision_to_planning_edges,
      upstream_amendment_watch: current.upstream_amendment_watch,
      blockers: current.blockers,
      legal_harmonization: 'NOT_ASSESSED',
      readiness: 'ABSTAIN',
      numeric_comparison: 'BLOCKED',
      production_admission: 'DENIED',
      purpose: 'REVIEW_ONLY_NOT_A_LEGAL_OR_BUDGET_DECISION'
    }
    const url = URL.createObjectURL(new Blob([JSON.stringify(packet, null, 2)], {type:'application/json'}))
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = 'PI_R75_REGULATORY_REVIEW_CANDIDATES.json'
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  }
  refresh.addEventListener('click', load)
  filter.addEventListener('change', render)
  exportButton.addEventListener('click', download)
  load()
})()
