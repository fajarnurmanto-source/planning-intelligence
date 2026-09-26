import { useEffect, useState } from 'react'

type Period={id:string,name:string,start_year:number,end_year:number}
type Project={id:string,name:string,level:string,periods:Period[]}
type Notice={kind:'success'|'error'|'info',text:string}

const api=async(path:string, opts?:RequestInit)=>{
  const r=await fetch(path,opts)
  if(!r.ok) throw new Error(await r.text())
  return r.json()
}

export default function App(){
 const [tab,setTab]=useState('Home')
 const [projects,setProjects]=useState<Project[]>([])
 const [docs,setDocs]=useState<any[]>([])
 const [regs,setRegs]=useState<any[]>([])
 const [audits,setAudits]=useState<any[]>([])
 const [events,setEvents]=useState<any[]>([])
 const [health,setHealth]=useState<any>(null)
 const [notice,setNotice]=useState<Notice|null>(null)
 const [busy,setBusy]=useState<string|null>(null)
 const [lastRefresh,setLastRefresh]=useState<string>('')

 const loadAll=async()=>{
   const errors:string[]=[]
   const load=async(path:string,setter:(v:any)=>void)=>{
     try{ setter(await api(path)) }catch(e:any){ errors.push(`${path}: ${e.message}`) }
   }
   await Promise.all([
     load('/api/projects',setProjects),
     load('/api/documents',setDocs),
     load('/api/regulations',setRegs),
     load('/api/audits',setAudits),
     load('/api/events?limit=50',setEvents),
     load('/health',setHealth),
   ])
   setLastRefresh(new Date().toLocaleTimeString())
   if(errors.length) setNotice({kind:'error',text:'Sebagian data gagal dimuat: '+errors.join(' | ')})
 }
 useEffect(()=>{loadAll()},[])
 useEffect(()=>{loadAll()},[tab])

 const projectOptions=projects.flatMap(p=>p.periods.map(per=>({j:p.id,p:per.id,label:`${p.name} — ${per.name}`})))

 async function runAction(name:string,action:()=>Promise<string>){
   setBusy(name); setNotice(null)
   try{
     const text=await action()
     await loadAll()
     setNotice({kind:'success',text})
   }catch(e:any){
     setNotice({kind:'error',text:`Gagal: ${e.message}`})
   }finally{ setBusy(null) }
 }

 async function createProject(ev:any){
   ev.preventDefault(); const form=ev.currentTarget; const f=new FormData(form)
   await runAction('project',async()=>{
     const result=await api('/api/projects',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({jurisdiction_name:f.get('name'),jurisdiction_level:f.get('level'),period_name:f.get('period'),start_year:Number(f.get('start')),end_year:Number(f.get('end'))})})
     form.reset()
     return result.created===false ? 'Project tersebut sudah ada; data lama digunakan kembali.' : 'Project berhasil disimpan.'
   })
 }
 async function uploadDoc(ev:any){
   ev.preventDefault(); const form=ev.currentTarget; const f=new FormData(form); const chosen=String(f.get('project')).split('|'); f.delete('project'); f.set('jurisdiction_id',chosen[0]); f.set('planning_period_id',chosen[1])
   await runAction('document',async()=>{const result=await api('/api/documents',{method:'POST',body:f}); form.reset(); return `Dokumen berhasil disimpan. SHA-256: ${result.sha256.slice(0,16)}…`})
 }
 async function addReg(ev:any){
   ev.preventDefault(); const form=ev.currentTarget; const f=new FormData(form)
   await runAction('regulation',async()=>{const result=await api('/api/regulations',{method:'POST',body:f}); form.reset(); return result.created===false ? 'Regulasi tersebut sudah ada; data lama digunakan kembali.' : 'Regulasi berhasil disimpan.'})
 }
 async function addAudit(ev:any){
   ev.preventDefault(); const form=ev.currentTarget; const f=new FormData(form); const chosen=String(f.get('project')).split('|')
   await runAction('audit',async()=>{await api('/api/audits',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({jurisdiction_id:chosen[0],planning_period_id:chosen[1],title:f.get('title'),audit_type:f.get('audit_type')})}); form.reset(); return 'Audit berhasil dibuat dan dicatat di Audit Ledger.'})
 }

 const tabs=['Home','Projects','Documents','Regulations','Audits','Activity','Settings']
 return <div className="shell"><aside><h1>Planning Intelligence</h1><div className="version">V0.0 FOUNDATION</div>{tabs.map(t=><button className={tab===t?'active':''} onClick={()=>setTab(t)} key={t}>{t}</button>)}</aside><main><header><div><b>Local-first workspace</b><span className="badge">{health?.ai_provider||'...'}</span></div><div className="status">● LOCAL</div></header>
 {notice&&<div className="message"><span>{notice.kind==='success'?'✓ ':notice.kind==='error'?'✕ ':''}{notice.text}</span><button onClick={()=>setNotice(null)}>×</button></div>}
 {tab==='Home'&&<section><h2>Foundation Health</h2><div className="cards"><Card title="Aplikasi" value={health?.status==='ok'?'ONLINE':'...'} note="localhost:8080"/><Card title="Project" value={String(projects.length)} note="jurisdiction / period"/><Card title="Documents" value={String(docs.length)} note="raw + checksum"/><Card title="Audit events" value={String(events.length)} note="tamper-evident chain"/></div><h3>Target V0.0</h3><p>Buat project → upload dokumen → register regulasi → buat audit → cek Activity → backup → restart → data tetap ada.</p><p><small>Terakhir sinkron: {lastRefresh||'-'} · <button onClick={loadAll}>Refresh data</button></small></p></section>}
 {tab==='Projects'&&<section><h2>Projects</h2><form onSubmit={createProject} className="gridform"><input name="name" placeholder="Nama daerah / Kota Uji" required/><select name="level"><option value="CITY">Kota</option><option value="REGENCY">Kabupaten</option><option value="PROVINCE">Provinsi</option></select><input name="period" placeholder="RPJMD 2025–2029" required/><input name="start" type="number" defaultValue="2025" required/><input name="end" type="number" defaultValue="2029" required/><button disabled={busy==='project'}>{busy==='project'?'Menyimpan…':'Simpan Project'}</button></form><List rows={projects.map(p=>[p.name,p.level,p.periods.map(x=>x.name).join(', ')])} headers={['Nama','Level','Periode']}/></section>}
 {tab==='Documents'&&<section><h2>Documents</h2>{projectOptions.length? <form onSubmit={uploadDoc} className="gridform" encType="multipart/form-data"><select name="project">{projectOptions.map(x=><option key={`${x.j}-${x.p}`} value={`${x.j}|${x.p}`}>{x.label}</option>)}</select><select name="doc_type"><option>RPJPD</option><option>RPJMD</option><option>RENSTRA</option><option>RKPD</option><option>RENJA</option><option>DPA</option><option>OTHER</option></select><input name="title" placeholder="Judul dokumen" required/><input name="version_label" placeholder="Versi (opsional)"/><input name="file" type="file" required/><button disabled={busy==='document'}>{busy==='document'?'Mengunggah…':'Upload'}</button></form>:<p>Belum ada Project yang terbaca. Buka menu Projects, buat/simpan Project, lalu kembali ke sini.</p>}<List rows={docs.map(d=>[d.doc_type,d.title,d.original_name,d.sha256.slice(0,14)+'…'])} headers={['Tipe','Judul','File','SHA-256']}/></section>}
 {tab==='Regulations'&&<section><h2>Regulations</h2><form onSubmit={addReg} className="gridform" encType="multipart/form-data"><input name="title" placeholder="Judul regulasi" required/><select name="regulation_type"><option>UU</option><option>PP</option><option>PERPRES</option><option>PERMENDAGRI</option><option>PERMEN_SEKTORAL</option><option>PERDA</option><option>PERKADA</option><option>OTHER</option></select><input name="number" placeholder="Nomor"/><input name="year" type="number" placeholder="Tahun"/><input name="jurisdiction_scope" placeholder="NATIONAL / DIY / Kota X" defaultValue="NATIONAL"/><input name="sector_scope" placeholder="GENERAL / HEALTH / ..." defaultValue="GENERAL"/><select name="status"><option>DRAFT</option><option>HUMAN_REVIEWED</option><option>VALIDATED</option><option>ACTIVE</option><option>SUPERSEDED</option><option>REVOKED</option></select><input name="source_url" placeholder="URL sumber resmi (opsional)"/><input name="file" type="file"/><button disabled={busy==='regulation'}>{busy==='regulation'?'Menyimpan…':'Simpan Regulasi'}</button></form><List rows={regs.map(r=>[r.regulation_type,r.title,r.status,r.sha256?r.sha256.slice(0,14)+'…':'-'])} headers={['Jenis','Judul','Status','SHA-256']}/></section>}
 {tab==='Audits'&&<section><h2>Audits</h2>{projectOptions.length?<form onSubmit={addAudit} className="gridform"><select name="project">{projectOptions.map(x=><option key={`${x.j}-${x.p}`} value={`${x.j}|${x.p}`}>{x.label}</option>)}</select><input name="title" placeholder="Audit fondasi / uji pertama" required/><select name="audit_type"><option>FOUNDATION_TEST</option><option>RPJMD_RENSTRA</option><option>RKPD_RENJA</option><option>FULL_JURISDICTION</option></select><button disabled={busy==='audit'}>{busy==='audit'?'Membuat…':'Buat Audit'}</button></form>:<p>Belum ada Project yang terbaca. Buka menu Projects, buat/simpan Project, lalu kembali ke sini.</p>}<List rows={audits.map(a=>[a.title,a.audit_type,a.status])} headers={['Audit','Tipe','Status']}/></section>}
 {tab==='Activity'&&<section><h2>Audit Ledger</h2><p>Setiap baris memiliki hash dan terhubung dengan hash event sebelumnya.</p><List rows={events.map(e=>[new Date(e.created_at).toLocaleString(),e.event_type,e.message,e.event_hash.slice(0,12)+'…'])} headers={['Waktu','Event','Pesan','Hash']}/></section>}
 {tab==='Settings'&&<section><h2>Settings</h2><div className="panel"><b>AI Provider</b><p>V0.0 dikunci ke <code>NO_AI</code>. OpenAI API, Manual ChatGPT, dan Local Model akan diaktifkan pada milestone selanjutnya tanpa mengubah data inti.</p></div><div className="panel"><b>Lokasi data</b><p>Database dan file mentah berada di folder <code>data</code> pada drive D tempat package ini diekstrak.</p></div></section>}
 </main></div>
}
function Card({title,value,note}:{title:string,value:string,note:string}){return <div className="card"><small>{title}</small><strong>{value}</strong><span>{note}</span></div>}
function List({headers,rows}:{headers:string[],rows:any[][]}){return <div className="tablewrap"><table><thead><tr>{headers.map(h=><th key={h}>{h}</th>)}</tr></thead><tbody>{rows.length?rows.map((r,i)=><tr key={i}>{r.map((c,j)=><td key={j}>{String(c??'')}</td>)}</tr>):<tr><td colSpan={headers.length}>Belum ada data.</td></tr>}</tbody></table></div>}
