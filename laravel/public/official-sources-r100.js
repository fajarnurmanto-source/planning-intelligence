/* PI R100: render official indexed metadata from authenticated LOCAL Laravel.
 * No web scraping, provider request, evidence promotion, numeric inference or POST.
 */
(() => {
  'use strict'
  const endpoint = '/api/qa-superior-r100/official-sources'
  const state = {documents:[], expanded:false, lane:'ALL'}
  const byId = id => document.getElementById(id)
  const write = (tag, text, className) => {
    const node = document.createElement(tag)
    node.textContent = String(text ?? '')
    if (className) node.className = className
    return node
  }

  const safeOfficialLink = value => {
    try {
      const url = new URL(value)
      if (url.protocol !== 'https:' || url.hostname !== 'jdih.jogjakota.go.id'
          || url.username || url.password || url.port || url.search || url.hash) {
        return null
      }
      if (!(/^\/home\/produk_hukum\/detail\/[0-9]+$/.test(url.pathname)
          || /^\/asset\/naskah\/[a-zA-Z0-9._-]+\.pdf$/.test(url.pathname))) {
        return null
      }
      return url.href
    } catch {
      return null
    }
  }

  const laneLabel = {
    PLANNING:'Perencanaan',
    FISCAL:'Penganggaran',
    SECTOR:'Kebijakan persampahan',
    ACCOUNTABILITY:'Pertanggungjawaban tahun sebelumnya'
  }
  const status = message => {
    const node = byId('r100-state')
    if (node) node.textContent = message
  }
  const render = () => {
    const list = byId('r100-list')
    const more = byId('r100-more')
    if (!list || !more) return
    list.replaceChildren()
    const items = state.lane === 'ALL'
      ? state.documents
      : state.documents.filter(doc => doc.lane === state.lane)
    const visible = state.expanded ? items : items.slice(0,6)
    for (const doc of visible) {
      const article = document.createElement('article')
      article.className = 'r100-item'
      article.appendChild(write('span',
        (laneLabel[doc.lane] || doc.lane)+' · '+doc.instrument+' '+doc.number+'/'+doc.year,
        'r100-kicker'))
      article.appendChild(write('strong',window.PIRegulatoryIdentity?.label(doc) || doc.title))
      article.appendChild(write('p','Periode '+doc.period+' · '+doc.locator))
      article.appendChild(write('span','METADATA RESMI TERINDEKS · BERKAS BELUM DIPIN HASH','r100-status'))
      const link = safeOfficialLink(doc.official_url)
      if (link) {
        const anchor = write('a','Buka rekaman JDIH resmi')
        anchor.href=link
        anchor.target='_blank'
        anchor.rel='noopener noreferrer'
        article.appendChild(anchor)
      }
      list.appendChild(article)
    }
    more.hidden = items.length <= 6
    more.textContent = state.expanded ? 'Tampilkan ringkas' : 'Lihat semua '+items.length+' sumber'
    status(items.length+' rekaman metadata JDIH · 0 berkas baru terverifikasi hash · 0 angka subkegiatan diterima')
  }

  const init = async () => {
    if (!byId('r100-list')) return
    try {
      const response = await fetch(endpoint,{
        credentials:'same-origin',cache:'no-store',
        headers:{Accept:'application/json'}
      })
      if (!response.ok) throw new Error('OFFICIAL_SOURCE_ENDPOINT_UNAVAILABLE')
      const model = await response.json()
      if (model.schema !== 'PI_R100_OFFICIAL_SOURCE_METADATA_READ_MODEL_V1'
          || !Array.isArray(model.documents)
          || model.counts?.indexed_official_metadata !== 18
          || model.counts?.new_document_bytes_hash_verified !== 0
          || model.counts?.new_subactivity_budget_amounts_verified !== 0
          || model.interpretation?.numeric_comparison !== 'BLOCKED'
          || model.interpretation?.production_admission !== 'DENIED') {
        throw new Error('OFFICIAL_SOURCE_PROVENANCE_GUARDS_NOT_MET')
      }
      state.documents=model.documents
      byId('r100-count-metadata').textContent=String(model.counts.indexed_official_metadata)
      byId('r100-count-hash').textContent='0'
      byId('r100-count-amount').textContent='0'
      const gaps = byId('r100-gaps')
      gaps.replaceChildren(write('strong','Celah bukti yang masih perlu diisi'))
      const ul=document.createElement('ul')
      for (const gap of model.evidence_gaps ?? []) {
        ul.appendChild(write('li',String(gap).replaceAll('_',' ').toLowerCase()))
      }
      gaps.appendChild(ul)
      render()
    } catch {
      status('ABSTAIN · Sumber resmi belum dapat dimuat. Jangan gunakan metadata sebagai verifikasi angka.')
      byId('r100-list')?.replaceChildren()
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const selector=byId('r100-lane')
    selector?.addEventListener('change', () => {
      if (!Object.hasOwn(laneLabel,selector.value) && selector.value !== 'ALL') return
      state.lane=selector.value
      state.expanded=false
      render()
    })
    byId('r100-more')?.addEventListener('click', () => {
      state.expanded=!state.expanded
      render()
    })
    init()
  })
})()
