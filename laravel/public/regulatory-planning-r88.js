(() => {
  'use strict'
  const get = id => document.getElementById(id)
  const add = (parent, tag, text) => {
    const el = document.createElement(tag)
    el.textContent = String(text ?? '')
    parent.appendChild(el)
    return el
  }
  let data = null
  const resetSemantic = message => {
    get('r89-run').disabled = true
    get('r89-results').replaceChildren()
    get('r89-status').textContent = message
  }
  const fail = message => {
    resetSemantic('Crosswalk belum siap; analisis model lokal tidak dijalankan.')
    data = null
    get('r88-export').disabled = true
    get('r88-results').replaceChildren()
    get('r88-status').textContent = `ABSTAIN · ${message}`
  }
  get('r88-controls').addEventListener('submit', async event => {
    event.preventDefault()
    fail('Sedang memeriksa bukti…')
    const query = new URLSearchParams(new FormData(event.currentTarget))
    try {
      const response = await fetch(`/api/qa-superior-r88/planning-crosswalk?${query}`, {credentials:'same-origin',cache:'no-store'})
      if (!response.ok) throw new Error(`Permintaan ditolak (${response.status})`)
      const next = await response.json()
      const gates = next.interpretation
      if (next.schema !== 'PI_R88_REGULATORY_PLANNING_CROSSWALK_V1'
        || next.tenant_id !== 'ID-DIY-YOG'
        || gates?.legal_harmonization !== 'NOT_ASSESSED'
        || gates?.numeric_comparison !== 'BLOCKED'
        || gates?.review_decision !== 'NO_ACTIVE_DECISION'
        || gates?.production_admission !== 'DENIED'
        || !Array.isArray(next.relations) || !Array.isArray(next.gaps)) throw new Error('Kontrak atau gate tidak cocok')
      data = next
      resetSemantic('Crosswalk dimuat. Model lokal tetap opt-in dan default nonaktif.')
      get('r89-run').disabled = !get('r89-consent').checked || next.result_count !== 1
      get('r88-export').disabled = false
      get('r88-status').textContent = `${next.result_count ? 'Jejak tersedia untuk review' : 'ABSTAIN · Tidak ada jejak sesuai filter'} · ${next.binding.status} · ${next.continuity.status}`
      const root = get('r88-results')
      for (const item of next.hierarchy) {
        const card = add(root, 'article', '')
        add(card, 'h3', `${item.level} · ${item.code}`)
        add(card, 'p', item.label)
        add(card, 'p', `${item.version} · ${item.locator} · ${item.status}`)
      }
      for (const item of next.operational_definitions) {
        const card = add(root, 'article', '')
        add(card, 'h3', 'Definisi operasional · hubungan kandidat')
        add(card, 'p', `${item.preferred_term} ↔ ${item.observed_term}`)
        add(card, 'p', `${item.relation} · ${item.reason}`)
      }
      const nodes = new Map(next.nodes.map(node => [node.id,node.label]))
      for (const edge of next.relations) {
        const card = add(root, 'article', '')
        add(card, 'h3', `${edge.id} · ${nodes.get(edge.from)} → ${nodes.get(edge.to)}`)
        add(card, 'p', `${edge.relation} · ${edge.status} · Ketidakpastian ${edge.relation_uncertainty}`)
        const source = edge.provenance
        add(card, 'p', `${source.locator} · ${source.version} · ${source.as_of_date} · ${source.verification_status}`)
        add(card, 'p', source.source_sha256 ? `SHA-256 ${source.source_sha256}` : 'Hash sumber belum tersedia')
        const url = new URL(source.source_url, location.href)
        const hosts = ['peraturan.bpk.go.id','peraturan.go.id','www.peraturan.go.id','jdih.jogjakota.go.id']
        if ((url.protocol === 'https:' && hosts.includes(url.hostname) && !url.username && !url.password && !url.port)
          || (source.source_url === './overview-r69c.json')) {
          const link = add(card, 'a', 'Periksa sumber ↗')
          link.href = url.href
          link.target = '_blank'
          link.rel = 'noopener noreferrer'
        }
      }
      for (const gap of next.gaps) {
        const card = add(root, 'article', '')
        add(card, 'h3', `${gap.status} · ${gap.id}`)
        add(card, 'p', gap.detail)
      }
    } catch (error) { fail(error.message) }
  })
  const approved = get('r89-consent')
  approved.addEventListener('change', () => {
    get('r89-run').disabled = !approved.checked || !data || data.result_count !== 1
    if (!approved.checked) resetSemantic('Persetujuan dimatikan. Tidak ada pemanggilan AI.')
  })
  get('r89-run').addEventListener('click', async () => {
    if (!approved.checked || !data || data.result_count !== 1) return
    const current = new URLSearchParams(new FormData(get('r88-controls')))
    if (current.get('year') !== String(data.filters.year) ||
      current.get('as_of') !== data.filters.as_of || current.get('code') !== data.filters.code) {
      resetSemantic('Filter berubah. Telusuri ulang crosswalk sebelum analisis AI.')
      return
    }
    get('r89-run').disabled = true
    get('r89-results').replaceChildren()
    get('r89-status').textContent = 'Memeriksa bukti dan konfigurasi model lokal…'
    try {
      const sessionResponse = await fetch('/api/auth/session', {credentials:'same-origin', cache:'no-store'})
      if (!sessionResponse.ok) throw new Error('Sesi login sudah berakhir')
      const session = await sessionResponse.json()
      if (!session.csrf) throw new Error('Token CSRF sesi tidak tersedia')
      const response = await fetch('/api/qa-superior-r89/semantic-candidates', {
        method:'POST', credentials:'same-origin', cache:'no-store',
        headers:{'Content-Type':'application/json','Accept':'application/json','X-PI-CSRF':session.csrf},
        body:JSON.stringify({consent:true, year:data.filters.year, as_of:data.filters.as_of, code:data.filters.code})
      })
      const output = await response.json()
      if (!response.ok) throw new Error(output.code || 'HTTP ' + response.status)
      const gate = output.semantic_reasoning?.gates
      if (output.schema !== 'PI_R89_EVIDENCE_BOUND_SEMANTIC_ANALYSIS_V1'
        || output.tenant_id !== data.tenant_id
        || output.source_registry_sha256 !== data.source_registry_sha256
        || output.definitions_registry_sha256 !== data.definitions_registry_sha256
        || gate?.equivalence_asserted !== false
        || gate?.relation_promotion !== 'NOT_PERFORMED'
        || gate?.numeric_comparison !== 'BLOCKED'
        || gate?.production_admission !== 'DENIED'
        || !Array.isArray(output.semantic_reasoning.candidates)) {
        throw new Error('Kontrak bukti berubah atau legal gate tidak cocok')
      }
      const reasoning = output.semantic_reasoning
      get('r89-status').textContent = reasoning.status + ' · ' + reasoning.provider_execution + ' · Legal ' + reasoning.legal_validation_status
      if (reasoning.candidates.length > 0) {
        const card = add(get('r89-results'),'article','')
        const candidate = reasoning.candidates[0]
        add(card,'h3','Hipotesis semantik: ' + candidate.semantic_relation)
        add(card,'p',candidate.explanation)
        add(card,'p','Keyakinan model: ' + candidate.semantic_confidence + ' · Bukti: ' + candidate.evidence_confidence)
        add(card,'p','Jalur bukti: ' + candidate.supporting_relation_ids.join(' → ') + ' · Status: ' + candidate.review_status)
        add(card,'p','Tidak ada kesetaraan, hukum, angka, atau keputusan reviewer yang dipromosikan.')
      }
      // Preserve the full canonical R88 evidence and append only the validated
      // semantic envelope to the user's optional JSON export (no persistence).
      data.semantic_reasoning = reasoning
      data.semantic_analysis = {
        schema:output.schema, context_class:output.context_class,
        context_sha256:output.context_sha256, source_rights:output.source_rights
      }
    } catch (error) {
      resetSemantic('ABSTAIN · ' + error.message)
    } finally {
      get('r89-run').disabled = !approved.checked || !data || data.result_count !== 1
    }
  })
  get('r88-export').addEventListener('click', () => {
    if (!data) return
    const url = URL.createObjectURL(new Blob([JSON.stringify(data,null,2)],{type:'application/json;charset=utf-8'}))
    const link = document.createElement('a')
    link.href = url
    link.download = 'PI-R88-crosswalk-review-not-approved.json'
    link.click()
    setTimeout(() => URL.revokeObjectURL(url),1000)
  })
})()
