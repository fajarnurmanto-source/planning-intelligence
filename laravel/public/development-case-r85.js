(() => {
  'use strict'

  const byId = id => document.getElementById(id)
  const add = (parent, tag, className, text) => {
    const element = document.createElement(tag)
    if (className) element.className = className
    if (text != null) element.textContent = String(text)
    parent.appendChild(element)
    return element
  }
  const allowedSource = value => {
    if (value === './overview-r69c.json') return value
    try {
      const url = new URL(value)
      const hosts = new Set(['peraturan.go.id', 'www.peraturan.go.id', 'peraturan.bpk.go.id', 'jdih.jogjakota.go.id'])
      return url.protocol === 'https:' && hosts.has(url.hostname) && !url.username && !url.password && !url.port ? url.href : null
    } catch (_) {
      return null
    }
  }
  // Planner labels are a PRESENTATION of the original machine statuses.
  // The unmodified raw status stays available in an audit disclosure.
  const statusLabels=Object.freeze({
    REVIEW_READY_WITH_ABSTENTIONS:'Siap ditelaah · beberapa bukti belum cukup',
    MIXED_VERIFIED_SOURCE_AND_CANDIDATE_RELATION:'Sumber ditemukan · hubungan perlu pemeriksaan',
    CANDIDATE_REQUIRES_LEGAL_REVIEW:'Hubungan perlu pemeriksaan hukum',
    DOCUMENTARY_SEQUENCE_REVIEW_REQUIRED:'Urutan dokumen perlu diperiksa',
    OUTPUT_TRACE_CANDIDATE_NUMERIC_BLOCKED:'Jejak keluaran ditemukan · angka belum boleh dibandingkan',
    TRACE_AVAILABLE_DECISION_ABSTAIN:'Jejak tersedia · keputusan belum dapat diambil',
    VERIFIED:'Sumber telah diperiksa',
    VERIFIED_SOURCE:'Sumber telah diperiksa',
    NOT_ASSESSED:'Belum dinilai',
    BLOCKED:'Belum diizinkan',
    NO_ACTIVE_DECISION:'Belum ada keputusan pemeriksa',
    DENIED:'Tidak diizinkan',
    HIGH:'Tinggi',MEDIUM:'Sedang',LOW:'Rendah'
  })
  const explainStatus=code=>statusLabels[code]||'Perlu pemeriksaan lanjutan'
  const technical=(parent,code,refs)=> {
    const details=add(parent,'details','r85-technical')
    add(details,'summary','','Lihat jejak bukti teknis')
    add(details,'code','r85-edge-ref',refs||'Identitas bukti tidak tersedia')
    add(details,'code','r85-edge-ref','Status sistem: '+code)
  }
  const fail = message => {
    for (const id of ['r85-questions', 'r85-graphs', 'r85-evidence', 'r85-guards']) {
      const target = byId(id)
      target.replaceChildren(add(document.createDocumentFragment(), 'p', 'r85-error', message))
    }
    byId('r85-case-label').textContent = 'Kasus tidak tersedia'
    byId('r85-case-summary').textContent = 'Pemeriksaan dihentikan untuk mencegah tampilan data tanpa kontrak.'
    byId('r85-case-status').textContent = '● Belum dapat disimpulkan'
    document.dispatchEvent(new CustomEvent('pi:case-state',{detail:{ready:false}}))
  }
  const assertData = data => {
    const caseData = data.case
    if (data.schema !== 'PI_R85_GOVERNMENT_DEVELOPMENT_CASE_READ_MODEL_V1'
      || data.tenant_id !== 'ID-DIY-YOG'
      || data.result_count !== 1
      || !caseData
      || caseData.id !== 'YOG-2026-PERSAMPAHAN'
      || caseData.planning_year !== 2026
      || caseData.questions?.length !== 5
      || caseData.graphs?.length !== 4
      || !Array.isArray(caseData.nodes)
      || !Array.isArray(caseData.edges)
      || data.interpretation?.legal_harmonization !== 'NOT_ASSESSED'
      || data.interpretation?.numeric_comparison !== 'BLOCKED'
      || data.interpretation?.review_decision !== 'NO_ACTIVE_DECISION'
      || data.interpretation?.production_admission !== 'DENIED') throw new Error('Kontrak kasus atau gerbang keamanan tidak cocok')
    return caseData
  }
  const fillSelect = (id, values, selected, label = value => value) => {
    const select = byId(id)
    select.replaceChildren()
    for (const value of values) {
      const raw = typeof value === 'object' ? value.id : value
      const option = add(select, 'option', '', label(value))
      option.value = String(raw)
      option.selected = String(raw) === String(selected)
    }
  }
  const renderFilters = data => {
    const available = data.filters.available
    const selected = data.filters.selected
    fillSelect('r85-jurisdiction', available.jurisdictions, selected.jurisdiction)
    fillSelect('r85-year', available.years, selected.year)
    fillSelect('r85-topic', available.topics, selected.topic, value => value === 'PERSAMPAHAN' ? 'Persampahan' : value)
    fillSelect('r85-case', available.cases, selected.case, value => value.label)
    document.dispatchEvent(new CustomEvent('pi:case-state',{detail:{ready:true}}))
  }
  const renderQuestions = caseData => {
    const container = byId('r85-questions')
    container.replaceChildren()
    for (const item of caseData.questions) {
      const card = add(container, 'article', 'r85-question')
      const badge=add(card, 'span', 'r85-status', explainStatus(item.status))
      badge.title='Status sumber: '+item.status
      add(card, 'h3', '', item.question)
      add(card, 'p', '', item.answer)
      technical(card,item.status,`Relasi: ${item.edge_ids.join(' · ')}`)
    }
  }
  const renderGraphs = caseData => {
    const nodes = new Map(caseData.nodes.map(node => [node.id, node]))
    const edges = new Map(caseData.edges.map(edge => [edge.id, edge]))
    const container = byId('r85-graphs')
    container.replaceChildren()
    for (const graph of caseData.graphs) {
      const card = add(container, 'article', 'r85-graph')
      add(card, 'h3', '', graph.label)
      const flow = add(card, 'div', 'r85-flow')
      for (const id of graph.edge_ids) {
        const edge = edges.get(id)
        const from = nodes.get(edge.from)
        const to = nodes.get(edge.to)
        const row = add(flow, 'div', 'r85-flow-row')
        const fromBox = add(row, 'div', 'r85-node')
        add(fromBox, 'small', '', from.type)
        add(fromBox, 'span', '', from.label)
        add(row, 'span', 'r85-arrow', '→')
        const toBox = add(row, 'div', 'r85-node')
        add(toBox, 'small', '', to.type)
        add(toBox, 'span', '', to.label)
        add(row, 'code', 'r85-relation', `${id} · ${edge.relation} · ${edge.status}`)
      }
    }
  }
  const renderEvidence = caseData => {
    const container = byId('r85-evidence')
    container.replaceChildren()
    for (const edge of caseData.edges) {
      const source = edge.provenance
      const item = add(container, 'article', 'r85-evidence-item')
      add(item, 'code', '', edge.id)
      const relation = add(item, 'div')
      add(relation, 'strong', '', edge.relation)
      add(relation, 'p', '', `${edge.from} → ${edge.to}`)
      const proof = add(item, 'div')
      const href = allowedSource(source.source_url)
      if (!href) throw new Error('Origin sumber tidak diizinkan')
      const anchor = add(proof, 'a', '', `${source.source_id} · buka sumber ↗`)
      anchor.href = href
      if (href !== './overview-r69c.json') {
        anchor.target = '_blank'
        anchor.rel = 'noopener noreferrer'
      }
      add(proof, 'p', '', source.locator)
      add(proof, 'span', 'r85-hash', source.source_sha256 ? `SHA-256 ${source.source_sha256}` : 'SHA-256 belum tersedia')
      const status = add(item, 'div', 'r85-verification')
      const sourceBadge=add(status, 'span', '', explainStatus(source.verification_status))
      sourceBadge.title='Status sumber: '+source.verification_status
      add(status, 'span', source.uncertainty === 'HIGH' ? 'high' : '', `Ketidakpastian ${explainStatus(source.uncertainty)}`)
      add(status, 'small', '', `${source.version} · ${source.as_of_date}`)
    }
  }
  const renderGuards = interpretation => {
    const guards = byId('r85-guards')
    guards.replaceChildren()
    const labels = [
      `Harmonisasi hukum: ${explainStatus(interpretation.legal_harmonization)}`,
      `Keselarasan substansi: ${explainStatus(interpretation.substantive_alignment)}`,
      `Perbandingan angka: ${explainStatus(interpretation.numeric_comparison)}`,
      `Keputusan pemeriksa: ${explainStatus(interpretation.review_decision)}`,
      `Penggunaan resmi: ${explainStatus(interpretation.production_admission)}`,
    ]
    for (const label of labels) add(guards, 'span', '', label)
  }
  async function load(filters = {}) {
    try {
      const query = new URLSearchParams(filters)
      const response = await fetch(`/api/qa-superior-r85/development-case${query.size ? `?${query}` : ''}`, {credentials:'same-origin', cache:'no-store'})
      if (!response.ok) throw new Error(response.status === 401 ? 'Sesi login diperlukan' : `Endpoint menolak permintaan (${response.status})`)
      const data = await response.json()
      const caseData = assertData(data)
      byId('r85-case-label').textContent = caseData.label
      byId('r85-case-summary').textContent = caseData.summary
      byId('r85-case-status').textContent = `● ${explainStatus(caseData.case_status)}`
      byId('r85-case-status').title = 'Status sistem: '+caseData.case_status
      renderFilters(data)
      renderQuestions(caseData)
      renderGraphs(caseData)
      renderEvidence(caseData)
      renderGuards(data.interpretation)
    } catch (error) {
      fail(`Tidak dapat menampilkan kasus: ${String(error.message || error)}.`)
    }
  }
  document.addEventListener('DOMContentLoaded', () => {
    byId('r85-filters').addEventListener('submit', event => {
      event.preventDefault()
      load({
        jurisdiction: byId('r85-jurisdiction').value,
        year: byId('r85-year').value,
        topic: byId('r85-topic').value,
        case: byId('r85-case').value,
      })
    })
    load()
  })
})()
