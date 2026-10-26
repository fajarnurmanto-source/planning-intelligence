import { useEffect, useState } from 'react'

type Evidence = { unit_fingerprint: string; excerpt?: string; locator: Record<string, unknown> }
type Entity = { id: string; kind: string; title: string; code?: string; attributes: Record<string, string>; evidence: Evidence[] }
type Finding = { id: string; upstream_role: string; downstream_role: string; match_type: string; target_comparison: string; evidence: Record<string, Evidence[]>; status: string }
type Pipeline = { schema: string; report_sha256: string; production_admission: string; regulatory_taxonomy: string; documents: Record<string, { entities: Entity[] }>; coherence: { findings: Finding[] } }
type TargetScreenFinding = { id: string; upstream_role: string; downstream_role: string; match_types: string[]; status: string; reason: string; declared_target_texts: string[]; normalized_numeric_targets: string[]; source_m2_finding_ids: string[]; evidence: Record<string, Evidence[]>; review_required: boolean }
type TargetScreen = { schema: string; report_sha256: string; source_m2_report_sha256: string; production_admission: string; regulatory_taxonomy: string; findings: TargetScreenFinding[]; metrics: { candidate_findings: number; source_m2_match_findings: number; verified_substantive_relationships: number; confirmed_contradictions: number } }
type ReviewResponse = { review: unknown; report_markdown: string }

const choices = ['NEEDS_MORE_EVIDENCE', 'ACCEPT_CANDIDATE', 'REJECT_CANDIDATE'] as const
const roles = ['RPJMD', 'RKPD', 'RENSTRA', 'RENJA']

function download(filename: string, content: string, type: string) {
  const url = URL.createObjectURL(new Blob([content], { type }))
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}

