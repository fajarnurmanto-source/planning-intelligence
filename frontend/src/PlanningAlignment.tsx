import { useState } from 'react'
import { extractInsights, type PlanningSourceUnit } from './PlanningInsights'

type DocumentRow = {
  id: string
  title: string
  doc_type: string
  sha256: string
  latest_version_id: string | null
}

type Version = { id: string; sha256: string }
type Run = { id: string; input_sha256: string; status: string; source_unit_count: number }
type Finding = {
  indicator: string
  unit: string
  rpjmd_target: string
  target_year: string
  rpjmd_page: number | null
  rpjmd_source_unit_id: string
  comparison_status: 'TARGET_TEXT_NEAR_LABEL' | 'INDICATOR_TEXT_FOUND' | 'NO_TEXT_MATCH'
  comparison_page: number | null
  comparison_source_unit_id: string | null
  comparison_source_fingerprint: string | null
  comparison_excerpt: string | null
}
type Result = {
  source: DocumentRow
  comparison: DocumentRow
  source_run_id: string
  comparison_run_id: string
  year: string
  findings: Finding[]
}

async function jsonResponse<T>(path: string, options?: RequestInit): Promise<T> {
  const response = await fetch(path, options)
  if (!response.ok) throw new Error('HTTP ' + response.status + ': ' + (await response.text()).slice(0, 350))
  return response.json() as Promise<T>
}

async function loadSourceUnits(document: DocumentRow): Promise<{ run: Run; units: PlanningSourceUnit[] }> {
  if (!document.latest_version_id) throw new Error('Dokumen tanpa versi aktif: ' + document.title)
  const versions = await jsonResponse<Version[]>('/api/documents/' + encodeURIComponent(document.id) + '/versions')
  const version = versions.find(item => item.id === document.latest_version_id)
  if (!version) throw new Error('Versi dokumen tidak ditemukan: ' + document.title)
  const run = await jsonResponse<Run>(
    '/api/document-versions/' + encodeURIComponent(version.id) + '/parse', { method: 'POST' },
  )
  if (run.status !== 'SUCCEEDED' || run.input_sha256.toLowerCase() !== version.sha256.toLowerCase()) {
    throw new Error('Hasil parsing belum siap atau checksum tidak cocok: ' + document.title)
  }
  const units: PlanningSourceUnit[] = []
  for (let offset = 0; offset < run.source_unit_count; offset += 1000) {
    const batch = await jsonResponse<PlanningSourceUnit[]>(
      '/api/parsing-runs/' + encodeURIComponent(run.id) + '/source-units?offset=' + offset + '&limit=1000',
    )
    if (!batch.length) throw new Error('Data sumber terputus pada urutan ' + offset)
    units.push(...batch)
  }
  if (units.length !== run.source_unit_count) throw new Error('Jumlah unit sumber tidak cocok.')
  return { run, units }
}

const plain = (text: string) => text.normalize('NFKD').toLowerCase()
  .replace(/[^a-z0-9]+/g, ' ').trim().replace(/\s+/g, ' ')

const targetText = (text: string) => text.toLowerCase().replace(/[–—]/g, '-')
  .replace(/\./g, ',').replace(/\s+/g, '')

function findEvidence(
  indicator: string, target: string, units: PlanningSourceUnit[],
): Pick<Finding, 'comparison_status' | 'comparison_page' | 'comparison_source_unit_id' |
  'comparison_source_fingerprint' | 'comparison_excerpt'> {
  const blocks = units.filter(unit => unit.unit_type === 'TEXT_BLOCK' && !!unit.normalized_text)
    .sort((a, b) => a.sequence_index - b.sequence_index)
  const label = plain(indicator)
  const labelTokens = Array.from(new Set(label.split(' ').filter(token => token.length > 2)))
  const desired = targetText(target)
  let best: { score: number; sameTarget: boolean; source: PlanningSourceUnit; text: string } | null = null

  for (let index = 0; index < blocks.length; index++) {
    const source = blocks[index]
    const nearby = blocks.slice(index, index + 3).filter(unit => unit.page_start === source.page_start)
    const excerpt = nearby.map(unit => unit.normalized_text || '').join(' ').replace(/\s+/g, ' ').trim()
    const words = plain(excerpt)
    const wordSet = new Set(words.split(' '))
    const coverage = labelTokens.length >= 2
      ? labelTokens.filter(token => wordSet.has(token)).length / labelTokens.length : 0
    const exactLabel = !!label && words.includes(label)
    if (!exactLabel && coverage < 0.75) continue
    const sameTarget = desired.length >= 4 && targetText(excerpt).includes(desired)
    const score = (sameTarget ? 10 : 0) + (exactLabel ? 2 : coverage)
    if (!best || score > best.score) best = { score, sameTarget, source, text: excerpt }
    if (score >= 12) break
  }

  if (!best) return {
    comparison_status: 'NO_TEXT_MATCH',
    comparison_page: null,
    comparison_source_unit_id: null,
    comparison_source_fingerprint: null,
    comparison_excerpt: null,
  }
  return {
    comparison_status: best.sameTarget ? 'TARGET_TEXT_NEAR_LABEL' : 'INDICATOR_TEXT_FOUND',
    comparison_page: best.source.page_start,
    comparison_source_unit_id: best.source.id,
    comparison_source_fingerprint: best.source.unit_fingerprint,
    comparison_excerpt: best.text.slice(0, 500),
  }
}

