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
  const relationGroups=Object.freeze({CITES_AS_BASIS:['Rujukan dasar hukum','legal'],ISSUED_UNDER_AUTHORITY:['Rujukan kewenangan','legal'],IMPLEMENTS_PROVISION_OF:['Kandidat pelaksanaan ketentuan','legal'],DELEGATED_BY:['Rujukan delegasi','legal'],PARTIALLY_REPEALS:['Kandidat pencabutan sebagian','legal'],REPEALS:['Kandidat pencabutan','legal'],REPLACES:['Kandidat penggantian','legal'],AMENDS:['Hubungan perubahan peraturan','version'],AMENDS_PROVISION_OF:['Hubungan perubahan ketentuan','version'],REGULATES_TOPIC:['Keterkaitan tema','thematic'],BROADER_TOPIC:['Tema yang lebih luas','thematic'],PROVISION_OF:['Bagian dari peraturan','structural']})
  const nodeKinds={INSTRUMENT:'Peraturan',PROVISION:'Ketentuan',TOPIC:'Tema'}
  let generation=0,loading=false
  const owns=(o,k)=>Object.prototype.hasOwnProperty.call(o,k)
  function draw(result,request) {
    if (result.schema !== 'PI_R74_BIDIRECTIONAL_GENEALOGY_TRACE_V1' ||
        result.legal_harmonization !== 'NOT_ASSESSED' ||
        result.production_admission !== 'DENIED' ||
        result.numeric_comparison !== 'BLOCKED' || result.tenant_id!=='ID-DIY-YOG' ||
        result.temporal_applicability!=='NOT_ASSESSED' || result.review_status!=='REVIEW_REQUIRED' ||
        result.result_status!=='PARTIAL_EVIDENCE_GRAPH_NOT_LEGAL_FINDING' ||
        result.root!==request.root || result.lens!==request.lens || result.direction!==request.direction || result.max_depth!==4 ||
        !Array.isArray(result.nodes) || !Array.isArray(result.relations) ||
        result.node_count !== result.nodes.length ||
        result.edge_count !== result.relations.length) {
      throw Error('Kontrak graf R74 tidak sesuai; hasil ditahan.')
    }
    const nodeIds=new Set(),edgeIds=new Set()
    if(result.nodes.length>1600||result.relations.length>4000)throw Error('GRAPH_HOLD')
    for(const node of result.nodes){
      if(!node||typeof node.id!=='string'||nodeIds.has(node.id)||!owns(nodeKinds,node.node_type)||typeof node.label!=='string'||!Number.isInteger(node.distance)||node.distance<0||node.distance>4)throw Error('GRAPH_NODE_HOLD')
      nodeIds.add(node.id)
    }
    if(!result.nodes.some(n=>n.id===request.root&&n.distance===0))throw Error('GRAPH_ROOT_HOLD')
    for(const edge of result.relations){
      if(!edge||typeof edge.id!=='string'||edgeIds.has(edge.id)||!nodeIds.has(edge.from)||!nodeIds.has(edge.to)||edge.from===edge.to||!owns(relationGroups,edge.type)||edge.legal_effect!=='NOT_ASSESSED')throw Error('GRAPH_EDGE_HOLD')
      const family=relationGroups[edge.type][1]
      if(request.lens!=='all'&&family!=='structural'&&family!==request.lens)throw Error('GRAPH_LENS_HOLD')
      edgeIds.add(edge.id)
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
      make(card, 'small', 'r74-node-meta', nodeKinds[item.node_type] + ' · jarak penelusuran ' + item.distance + ' (bukan tingkat hukum)')
      make(card, 'strong', '', item.label)
      if (item.article_group==='ARTICLE_7') make(card,'span','r74-kind','Pasal 7 · peringkat tercatat: '+(item.formal_rank??'belum diketahui'))
      else if(item.article_group==='ARTICLE_8') make(card,'span','r74-kind','Pasal 8 · tidak memiliki peringkat universal')
      if(item.node_type==='INSTRUMENT'&&item.jurisdiction)make(card,'p','r74-source-status','Yurisdiksi tercatat: '+item.jurisdiction)
      if(item.node_type==='PROVISION')make(card,'p','r74-source-status','Lokasi ketentuan · hash sumber belum terikat')
      const audit=make(card,'details','pi-reg-audit');make(audit,'summary','','Identitas dan status sumber');make(audit,'code','',item.id+' · '+(item.source_status||'STATUS_UNAVAILABLE'))
      official(card, item.official_url)
    }
    const relations = make(target, 'section', 'r74-relations')
    make(relations, 'h3', '', 'Relasi dan jejak sumber')
    if (!result.relations.length) make(relations, 'p', '', 'Belum ditemukan hubungan dalam cakupan bukti dan mode yang dipilih.')
    for (const edge of result.relations) {
      const item = make(relations, 'article', 'r74-relation')
      const [label,family]=relationGroups[edge.type]
      make(item,'strong','',label)
      make(item,'span','r74-kind',family==='thematic'?'Tematik · bukan hubungan hukum':family==='structural'?'Struktur dokumen · bukan delegasi':family==='version'?'Riwayat versi · perlu verifikasi cakupan':'Rujukan hukum · perlu telaah')
      make(item, 'p', '', (nodes.get(edge.from)?.label || edge.from) + ' → ' +
        (nodes.get(edge.to)?.label || edge.to))
      make(item, 'small', '', 'Akibat hukum belum dinilai. Arah relasi mengikuti sumber, bukan arah penelusuran.')
      const audit=make(item,'details','pi-reg-audit');make(audit,'summary','','Lihat tipe dan status teknis');make(audit,'code','',edge.type+' · '+edge.status+' · '+edge.legal_effect)
      if (edge.source_locator) make(item, 'p', 'r74-source-status', 'Lokasi bukti: ' + edge.source_locator)
      else make(item,'p','r74-source-status','Lokasi bukti belum tersedia pada relasi ini.')
      official(item, edge.source_url)
    }
  }
  function syncLens(){
    const thematic=$('r74-lens').value==='thematic'
    $('r74-direction').disabled=thematic
    const note=$('r74-lens-note')
    if(note)note.textContent=thematic?'Tema ditelusuri kedua arah. Kedekatan tema bukan hierarki atau delegasi hukum.':'Arah penelusuran tidak mengubah arah relasi sumber. Jarak graf bukan peringkat hukum.'
  }
  function invalidate(){
    generation++
    syncLens()
    $('r74-results').replaceChildren()
    $('r74-status').textContent='Pilihan berubah. Pilih Telusuri hubungan untuk memeriksa konteks baru.'
  }
  async function trace() {
    if(loading)return
    const root = $('r74-root').value
    const lens = $('r74-lens').value
    if (!root) return
    syncLens()
    const request={root,lens,direction:lens==='thematic'?'both':$('r74-direction').value}
    const own=++generation
    const target=$('r74-results'),button=$('r74-run')
    loading=true
    if(button)button.disabled=true
    $('r74-status').textContent = 'Menelusuri bukti yang tersedia…'
    target.replaceChildren();target.setAttribute('aria-busy','true')
    try {
      const query = new URLSearchParams({...request, depth:'4'})
      const result = await getJson('/api/qa-regulatory-r74/genealogy?' + query)
      if(own!==generation)return
      draw(result,request)
      $('r74-status').textContent = 'Jejak parsial berhasil ditampilkan; kesimpulan hukum tetap memerlukan penelaahan.'
    } catch (error) {
      if(own!==generation)return
      target.replaceChildren()
      $('r74-status').textContent = 'Hubungan belum dapat diperiksa. Periksa ketersediaan sumber atau pilih Telusuri hubungan kembali.'
    }finally{loading=false;target.setAttribute('aria-busy','false');if(button)button.disabled=false}
  }
  async function init() {
    if (!$('r74-form')) return
    $('r74-form').addEventListener('submit', event => {event.preventDefault(); void trace()})
    for(const id of ['r74-root','r74-direction','r74-lens'])$(id).addEventListener('change',invalidate)
    syncLens()
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
      $('r74-results').replaceChildren();$('r74-results').setAttribute('aria-busy','false')
      $('r74-status').textContent = 'Daftar peraturan belum tersedia. Muat ulang halaman untuk memeriksa kembali.'
    }
  }
  document.addEventListener('DOMContentLoaded', () => {void init()})
})()
