/* R69E inline account menu. Backend authorizes every resource. */
(() => {
  async function setup() {
    const response = await fetch('/api/auth/session', {cache:'no-store'})
    if (!response.ok) {location.replace('./login.html');return}
    const user = await response.json()
    const menu = document.createElement('div')
    menu.className = 'pi-user-bar'
    const identity = document.createElement('span')
    identity.textContent = `${user.username} · ${user.role.replace('_',' ')}`
    const button = document.createElement('button')
    button.textContent = 'Keluar'
    button.onclick = async () => {
      button.disabled = true
      try {
        const end = await fetch('/api/auth/logout', {method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':user.csrf}})
        if (!end.ok) throw new Error('Gagal keluar')
        location.replace('./login.html')
      } catch {button.textContent='Coba lagi';button.disabled=false}
    }
    menu.append(identity,button)
    const attach = () => {
      const host = document.querySelector('.topbar, .shell > main > header, .masthead')
      if(!host)return false
      if(menu.parentNode!==host)host.appendChild(menu)
      return true
    }
    if(!attach()) {
      const observer = new MutationObserver(() => {if(attach())observer.disconnect()})
      observer.observe(document.body,{childList:true,subtree:true})
    }
  }
  document.addEventListener('DOMContentLoaded', () => setup().catch(() => location.replace('./login.html')))
})()