const statusLabel: Record<Finding['comparison_status'], string> = {
  TARGET_TEXT_NEAR_LABEL: 'Kandidat angka sama',
  INDICATOR_TEXT_FOUND: 'Indikator ditemukan; angka belum pasti',
  NO_TEXT_MATCH: 'Belum ditemukan pada teks',
}

export default function PlanningAlignment({ documents }: { documents: DocumentRow[] }) {
  const [sourceChoice, setSourceChoice] = useState('')
  const [comparisonChoice, setComparisonChoice] = useState('')
  const [year, setYear] = useState('2026')
  const [result, setResult] = useState<Result | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  const sources = documents.filter(doc => doc.doc_type === 'RPJMD' && !!doc.latest_version_id)
  const sourceId = sourceChoice || sources[0]?.id || ''
  const sourceDocument = sources.find(doc => doc.id === sourceId)
  const comparisonOptions = documents.filter(doc => doc.id !== sourceId && !!doc.latest_version_id)
  const comparisonId = comparisonOptions.some(doc => doc.id === comparisonChoice)
    ? comparisonChoice : comparisonOptions[0]?.id || ''
  const titlePeriod = sourceDocument?.title.match(/(20\d{2})\s*[-–]\s*(20\d{2})/)
  const startYear = titlePeriod ? Number(titlePeriod[1]) : 2025
  const endYear = titlePeriod ? Number(titlePeriod[2]) : 2029
  const yearOptions = Array.from({ length: Math.max(1, Math.min(10, endYear - startYear + 2)) },
    (_, index) => String(startYear + index))
  const selectedYear = yearOptions.includes(year) ? year : yearOptions[0]

  async function compare() {
    const source = sources.find(doc => doc.id === sourceId)
    const comparison = comparisonOptions.find(doc => doc.id === comparisonId)
    if (!source || !comparison || busy) return
    setBusy(true)
    setError('')
    setResult(null)
    try {
      const original = await loadSourceUnits(source)
      const target = await loadSourceUnits(comparison)
      const insights = extractInsights(original.units)
      if (!insights.indicators.length) throw new Error('Indikator RPJMD belum berhasil dikenali. Buka Telaah Awal pada menu Documents.')
      if (!insights.years.includes(selectedYear)) throw new Error('Tahun ' + selectedYear + ' tidak tercantum pada tabel target RPJMD.')
      const findings: Finding[] = insights.indicators.map(indicator => ({
        indicator: indicator.name,
        unit: indicator.unit,
        rpjmd_target: indicator.targets[selectedYear],
        target_year: selectedYear,
        rpjmd_page: indicator.page,
        rpjmd_source_unit_id: indicator.source_unit_id,
        ...findEvidence(indicator.name, indicator.targets[selectedYear], target.units),
      }))
      setResult({ source, comparison, source_run_id: original.run.id,
        comparison_run_id: target.run.id, year: selectedYear, findings })
    } catch (e) {
      setError(e instanceof Error ? e.message : String(e))
    } finally {
      setBusy(false)
    }
  }

  function download() {
    if (!result) return
    const data = {
      schema: 'pi.local.rpjmd-comparison-candidate.v1',
      generated_at: new Date().toISOString(),
      status: 'EVIDENCE_CANDIDATE_UNREVIEWED',
      note: 'Numbers near labels may occur in different columns or years. Absence in extracted text does not prove policy omission.',
      source: { title: result.source.title, sha256: result.source.sha256, run_id: result.source_run_id },
      comparison: { title: result.comparison.title, sha256: result.comparison.sha256, run_id: result.comparison_run_id },
      year: result.year,
      findings: result.findings,
    }
    const url = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' }))
    const link = document.createElement('a')
    link.href = url
    link.download = 'pi_perbandingan_rpjmd_' + result.year + '.json'
    link.click()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  }

  const matched = result?.findings.filter(row => row.comparison_status === 'TARGET_TEXT_NEAR_LABEL').length || 0
  const mentioned = result?.findings.filter(row => row.comparison_status === 'INDICATOR_TEXT_FOUND').length || 0
  const missing = result?.findings.filter(row => row.comparison_status === 'NO_TEXT_MATCH').length || 0

  return <section>
    <h2>Perbandingan dokumen perencanaan</h2>
    <p>Bandingkan indikator dan target RPJMD dengan bukti teks pada RKPD, Renstra, atau dokumen lain yang telah diunggah. Hasilnya berupa kandidat temuan dengan halaman rujukan, bukan putusan otomatis bahwa dokumen telah selaras atau bertentangan.</p>
    {!sources.length && <div className="message">Belum ada RPJMD yang terunggah. Tambahkan melalui menu Documents.</div>}
    {!!sources.length && !comparisonOptions.length && <div className="panel">
      <h3>Dokumen pembanding belum tersedia</h3>
      <p>RPJMD telah siap. Unggah <strong>RKPD 2026</strong> atau <strong>Renstra perangkat daerah 2025–2029</strong> melalui menu Documents, lalu kembali ke Perbandingan.</p>
    </div>}
    <div className="gridform">
      <label>RPJMD dasar<select style={{ display: 'block', width: '100%', marginTop: 6 }} value={sourceId} onChange={e => { setSourceChoice(e.target.value); setComparisonChoice(''); setResult(null) }}>
        {sources.map(doc => <option key={doc.id} value={doc.id}>{doc.title}</option>)}
      </select></label>
      <label>Dokumen pembanding<select style={{ display: 'block', width: '100%', marginTop: 6 }} value={comparisonId} onChange={e => { setComparisonChoice(e.target.value); setResult(null) }}>
        {comparisonOptions.map(doc => <option key={doc.id} value={doc.id}>{doc.doc_type} — {doc.title}</option>)}
      </select></label>
      <label>Tahun target<select style={{ display: 'block', width: '100%', marginTop: 6 }} value={selectedYear} onChange={e => { setYear(e.target.value); setResult(null) }}>
        {yearOptions.map(y => <option key={y} value={y}>{y}</option>)}
      </select></label>
      <button type="button" disabled={!sourceId || !comparisonId || busy} onClick={compare}>
        {busy ? 'Mengolah dua dokumen…' : 'Bandingkan dokumen'}
      </button>
    </div>
    {error && <p role="alert" style={{ color: '#ffb0a0' }}>Gagal: {error}</p>}
    {result && <>
      <div className="cards">
        <div className="card"><small>Indikator RPJMD</small><strong>{result.findings.length}</strong><span>Target {result.year}</span></div>
        <div className="card"><small>Kandidat angka sama</small><strong>{matched}</strong><span>Butuh konfirmasi tahun dan tabel</span></div>
        <div className="card"><small>Nama indikator ditemukan</small><strong>{mentioned}</strong><span>Angka belum terkonfirmasi</span></div>
        <div className="card"><small>Belum ditemukan</small><strong>{missing}</strong><span>Jangan disimpulkan sebagai ketidaksesuaian</span></div>
      </div>
      <div className="panel" style={{ marginTop: 16 }}>
        <h3>{result.source.title} ↔ {result.comparison.title}</h3>
        <p><button type="button" onClick={download}>Unduh perbandingan beserta sumber (JSON)</button></p>
        <div className="tablewrap"><table>
          <thead><tr><th>Indikator</th><th>Target RPJMD {result.year}</th><th>Hasil penelusuran</th><th>Bukti dokumen pembanding</th></tr></thead>
          <tbody>{result.findings.map(row => <tr key={row.rpjmd_source_unit_id}>
            <td><strong>{row.indicator}</strong><br/><small>RPJMD PDF hlm. {row.rpjmd_page ?? '—'}</small></td>
            <td>{row.rpjmd_target} <small>{row.unit}</small></td>
            <td>{statusLabel[row.comparison_status]}</td>
            <td>{row.comparison_page != null ? <><small>PDF hlm. {row.comparison_page} · unit {row.comparison_source_unit_id?.slice(0, 8)}</small><p>{row.comparison_excerpt}</p></> : '—'}</td>
          </tr>)}</tbody>
        </table></div>
      </div>
      <p><small>Temuan masih memerlukan peninjauan tabel asli, konteks waktu, dan jenis indikator. PI tidak menyatakan suatu dokumen melanggar ketentuan hanya karena teks atau angka tidak ditemukan.</small></p>
    </>}
  </section>
}
