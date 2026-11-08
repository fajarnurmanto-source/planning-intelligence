/* R69A UI: only curated public evidence leaves this browser by explicit click. */
const el = id => document.getElementById(id)
let statuses = []
let preview = null
let result = null
let resultProofs = []
const labels = {
  API_KEY_OR_MODEL_NOT_CONFIGURED: 'API key atau model belum dikonfigurasi pada backend.',
  MODEL_NOT_CONFIGURED_OR_INVALID: 'Nama model belum dikonfigurasi atau tidak valid.',
  COMPAT_PROVIDER_REQUIRES_EXPLICIT_HTTPS_HOST_ALLOWLIST: 'Endpoint kompatibel perlu HTTPS dan daftar host yang diizinkan.',
  CLOUD_EVIDENCE_TRANSFER_REQUIRES_EXPLICIT_CONSENT: 'Centang persetujuan pengiriman data publik untuk permintaan ini.',
  PROVIDER_CONNECTION_OR_AUTH_FAILED: 'Model belum dapat dihubungi, koneksi gagal, atau kredensial ditolak.',
  MODEL_JSON_CONTRACT_FAILED: 'Keluaran model tidak memenuhi format JSON wajib; tidak disulap menjadi keputusan.',
  MODEL_EVIDENCE_REF_NOT_SUPPORTED: 'AI mengutip sumber di luar bukti resmi. Keluaran ditolak.',
  MODEL_MISSING_DECISION_FIELDS: 'AI belum menyediakan seluruh unsur rekomendasi yang wajib.',
  OLLAMA_MUST_BE_LOCAL_LOOPBACK: 'Ollama wajib menggunakan server lokal loopback.',
}

