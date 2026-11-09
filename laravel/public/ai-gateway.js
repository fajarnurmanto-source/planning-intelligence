/* PI Unified AI Gateway. Requests are initiated only by user clicks. */
(() => {
  'use strict'
  const el = id => document.getElementById(id)
  let providers = []
  let context = null
  let output = null

  const errors = {
    PROVIDER_NOT_CONFIGURED: 'API key atau model provider belum tersedia di backend.',
    CLOUD_RESPONSE_UNAVAILABLE: 'Provider cloud belum merespons atau akses ditolak.',
    CLOUD_OUTPUT_CONTRACT_FAILED: 'Model tidak menghasilkan format telaah yang dipersyaratkan.',
    CLOUD_UNSUPPORTED_EVIDENCE: 'Jawaban mengandung referensi yang tidak ada dalam bukti resmi.',
    LOCAL_AI_PROVIDER_UNAVAILABLE: 'Ollama atau model lokal belum tersedia.',
    LOCAL_AI_INFERENCE_UNAVAILABLE: 'Inferensi lokal tidak dapat dijalankan.',
    AI_EVIDENCE_CONTEXT_UNAVAILABLE: 'Paket bukti regulasi belum siap.',
    AI_REVIEW_BRIEF_UNAVAILABLE: 'Paket telaah deterministik belum siap.',
  }

  function feedback(message, error = false) {
    el('feedback').textContent = message
    el('feedback').classList.toggle('bad', error)
  }

  async function getJson(path) {
    const response = await fetch(path, {credentials: 'same-origin', cache: 'no-store'})
    const data = await response.json()
    if (!response.ok) throw new Error(errors[data.code] || data.code || `HTTP ${response.status}`)
    return data
  }

  async function postJson(path, payload) {
    const session = await getJson('/api/auth/session')
    if (typeof session.csrf !== 'string' || session.csrf === '') {
      throw new Error('Sesi tidak menyediakan token CSRF. Masuk kembali.')
    }
    const response = await fetch(path, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: {'Content-Type': 'application/json', 'X-PI-CSRF': session.csrf},
      body: JSON.stringify(payload),
    })
    const data = await response.json()
    if (!response.ok) throw new Error(errors[data.code] || data.code || `HTTP ${response.status}`)
    return data
  }

  function clearApproval() {
    el('approvedFocus').checked = false
    el('approvedEvidence').checked = false
    el('approvedLocal').checked = false
    el('briefView').hidden = true
    el('emptyResult').hidden = false
    el('resultStatus').textContent = 'BELUM DIJALANKAN'
    output = null
  }

  function listItems(id, items) {
    const list = el(id)
    list.replaceChildren()
    for (const item of items) {
      const li = document.createElement('li')
      li.textContent = String(item)
      list.appendChild(li)
    }
  }

  function renderContext() {
    const list = el('sourceList')
    list.replaceChildren()
    if (!context || !Array.isArray(context.documents)) {
      el('sourceSummary').textContent = 'Belum ada konteks regulasi yang tervalidasi.'
      el('publicPreview').textContent = '{}'
      return
    }
    const docs = context.documents
    el('sourceSummary').textContent = `${docs.length} dokumen tersedia untuk telaah. Bukti dokumen tetap perlu diperiksa reviewer.`
    const metadata = []
    for (const doc of docs) {
      const li = document.createElement('li')
      const title = `${doc.source_id || 'Sumber'} · ${doc.title || 'Tanpa judul'}`
      const url = typeof doc.source_url === 'string' ? doc.source_url : ''
      if (/^https:\/\//i.test(url)) {
        const anchor = document.createElement('a')
        anchor.href = url
        anchor.target = '_blank'
        anchor.rel = 'noopener noreferrer'
        anchor.textContent = title
        li.appendChild(anchor)
      } else {
        li.textContent = title
      }
      list.appendChild(li)
      metadata.push({
        source_id: doc.source_id, title: doc.title, official_url: url,
        available_evidence_anchors: Array.isArray(doc.evidence_anchors) ? doc.evidence_anchors.length : 0,
        sample_public_evidence_refs: Array.isArray(doc.evidence_anchors)
          ? doc.evidence_anchors.slice(0, 5).map(ref => ({
              source_id: ref.source_id,
              pdf_page: ref.pdf_page,
              excerpt_sha256: ref.excerpt_sha256,
            })) : [],
      })
    }
    el('publicPreview').textContent = JSON.stringify({
      focus_for_this_request: el('focus').value.trim(),
      public_regulatory_sources_preview: metadata,
      note: 'Backend menyaring lagi referensi bukti saat request; ini bukan payload byte-identik.',
    }, null, 2)
  }

  function refreshProvider() {
    clearApproval()
    const id = el('provider').value
    const remote = id !== 'ollama'
    el('cloudArea').hidden = !remote
    el('localArea').hidden = remote
    el('currentMode').textContent = remote ? 'CLOUD · OPT-IN' : 'LOCAL · OPT-IN'
    const status = providers.find(item => item.id === id)
    el('providerModel').textContent = status?.model || 'Belum dikonfigurasi'
    el('providerState').textContent = status?.configured
      ? 'Konfigurasi tersedia; koneksi ke model belum diuji.'
      : `Belum siap: ${status?.reason || 'status provider belum tersedia'}`
    feedback('Periksa sumber dan berikan persetujuan untuk setiap inferensi.')
  }

  function renderResult(response, deterministic = false) {
    const brief = deterministic ? {
      status: 'REVIEW_REQUIRED',
      summary: `Paket telaah deterministik tersedia untuk ${response.sources?.length || 0} sumber; belum ada analisis AI.`,
      uncertainties: ['Keselarasan norma, legalitas, dan perbandingan numerik belum dinilai.'],
      questions_for_reviewer: response.review_questions || [],
      evidence_refs: (response.sources || []).flatMap(source =>
        (source.evidence_anchors || []).slice(0, 5)),
    } : response.brief
    if (!brief || !['REVIEW_REQUIRED', 'ABSTAIN'].includes(brief.status)
      || typeof brief.summary !== 'string' || !Array.isArray(brief.uncertainties)
      || !Array.isArray(brief.questions_for_reviewer) || !Array.isArray(brief.evidence_refs)) {
      throw new Error('Hasil tidak memenuhi kontrak telaah PI.')
    }
    el('briefSummary').textContent = brief.summary
    listItems('briefUncertainties', brief.uncertainties)
    listItems('briefQuestions', brief.questions_for_reviewer)
    const proofs = brief.evidence_refs.map(ref => {
      const id = String(ref.source_id || 'SUMBER')
      const page = Number.isInteger(ref.pdf_page) ? ref.pdf_page : '?'
      const hash = String(ref.excerpt_sha256 || '')
      return `${id} · halaman ${page} · ${hash.slice(0, 12)}…`
    })
    listItems('briefProofs', proofs)
    el('briefMeta').textContent = deterministic
      ? 'Paket deterministik · tanpa inferensi AI · review manusia wajib'
      : `Provider: ${response.provider} · Model: ${response.model} · review manusia wajib`
    el('resultStatus').textContent = deterministic ? 'PAKET BUKTI' : `DRAF ${brief.status}`
    el('resultSub').textContent = deterministic
      ? 'Paket dari Laravel, tanpa permintaan provider.'
      : 'Draf model belum diverifikasi sebagai temuan legal atau numerik.'
    el('emptyResult').hidden = true
    el('briefView').hidden = false
    output = response
  }

  async function showDeterministicPacket() {
    const button = el('reviewButton')
    button.disabled = true
    try {
      const result = await getJson('/api/ai-r69a/review-brief')
      renderResult(result, true)
      feedback('Paket bukti berhasil dibuka tanpa memanggil AI.')
    } catch (error) {
      feedback(String(error.message || error), true)
    } finally { button.disabled = false }
  }

  async function requestInference() {
    if (!context || !Array.isArray(context.documents) || context.documents.length === 0) {
      feedback('Tidak ada paket bukti yang siap untuk telaah.', true)
      return
    }
    const provider = el('provider').value
    const focus = el('focus').value.trim()
    const remote = provider !== 'ollama'
    const status = providers.find(item => item.id === provider)
    if (!status?.configured) {
      feedback('Provider belum dikonfigurasi. Pilih provider lain atau atur model/API key pada backend.', true)
      return
    }
    if (remote && (!focus || !el('approvedFocus').checked || !el('approvedEvidence').checked)) {
      feedback('Cloud memerlukan pertanyaan serta dua persetujuan eksplisit yang berbeda.', true)
      return
    }
    if (!remote && !el('approvedLocal').checked) {
      feedback('Setujui inferensi lokal untuk permintaan ini.', true)
      return
    }
    const button = el('generateButton')
    button.disabled = true
    feedback(remote ? 'Mengirim metadata publik tersaring ke provider yang dipilih…' : 'Memanggil Ollama lokal…')
    try {
      const endpoint = remote ? '/api/ai-r69a/cloud-inference' : '/api/ai-r69a/local-inference'
      const payload = remote
        ? {provider, focus, approved_focus: true, approved_public_evidence: true}
        : {provider: 'ollama', focus, consent: true}
      const result = await postJson(endpoint, payload)
      renderResult(result)
      feedback('Draf telaah telah diterima. Validasi bukti tetap menjadi tanggung jawab reviewer.')
    } catch (error) {
      feedback(`Inferensi gagal: ${String(error.message || error)}`, true)
    } finally {
      button.disabled = false
      el('approvedFocus').checked = false
      el('approvedEvidence').checked = false
      el('approvedLocal').checked = false
    }
  }

  function download() {
    if (!output) return
    const blob = new Blob([JSON.stringify(output, null, 2) + '\n'], {type: 'application/json'})
    const href = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = href
    anchor.download = 'PI_AI_Review_Draft_NOT_APPROVED.json'
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
    setTimeout(() => URL.revokeObjectURL(href), 1200)
  }

  async function initialize() {
    el('provider').addEventListener('change', refreshProvider)
    el('focus').addEventListener('input', () => {clearApproval(); renderContext()})
    el('reviewButton').addEventListener('click', showDeterministicPacket)
    el('generateButton').addEventListener('click', requestInference)
    el('downloadResult').addEventListener('click', download)
    try {
      const [status, sources] = await Promise.all([
        getJson('/api/ai-r69a/status'), getJson('/api/ai-r69a/evidence-context'),
      ])
      providers = Array.isArray(status.providers) ? status.providers : []
      context = sources
      renderContext()
      refreshProvider()
      feedback('Gateway Laravel tersambung. Tidak ada inferensi yang dijalankan.')
    } catch (error) {
      renderContext()
      refreshProvider()
      feedback(`Konteks AI belum tersedia: ${String(error.message || error)}`, true)
    }
  }
  initialize()
})()
