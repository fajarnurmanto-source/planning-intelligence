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
  let semanticPreview = null
  const resetSemantic = message => {
    semanticPreview = null
    get('r89-run').disabled = true
    get('r89-consent').checked = false
    get('r89-consent').disabled = true
    get('r89-data').hidden = true
    get('r89-data').textContent = ''
    get('r89-results').replaceChildren()
    get('r89-status').textContent = message
  }
  const fail = message => {
    resetSemantic('Crosswalk belum siap; tidak ada panggilan AI.')
    get('r89-preview').disabled = true
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
      resetSemantic('Crosswalk dimuat. Periksa pratinjau sebelum menyetujui panggilan cloud.')
      get('r89-preview').disabled = next.result_count !== 1
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
  const sameFilters = () => {
    if (!data) return false
    const current = new URLSearchParams(new FormData(get('r88-controls')))
    return current.get('year') === String(data.filters.year)
      && current.get('as_of') === data.filters.as_of
      && current.get('code') === data.filters.code
  }
  get('r88-controls').addEventListener('change', () => {
    resetSemantic('Filter berubah; telusuri ulang crosswalk sebelum pratinjau cloud.')
    get('r89-preview').disabled = true
  })
  get('r89-provider').addEventListener('change', () => {
    resetSemantic('Provider berubah; periksa ulang data yang akan dikirim.')
    get('r89-preview').disabled = !data || data.result_count !== 1 || !sameFilters()
  })
  approved.addEventListener('change', () => {
    get('r89-run').disabled = !approved.checked || !semanticPreview || !sameFilters()
    if (!approved.checked) get('r89-status').textContent = 'Persetujuan belum diberikan; tidak ada panggilan AI.'
  })
  get('r89-preview').addEventListener('click', async () => {
    if (!data || data.result_count !== 1 || !sameFilters()) return
    resetSemantic('Memverifikasi bukti untuk pratinjau…')
    const query = new URLSearchParams(data.filters)
    try {
      const response = await fetch('/api/qa-superior-r89/semantic-preview?' + query, {
        credentials:'same-origin', cache:'no-store', headers:{Accept:'application/json'}
      })
      const payload = await response.json()
      if (!response.ok) throw new Error(payload.code || 'HTTP ' + response.status)
      if (payload.schema !== 'PI_R89_EVIDENCE_BOUND_SEMANTIC_ANALYSIS_V1'
        || payload.tenant_id !== data.tenant_id
        || payload.source_registry_sha256 !== data.source_registry_sha256
        || payload.definitions_registry_sha256 !== data.definitions_registry_sha256
        || payload.semantic_reasoning?.gates?.production_admission !== 'DENIED'
        || payload.preview?.status !== 'READY_FOR_EXPLICIT_CLOUD_APPROVAL'
        || !/^[A-F0-9]{64}$/.test(payload.preview.sha256 || '')
        || !payload.preview.context) {
        throw new Error('Pratinjau atau kontrak bukti tidak valid; ABSTAIN')
      }
      semanticPreview = payload
      get('r89-data').textContent = JSON.stringify(payload.preview.context,null,2)
      get('r89-data').hidden = false
      approved.disabled = false
      get('r89-status').textContent = 'Pratinjau siap. Pengiriman hanya dilakukan setelah persetujuan Anda.'
    } catch(error) { resetSemantic('ABSTAIN · ' + error.message) }
  })
  get('r89-run').addEventListener('click', async () => {
    if (!approved.checked || !semanticPreview || !data || !sameFilters()) return
    get('r89-run').disabled = true
    get('r89-results').replaceChildren()
    get('r89-status').textContent = 'Memeriksa konfigurasi dan memanggil provider pilihan…'
    try {
      const sessionResponse = await fetch('/api/auth/session',{credentials:'same-origin',cache:'no-store'})
      if (!sessionResponse.ok) throw new Error('Sesi login berakhir')
      const session = await sessionResponse.json()
      if (typeof session.csrf !== 'string' || !session.csrf) throw new Error('Token CSRF tidak tersedia')
      const response = await fetch('/api/qa-superior-r89/semantic-candidates', {
        method:'POST', credentials:'same-origin', cache:'no-store',
        headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':session.csrf},
        body:JSON.stringify({
          provider:get('r89-provider').value, consent:true,
          approved_cloud_metadata:true, approved_provider_charges:true,
          approved_context_sha256:semanticPreview.preview.sha256,
          year:data.filters.year,as_of:data.filters.as_of,code:data.filters.code
        })
      })
      const payload = await response.json()
      if (!response.ok) throw new Error(payload.semantic_reasoning?.status || payload.code || 'HTTP ' + response.status)
      const gate = payload.semantic_reasoning?.gates
      if (payload.schema !== 'PI_R89_EVIDENCE_BOUND_SEMANTIC_ANALYSIS_V1'
        || payload.tenant_id !== data.tenant_id
        || payload.context_sha256 !== semanticPreview.preview.sha256
        || payload.source_registry_sha256 !== data.source_registry_sha256
        || payload.definitions_registry_sha256 !== data.definitions_registry_sha256
        || gate?.equivalence_asserted !== false
        || gate?.relation_promotion !== 'NOT_PERFORMED'
        || gate?.numeric_comparison !== 'BLOCKED'
        || gate?.production_admission !== 'DENIED'
        || !Array.isArray(payload.semantic_reasoning?.candidates)) {
        throw new Error('Hasil tidak cocok dengan bukti atau pengaman hukum')
      }
      const reasoning = payload.semantic_reasoning
      get('r89-status').textContent = reasoning.status + ' · ' + reasoning.provider_execution + ' · Legal ' + reasoning.legal_validation_status
      if (reasoning.candidates.length > 0) {
        const card = add(get('r89-results'),'article','')
        const candidate = reasoning.candidates[0]
        add(card,'h3','Usulan semantik: ' + candidate.semantic_relation)
        add(card,'p',candidate.explanation)
        add(card,'p','Model: ' + payload.provider + ' · ' + payload.model)
        add(card,'p','Keyakinan model: ' + candidate.semantic_confidence + ' · Bukti: ' + candidate.evidence_confidence)
        add(card,'p','Jalur bukti: ' + candidate.supporting_relation_ids.join(' → '))
        add(card,'p','Tidak ada keputusan hukum, kesetaraan indikator, atau anggaran yang disahkan.')
      }
      data.semantic_reasoning = reasoning
      data.semantic_analysis = {
        schema:payload.schema,provider:payload.provider,model:payload.model,
        context_sha256:payload.context_sha256,context_class:payload.context_class,
        source_rights:payload.source_rights
      }
    } catch(error) {
      get('r89-results').replaceChildren()
      get('r89-status').textContent = 'ABSTAIN · ' + error.message
    } finally {
      get('r89-run').disabled = !approved.checked || !semanticPreview || !sameFilters()
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
