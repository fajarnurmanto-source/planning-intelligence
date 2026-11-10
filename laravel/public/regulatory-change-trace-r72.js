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

  // R73: source-bound comparison of short operative-target excerpts on reviewer request.
  // Never present this lexical comparison as a full annex, numeric, or legal diff.
  const comparisonTokenLimit = 140
  function selectedReference(event) {
    return event.evidence_refs.find(ref => ref.pdf_page === event.operative_verb_pdf_page) ||
      event.evidence_refs[0]
  }
  function checkedExcerpt(data, event, ref, integrity) {
    if (!data || data.schema !== 'PI_R70H_VERIFIED_EVIDENCE_LOOKUP_V1' ||
        data.source_id !== event.source_id ||
        data.source_pdf_sha256 !== event.source_pdf_sha256 ||
        data.excerpt_sha256 !== ref.excerpt_sha256 ||
        data.source_title !== event.source_title ||
        data.review_status !== 'REVIEW_REQUIRED' ||
        data.legal_harmonization !== 'NOT_ASSESSED' ||
        data.numeric_comparison !== 'BLOCKED' ||
        data.production_admission !== 'DENIED' ||
        data.source_integrity?.ledger_sha256 !== integrity.ledger_sha256 ||
        data.source_integrity?.scope_sha256 !== integrity.scope_sha256 ||
        data.source_integrity?.scope_links_to_ledger !== true ||
        !Array.isArray(data.matches) || !data.matches.length || data.matches.length > 20) {
      throw Error('Bukti R70H tidak cocok dengan snapshot R72. Kutipan ditahan.')
    }
    for (const match of data.matches) {
      if (!Number.isInteger(match.pdf_page) || match.pdf_page < 1 ||
          match.excerpt_sha256 !== ref.excerpt_sha256 ||
          typeof match.excerpt !== 'string' || !match.excerpt ||
          match.excerpt.length > 20000 || match.review_status === 'APPROVED') {
        throw Error('Halaman, hash, atau status kutipan tidak sah.')
      }
    }
    const match = data.matches.find(item => item.pdf_page === ref.pdf_page &&
      item.context === 'operative_target' && item.context_detail === event.target &&
      item.review_status === 'REVIEW_REQUIRED')
    if (!match) throw Error('Kutipan sasaran lampiran tidak tersedia pada halaman terikat.')
    return { text: match.excerpt.trim(), page: match.pdf_page, hash: match.excerpt_sha256 }
  }
  async function fetchExcerpt(event, integrity) {
    const ref = selectedReference(event)
    const endpoint = '/api/qa-regulatory-r70h/evidence/' + event.source_id +
      '/' + ref.excerpt_sha256
    const response = await fetch(endpoint, { credentials: 'same-origin', cache: 'no-store' })
    if (response.status === 401) {
      location.replace('./login.html')
      throw Error('Sesi masuk telah berakhir.')
    }
    if (!response.ok) throw Error('Kutipan tidak dapat dimuat (HTTP ' + response.status + ').')
    return checkedExcerpt(await response.json(), event, ref, integrity)
  }
  function words(value) {
    const tokens = value.split(/\s+/u).filter(Boolean)
    return { tokens: tokens.slice(0, comparisonTokenLimit),
      clipped: tokens.length > comparisonTokenLimit }
  }
  function lexicalDiff(previous, current) {
    const left = words(previous)
    const right = words(current)
    const a = left.tokens
    const b = right.tokens
    const dp = Array.from({ length: a.length + 1 },
      () => new Uint16Array(b.length + 1))
    for (let i = a.length - 1; i >= 0; i--) {
      for (let j = b.length - 1; j >= 0; j--) {
        dp[i][j] = a[i] === b[j] ? 1 + dp[i + 1][j + 1] :
          Math.max(dp[i + 1][j], dp[i][j + 1])
      }
    }
    let i = 0
    let j = 0
    const changes = []
    while (i < a.length || j < b.length) {
      if (i < a.length && j < b.length && a[i] === b[j]) {
        changes.push(['same', a[i++]])
        j++
      } else if (i < a.length && (j === b.length || dp[i + 1][j] >= dp[i][j + 1])) {
        changes.push(['remove', a[i++]])
      } else {
        changes.push(['add', b[j++]])
      }
    }
    return { changes, clipped: left.clipped || right.clipped }
  }
  function renderComparison(area, previousEvent, currentEvent, before, after) {
    const diff = lexicalDiff(before.text, after.text)
    area.replaceChildren()
    add(area, 'h4', 'R73 · Perbandingan leksikal kutipan terverifikasi')
    add(area, 'p', 'Dua kutipan pendek dari sasaran ' + currentEvent.target +
      ' pada dua peraturan berurutan. Perbedaan redaksi BUKAN perubahan isi seluruh lampiran.')
    const cards = add(area, 'div', undefined, 'r73-excerpts')
    for (const item of [
      { event: previousEvent, evidence: before, title: 'Sebelumnya' },
      { event: currentEvent, evidence: after, title: 'Sesudahnya' }
    ]) {
      const card = add(cards, 'div', undefined, 'r73-excerpt')
      add(card, 'strong', item.title + ' · ' + item.event.source_id)
      add(card, 'small', 'Halaman PDF ' + item.evidence.page +
        ' · SHA-256 ' + item.evidence.hash)
      add(card, 'blockquote', item.evidence.text)
    }
    add(area, 'strong', 'Kata yang dihapus / ditambahkan pada cuplikan:')
    const marked = add(area, 'p', undefined, 'r73-diff')
    for (const [kind, token] of diff.changes) {
      const piece = add(marked, kind === 'same' ? 'span' :
        kind === 'remove' ? 'del' : 'ins', token)
      piece.appendChild(document.createTextNode(' '))
    }
    if (diff.clipped) {
      add(area, 'p', 'Pratinjau selisih dibatasi pada ' + comparisonTokenLimit +
        ' kata pertama tiap kutipan. Bacalah dua kutipan lengkap di atas.', 'r73-limit')
    }
    add(area, 'p', 'REVIEW MANUSIA WAJIB · bukan diff PDF/lampiran lengkap · ' +
      'harmonisasi hukum belum dinilai · perbandingan angka diblokir · produksi ditolak.',
      'r73-guard')
  }
  function attachComparison(item, previousEvent, event, integrity) {
    const control = add(item, 'button',
      'Bandingkan kutipan dengan perubahan sebelumnya', 'r73-compare-button')
    control.type = 'button'
    const area = add(item, 'div', undefined, 'r73-comparison')
    area.setAttribute('aria-live', 'polite')
    control.addEventListener('click', async () => {
      if (control.disabled) return
      control.disabled = true
      area.replaceChildren()
      add(area, 'p', 'Mengambil dua kutipan R70H yang terikat hash…')
      try {
        const [before, after] = await Promise.all([
          fetchExcerpt(previousEvent, integrity), fetchExcerpt(event, integrity)
        ])
        renderComparison(area, previousEvent, event, before, after)
      } catch (error) {
        area.replaceChildren()
        add(area, 'p', 'Perbandingan ditahan: ' +
          (error.message || 'bukti tidak tersedia'), 'r73-error')
      } finally {
        control.disabled = false
      }
    })
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
      let previousEvent = null
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
        if (previousEvent) attachComparison(item, previousEvent, event, data.source_integrity)
        previousEvent = event
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
