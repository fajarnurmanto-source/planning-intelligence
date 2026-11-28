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
  const explainStatus=code=>window.PIGlossary?.status(code)||'Perlu pemeriksaan lanjutan'
  const technical=(parent,code,refs)=> {
    const details=add(parent,'details','r85-technical')
    add(details,'summary','','Lihat jejak bukti teknis')
    add(details,'code','r85-edge-ref',refs||'Identitas bukti tidak tersedia')
    add(details,'code','r85-edge-ref','Status sistem: '+code)
  }
  const fail = message => {
    byId('pi-performance-evidence')?.replaceChildren(add(document.createDocumentFragment(),'p','','Bukti kasus belum tersedia. Belum dapat disimpulkan.'))
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
        add(fromBox, 'small', '', window.PIGlossary?.node(from.type)||'Jenis bukti perlu diperiksa')
        add(fromBox, 'span', '', from.label)
        add(row, 'span', 'r85-arrow', '→')
        const toBox = add(row, 'div', 'r85-node')
        add(toBox, 'small', '', window.PIGlossary?.node(to.type)||'Jenis bukti perlu diperiksa')
        add(toBox, 'span', '', to.label)
        add(row, 'p', 'r85-relation', (window.PIGlossary?.relation(edge.relation)||'Hubungan perlu diperiksa')+' · '+explainStatus(edge.status))
        technical(row,edge.status,`${id} · ${edge.relation} · ${from.type} → ${to.type}`)
      }
    }
  }
  const renderEvidence = caseData => {
    const container = byId('r85-evidence')
    container.replaceChildren()
    const nodeLabels=new Map(caseData.nodes.map(node=>[node.id,node.label]))
    for (const edge of caseData.edges) {
      const source = edge.provenance
      const item = add(container, 'article', 'r85-evidence-item')
      add(item, 'code', '', 'Bukti '+edge.id)
      const relation = add(item, 'div')
      add(relation, 'strong', '', (nodeLabels.get(edge.from)||edge.from)+' → '+(nodeLabels.get(edge.to)||edge.to))
      add(relation, 'p', '', 'Hubungan kandidat untuk diperiksa; bukan kesimpulan otomatis.')
      const proof = add(item, 'div')
      const href = allowedSource(source.source_url)
      if (!href) throw new Error('Origin sumber tidak diizinkan')
      const anchor = add(proof, 'a', '', 'Buka dokumen sumber')
      anchor.href = href
      if (href !== './overview-r69c.json') {
        anchor.target = '_blank'
        anchor.rel = 'noopener noreferrer'
      }
      const technicalSource=add(proof,'details','r85-technical')
      add(technicalSource,'summary','','Lihat identitas sumber')
      add(technicalSource,'p','',source.locator)
      add(technicalSource,'code','r85-edge-ref',edge.id+' · '+edge.relation+' · '+edge.from+' → '+edge.to)
      add(technicalSource,'code','r85-edge-ref',source.source_id+' · '+source.version+' · '+source.as_of_date)
      add(technicalSource,'span','r85-hash',source.source_sha256 ? 'SHA-256 '+source.source_sha256 : 'SHA-256 belum tersedia')
      const status = add(item, 'div', 'r85-verification')
      const sourceBadge=add(status, 'span', '', explainStatus(source.verification_status))
      sourceBadge.title='Status sumber: '+source.verification_status
      add(status, 'span', source.uncertainty === 'HIGH' ? 'high' : '', `Ketidakpastian ${explainStatus(source.uncertainty)}`)
      add(status, 'small', '', source.as_of_date)
    }
    const cards=new Map(caseData.edges.map((edge,index)=>[edge.id,container.children[index]]))
    const used=new Set()
    container.replaceChildren()
    const grouped=[]
    for(const graph of caseData.graphs){
      const ids=graph.edge_ids.filter(id=>cards.has(id)&&!used.has(id))
      ids.forEach(id=>used.add(id))
      if(ids.length)grouped.push({title:graph.label,ids})
    }
    const remaining=caseData.edges.map(edge=>edge.id).filter(id=>!used.has(id))
    if(remaining.length)grouped.push({title:'Bukti lain untuk diperiksa',ids:remaining})
    add(container,'p','r85-evidence-summary',caseData.edges.length+' hubungan bukti dalam '+grouped.length+' kelompok. Pilih kelompok untuk melihat sumber dan status pemeriksaannya.')
    for(const group of grouped){
      const details=add(container,'details','r85-evidence-group')
      add(details,'summary','r85-evidence-group-heading',group.title+' · '+group.ids.length+' hubungan')
      const rows=add(details,'div','r85-evidence-rows')
      group.ids.forEach(id=>rows.append(cards.get(id)))
    }
  }

  // R108 W3C: read-only presentation of the already authenticated R85 graph.
  // No numeric inference, causal claim, new source, or SCP-01 engine admission.
  const renderPerformance = caseData => {
    const root=byId('pi-performance-evidence')
    if(!root)return
    root.replaceChildren()
    const nodes=new Map(caseData.nodes.map(n=>[n.id,n]))
    const edges=new Map(caseData.edges.map(e=>[e.id,e]))
    const lanes=[
      ['Arah kebijakan dan sasaran',['E06','E08','E09']],
      ['Program dan subkegiatan',['E10','E11']],
      ['Indikator keluaran',['E12']]
    ]
    for(const [label,ids] of lanes){
      const card=add(root,'article','pi-performance-card')
      add(card,'h4','',label)
      for(const id of ids){
        const e=edges.get(id)
        const from=nodes.get(e?.from),to=nodes.get(e?.to)
        if(!e||!from||!to)continue
        add(card,'p','',from.label+' → '+to.label)
        add(card,'small','',explainStatus(e.provenance.verification_status)+' · Hubungan perlu telaah')
        const audit=add(card,'details','r85-technical')
        add(audit,'summary','','Sumber dan status hubungan')
        add(audit,'p','',e.provenance.locator+' · '+e.provenance.version)
        add(audit,'code','r85-edge-ref',e.id+' · '+e.status+' · '+e.provenance.as_of_date)
        const href=allowedSource(e.provenance.source_url)
        if(href && href!=='./overview-r69c.json'){
          const link=add(audit,'a','','Buka sumber resmi')
          link.href=href;link.target='_blank';link.rel='noopener noreferrer'
        }
      }
      if(!card.querySelector('details'))add(card,'p','','Belum tersedia bukti hubungan.')
    }
    const gaps=add(root,'article','pi-performance-card')
    add(gaps,'h4','','Nilai dan hasil yang belum dapat dinilai')
    for(const name of ['Sasaran substantif','Baseline','Target dan satuan','Realisasi','Outcome dan dampak']){
      add(gaps,'p','',name+': belum tersedia bukti nilai pada kasus ini.')
    }
    add(gaps,'small','','Keluaran tidak membuktikan outcome. Anggaran bukan realisasi kinerja.')
    const link=add(gaps,'a','','Periksa dokumen dan bukti')
    link.href='#pi-view=dokumen'
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
      renderPerformance(caseData)
    } catch (error) {
      fail(error?.message==='Sesi login diperlukan'?'Sesi masuk diperlukan. Masuk kembali untuk melanjutkan.':'Kasus belum dapat ditampilkan. Periksa akses dan ketersediaan sumber, lalu pilih Muat kasus. Belum dapat menyimpulkan hasil.')
    }
  }
  document.addEventListener('DOMContentLoaded', () => {
    byId('r85-filters').addEventListener('submit', event => {
      event.preventDefault()
      byId('pi-performance-evidence')?.replaceChildren(add(document.createDocumentFragment(),'p','','Memuat kembali bukti kasus…'))
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
