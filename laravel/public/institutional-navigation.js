/* PI R69C: QA-only legacy compiled-app navigation for institutional workspace.
 * Canonical React source overlay is included separately for a future rebuild.
 * This DOM facade is NOT tenant authentication or access-control enforcement.
 */
(() => {
  const query = new URLSearchParams(window.location.search)
  if (query.get('pi_dev_nav') === '1') return // Developer-only QA inspection, not authorization.
  const requestedSection = query.get('pi_section')
  const sections = {
    regulasi: /^PI Regulatory R\d+$/,
    perencanaan: /^PI Planning Bridge R63$/,
    taksonomi: /^PI Taxonomy R64$/,
    kinerja: /^PI Cascading R65$/,
    renstra: /^PI Renstra[–-]Renja R66$/,
    penganggaran: /^PI Annual Chain R68$/,
  }
  let appliedRequestedSection = false
  // "PI Multi-Pemda R67" is deliberately excluded from institution user menus.
  const rules = [
    [/^PI Superior R9$/, 'Beranda', 10],
    [/^PI Regulatory R\d+$/, 'Regulasi', 15],
    [/^PI Planning Bridge R63$/, 'Perencanaan', 20],
    [/^PI Taxonomy R64$/, 'Taksonomi', 25],
    [/^PI Cascading R65$/, 'Kinerja', 30],
    [/^PI Renstra[–-]Renja R66$/, 'Renstra dan Renja', 35],
    [/^PI Annual Chain R68$/, 'Penganggaran', 40],
    [/^Documents$/, 'Dokumen · fondasi', 65],
    [/^Audits$/, 'Audit · fondasi', 80],
    [/^Settings$/, 'Pengaturan', 90],
  ]
  function makeDiv(cls, text, parent) {
    const d = document.createElement('div')
    d.className = cls
    if (text) d.textContent = text
    parent.appendChild(d)
    return d
  }
  function patch() {
    const aside = document.querySelector('.shell > aside')
    if (!aside) return
    aside.classList.add('pi-institutional-nav')
    const h1 = aside.querySelector(':scope > h1')
    if (h1 && h1.textContent !== 'Planning Intelligence') h1.textContent = 'Planning Intelligence'
    const version = aside.querySelector(':scope > .version')
    if (version && version.textContent !== 'INSTITUTIONAL WORKSPACE · QA') version.textContent = 'INSTITUTIONAL WORKSPACE · QA'
    if (!aside.querySelector(':scope > .pi-tenant-card')) {
      const card = makeDiv('pi-tenant-card', '', aside)
      const label = makeDiv('pi-tenant-label', 'RUANG KERJA INSTITUSI', card)
      label.setAttribute('aria-hidden', 'true')
      makeDiv('pi-tenant-name', 'Pemerintah Kota Yogyakarta', card)
      makeDiv('pi-tenant-disclaimer', 'Pratinjau tampilan · akun belum diverifikasi', card)
    }
    if (!aside.querySelector(':scope > .pi-menu-label')) makeDiv('pi-menu-label', 'MENU UTAMA', aside)
    for (const button of aside.querySelectorAll(':scope > button')) {
      const original = button.dataset.piOriginal || button.textContent.trim()
      if (!button.dataset.piOriginal) button.dataset.piOriginal = original
      const matched = rules.find(([re]) => re.test(original))
      if (matched) {
        const label = matched[1]
        if (button.textContent !== label) button.textContent = label
        if (button.dataset.piHidden !== 'false') button.dataset.piHidden = 'false'
        const order = String(matched[2])
        if (button.style.order !== order) button.style.order = order
        if (button.getAttribute('title') !== original) button.title = original
      } else {
        if (button.dataset.piHidden !== 'true') button.dataset.piHidden = 'true'
      }
    }
    if (!aside.querySelector(':scope > .pi-institutional-ai')) {
      const link = document.createElement('a')
      link.className = 'pi-institutional-ai'
      link.href = './ai-decision.html'
      link.textContent = '✦ Analisis AI'
      aside.appendChild(link)
    }
    if (!aside.querySelector(':scope > .pi-ux-caveat')) makeDiv('pi-ux-caveat', 'R69C · UI institusional QA. Data dan akun tenant belum diautentikasi.', aside)
    const badge = document.querySelector('.shell > main > header .badge')
    if (badge && badge.textContent !== 'KOTA YOGYAKARTA · INSTITUTIONAL QA') badge.textContent = 'KOTA YOGYAKARTA · INSTITUTIONAL QA'
    const headerTitle = document.querySelector('.shell > main > header b')
    if (headerTitle && headerTitle.textContent !== 'Workspace Pemerintah Kota Yogyakarta') headerTitle.textContent = 'Workspace Pemerintah Kota Yogyakarta'
    // R68 used to invite a jurisdiction switch from its hero. Ordinary institution
    // users should never need it. A raw QA URL is not a security barrier.
    for (const button of document.querySelectorAll('.pi68-hero button')) {
      if (/Pilih Pemda|R67/.test(button.textContent || '')) {
        if (button.dataset.piInstitutionHidden !== 'true') button.dataset.piInstitutionHidden = 'true'
      }
    }
    // Keep old foundation connection warning truthful and understandable.
    const notice = document.querySelector('.shell > main > .message span')
    if (notice && /Backend Foundation tidak terhubung/.test(notice.textContent || '')) {
      const message = 'Pratinjau QA: backend Foundation belum dijalankan. Telaah bukti resmi dan AI Decision Studio tetap dapat diakses melalui layanan QA.'
      if (notice.textContent !== message) notice.textContent = message
    }
    if (!appliedRequestedSection && sections[requestedSection]) {
      const target = [...aside.querySelectorAll(':scope > button')]
        .find(button => sections[requestedSection].test(button.dataset.piOriginal || ''))
      if (target) {
        appliedRequestedSection = true
        // Reuse the compiled React application's real navigation action.
        target.click()
      }
    }
  }
  document.addEventListener('click', event => {
    const button = event.target.closest?.('.shell > aside > button')
    if (button && button.dataset.piOriginal === 'PI Superior R9') {
      event.preventDefault()
      event.stopImmediatePropagation()
      window.location.assign('./index.html')
    }
  }, true)
  let scheduled = false
  function schedule() {
    if (scheduled) return
    scheduled = true
    window.requestAnimationFrame(() => {scheduled = false; patch()})
  }
  patch()
  const root = document.getElementById('root')
  if (root) new MutationObserver(schedule).observe(root, {childList:true,characterData:true,subtree:true})
  document.addEventListener('DOMContentLoaded', schedule)
})()
