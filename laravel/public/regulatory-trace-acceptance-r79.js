/* PI R79 QA: same-origin, opt-in browser acceptance. No mutations/exports. */
(() => {
  'use strict'

  const has = (data, id) => Array.isArray(data?.nodes) && data.nodes.some(item => item.id === id)
  const matches = (a, b) => typeof a === 'string' && typeof b === 'string' &&
    /^[a-f0-9]{64}$/i.test(a) && a.toUpperCase() === b.toUpperCase()

  // Pure evaluator: can be tested offline with synthetic fixtures in Node 22.
  function evaluate(trace, catalog, parents, children, thematic) {
    const checks = []
    const check = (label, ok) => checks.push({label, pass: Boolean(ok)})
    const local = Array.isArray(trace?.nodes)
      ? trace.nodes.filter(item => item.node_type === 'LOCAL_SOURCE_SNAPSHOT')
      : []
    const sources = Array.isArray(catalog?.sources) ? catalog.sources : []
    const localById = new Map(local.map(item => [item.id, item]))
    const ids = new Set(sources.map(item => item.id))
    const timeline = Array.isArray(trace?.amendment_timeline) ? trace.amendment_timeline : []
    const relations = Array.isArray(trace?.relationships) ? trace.relationships : []

    check('R79: format, tenant, dan pengaman QA', trace?.schema === 'PI_R70A_REGULATORY_LEGAL_TRACE_QA_V1' &&
      trace?.tenant_id === 'ID-DIY-YOG' &&
      trace?.decision === 'ABSTAIN' &&
      trace?.legacy_feature_parity === 'PARTIAL_NOT_EQUIVALENT' &&
      trace?.legal_harmonization === 'NOT_ASSESSED' &&
      trace?.numeric_comparison === 'BLOCKED' &&
      trace?.production_admission === 'DENIED')

    check('R78: tujuh sumber dan tujuh snapshot valid', catalog?.schema === 'PI_R70B_OFFICIAL_SOURCE_SNAPSHOTS_QA_V1' &&
      catalog?.tenant_id === trace?.tenant_id &&
      catalog?.total === 7 && catalog?.verified === 7 &&
      catalog?.legal_harmonization === 'NOT_ASSESSED' &&
      catalog?.production_admission === 'DENIED' &&
      sources.length === 7 && ids.size === 7 &&
      sources.every(item => item.has_local_pdf === true && item.identity === 'PDF_SHA256_MATCHES_LEDGER'))

    check('R79 ↔ R78: identitas dan SHA-256 seluruh sumber cocok',
      local.length === 7 && localById.size === 7 && sources.length === 7 &&
      sources.every(item => matches(item.sha256, localById.get(item.id)?.pdf_sha256)))

    check('R79: lima perubahan terurut, bukan rekonsiliasi', timeline.length === 5 &&
      timeline.map(item => item.sequence).join(',') === '1,2,3,4,5' &&
      timeline.every(item => item.status === 'ORDER_ONLY_NOT_VERSION_RECONCILED') &&
      trace?.version_reconciliation === 'NOT_PERFORMED')

    check('R79: relasi bertanda sumber tanpa putusan hukum',
      relations.length === 5 &&
      relations.some(item => item.source === 'PP-81-2012' &&
        item.target === 'UU-18-2008:PASAL-16' &&
        item.kind === 'IMPLEMENTS_PROVISION_OF') &&
      relations.every(item =>
        typeof item.evidence === 'string' && item.evidence.length > 0 &&
        typeof item.href === 'string' && item.href.startsWith('https://') &&
        !['CERTIFIED_HARMONIZATION','LEGALLY_VALIDATED'].includes(item.status)))

    const safeGraph = data => data?.schema === 'PI_R74_BIDIRECTIONAL_GENEALOGY_TRACE_V1' &&
      data?.tenant_id === 'ID-DIY-YOG' && data?.legal_harmonization === 'NOT_ASSESSED' &&
      data?.production_admission === 'DENIED' &&
      data?.result_status === 'PARTIAL_EVIDENCE_GRAPH_NOT_LEGAL_FINDING'

    check('R74: PP 81/2012 menuju Pasal 16 dan UU 18/2008',
      safeGraph(parents) && has(parents, 'PP-81-2012') &&
      has(parents, 'UU-18-2008:PASAL-16') && has(parents, 'UU-18-2008'))

    check('R74: UU 18/2008 menemukan turunannya PP 81/2012',
      safeGraph(children) && has(children, 'UU-18-2008') && has(children, 'PP-81-2012'))

    check('R74: tema Persampahan menemukan UU dan PP',
      safeGraph(thematic) && has(thematic, 'TOPIC:PERSAMPAHAN') &&
      has(thematic, 'UU-18-2008') && has(thematic, 'PP-81-2012'))

    return {pass: checks.every(item => item.pass), checks}
  }

  async function read(url) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {Accept: 'application/json'}
    })
    if (response.status === 401) throw new Error('Sesi login PI tidak aktif (HTTP 401)')
    if (!response.ok) throw new Error('API belum siap: HTTP ' + response.status + ' · ' + url.split('?')[0])
    return response.json()
  }

  async function run(button, area) {
    button.disabled = true
    area.replaceChildren()
    const summary = document.createElement('p')
    summary.textContent = 'Memeriksa API Laravel yang sedang aktif…'
    area.appendChild(summary)
    try {
      // Sequential, bounded same-origin GETs; no external calls or saved state.
      const trace = await read('/api/qa-regulatory-r70a/trace')
      const catalog = await read('/api/qa-regulatory-r70b/sources')
      const parents = await read('/api/qa-regulatory-r74/genealogy?root=PP-81-2012&direction=ancestors&lens=legal&depth=3')
      const children = await read('/api/qa-regulatory-r74/genealogy?root=UU-18-2008&direction=descendants&lens=legal&depth=3')
      const thematic = await read('/api/qa-regulatory-r74/genealogy?root=TOPIC%3APERSAMPAHAN&direction=both&lens=thematic&depth=3')
      const result = evaluate(trace, catalog, parents, children, thematic)
      summary.textContent = result.pass
        ? 'PI_R79_REAL_BROWSER_ACCEPTANCE=PASS (8/8). Batas hukum tetap REVIEW_REQUIRED; produksi DENIED.'
        : 'PI_R79_REAL_BROWSER_ACCEPTANCE=FAIL. Periksa pemeriksaan yang belum lulus.'
      summary.className = result.pass ? 'trace-acceptance-ok' : 'trace-acceptance-fail'
      const list = document.createElement('ol')
      list.className = 'trace-acceptance-list'
      for (const item of result.checks) {
        const row = document.createElement('li')
        row.textContent = (item.pass ? 'PASS — ' : 'FAIL — ') + item.label
        row.className = item.pass ? 'trace-acceptance-ok' : 'trace-acceptance-fail'
        list.appendChild(row)
      }
      area.appendChild(list)
    } catch (error) {
      summary.textContent = 'PI_R79_REAL_BROWSER_ACCEPTANCE=FAIL — ' +
        (error instanceof Error ? error.message : 'Kegagalan tak teridentifikasi')
      summary.className = 'trace-acceptance-fail'
    } finally {
      button.disabled = false
    }
  }

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = {evaluate}
  }
  if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
      const button = document.getElementById('r79-acceptance-run')
      const area = document.getElementById('r79-acceptance-result')
      if (button && area) button.addEventListener('click', () => run(button, area))
    })
  }
})()