export default function PlanningWorkbenchM2() {
  const [pipeline, setPipeline] = useState<Pipeline | null>(null)
  const [targetScreen, setTargetScreen] = useState<TargetScreen | null>(null)
  const [targetScreenError, setTargetScreenError] = useState('')
  const [decisions, setDecisions] = useState<Record<string, string>>({})
  const [report, setReport] = useState<ReviewResponse | null>(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let active = true
    fetch('/api/m2/synthetic-demo')
      .then(async response => {
        if (!response.ok) throw new Error(`Demo HTTP ${response.status}`)
        return response.json()
      })
      .then((data: Pipeline) => { if (active) setPipeline(data) })
      .catch((err: Error) => { if (active) setError(err.message) })
    fetch('/api/m3/synthetic-target-screen')
      .then(async response => {
        if (!response.ok) throw new Error(`M3 HTTP ${response.status}`)
        return response.json()
      })
      .then((data: TargetScreen) => { if (active) setTargetScreen(data) })
      .catch((err: Error) => { if (active) setTargetScreenError(err.message) })
    return () => { active = false }
  }, [])

  async function exportReview() {
    if (!pipeline) return
    setBusy(true); setError(''); setReport(null)
    try {
      const response = await fetch('/api/m2/synthetic-review', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pipeline_report_sha256: pipeline.report_sha256, decisions }),
      })
      if (!response.ok) throw new Error(`Gagal mengekspor anotasi (${response.status}): ${await response.text()}`)
      setReport(await response.json())
    } catch (err) { setError(err instanceof Error ? err.message : String(err)) }
    finally { setBusy(false) }
  }

  return <section>
    <h2>M2 — Planning Entity Workbench</h2>
    <p><strong>DEMO SINTETIS SAJA.</strong> Tidak membaca dokumen unggahan, tidak memberi persetujuan produksi, tidak mengaktifkan taksonomi regulasi, dan tidak menggunakan LLM lokal maupun API berbayar. Keputusan di bawah adalah anotasi simulasi.</p>
    {error && <p role="alert" style={{ color: '#b42318' }}>{error}</p>}
    {!pipeline && !error && <p>Menyiapkan contoh dokumen dan analisis deterministik…</p>}
    {pipeline && <>
      <p><small>SHA-256 laporan: <code>{pipeline.report_sha256}</code> · Production: {pipeline.production_admission} · Regulatory taxonomy: {pipeline.regulatory_taxonomy}</small></p>
      <h3>Entitas hasil parsing</h3>
      <div className="tablewrap"><table><thead><tr><th>Dokumen</th><th>Jenis</th><th>Nama</th><th>Kode</th><th>Sumber</th></tr></thead><tbody>
        {roles.flatMap(role => (pipeline.documents[role]?.entities || []).map(entity => <tr key={entity.id}>
          <td>{role}</td><td>{entity.kind}</td><td>{entity.title}</td><td>{entity.code || '—'}</td>
          <td><details><summary>{entity.evidence.length} bukti</summary>{entity.evidence.map(ev => <div key={ev.unit_fingerprint}><small>{ev.excerpt}<br />{JSON.stringify(ev.locator)}<br />{ev.unit_fingerprint}</small></div>)}</details></td>
        </tr>))}
      </tbody></table></div>
      <h3>M3.1 — Penyaringan target substantif</h3>
      <p>Perbandingan angka hanya dilakukan bila indikator, tahun, dan satuan dinyatakan sebanding. Satu pasangan entitas dihitung satu kali meski cocok lewat beberapa kunci. <strong>Temuan ini bukan bukti kontradiksi kebijakan maupun keputusan hukum.</strong></p>
      {targetScreenError && <p role="alert">Layanan penyaringan M3 belum tersedia: {targetScreenError}. Analisis M2 tetap dapat digunakan.</p>}
      {!targetScreen && !targetScreenError && <p>Menyaring kandidat dengan aturan deterministik…</p>}
      {targetScreen && targetScreen.source_m2_report_sha256 !== pipeline.report_sha256 && <p role="alert">Digest sumber M3 tidak cocok dengan laporan M2. Hasil M3 disembunyikan hingga data konsisten.</p>}
      {targetScreen && targetScreen.source_m2_report_sha256 === pipeline.report_sha256 && <>
        <p><small>Pasangan entitas: {targetScreen.metrics.candidate_findings} · kecocokan M2: {targetScreen.metrics.source_m2_match_findings} · kontradiksi terkonfirmasi: {targetScreen.metrics.confirmed_contradictions} · relasi substantif terverifikasi: {targetScreen.metrics.verified_substantive_relationships} · Regulatory taxonomy: {targetScreen.regulatory_taxonomy}</small></p>
        {targetScreen.findings.length === 0 && <p>Belum ada pasangan entitas yang dapat disaring.</p>}
        {targetScreen.findings.map(finding => <div className="panel" key={finding.id}>
          <p><strong>{finding.upstream_role} → {finding.downstream_role}</strong> · {finding.status}</p>
          <p>{finding.reason}</p>
          <small>Target sumber: {finding.declared_target_texts.join(' vs ')} · Kecocokan: {finding.match_types.join(', ')} · ID M2: {finding.source_m2_finding_ids.join(', ')}</small>
          <details><summary>Telusuri bukti dan lokasi sumber</summary>{Object.entries(finding.evidence).map(([role, items]) => <div key={role}><b>{role}</b>{items.map(ev => <p key={ev.unit_fingerprint}><small>{ev.excerpt}<br />Locator: {JSON.stringify(ev.locator)}<br />Fingerprint: {ev.unit_fingerprint}</small></p>)}</div>)}</details>
        </div>)}
        <button onClick={() => download('pi_m3_synthetic_target_screen.json', JSON.stringify(targetScreen, null, 2), 'application/json')}>Unduh hasil penyaringan M3 (JSON)</button>
      </>}
      <h3>Temuan kandidat dan anotasi reviewer</h3>
      {pipeline.coherence.findings.length === 0 && <p>Belum ada temuan kandidat.</p>}
      {pipeline.coherence.findings.map(finding => <div className="panel" key={finding.id}>
        <p><strong>{finding.upstream_role} → {finding.downstream_role}</strong> · {finding.match_type} · target: {finding.target_comparison}</p>
        <small>ID temuan: {finding.id}</small>
        <details><summary>Telusuri bukti sumber</summary>{Object.entries(finding.evidence).map(([role, items]) => <div key={role}><b>{role}</b>{items.map(ev => <p key={ev.unit_fingerprint}><small>{ev.excerpt}<br />Locator: {JSON.stringify(ev.locator)}<br />Fingerprint: {ev.unit_fingerprint}</small></p>)}</div>)}</details>
        <p><label htmlFor={`m2-${finding.id}`}>Anotasi simulasi: </label>
          <select id={`m2-${finding.id}`} value={decisions[finding.id] || ''} onChange={e => { setDecisions(previous => ({ ...previous, [finding.id]: e.target.value })); setReport(null) }}>
            <option value="">Belum ditinjau</option>
            {choices.map(choice => <option key={choice} value={choice}>{choice}</option>)}
          </select></p>
      </div>)}
      <button disabled={busy} onClick={exportReview}>{busy ? 'Memproses…' : 'Susun hasil review sintetis'}</button>
      {report && <div className="panel">
        <p>Hasil siap diunduh secara lokal. Tidak ada penyimpanan keputusan ke database.</p>
        <button onClick={() => download('pi_m2_synthetic_review.json', JSON.stringify(report.review, null, 2), 'application/json')}>Unduh JSON</button>{' '}
        <button onClick={() => download('pi_m2_synthetic_report.md', report.report_markdown, 'text/markdown')}>Unduh laporan Markdown</button>
      </div>}
    </>}
  </section>
}

