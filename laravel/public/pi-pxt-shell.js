/* PI-PXT: progressive mobile navigation, no access or route changes. */
(() => {
 'use strict'
 document.addEventListener('DOMContentLoaded',()=>{
  const button=document.querySelector('.pi-nav-toggle')
  const nav=document.getElementById('pi-primary-nav')
  if(!button||!nav||typeof matchMedia!=='function')return
  const narrow=matchMedia('(max-width:850px)')
  const set=open=>{nav.hidden=!open;button.setAttribute('aria-expanded',String(open))}
  const sync=()=>{button.hidden=!narrow.matches;set(!narrow.matches)}
  button.addEventListener('click',()=>set(nav.hidden))
  if(narrow.addEventListener)narrow.addEventListener('change',sync)
  else if(narrow.addListener)narrow.addListener(sync)
  sync()
 })
})()
