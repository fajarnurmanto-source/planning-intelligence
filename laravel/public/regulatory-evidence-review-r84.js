/* R84: evidence review workspace, not a legal/graph approval workflow.
 * Source hashes are provided by PHP and revalidated by the store endpoint.
 * No localStorage, HTML injection, or migrations from the browser.
 */
(() => {
  'use strict'
  const ARTICLES = [11, 16, 20, 22, 24, 25, 28]
  const R82 = '/api/qa-regulatory-r82/passage-candidates'
  const R84 = '/api/qa-regulatory-r84/evidence-reviews'
  const REVIEW_ROLES = ['reviewer', 'institution_admin']
  const DIMENSIONS = ['CITATION_ACCURACY', 'LEGAL_RELATION', 'HARMONIZATION']
  const DECISIONS = ['ACCEPTED', 'REJECTED', 'REVISION_REQUIRED']
  const dom = id => document.getElementById(id)
  const state = {report:null, session:null, selected:null, ready:false, loading:false}
  const create = (tag, cls, text, parent) => {
    const node = document.createElement(tag)
    if (cls) node.className = cls
    if (text !== undefined && text !== null) node.textContent = text
    if (parent) parent.appendChild(node)
    return node
  }
  const isSha = value => typeof value === 'string' && /^[A-F0-9]{64}$/.test(value)
  const legalGuard = report => report &&
    report.schema === 'PI_R82_NATIONAL_PASSAGE_CANDIDATES_QA_V1' &&
    report.tenant_id === 'ID-DIY-YOG' &&
    report.r80_candidate_article_count === 7 &&
    report.passage_verification === 'UNREVIEWED_TEXT_LAYER_CANDIDATES_ONLY' &&
    report.review_status === 'REVIEW_REQUIRED' &&
    report.relation_admission === 'NOT_PERFORMED' &&
    report.legal_harmonization === 'NOT_ASSESSED' &&
    report.production_admission === 'DENIED' &&
    report.source_snapshot_integrity === 'R81_TWO_INDEPENDENT_SHA256_PINS_MATCH' &&
    report.text_candidate_article_count + report.unresolved_article_count === 7 &&
    isSha(report.r81_manifest_sha256) &&
    Array.isArray(report.candidates) && report.candidates.length === 7 &&
    report.candidates.every((candidate, index) =>
      candidate.article === ARTICLES[index] &&
      candidate.target_provision_id === 'UU-18-2008:PASAL-' + ARTICLES[index] &&
      candidate.review_status === 'REVIEW_REQUIRED' &&
      candidate.relation_admission === 'NOT_PERFORMED' &&
      candidate.legal_effect === 'NOT_ASSESSED' &&
      isSha(candidate.review_candidate_sha256) &&
      Array.isArray(candidate.paragraphs_requested) &&
      Array.isArray(candidate.uu_passages))
  const form = () => dom('r84-decision-form')
  const status = (message, warning=false) => {
    const node = dom('r84-review-status')
    if (!node) return
    node.textContent = message
    node.classList.toggle('r84-warning', warning)
  }
  const request = async (url, options={}) => fetch(url, {
    cache:'no-store', credentials:'same-origin',
    headers:{Accept:'application/json', ...(options.headers || {})},
    ...options,
  })
  const canSubmit = () => Boolean(state.ready && !state.loading &&
    state.session && REVIEW_ROLES.includes(state.session.role) &&
    state.selected && state.selected.evidence_status === 'TEXT_LAYER_CANDIDATE_REVIEW_REQUIRED')

  function syncForm() {
    const node = form()
    if (!node) return
    const allowed = canSubmit()
    node.hidden = !allowed
    const button = dom('r84-decision-submit')
    if (button) button.disabled = !allowed || !dom('r84-reviewed-original').checked
    const msg = dom('r84-write-status')
    if (msg && !allowed) {
      msg.textContent = !state.ready
        ? 'Penyimpanan keputusan belum aktif. Migrasi R84 harus disetujui dan diterapkan secara terpisah.'
        : 'Akun Anda hanya memiliki akses membaca atau kandidat belum siap untuk direview.'
    }
  }

  function sourceLink(parent, sourceId, page) {
    if (!['PP-81-2012', 'UU-18-2008'].includes(sourceId) ||
        !Number.isInteger(page) || page < 1) return
    const link = create('a', 'r84-pdf-link', 'Periksa PDF asli · halaman ' + page, parent)
    link.href = '/api/qa-regulatory-r83/national-pdf?source_id=' +
      encodeURIComponent(sourceId) + '#page=' + page
    link.target = '_blank'
    link.rel = 'noopener noreferrer'
  }

  function passage(parent, label, excerpt, sourceId) {
    const card = create('div', 'r84-source-card', null, parent)
    create('strong', 'r84-source-title', label, card)
    if (!excerpt || !isSha(excerpt.excerpt_sha256)) {
      create('p', 'r84-warning', 'Kutipan belum tersedia. Verifikasi sumber diperlukan.', card)
      return
    }
    create('small', 'r84-source-meta', 'PDF halaman ' + excerpt.pdf_page +
      ' · SHA kutipan ' + excerpt.excerpt_sha256.slice(0,16) + '…', card)
    sourceLink(card, sourceId, excerpt.pdf_page)
    create('blockquote', 'r84-quote', excerpt.excerpt, card)
    if (excerpt.truncated) {
      create('p', 'r84-warning', 'Kutipan terpotong. Baca seluruh isi pada PDF sumber.', card)
    }
  }

  function renderCandidates() {
    const menu = dom('r84-candidate-menu')
    menu.replaceChildren()
    for (const candidate of state.report.candidates) {
      const btn = create('button', 'r84-candidate-button', null, menu)
      btn.type = 'button'
      btn.dataset.candidate = candidate.target_provision_id
      btn.setAttribute('aria-pressed', String(state.selected?.target_provision_id === candidate.target_provision_id))
      create('strong', '', 'Pasal '+candidate.article+
        (candidate.paragraphs_requested.length
          ? ' ayat ('+candidate.paragraphs_requested.join('), (')+')' : ''), btn)
      create('small', '', candidate.evidence_status === 'TEXT_LAYER_CANDIDATE_REVIEW_REQUIRED'
        ? 'Teks ditemukan · perlu reviewer' : 'Belum lengkap · ABSTAIN', btn)
      btn.addEventListener('click', () => selectCandidate(candidate))
    }
  }

  function renderSelected() {
    const output = dom('r84-evidence-detail')
    output.replaceChildren()
    const candidate = state.selected
    if (!candidate) return
    create('h3', '', 'Bukti Pasal ' + candidate.article, output)
    create('p', 'r84-guard', 'Teks diekstrak otomatis dari PDF yang terkunci. Kebenaran hukum, keberlakuan dan hubungan normatif belum disahkan.', output)
    const panels = create('div', 'r84-side-by-side', null, output)
    passage(panels, 'PP 81/2012 · Rujukan dalam Menimbang', candidate.pp_reference, 'PP-81-2012')
    if (candidate.uu_passages.length === 0) {
      passage(panels, 'UU 18/2008 · Batang tubuh', null, 'UU-18-2008')
    } else {
      candidate.uu_passages.forEach(item => passage(panels,
        'UU 18/2008 · ' + (item.paragraph ? 'Ayat ('+item.paragraph+')' : 'Pasal '+candidate.article),
        item, 'UU-18-2008'))
    }
    const name = dom('r84-current-candidate')
    if (name) name.textContent = candidate.target_provision_id
    const formNode = form()
    if (formNode) formNode.reset()
    syncForm()
  }

  async function loadHistory() {
    const history = dom('r84-review-history')
    history.replaceChildren()
    if (!state.selected) return
    if (!state.ready) {
      create('p', 'r84-warning', 'Ledger belum aktif; riwayat keputusan belum dapat dibaca.', history)
      return
    }
    const selectedId = state.selected.target_provision_id
    create('p', '', 'Memuat riwayat keputusan…', history)
    try {
      const response = await request(R84 + '?candidate_id=' + encodeURIComponent(selectedId))
      if (state.selected.target_provision_id !== selectedId) return
      if (!response.ok) throw Error('Riwayat belum tersedia (HTTP ' + response.status + ')')
      const data = await response.json()
      if (data.schema !== 'PI_R84_CONTROLLED_RELATION_REVIEW_V1' ||
        data.tenant_id !== state.session.tenant_id ||
        data.relation_promotion !== 'NOT_PERFORMED' ||
        data.production_admission !== 'DENIED' || !Array.isArray(data.history)) {
        throw Error('Kontrak ledger R84 tidak sesuai')
      }
      history.replaceChildren()
      if (data.history.length === 0) {
        create('p', 'r84-empty', 'Belum ada keputusan reviewer untuk kandidat ini.', history)
      }
      for (const entry of data.history) {
        const record = create('article', 'r84-history-item', null, history)
        create('strong', '', entry.review_dimension + ' · ' + entry.decision, record)
        create('small', '', (entry.reviewer?.username || 'Reviewer') + ' · ' +
          (entry.decided_at || 'Waktu belum tercatat'), record)
        create('p', '', entry.reason || '', record)
        if (entry.candidate_sha256 !== state.selected.review_candidate_sha256) {
          create('p', 'r84-warning', 'Riwayat menggunakan fingerprint kandidat berbeda; periksa versi bukti.', record)
        }
      }
    } catch (error) {
      history.replaceChildren()
      create('p', 'r84-warning', error.message || 'Tidak dapat memuat ledger.', history)
    }
  }

  async function selectCandidate(candidate) {
    state.selected = candidate
    renderCandidates()
    renderSelected()
    await loadHistory()
  }

  async function init() {
    if (!dom('r84-review-workspace')) return
    status('Memeriksa akun, sumber R82 dan status migrasi R84…')
    try {
      const sessionResponse = await request('/api/auth/session')
      if (!sessionResponse.ok) throw Error('Sesi login berakhir. Masuk kembali ke PI untuk membuka review.')
      const user = await sessionResponse.json()
      if (user.status !== 'AUTHENTICATED' || user.tenant_id !== 'ID-DIY-YOG' ||
        typeof user.csrf !== 'string' || user.csrf.length < 10) {
        throw Error('Sesi PI atau token CSRF tidak valid')
      }
      state.session = user
      const evidenceResponse = await request(R82)
      if (!evidenceResponse.ok) throw Error('Laporan R82 belum tersedia (HTTP ' + evidenceResponse.status + ')')
      const report = await evidenceResponse.json()
      if (!legalGuard(report)) throw Error('Kandidat atau pengaman R82 tidak konsisten')
      state.report = report

      // A source-only deployment is *not* authority to run the database migration.
      const health = await request(R84 + '?candidate_id=UU-18-2008:PASAL-16')
      state.ready = false
      if (health.ok) {
        const data = await health.json()
        state.ready = data.schema === 'PI_R84_CONTROLLED_RELATION_REVIEW_V1' &&
          data.review_storage_state === 'READY' &&
          data.tenant_id === user.tenant_id &&
          data.relation_promotion === 'NOT_PERFORMED' &&
          data.production_admission === 'DENIED'
      } else if (health.status === 503) {
        state.ready = false
      } else {
        throw Error('Ledger R84 menolak permintaan (HTTP ' + health.status + ')')
      }

      const summary = '7 kandidat R82 · ' + report.text_candidate_article_count +
        ' kutipan terpetakan · ' + (state.ready ? 'ledger siap' : 'ledger menunggu migrasi')
      status(summary, !state.ready)
      await selectCandidate(report.candidates[0])
    } catch (error) {
      status('R84: ' + (error.message || 'Tidak dapat membuka ruang reviewer'), true)
      state.ready = false
      syncForm()
    }
  }

  async function submit(event) {
    event.preventDefault()
    if (!canSubmit() || !dom('r84-reviewed-original').checked) return
    const candidate = state.selected
    const dimension = dom('r84-review-dimension').value
    const decision = dom('r84-review-decision').value
    const reason = dom('r84-review-reason').value.trim()
    const notice = dom('r84-write-status')
    if (!DIMENSIONS.includes(dimension) || !DECISIONS.includes(decision) ||
      reason.length < 12 || reason.length > 2000 || !isSha(candidate.review_candidate_sha256)) {
      notice.textContent = 'Lengkapi dimensi, keputusan dan alasan (12–2.000 karakter).'
      return
    }
    const selectedId = candidate.target_provision_id
    state.loading = true
    syncForm()
    notice.textContent = 'Merekam keputusan reviewer…'
    try {
      const response = await request(R84, {
        method:'POST',
        headers:{'Content-Type':'application/json', 'X-CSRF-TOKEN':state.session.csrf},
        body:JSON.stringify({
          candidate_id:selectedId,
          review_dimension:dimension,
          decision:decision,
          reason,
          source_manifest_sha256:state.report.r81_manifest_sha256,
          candidate_sha256:candidate.review_candidate_sha256
        })
      })
      if (!response.ok) {
        if ([401,419].includes(response.status)) throw Error('Sesi login/CSRF habis. Login ulang dan periksa status ledger sebelum mencoba kembali.')
        if (response.status === 403) throw Error('Akun tidak diizinkan menyimpan keputusan.')
        if (response.status === 422) throw Error('Data keputusan tidak valid. Periksa kolom dan alasan.')
        if (response.status === 503) throw Error('Migrasi atau integritas sumber belum siap. Keputusan tidak disimpan.')
        throw Error('Penyimpanan tidak terkonfirmasi (HTTP '+response.status+'). Periksa riwayat sebelum mengulangi.')
      }
      const result = await response.json()
      if (response.status !== 201 ||
        result.schema !== 'PI_R84_CONTROLLED_RELATION_REVIEW_V1' ||
        result.relation_promotion !== 'NOT_PERFORMED' ||
        result.production_admission !== 'DENIED' ||
        result.review?.candidate_id !== selectedId) {
        throw Error('Respons tidak membuktikan ledger menerima keputusan. Periksa riwayat.')
      }
      notice.textContent = 'Keputusan tercatat pada ledger. Tidak ada promosi relasi atau persetujuan otomatis.'
      if (state.selected?.target_provision_id === selectedId) {
        form().reset()
        await loadHistory()
      }
    } catch (error) {
      notice.textContent = error.message || 'Penyimpanan belum terkonfirmasi'
    } finally {
      state.loading = false
      syncForm()
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (!dom('r84-review-workspace')) return
    form().addEventListener('submit', submit)
    dom('r84-reviewed-original').addEventListener('change', syncForm)
    dom('r84-review-refresh').addEventListener('click', init)
    init()
  })
})()
