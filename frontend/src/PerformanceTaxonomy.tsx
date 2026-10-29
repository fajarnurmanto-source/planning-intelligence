import { useEffect, useState, type FormEvent } from 'react'

type DocumentRow = { id: string; title: string; doc_type: string; latest_version_id: string | null }
type Registry = {
  schema: string
  structural_kinds: string[]
  indicator_levels: string[]
  indicator_natures: string[]
  measure_roles: string[]
  lineage_types: string[]
}
type Run = { id: string; status: string; source_unit_count: number }
type Finding = {
  indicator_label: string
  measure_role_candidate: string
  header_year_candidate: number | null
  value_shape: string
  parsed_value: string[] | null
  context_status: string
  source_page: number | null
  physical_header_link_verification: string
}

async function readJson<T>(path: string, options?: RequestInit): Promise<T> {
  const response = await fetch(path, options)
  if (!response.ok) throw new Error('HTTP ' + response.status + ': ' + (await response.text()).slice(0, 300))
  return response.json() as Promise<T>
}

export default function PerformanceTaxonomy({ documents }: { documents: DocumentRow[] }) {
  const [registry, setRegistry] = useState<Registry | null>(null)
  const [runs, setRuns] = useState<Run[]>([])
  const [selectedDoc, setSelectedDoc] = useState('')
  const [selectedRun, setSelectedRun] = useState('')
  const [sourceId, setSourceId] = useState('')
  const [fingerprint, setFingerprint] = useState('')
  const [indicator, setIndicator] = useState('')
  const [rawValue, setRawValue] = useState('')
  const [year, setYear] = useState('2026')
  const [headerIds, setHeaderIds] = useState('')
  const [finding, setFinding] = useState<Finding | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    readJson<Registry>('/api/performance-taxonomy/registry')
      .then(setRegistry).catch(err => setError(String(err)))
  }, [])

  const docId = selectedDoc || documents.find(doc => doc.latest_version_id)?.id || ''
  const document = documents.find(doc => doc.id === docId)
  useEffect(() => {
    setRuns([])
    setSelectedRun('')
    setFinding(null)
    if (!document?.latest_version_id) return
    readJson<Run[]>('/api/document-versions/' + encodeURIComponent(document.latest_version_id) + '/parsing-runs')
      .then(items => setRuns(items.filter(item => item.status === 'SUCCEEDED')))
      .catch(err => setError(String(err)))
  }, [document?.latest_version_id])
  const runId = runs.some(run => run.id === selectedRun) ? selectedRun : runs[0]?.id || ''

  async function inspect(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!runId || busy) return
    setBusy(true)
    setError('')
    setFinding(null)
    try {
      const result = await readJson<{ findings: Finding[] }>(
        '/api/performance-taxonomy/classify-measurements', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ items: [{
            parsing_run_id: runId,
            source_unit_id: sourceId.trim(), unit_fingerprint: fingerprint.trim(),
            indicator_label: indicator.trim(), raw_value: rawValue.trim(),
            comparison_year: year ? Number(year) : null,
            header_source_unit_ids: headerIds.split(',').map(value => value.trim()).filter(Boolean),
          }] }),
        },
      )
      setFinding(result.findings[0] || null)
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err))
    } finally {
      setBusy(false)
    }
  }

  return <section>
    <h2>Taksonomi kinerja pembangunan</h2>
    <p>PI-PERF-TAX-01 memisahkan struktur perencanaan, definisi indikator, dan pengukuran per tahun/periode. Modul ini menguji konteks kandidat terhadap sumber persisten; bukan penetapan keselarasan atau kepatuhan hukum.</p>
    {error && <p role="alert" style={{ color: '#b42318' }}>{error}</p>}
    {registry && <div className="panel">
      <p><strong>Kontrak:</strong> {registry.schema}</p>
      <div className="tablewrap"><table>
        <thead><tr><th>Dimensi</th><th>Klasifikasi yang dikenali</th></tr></thead>
        <tbody>
          <tr><td>Entitas perencanaan</td><td>{registry.structural_kinds.join(', ')}</td></tr>
          <tr><td>Tingkat indikator</td><td>{registry.indicator_levels.join(', ')}</td></tr>
          <tr><td>Karakter indikator</td><td>{registry.indicator_natures.join(', ')}</td></tr>
          <tr><td>Peran nilai</td><td>{registry.measure_roles.join(', ')}</td></tr>
          <tr><td>Relasi lintas dokumen</td><td>{registry.lineage_types.join(', ')}</td></tr>
        </tbody>
      </table></div>
    </div>}
    <div className="panel" style={{ marginTop: 16 }}>
      <h3>Periksa konteks satu nilai terekstraksi</h3>
      <p>Gunakan ID dan fingerprint dari JSON ekstraksi/perbandingan yang sudah tersimpan. Pemeriksaan ini tidak mengunggah, mengurai ulang, atau mengubah PDF/database. Header kolom hanya dianggap terhubung jika SourceUnit aslinya memiliki struktur sel yang dapat diverifikasi.</p>
      <form onSubmit={inspect} className="gridform">
        <label>Dokumen<select style={{ display: 'block', width: '100%' }} value={docId} onChange={event => setSelectedDoc(event.target.value)}>
          {documents.filter(doc => !!doc.latest_version_id).map(doc => <option key={doc.id} value={doc.id}>{doc.doc_type} — {doc.title}</option>)}
        </select></label>
        <label>Parsing run tersimpan<select style={{ display: 'block', width: '100%' }} value={runId} onChange={event => setSelectedRun(event.target.value)}>
          {runs.map(run => <option key={run.id} value={run.id}>{run.id.slice(0, 8)} · {run.source_unit_count} unit</option>)}
        </select></label>
        <label>Nama indikator<input required value={indicator} onChange={event => setIndicator(event.target.value)} /></label>
        <label>Nilai mentah dari unit<input required value={rawValue} onChange={event => setRawValue(event.target.value)} /></label>
        <label>SourceUnit ID<input required value={sourceId} onChange={event => setSourceId(event.target.value)} /></label>
        <label>Fingerprint SHA-256 unit<input required minLength={64} maxLength={64} value={fingerprint} onChange={event => setFingerprint(event.target.value)} /></label>
        <label>Tahun pembanding<input type="number" min="1900" max="2100" value={year} onChange={event => setYear(event.target.value)} /></label>
        <label>SourceUnit ID header kolom (opsional, pisahkan koma)<input value={headerIds} onChange={event => setHeaderIds(event.target.value)} /></label>
        <button type="submit" disabled={!runId || busy}>{busy ? 'Memeriksa…' : 'Periksa konteks bukti'}</button>
      </form>
    </div>
    {finding && <div className="panel" role="status" style={{ marginTop: 16 }}>
      <h3>Hasil klasifikasi kandidat</h3>
      <p><strong>{finding.context_status}</strong></p>
      <p>Peran: {finding.measure_role_candidate} · tahun dari header: {finding.header_year_candidate ?? 'BELUM ADA'} · bentuk nilai: {finding.value_shape}</p>
      <p>Angka terurai: {finding.parsed_value?.join(' – ') || 'BELUM DAPAT DIURAI'} · halaman: {finding.source_page ?? '—'}</p>
      <p><small>Hubungan fisik dengan header: {finding.physical_header_link_verification}. Hasil belum merupakan keputusan reviewer atau pembuktian koherensi.</small></p>
    </div>}
  </section>
}
