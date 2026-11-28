(() => {
  'use strict'
  const el = id => document.getElementById(id)
  let needsSetup = false
  let csrf = ''
  let initialized = false
  const safeMessages = new Set(['Status autentikasi tidak tersedia','Status sesi login tidak lengkap','Login ditolak. Ketik ulang kata sandi secara manual; periksa sandi yang tersimpan di browser.','Login diterima, tetapi sesi browser belum aktif. Periksa cookie situs lokal lalu coba lagi.','Nama pengguna atau kata sandi belum dapat diverifikasi.','Terlalu banyak percobaan. Tunggu sebelum mencoba kembali.','Sesi perlu diperbarui. Muat ulang halaman lalu coba lagi.','Akses ditolak. Hubungi pengelola akun.'])
  const showError = error => { const message=error?.message; el('feedback').textContent=safeMessages.has(message)?message:'Akses belum dapat diproses. Periksa sambungan layanan atau muat ulang halaman.' }
  async function initialize() {
    try {
      const response = await fetch('/api/auth/setup-status', {cache:'no-store'})
      if (!response.ok) throw new Error('Status autentikasi tidak tersedia')
      const status = await response.json()
      needsSetup = Boolean(status.needs_setup)
      csrf = String(status.csrf || '')
      if (!csrf || typeof status.needs_setup !== 'boolean') throw new Error('Status sesi login tidak lengkap')
      el('heading').textContent = needsSetup ? 'Siapkan akun administrator' : 'Masuk ke ruang kerja'
      el('description').textContent = needsSetup
        ? 'Siapkan satu akun untuk ruang kerja lokal Kota Yogyakarta. Data akun tidak dikirim ke penyedia AI.'
        : 'Masukkan nama pengguna dan kata sandi akun lokal Planning Intelligence.'
      el('password-hint').textContent = needsSetup ? '· minimal 12 karakter' : ''
      el('password').autocomplete = needsSetup ? 'new-password' : 'current-password'
      el('confirm-label').hidden = !needsSetup
      el('confirm-password').hidden = !needsSetup
      el('confirm-password').required = needsSetup
      el('submit').textContent = needsSetup ? 'Buat akun dan masuk' : 'Masuk'
      initialized = true
      el('submit').disabled = false
    } catch (error) { showError(error) }
  }
  async function submit(event) {
    event.preventDefault()
    if (!initialized) return
    el('feedback').textContent = ''
    const username = el('username').value.trim(), password = el('password').value
    if (needsSetup && password !== el('confirm-password').value) {
      el('feedback').textContent = 'Pengulangan kata sandi tidak sama.'; return
    }
    const submitButton = el('submit'); submitButton.disabled = true
    try {
      const response = await fetch(needsSetup ? '/api/auth/bootstrap' : '/api/auth/login', {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf}, credentials:'same-origin',
        body:JSON.stringify({username,password}),
      })
      const result = await response.json()
      if (!response.ok) {
        if (result.code === 'INVALID_CREDENTIALS') {
          // Do not permit a potentially stale browser-autofilled password to
          // be submitted repeatedly. Never log or display entered credentials.
          el('password').value = ''
          el('password').focus()
          throw new Error('Login ditolak. Ketik ulang kata sandi secara manual; periksa sandi yang tersimpan di browser.')
        }
        throw new Error(response.status===429?'Terlalu banyak percobaan. Tunggu sebelum mencoba kembali.':response.status===419?'Sesi perlu diperbarui. Muat ulang halaman lalu coba lagi.':response.status===403?'Akses ditolak. Hubungi pengelola akun.':'Nama pengguna atau kata sandi belum dapat diverifikasi.')
      }
      // A successful POST alone is not enough. Verify that the browser sent
      // back its new authenticated cookie before navigating to the workspace.
      const check = await fetch('/api/auth/session', {
        credentials: 'same-origin', cache: 'no-store',
        headers: {'Accept': 'application/json'},
      })
      const verified = check.ok ? await check.json() : null
      if (!verified || verified.status !== 'AUTHENTICATED'
          || verified.username !== username.toLowerCase()) {
        throw new Error('Login diterima, tetapi sesi browser belum aktif. Periksa cookie situs lokal lalu coba lagi.')
      }
      location.replace('./index.html')
    } catch (error) { showError(error) }
    finally { submitButton.disabled = false }
  }
  document.addEventListener('DOMContentLoaded', () => {
    el('login-form').addEventListener('submit', submit)
    el('toggle-password').addEventListener('click', () => {
      const password = el('password')
      const showing = password.type === 'password'
      password.type = showing ? 'text' : 'password'
      el('toggle-password').textContent = showing ? 'Sembunyikan sandi' : 'Lihat sandi'
      el('toggle-password').setAttribute('aria-pressed', String(showing))
    })
    initialize()
  })
})()
