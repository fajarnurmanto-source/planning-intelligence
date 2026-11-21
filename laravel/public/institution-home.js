/* R69C QA homepage. Read-only from an installer-generated R68 evidence projection.
 * The presence of a document in the catalog is not the same as PDF availability.
 * No API/provider calls are made from this screen. */
(() => {
  'use strict'
  const id = name => document.getElementById(name)
  const add = (parent, tag, cls, value) => {
    const el = document.createElement(tag)
    if (cls) el.className = cls
    if (value != null) el.textContent = String(value)
    parent.appendChild(el)
    return el
  }
  const link = (parent, href, label, cls) => {
    // Permit only known same-origin paths from this closed QA data projection.
    if (!/^\.\/(workspace\.html\?pi_section=penganggaran|ai-decision\.html)$/.test(href)
      && !/^sources_r6[78]\/r6[78]_yog_[a-z_]+\.pdf#page=\d+$/.test(href)) return null
    const a = add(parent, 'a', cls || '', label)
    a.href = href
    return a
  }
  const error = message => {
    for (const section of ['findings-list', 'chain-list', 'documents-list']) {
      const target = id(section)
      if (target) target.replaceChildren(add(document.createDocumentFragment(), 'p', 'loading', message))
    }
  }
  async function load() {
    try {
      const response = await fetch('./overview-r69c.json', {cache:'no-store'})
      if (!response.ok) throw new Error('proyeksi bukti tidak tersedia')
      const data = await response.json()
      if (data.schema !== 'PI_R69C_INSTITUTIONAL_HOME_EVIDENCE_V1'
        || data.jurisdiction !== 'ID-DIY-YOG' || data.guards?.production_admission !== 'DENIED'
        || data.guards?.numeric_comparison !== 'BLOCKED' || data.findings.length !== 3)
        throw new Error('identitas atau batas keamanan data tidak cocok')
      id('metric-docs').textContent = data.documents.length.toString().padStart(2, '0')
      id('metric-pdfs').textContent = data.documents.filter(doc => doc.pdf_available).length.toString().padStart(2, '0')
      id('metric-findings').textContent = data.findings.length.toString().padStart(2, '0')
      id('metric-decisions').textContent = `${data.human_decisions_confirmed}/${data.findings.length}`

      const findings = id('findings-list'); findings.replaceChildren()
      for (const item of data.findings) {
        const card = add(findings, 'article', 'finding')
        const top = add(card, 'div', 'finding-top')
        add(top, 'span', 'finding-code', item.id)
        add(top, 'span', 'finding-priority', 'PRIORITAS TINGGI · BELUM DIPUTUSKAN')
        add(card, 'h3', '', item.title)
        add(card, 'p', '', item.assessment)
        const actions = add(card, 'div', 'finding-links')
        link(actions, './workspace.html?pi_section=penganggaran', 'Buka catatan reviewer ↗')
        if (item.first_available_pdf) link(actions, item.first_available_pdf, 'Lihat halaman bukti ↗')
        else add(actions, 'span', 'muted', 'PDF rujukan belum ada dalam paket')
      }

      const chain = id('chain-list'); chain.replaceChildren()
      for (const item of data.stages) {
        const box = add(chain, 'article', 'stage')
        add(box, 'small', '', item.type)
        add(box, 'strong', '', item.title)
        add(box, 'p', '', item.summary)
        if (item.pdf_available) link(box, item.pdf_href, 'Lihat bukti ↗')
        else add(box, 'small', '', 'Cuplikan belum terkemas')
      }

      const documents = id('documents-list'); documents.replaceChildren()
      for (const item of data.documents) {
        const box = add(documents, 'article', 'document')
        const top = add(box, 'div', 'document-top')
        add(top, 'span', 'document-code', item.id)
        add(top, 'span', 'document-status ' + (item.pdf_available ? 'yes' : 'no'), item.pdf_available ? 'PDF TERSEDIA' : 'KATALOG SAJA')
        add(box, 'p', '', item.title)
        if (item.pdf_available) link(box, item.pdf_href, 'Buka cuplikan PDF ↗', 'document-link')
      }
      id('source-caveat').textContent = `${data.documents.filter(doc => doc.pdf_available).length} dari ${data.documents.length} dokumen katalog memiliki cuplikan PDF dalam instalasi QA ini. Dokumen lain tetap tercatat sebagai rujukan, bukan bukti PDF lokal yang sudah diperiksa.`
    } catch (exc) {
      for (const field of ['metric-docs','metric-pdfs','metric-findings','metric-decisions']) id(field).textContent = '—'
      error('Tidak dapat menampilkan ringkasan: ' + String(exc.message || exc) + '. Pemeriksaan harus dihentikan.')
    }
  }
  document.addEventListener('DOMContentLoaded', load)
})()
