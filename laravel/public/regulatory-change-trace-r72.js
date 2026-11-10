/* PI R72: structural amendment trace, deterministic read-only. */
(() => {
  'use strict'
  const status = document.getElementById('r72-status')
  const groupsArea = document.getElementById('r72-groups')
  const summary = document.getElementById('r72-summary')
  if (!status || !groupsArea || !summary) return

  const hex = value => typeof value === 'string' && /^[A-F0-9]{64}$/.test(value)
  const id = value => typeof value === 'string' && /^[A-Z0-9_]+$/.test(value)
  function add(parent, tag, value, cls = '') {
    const node = document.createElement(tag)
    if (cls) node.className = cls
    if (value !== undefined) node.textContent = String(value)
    parent.appendChild(node)
    return node
  }
  function validate(data) {
    if (!data || data.schema !== 'PI_R72_STRUCTURAL_AMENDMENT_TRACE_V1' ||
        data.review_status !== 'CANDIDATE_TRACE_ONLY' ||
        data.legal_harmonization !== 'NOT_ASSESSED' ||
        data.annex_content_diff !== 'NOT_PERFORMED' ||
        data.numeric_comparison !== 'BLOCKED' ||
        data.reviewer_gate !== 'REQUIRED' ||
        data.production_admission !== 'DENIED' ||
        !hex(data.source_integrity?.ledger_sha256) ||
        !hex(data.source_integrity?.scope_sha256) ||
        data.source_integrity?.scope_links_to_ledger !== true ||
        !Array.isArray(data.target_groups) || data.target_groups.length > 20 ||
        !Number.isInteger(data.summary?.structural_change_candidate_count) ||
        !Number.isInteger(data.summary?.distinct_target_count)) {
      throw Error('Kontrak R72 tidak cocok; garis waktu tidak ditampilkan.')
    }
    let total = 0
    const seen = new Set()
    for (const group of data.target_groups) {
      if (typeof group.target !== 'string' || seen.has(group.target) ||
          !Array.isArray(group.events) || group.event_count !== group.events.length ||
          group.interpretation !== 'CHRONOLOGICAL_TARGET_MENTIONS_NOT_CONTENT_DIFF') {
        throw Error('Kelompok sasaran tidak terverifikasi.')
      }
      seen.add(group.target)
      let previous = null
      let lastSequence = -1
      for (const event of group.events) {
        if (!id(event.source_id) || !hex(event.source_pdf_sha256) ||
            !Number.isInteger(event.sequence) || event.sequence <= lastSequence ||
            event.target !== group.target || event.previous_target_source_id !== previous ||
            event.review_status !== 'REVIEW_REQUIRED' ||
            event.structural_relation !== 'SUCCESSIVE_TARGET_MENTIONS_ONLY' ||
            !Array.isArray(event.evidence_refs) || !event.evidence_refs.length) {
          throw Error('Urutan atau bukti perubahan tidak terverifikasi.')
        }
        for (const proof of event.evidence_refs) {
          if (proof.source_id !== event.source_id ||
              !Number.isInteger(proof.pdf_page) || proof.pdf_page < 1 ||
              !hex(proof.excerpt_sha256)) {
            throw Error('Anchor bukti perubahan tidak sesuai.')
          }
        }
        previous = event.source_id
        lastSequence = event.sequence
        total++
      }
    }
    if (seen.size !== data.summary.distinct_target_count ||
        total !== data.summary.structural_change_candidate_count) {
      throw Error('Jumlah kandidat perubahan tidak sesuai.')
    }
  }
  function render(data) {
    validate(data)
    summary.textContent = data.summary.distinct_target_count + ' sasaran · ' +
      data.summary.structural_change_candidate_count +
      ' rujukan struktural · belum ada perbandingan isi lampiran.'
    groupsArea.replaceChildren()
    for (const group of data.target_groups) {
      const card = add(groupsArea, 'section', undefined, 'r72-card')
      add(card, 'h3', group.target + ' · ' + group.event_count + ' rujukan')
      const timeline = add(card, 'ol', undefined, 'r72-timeline')
      for (const event of group.events) {
        const item = add(timeline, 'li', undefined, 'r72-event')
        const top = add(item, 'div', undefined, 'r72-event-top')
        add(top, 'strong', 'Perubahan ke-' + event.sequence)
        const link = add(top, 'a', event.source_id + ' · buka sumber')
        link.href = '#r70g-doc-' + event.source_id
        add(item, 'p', event.source_title)
        add(item, 'p', 'Butir ' + (event.target_item ?? '—') +
          ' · kata kerja tercatat: ' + (event.change_verb_as_extracted || 'belum terverifikasi'))
        add(item, 'small', 'Halaman pembuka: ' + event.start_pdf_page +
          ' · kata kerja: ' + event.operative_verb_pdf_page +
          ' · bukti hash: ' + event.evidence_refs.length)
        const evidence = add(item, 'details')
        add(evidence, 'summary', 'Periksa referensi bukti SHA-256')
        for (const ref of event.evidence_refs) {
          add(evidence, 'code', 'Hal. ' + ref.pdf_page + ' · ' + ref.excerpt_sha256, 'r72-hash')
        }
      }
    }
    status.textContent = 'R72 siap untuk telaah. Ini urutan penyebutan sasaran, bukan perbandingan angka atau kesimpulan hukum.'
  }
  async function load() {
    const response = await fetch('/api/qa-regulatory-r72/change-trace', {
      credentials: 'same-origin', cache: 'no-store'
    })
    if (response.status === 401) { location.replace('./login.html'); return }
    if (!response.ok) throw Error('Kandidat R72 tidak tersedia (HTTP ' + response.status + ').')
    render(await response.json())
  }
  load().catch(error => {
    status.dataset.error = 'true'
    status.textContent = 'Garis waktu ditahan: ' + (error.message || 'kesalahan tidak diketahui')
  })
})()
