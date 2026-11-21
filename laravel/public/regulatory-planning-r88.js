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
  const fail = message => {
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