function setFeedback(message, bad = false) {
  el('feedback').textContent = message
  el('feedback').classList.toggle('bad', bad)
}
function itemList(targetId, entries, ordered = false) {
  const target = el(targetId)
  target.replaceChildren()
  for (const entry of entries) {
    const li = document.createElement('li')
    li.textContent = entry
    target.appendChild(li)
  }
}
function link(text, href) {
  const a = document.createElement('a')
  a.textContent = text + ' ↗'
  a.href = href
  a.target = '_blank'
  a.rel = 'noopener noreferrer'
  return a
}
function proofHref(p) {
  if (typeof p.pdf_href === 'string' && /^sources_r6[78]\/[a-z0-9_.-]+\.pdf#page=[0-9]+$/.test(p.pdf_href)) {
    return './' + p.pdf_href
  }
  return null
}
function renderEvidence() {
  if (!preview) return
  const p = structuredClone(preview)
  p.user_focus_question = el('focus').value.trim()
  el('findingName').textContent = p.finding.title
  el('findingObs').textContent = p.finding.observation
  el('evidenceJson').textContent = JSON.stringify(p, null, 2)
  el('evidenceRefs').replaceChildren()
  for (const source of p.finding.proofs) {
    const href = proofHref(source)
    if (href) el('evidenceRefs').appendChild(link(`${source.doc} · PDF h.${source.page}`, href))
  }
}
async function fetchJson(url, options) {
  if (options?.method === 'POST') {
    const identity = await fetch('/api/auth/session', {cache:'no-store'})
    if (!identity.ok) throw new Error('Sesi berakhir. Silakan masuk kembali.')
    const user = await identity.json()
    options = {...options, headers:{...(options.headers || {}), 'X-PI-CSRF':user.csrf}}
  }
  const r = await fetch(url, {cache:'no-store', ...options})
  const data = await r.json()
  if (!r.ok) throw new Error(labels[data.code] || data.code || 'API tidak tersedia')
  return data
}
function refreshProvider() {
  const provider = el('provider').value
  const remote = provider !== 'ollama'
  el('cloudArea').hidden = !remote
  el('currentMode').textContent = remote ? 'CLOUD API · OPT-IN' : 'LOCAL DEVICE'
  const record = statuses.find(s => s.id === provider)
  el('providerModel').textContent = record?.model || 'Model belum diatur'
  el('providerState').textContent = !record ? 'Status backend tidak tersedia' :
    record.configured ? 'Konfigurasi tersedia · koneksi/model belum diuji' : (labels[record.reason] || record.reason)
  el('apiHelp').textContent = remote
    ? 'Model cloud hanya menerima ringkasan bukti publik yang terlihat di sebelah kanan, jika Anda menyetujui tiap permintaan. Panggilan API dapat dikenai biaya sesuai akun Anda.'
    : 'Ollama harus berjalan di komputer ini dan model tersedia lokal. Tidak ada panggilan cloud dan tidak ada pengunduhan model otomatis.'
  el('cloudConsent').checked = false
  setFeedback('Belum menjalankan AI. Periksa bukti dan pilih model sebelum meminta rekomendasi.')
}
async function refreshEvidence() {
  preview = null
  result = null
  resultProofs = []
  el('emptyResult').hidden = false
  el('briefView').hidden = true
  el('resultStatus').textContent = 'BELUM DIJALANKAN'
  el('resultSub').textContent = 'Pilih bukti dan model, kemudian minta opsi keputusan baru.'
  el('findingName').textContent = 'Memuat…'
  try {
    const res = await fetchJson('/api/ai-r69a/evidence?finding_id=' + encodeURIComponent(el('finding').value))
    preview = res.payload
    renderEvidence()
  } catch (e) {
    el('findingName').textContent = 'Bukti tidak tersedia'
    el('findingObs').textContent = String(e.message || e)
  }
}
function renderBrief(report) {
  const b = report.brief
  el('emptyResult').hidden = true
  el('briefView').hidden = false
  el('briefSummary').textContent = b.summary
  el('briefRecommendation').textContent = b.recommendation
  itemList('briefAlternatives', b.alternatives)
  itemList('briefUncertainties', b.uncertainties)
  itemList('briefQuestions', b.questions_for_reviewer)
  const ul = el('briefProofs')
  ul.replaceChildren()
  for (const ref of b.evidence_refs) {
    const li = document.createElement('li')
    const source = resultProofs.find(p => p.doc === ref.doc && p.page === ref.page)
    const href = source && proofHref(source)
    if (href) li.appendChild(link(`${ref.doc} · h.${ref.page}`, href))
    else li.textContent = `${ref.doc} · h.${ref.page}`
    ul.appendChild(li)
  }
  el('resultStatus').textContent = b.status === 'ABSTAIN' ? 'ABSTAIN · REVIEW REQUIRED' : 'DRAF · REVIEW REQUIRED'
  el('briefMeta').textContent = `Penyedia: ${report.provider} · Model: ${report.model} · Prioritas: ${b.priority} · Keyakinan model: ${b.confidence} · Validasi halaman: ya, validasi isi: belum · Human signoff: NONE`
  el('resultSub').textContent = 'Respons model secara nyata diterima; ini bukan keputusan yang disahkan.'
}
async function requestBrief() {
  const provider = el('provider').value
  const remote = provider !== 'ollama'
  if (!preview) { setFeedback('Pratinjau data belum siap.', true); return }
  if (remote && !el('cloudConsent').checked) {
    setFeedback('Persetujuan eksplisit diperlukan sebelum bukti publik dikirim ke API cloud.', true)
    return
  }
  const button = el('generateButton')
  const originalEvidenceProofs = preview.finding.proofs.slice()
  button.disabled = true
  el('provider').disabled = true
  el('finding').disabled = true
  el('focus').disabled = true
  button.textContent = 'Meminta analisis…'
  setFeedback(remote ? 'Mengirim hanya data publik yang tampil pada pratinjau ke API pilihan Anda…' : 'Meminta inferensi pada server Ollama lokal…')
  try {
    const body = {provider, finding_id:el('finding').value, focus:el('focus').value.trim(), cloud_consent:remote && el('cloudConsent').checked}
    const report = await fetchJson('/api/ai-r69a/brief', {
      method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body),
    })
    result = report
    resultProofs = originalEvidenceProofs
    renderBrief(report)
    setFeedback('Draf rekomendasi diterima. Periksa seluruh klaim terhadap sumber; belum ada keputusan resmi.')
  } catch(e) {
    setFeedback('Permintaan tidak menghasilkan draf AI yang sah: ' + String(e.message || e), true)
  } finally {
    button.disabled = false
    el('provider').disabled = false
    el('finding').disabled = false
    el('focus').disabled = false
    button.textContent = '✦ Susun opsi keputusan'
    el('cloudConsent').checked = false
  }
}
function downloadResult() {
  if (!result) return
  const blob = new Blob([JSON.stringify(result, null, 2)+'\n'],{type:'application/json'})
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url; a.download = 'PI_R69A_AI_Decision_Draft_NOT_APPROVED.json'
  document.body.appendChild(a);a.click();a.remove()
  window.setTimeout(()=>URL.revokeObjectURL(url),1200)
}
async function initialize() {
  el('provider').addEventListener('change',refreshProvider)
  el('finding').addEventListener('change',refreshEvidence)
  el('focus').addEventListener('input',renderEvidence)
  el('previewButton').addEventListener('click',()=>{renderEvidence();el('evidenceJson').closest('details').open=true})
  el('generateButton').addEventListener('click',requestBrief)
  el('downloadResult').addEventListener('click',downloadResult)
  try {statuses = (await fetchJson('/api/ai-r69a/status')).providers}
  catch(e){setFeedback('Gateway AI belum tersambung: ' + String(e.message || e),true)}
  refreshProvider()
  await refreshEvidence()
}
initialize()
