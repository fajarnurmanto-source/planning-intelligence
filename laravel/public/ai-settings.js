/* PI administrator-only provider settings. Never store API keys in client storage. */
(() => {
  'use strict'
  const el = id => document.getElementById(id)
  const allowed = new Set(['gemini', 'deepseek', 'groq', 'openrouter', 'openai'])
  const errors = {
    AI_SETTINGS_ADMIN_REQUIRED: 'Hanya superadmin atau administrator institusi yang dapat mengelola API Key.',
    AI_SETTINGS_HTTPS_REQUIRED: 'Gunakan HTTPS untuk mengelola kunci dari luar komputer lokal.',
    ADMIN_PASSWORD_INVALID: 'Kata sandi akun PI tidak benar.',
    API_KEY_REQUIRED: 'Masukkan API Key baru untuk penyedia ini.',
    MODEL_OR_KEY_INVALID: 'Format nama model atau API Key tidak valid.',
    PROVIDER_UNSUPPORTED: 'Penyedia ini belum didukung.',
  }
  let csrf = ''
  let providers = []

  function feedback(message, bad = false) {
    el('feedback').textContent = message
    el('feedback').classList.toggle('bad', bad)
  }

  async function api(path, options = {}) {
    const response = await fetch(path, {
      credentials: 'same-origin', cache: 'no-store', ...options,
      headers: {...(options.headers || {}), ...(options.method && options.method !== 'GET'
        ? {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-PI-CSRF': csrf} : {})},
    })
    const data = await response.json()
    if (!response.ok) throw new Error(errors[data.code] || data.code || `HTTP ${response.status}`)
    return data
  }

  function render() {
    const selected = el('provider').value
    const info = providers.find(item => item.id === selected)
    el('state').textContent = info?.configured ? 'Siap dikonfigurasi untuk inferensi' : 'Belum lengkap'
    const source = {encrypted_vault:'Kunci tersimpan terenkripsi',environment:'Kunci dari environment server',none:'Belum ada kunci'}
    el('source').textContent = `${source[info?.source] || 'Belum terkonfigurasi'} · Koneksi model belum diuji dari halaman ini.`
    el('model').value = info?.model || ''
    el('apiKey').value = ''
    el('password').value = ''
    el('remove').disabled = !info?.stored_config
  }

  function busy(active) {
    for (const id of ['save','remove','provider','model','apiKey','password']) el(id).disabled = active
  }

  async function refresh() {
    const state = await api('/api/ai-r69a/provider-settings')
    providers = Array.isArray(state.providers) ? state.providers : []
    render()
  }

  async function modify(method) {
    const provider = el('provider').value
    if (!allowed.has(provider)) return feedback('Penyedia tidak dikenal.', true)
    const password = el('password').value
    if (!password) return feedback('Masukkan kata sandi akun PI terlebih dahulu.', true)
    if (location.protocol !== 'https:' && !['127.0.0.1','localhost','[::1]'].includes(location.hostname)) {
      return feedback('Pengelolaan kunci hanya diizinkan lewat HTTPS atau localhost.', true)
    }
    if (method === 'DELETE' && !confirm('Hapus konfigurasi tersimpan untuk penyedia ini?')) return
    const payload = method === 'PUT'
      ? {model:el('model').value.trim(),api_key:el('apiKey').value,password}
      : {password}
    busy(true)
    try {
      const state = await api(`/api/ai-r69a/provider-settings/${provider}`, {method, body:JSON.stringify(payload)})
      providers = Array.isArray(state.providers) ? state.providers : []
      render()
      feedback(method === 'DELETE' ? 'Konfigurasi terenkripsi dihapus.' : 'Konfigurasi tersimpan terenkripsi. API Key tidak ditampilkan kembali.')
    } catch (error) {
      feedback(String(error.message || error), true)
    } finally {
      el('apiKey').value = ''
      el('password').value = ''
      busy(false)
      renderRemoveButton()
    }
  }

  function renderRemoveButton() {
    const info = providers.find(item => item.id === el('provider').value)
    el('remove').disabled = !info?.stored_config
  }

  async function initialize() {
    const session = await api('/api/auth/session')
    if (!['superadmin','institution_admin'].includes(session.role)) {
      el('accessFeedback').textContent = 'Akun ini tidak memiliki izin administrasi AI.'
      return
    }
    csrf = String(session.csrf || '')
    if (!csrf) throw new Error('Sesi tidak menyediakan token CSRF. Masuk kembali.')
    el('accessNotice').hidden = true
    el('settingsPanel').hidden = false
    el('provider').addEventListener('change', render)
    el('save').addEventListener('click', () => modify('PUT'))
    el('remove').addEventListener('click', () => modify('DELETE'))
    await refresh()
    feedback('Pengaturan siap. Tidak ada kunci API yang dikirim ke browser.')
  }
  document.addEventListener('DOMContentLoaded', () => initialize().catch(error => {
    el('settingsPanel').hidden = true
    el('accessNotice').hidden = false
    el('accessFeedback').textContent = String(error.message || error)
  }))
})()
