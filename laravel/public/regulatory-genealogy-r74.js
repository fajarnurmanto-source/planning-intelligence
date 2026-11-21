/* PI R74: source-only bounded legal/thematic genealogy explorer. */
(() => {
  'use strict'
  const $ = id => document.getElementById(id)
  const make = (parent, tag, cls, label) => {
    const el = document.createElement(tag)
    if (cls) el.className = cls
    if (label !== undefined) el.textContent = String(label)
    parent.appendChild(el)
    return el
  }
  const officialUrl = value => typeof value === 'string' &&
    /^https:\/\/(?:www\.)?peraturan\.go\.id\//.test(value) ||
    typeof value === 'string' && /^https:\/\/peraturan\.bpk\.go\.id\//.test(value)
  function official(parent, url) {
    if (!officialUrl(url)) return
    const a = make(parent, 'a', 'r74-evidence-link', 'Buka sumber resmi ↗')
    a.href = url
    a.target = '_blank'
    a.rel = 'noopener noreferrer'
  }
  async function getJson(path) {
    const response = await fetch(path, {credentials: 'same-origin', cache: 'no-store'})
    if (response.status === 401) {location.replace('./login.html'); throw Error('Sesi berakhir.')}
    if (!response.ok) throw Error('Permintaan Laravel gagal (HTTP ' + response.status + ').')
    return response.json()
  }
  function draw(result) {
    if (result.schema !== 'PI_R74_BIDIRECTIONAL_GENEALOGY_TRACE_V1' ||
        result.legal_harmonization !== 'NOT_ASSESSED' ||
        result.production_admission !== 'DENIED' ||
        result.numeric_comparison !== 'BLOCKED' ||
        !Array.isArray(result.nodes) || !Array.isArray(result.relations) ||
        result.node_count !== result.nodes.length ||
        result.edge_count !== result.relations.length) {
      throw Error('Kontrak graf R74 tidak sesuai; hasil ditahan.')
    }
    const target = $('r74-results')
    target.replaceChildren()
    const header = make(target, 'p', 'r74-count', result.node_count + ' simpul · ' +
      result.edge_count + ' hubungan · kedalaman ' + result.max_depth)
    header.setAttribute('role', 'status')
    const nodes = new Map(result.nodes.map(item => [item.id, item]))
    const list = make(target, 'div', 'r74-node-grid')
    for (const item of result.nodes) {
      const card = make(list, 'article', 'r74-node')
      make(card, 'small', 'r74-node-meta', item.node_type + ' · ' + item.id +
        ' · jarak ' + item.distance)
      make(card, 'strong', '', item.label)
      if (item.article_group) make(card, 'span', 'r74-kind', item.article_group +
        (item.formal_rank === null ? ' · tanpa peringkat universal' : ' · tingkat ' + item.formal_rank))
      if (item.source_status) make(card, 'p', 'r74-source-status', item.source_status)
      official(card, item.official_url)
    }
    const relations = make(target, 'section', 'r74-relations')
    make(relations, 'h3', '', 'Relasi dan jejak sumber')
    if (!result.relations.length) make(relations, 'p', '', 'Belum ditemukan hubungan dalam cakupan bukti dan mode yang dipilih.')
    for (const edge of result.relations) {
      const item = make(relations, 'article', 'r74-relation')
      make(item, 'strong', '', edge.type)
      make(item, 'p', '', (nodes.get(edge.from)?.label || edge.from) + ' → ' +
        (nodes.get(edge.to)?.label || edge.to))
      make(item, 'small', '', edge.status + ' · akibat hukum: ' + edge.legal_effect)
      if (edge.source_locator) make(item, 'p', 'r74-source-status', 'Lokator: ' + edge.source_locator)
      official(item, edge.source_url)
    }
  }
  async function trace() {
    const root = $('r74-root').value
    const lens = $('r74-lens').value
    if (!root) return
    $('r74-status').textContent = 'Menelusuri bukti yang tersedia…'
    $('r74-results').replaceChildren()
    try {
      const query = new URLSearchParams({
        root, direction: $('r74-direction').value, lens, depth: '4'
      })
      const result = await getJson('/api/qa-regulatory-r74/genealogy?' + query)
      draw(result)
      $('r74-status').textContent = 'Jejak parsial berhasil ditampilkan; kesimpulan hukum tetap memerlukan penelaahan.'
    } catch (error) {
      $('r74-status').textContent = 'Graf belum tersedia: ' + (error?.message || 'Kesalahan pembacaan bukti.')
    }
  }
  async function init() {
    if (!$('r74-form')) return
    $('r74-form').addEventListener('submit', event => {event.preventDefault(); void trace()})
    try {
      const data = await getJson('/api/qa-regulatory-r74/taxonomy')
      if (data.schema !== 'PI_R74_REGULATORY_TAXONOMY_V1' ||
          data.production_admission !== 'DENIED' ||
          !Array.isArray(data.types) || !Array.isArray(data.instruments) ||
          !Array.isArray(data.topics)) {
        throw Error('Kontrak taksonomi R74 tidak sah.')
      }
      const article7 = data.types.filter(item => item.group === 'ARTICLE_7')
      const article8 = data.types.filter(item => item.group === 'ARTICLE_8')
      $('r74-taxonomy').textContent = article7.length + ' jenis Pasal 7 · ' +
        article8.length + ' subjenis Pasal 8 terdaftar · ' + data.topics.length +
        ' konsep tema (dapat memiliki lebih dari satu induk).'
      const select = $('r74-root')
      select.replaceChildren()
      for (const item of data.instruments) {
        const option = make(select, 'option', '', item.id + ' · ' + item.title)
        option.value = item.id
      }
      for (const item of data.topics) {
        const option = make(select, 'option', '', 'TEMA · ' + item.label)
        option.value = item.id
      }
      select.value = 'PERMENDAGRI-10-2025'
      await trace()
    } catch (error) {
      $('r74-taxonomy').textContent = 'Registry taksonomi belum tersedia.'
      $('r74-status').textContent = error?.message || 'Kegagalan pemuatan sumber.'
    }
  }
  document.addEventListener('DOMContentLoaded', () => {void init()})
})()
