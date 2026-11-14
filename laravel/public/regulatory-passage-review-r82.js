/* R82: reviewer-only, read-only national legislative passage candidates. */
(() => {
  'use strict'
  const id = text => document.getElementById(text)

  function render(report, container) {
    container.replaceChildren()
    if (!report || report.schema !== 'PI_R82_NATIONAL_PASSAGE_CANDIDATES_QA_V1' ||
      report.tenant_id !== 'ID-DIY-YOG' ||
      report.source_snapshot_integrity !== 'R81_TWO_INDEPENDENT_SHA256_PINS_MATCH' ||
      report.passage_verification !== 'UNREVIEWED_TEXT_LAYER_CANDIDATES_ONLY' ||
      report.relation_admission !== 'NOT_PERFORMED' ||
      report.legal_harmonization !== 'NOT_ASSESSED' ||
      report.numeric_comparison !== 'BLOCKED' ||
      report.production_admission !== 'DENIED' ||
      report.r80_candidate_article_count !== 7 ||
      !Array.isArray(report.candidates) || report.candidates.length !== 7 ||
      !Array.isArray(report.sources) || report.sources.length !== 2) {
      throw Error('Kontrak bukti R82 atau pengaman hukum tidak sesuai')
    }
    const headline = document.createElement('p')
    headline.className = 'r82-summary'
    headline.textContent = '7 kandidat pasal · ' + report.text_candidate_article_count +
      ' memiliki potongan teks untuk ditelaah · ' +
      report.unresolved_article_count + ' masih belum lengkap'
    container.appendChild(headline)

    for (const source of report.sources) {
      const line = document.createElement('p')
      line.className = 'r82-source'
      line.textContent = source.instrument_id + ' · SHA-256 ' +
        source.pdf_sha256.slice(0, 12) + '… · ' + source.text_layer_status
      container.appendChild(line)
    }

    for (const record of report.candidates) {
      const detail = document.createElement('details')
      detail.className = 'r82-candidate'
      const heading = document.createElement('summary')
      heading.textContent = 'Pasal ' + record.article +
        (record.paragraphs_requested.length
          ? ' ayat (' + record.paragraphs_requested.join('), (') + ')' : '') +
        ' — ' + record.evidence_status
      detail.appendChild(heading)

      const reference = record.pp_reference
      const fragments = (reference ? [{...reference, origin: 'PP 81/2012 – Menimbang'}] : [])
        .concat(record.uu_passages.map(x => ({...x, origin:'UU 18/2008 – batang tubuh'})))
      for (const excerpt of fragments) {
        const label = document.createElement('p')
        label.className = 'r82-excerpt-label'
        label.textContent = excerpt.origin + ' · PDF halaman ' + excerpt.pdf_page +
          ' · teks SHA-256 ' + excerpt.excerpt_sha256.slice(0, 16) + '…'
        detail.appendChild(label)
        const prose = document.createElement('blockquote')
        prose.className = 'r82-excerpt'
        prose.textContent = excerpt.excerpt
        detail.appendChild(prose)
      }
      const note = document.createElement('p')
      note.className = 'r82-warning'
      note.textContent = 'Belum diverifikasi reviewer. Periksa halaman PDF asli, ayat, versi, dan keberlakuan sebelum mempertimbangkan relasi.'
      detail.appendChild(note)
      container.appendChild(detail)
    }
  }

  async function load() {
    const area = id('r82-passage-results')
    if (!area) return
    const button = id('r82-passage-refresh')
    if (button) button.disabled = true
    area.textContent = 'Memeriksa laporan R82 dari Laravel…'
    try {
      const response = await fetch('/api/qa-regulatory-r82/passage-candidates', {
        credentials: 'same-origin', cache: 'no-store',
        headers: {Accept:'application/json'}
      })
      if (!response.ok) {
        throw Error(response.status === 503
          ? 'Laporan belum disiapkan dari PDF asli. Gunakan perintah php artisan pi:r82:build terlebih dahulu.'
          : 'API R82 HTTP ' + response.status)
      }
      render(await response.json(), area)
    } catch (error) {
      area.textContent = 'R82: ' + (error instanceof Error ? error.message : 'Gagal membaca laporan')
    } finally {
      if (button) button.disabled = false
    }
  }

  if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
      const button = id('r82-passage-refresh')
      if (button) button.addEventListener('click', load)
      load()
    })
  }
})()
