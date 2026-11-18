(() => {
  'use strict'

  const state = {data: null}
  const byId = id => document.getElementById(id)
  const add = (parent, tag, className, text) => {
    const element = document.createElement(tag)
    if (className) element.className = className
    if (text != null) element.textContent = String(text)
    parent.appendChild(element)
    return element
  }
  const allowedHosts = new Set([
    'peraturan.bpk.go.id', 'peraturan.go.id', 'www.peraturan.go.id',
    'jdih.bpk.go.id', 'jdih.jogjakota.go.id', 'jdih.kemendagri.go.id'
  ])
  const safeUrl = value => {
    try {
      const url = new URL(value)
      return url.protocol === 'https:' && allowedHosts.has(url.hostname)
        && !url.username && !url.password && !url.port ? url.href : null
    } catch (_) {
      return null
    }
  }
  const statusLabel = value => String(value).replaceAll('_', ' ')
  const assertData = data => {
    if (data.schema !== 'PI_R86_REGULATORY_DEVELOPMENT_LIFECYCLE_V1'
      || data.tenant_id !== 'ID-DIY-YOG'
      || data.catalogue?.entry_count !== 38
      || data.catalogue?.admission !== 'CANDIDATE_CATALOGUE_NOT_LEGAL_VALIDATION'
      || data.result_count !== 1
      || data.case?.id !== 'YOG-2026-PERSAMPAHAN'
      || data.case?.r85_contract?.question_ids?.length !== 5
      || data.case?.r85_contract?.graph_ids?.length !== 4
      || data.case?.lifecycle?.stage_count !== 10
      || data.case?.specialized_views?.length !== 5
      || data.interpretation?.legal_harmonization !== 'NOT_ASSESSED'
      || data.interpretation?.numeric_comparison !== 'BLOCKED'
      || data.interpretation?.review_decision !== 'NO_ACTIVE_DECISION'
      || data.interpretation?.production_admission !== 'DENIED') throw new Error('Kontrak R86 atau gate keamanan tidak cocok')
    return data
  }
  const fail = message => {
    state.data = null
    const status = byId('r86-status')
    status.className = 'r86-status error'
    status.textContent = `ABSTAIN · ${message}`
    for (const id of ['r86-metrics', 'r86-temporal', 'r86-stages', 'r86-apps', 'r86-sources', 'r86-brief']) byId(id).replaceChildren()
    byId('r86-export-json').disabled = true
    byId('r86-export-md').disabled = true
  }
  const fillStages = stages => {
    const select = byId('r86-stage')
    if (select.options.length > 1) return
    const labels = {
      authority:'Mandat dan kewenangan', planning:'Perencanaan', budgeting:'Penganggaran',
      execution_treasury:'Pelaksanaan/perbendaharaan', monitoring:'Monitoring', evaluation:'Evaluasi',
      internal_oversight:'Pengawasan intern/APIP', external_audit:'Pemeriksaan eksternal/BPK',
      follow_up:'Tindak lanjut', feedback_next_planning:'Perbaikan perencanaan'
    }
    for (const value of stages) {
      const option = add(select, 'option', '', labels[value] || statusLabel(value))
      option.value = value
    }
  }
  const renderMetrics = data => {
    const metrics = byId('r86-metrics')
    metrics.replaceChildren()
    const values = [
      [data.catalogue.entry_count, 'kandidat regulasi dalam katalog'],
      [data.case.lifecycle.stage_count, 'tahap siklus pemerintahan'],
      [data.case.evidence.filter(item => item.verification_status === 'VERIFIED_SOURCE_BYTES').length, 'sumber dengan byte terpin'],
      [data.case.lifecycle.stages.filter(item => item.evidence_status === 'EVIDENCE_NOT_AVAILABLE').length, 'tahap tanpa bukti kasus'],
    ]
    for (const [value, label] of values) {
      const card = add(metrics, 'article', 'r86-metric')
      add(card, 'strong', '', value)
      add(card, 'span', '', label)
    }
  }
  const renderTemporal = data => {
    const wanted = new Set(['REG-PMD10-2025', 'REG-PMD14-2026', 'REG-PMD14-2025', 'REG-PMD17-2026', 'REG-PBPK1-2017', 'REG-PBPK1-2026'])
    const container = byId('r86-temporal')
    container.replaceChildren()
    for (const item of data.case.temporal_rules.filter(row => wanted.has(row.regulation_version_id))) {
      const kind = item.status === 'CURRENTLY_APPLICABLE' ? 'current'
        : item.status === 'NOT_YET_EFFECTIVE' ? 'pending' : 'out'
      const card = add(container, 'article', `r86-time-card ${kind}`)
      add(card, 'span', 'r86-chip', statusLabel(item.status))
      add(card, 'h4', '', item.title)
      add(card, 'p', '', `Efektif: ${item.effective_from || 'belum terverifikasi'} · acuan ${item.as_of}`)
      add(card, 'p', '', statusLabel(item.reason))
    }
  }
  const renderStages = data => {
    const container = byId('r86-stages')
    container.replaceChildren()
    for (const item of data.case.lifecycle.stages) {
      const card = add(container, 'article', 'r86-stage')
      const head = add(card, 'div', 'r86-stage-head')
      add(head, 'h4', '', item.label)
      add(head, 'span', item.evidence_status === 'OBSERVED_SOURCE_LOCATED' ? 'r86-chip r86-observed' : 'r86-chip r86-gap', statusLabel(item.evidence_status))
      add(card, 'p', '', item.actor)
      add(card, 'small', '', `Artefak diwajibkan/dirujuk: ${item.required_artifacts.join(' · ')}`)
      add(card, 'p', '', item.observed_artifacts.length ? `Terobservasi: ${item.observed_artifacts.join(' · ')}` : 'Terobservasi: belum ada artefak kasus')
      const gaps = add(card, 'ul')
      for (const gap of item.evidence_gaps) add(gaps, 'li', '', gap)
    }
  }
  const renderApps = data => {
    const container = byId('r86-apps')
    container.replaceChildren()
    for (const view of data.case.specialized_views) {
      const card = add(container, 'article', 'r86-app')
      add(card, 'strong', '', view.app)
      add(card, 'span', '', view.focus)
      add(card, 'span', '', `${view.stage_ids.length} tahap dari registry bersama`)
    }
  }
  const renderSources = data => {
    const container = byId('r86-sources')
    container.replaceChildren()
    for (const item of data.case.evidence) {
      const href = safeUrl(item.source_url)
      if (!href) throw new Error('Origin sumber bukti ditolak')
      const card = add(container, 'article', 'r86-source')
      const identity = add(card, 'div')
      add(identity, 'strong', '', item.source_id)
      add(identity, 'p', '', statusLabel(item.verification_status))
      const proof = add(card, 'div')
      const link = add(proof, 'a', '', 'Buka sumber resmi ↗')
      link.href = href
      link.target = '_blank'
      link.rel = 'noopener noreferrer'
      add(proof, 'p', '', item.locator)
      add(card, 'code', '', item.source_sha256 ? `SHA-256 ${item.source_sha256}` : 'SHA-256 belum tersedia')
    }
  }
  const renderBrief = data => {
    const container = byId('r86-brief')
    container.replaceChildren()
    const brief = data.case.executive_review_brief
    const sections = [
      ['Yang diketahui', brief.known], ['Dependensi', brief.dependencies],
      ['Gap dan ketidakpastian', brief.gaps], ['Langkah pemeriksaan berikutnya', brief.next_checks]
    ]
    for (const [title, values] of sections) {
      const section = add(container, 'section')
      add(section, 'h4', '', title)
      const list = add(section, 'ul')
      for (const value of values) add(list, 'li', '', value)
    }
  }
  const markdown = data => {
    const lines = [
      '# PI R86 - Executive Review Brief', '',
      `- Case: ${data.case.id}`,
      `- As of: ${data.filters.selected.as_of}`,
      `- Regulatory catalogue: ${data.catalogue.entry_count} candidates (not legal validation)`,
      `- Legal harmonization: ${data.interpretation.legal_harmonization}`,
      `- Numeric comparison: ${data.interpretation.numeric_comparison}`,
      `- Review decision: ${data.interpretation.review_decision}`,
      `- Production admission: ${data.interpretation.production_admission}`, '',
      '## Lifecycle evidence'
    ]
    for (const stage of data.case.lifecycle.stages) {
      lines.push('', `### ${stage.label}`, `- Status: ${stage.evidence_status}`, `- Actor: ${stage.actor}`)
      for (const gap of stage.evidence_gaps) lines.push(`- Gap: ${gap}`)
    }
    const brief = data.case.executive_review_brief
    for (const [title, values] of [['Known', brief.known], ['Dependencies', brief.dependencies], ['Gaps', brief.gaps], ['Next checks', brief.next_checks]]) {
      lines.push('', `## ${title}`)
      for (const value of values) lines.push(`- ${value}`)
    }
    return `${lines.join('\n')}\n`
  }
  const download = (contents, type, filename) => {
    const url = URL.createObjectURL(new Blob([contents], {type}))
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = filename
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    URL.revokeObjectURL(url)
  }
  async function load() {
    try {
      const params = new URLSearchParams({
        case:'YOG-2026-PERSAMPAHAN', jurisdiction:'ID-DIY-YOG', year:'2026',
        as_of: byId('r86-as-of').value || '2026-11-18'
      })
      if (byId('r86-stage').value) params.set('stage', byId('r86-stage').value)
      const response = await fetch(`/api/qa-superior-r86/regulatory-lifecycle?${params}`, {credentials:'same-origin', cache:'no-store', headers:{Accept:'application/json'}})
      if (!response.ok) throw new Error(response.status === 401 ? 'Sesi login diperlukan' : `Endpoint R86 menolak permintaan (${response.status})`)
      const data = assertData(await response.json())
      state.data = data
      fillStages(data.filters.available.stages)
      renderMetrics(data)
      renderTemporal(data)
      renderStages(data)
      renderApps(data)
      renderSources(data)
      renderBrief(data)
      const status = byId('r86-status')
      status.className = 'r86-status'
      status.textContent = `Registry R86 siap · ${data.case.lifecycle.returned_stage_count}/${data.case.lifecycle.stage_count} tahap ditampilkan · keputusan tetap pada manusia.`
      byId('r86-export-json').disabled = false
      byId('r86-export-md').disabled = false
    } catch (error) {
      fail(String(error.message || error))
    }
  }
  document.addEventListener('DOMContentLoaded', () => {
    byId('r86-controls').addEventListener('submit', event => { event.preventDefault(); load() })
    byId('r86-export-json').addEventListener('click', () => {
      if (!state.data) return
      download(`${JSON.stringify(state.data, null, 2)}\n`, 'application/json;charset=utf-8', 'PI_R86_YOG_2026_REGULATORY_LIFECYCLE_NOT_APPROVED.json')
    })
    byId('r86-export-md').addEventListener('click', () => {
      if (!state.data) return
      download(markdown(state.data), 'text/markdown;charset=utf-8', 'PI_R86_YOG_2026_EXECUTIVE_REVIEW_NOT_APPROVED.md')
    })
    load()
  })
})()
