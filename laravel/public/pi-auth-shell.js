/* R69E inline account menu. Backend authorizes every resource. */
(() => {
  let cataloguePromise
  window.PIRegulatoryIdentity=Object.freeze({
    label(doc){
      const kinds={PERWAL:'Perwal',PERDA:'Perda',KEPWAL:'Kepwal'}
      return (kinds[doc.instrument]||doc.instrument)+' '+doc.number+'/'+doc.year+' tentang '+doc.title
    },
    load(){
      if(!cataloguePromise)cataloguePromise=fetch('/api/qa-superior-r100/official-sources',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}})
        .then(async r=>{if(!r.ok)throw Error('Katalog tidak tersedia');const d=await r.json();if(d.schema!=='PI_R100_OFFICIAL_SOURCE_METADATA_READ_MODEL_V1'||d.interpretation?.production_admission!=='DENIED'||!Array.isArray(d.documents))throw Error('Kontrak katalog tidak sesuai');return d.documents})
        .catch(e=>{cataloguePromise=null;throw e})
      return cataloguePromise
    },
    async resolve(id,fallback){
      try{const d=(await this.load()).find(x=>x.id===id);return d?this.label(d):fallback+' · judul belum terverifikasi'}
      catch{return fallback+' · judul belum terverifikasi'}
    }
  })
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
    const search=document.createElement('button')
    search.type='button';search.textContent='Cari di PI';search.setAttribute('aria-label','Cari di PI (Ctrl+K)')
    const dialog=document.createElement('dialog')
    dialog.setAttribute('aria-label','Pencarian PI')
    Object.assign(dialog.style,{width:'min(640px, 92vw)',maxHeight:'80vh',background:'#102e37',color:'#eaf8f3',border:'1px solid #39726d',borderRadius:'12px',padding:'20px',boxSizing:'border-box'})
    const heading=document.createElement('h2');heading.textContent='Cari di PI'
    const input=document.createElement('input');input.type='search';input.placeholder='Cari fitur, produk hukum, dokumen…';input.setAttribute('aria-label','Kata kunci pencarian')
    Object.assign(input.style,{width:'100%',padding:'12px',boxSizing:'border-box',fontSize:'16px'})
    const close=document.createElement('button');close.type='button';close.textContent='Tutup';close.onclick=()=>dialog.close()
    const status=document.createElement('p');status.setAttribute('role','status')
    const results=document.createElement('div')
    dialog.append(heading,input,close,status,results);document.body.append(dialog)
    let catalogue=[],loading=false,catalogueFailed=false
    const normalize=v=>String(v??'').normalize('NFKC').toLowerCase().replace(/[^\p{L}\p{N}]+/gu,' ').trim()
    const render=()=>{
      results.replaceChildren()
      const terms=normalize(input.value).split(' ').filter(Boolean)
      if(!terms.length){status.textContent='Ketik kata kunci. Cakupan awal: menu tersedia dan katalog resmi R100.';return}
      const entries=[]
      for(const a of document.querySelectorAll('nav[data-pi-app-nav] a.pi-app-item[href]')){
        const u=new URL(a.href,location.href)
        if(u.origin===location.origin)entries.push({label:a.textContent,href:u.href,kind:'Fitur'})
      }
      for(const d of catalogue){
        try{
          const u=new URL(d.official_url)
          if(u.protocol!=='https:'||u.hostname!=='jdih.jogjakota.go.id'||u.username||u.password||u.port||u.search||u.hash||!/^\/home\/produk_hukum\/detail\/[0-9]+$/.test(u.pathname))continue
          entries.push({label:window.PIRegulatoryIdentity.label(d),href:u.href,kind:'Metadata resmi · '+d.id,keywords:d.title+' '+d.kind+' '+d.period})
        }catch{}
      }
      const matches=entries.filter(e=>terms.every(t=>normalize(e.label+' '+e.kind+' '+(e.keywords||'')).includes(t))).slice(0,30)
      status.textContent=matches.length+' hasil ditampilkan'+(loading?' · memuat katalog…':catalogueFailed?' · katalog tidak tersedia':' · indeks awal, bukan seluruh isi PDF')
      for(const e of matches){
        const a=document.createElement('a');a.href=e.href;a.textContent=e.label+' · '+e.kind
        Object.assign(a.style,{display:'block',padding:'10px',color:'#baf1d5',borderBottom:'1px solid #31545b',overflowWrap:'anywhere'})
        a.addEventListener('click',()=>dialog.close());results.append(a)
      }
    }
    const open=async()=>{
      if(dialog.open)return
      dialog.showModal();input.focus();loading=true;render()
      try{catalogue=await window.PIRegulatoryIdentity.load();catalogueFailed=false}catch{catalogue=[];catalogueFailed=true}
      finally{loading=false;if(dialog.open)render()}
    }
    search.onclick=open;input.addEventListener('input',render)
    document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();open()}})
    dialog.addEventListener('close',()=>search.focus())
    menu.append(search,identity,button)
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
