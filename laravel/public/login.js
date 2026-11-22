(() => {
  'use strict'
  const el = id => document.getElementById(id)
  let needsSetup = false
  let csrf = ''
  async function initialize() {
    try {
      const response = await fetch('/api/auth/setup-status', {cache:'no-store'})
      if (!response.ok) throw new Error('Status autentikasi tidak tersedia')
      const status = await response.json()
      needsSetup = Boolean(status.needs_setup)
      csrf = String(status.csrf || '')
      el('heading').textContent = needsSetup ? 'Buat akun administrator QA' : 'Masuk ke ruang kerja'
      el('description').textContent = needsSetup
        ? 'Buat satu akun khusus pratinjau Kota Yogyakarta. Data akun tidak dikirim ke penyedia AI.'
        : 'Masukkan nama pengguna dan kata sandi akun lokal Planning Intelligence.'
      el('password-hint').textContent = needsSetup ? '· minimal 12 karakter' : ''
      el('password').autocomplete = needsSetup ? 'new-password' : 'current-password'
      el('confirm-label').hidden = !needsSetup
      el('confirm-password').hidden = !needsSetup
      el('confirm-password').required = needsSetup
      el('submit').textContent = needsSetup ? 'Buat akun dan masuk' : 'Masuk'
    } catch (error) { el('feedback').textContent = String(error.message || error) }
  }
  async function submit(event) {
    event.preventDefault()
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
        throw new Error(result.code || 'Autentikasi ditolak')
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
    } catch (error) { el('feedback').textContent = String(error.message || error) }
    finally { submitButton.disabled = false }
  }
  document.addEventListener('DOMContentLoaded', () => {el('login-form').addEventListener('submit', submit);initialize()})
})()
