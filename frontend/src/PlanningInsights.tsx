import { useMemo, useState } from 'react'

export type PlanningSourceUnit = {
  id: string
  unit_type: string
  sequence_index: number
  page_start: number | null
  normalized_text: string | null
  unit_fingerprint: string
}

type Evidence = { source_unit_id: string; page: number | null; fingerprint: string }
type EvidenceText = Evidence & { text: string }
type Indicator = Evidence & { name: string; unit: string; targets: Record<string, string> }
type Insights = {
  vision: EvidenceText | null
  missions: EvidenceText[]
  indicators: Indicator[]
  years: string[]
  sections: EvidenceText[]
}

const tidy = (value: string) => value.replace(/\s+/g, ' ').trim()
const evidence = (unit: PlanningSourceUnit): Evidence => ({
  source_unit_id: unit.id,
  page: unit.page_start,
  fingerprint: unit.unit_fingerprint,
})

function extractInsights(source: PlanningSourceUnit[]): Insights {
  const blocks = source.filter(unit => unit.unit_type === 'TEXT_BLOCK' && !!unit.normalized_text)
    .sort((a, b) => a.sequence_index - b.sequence_index)
  const getText = (unit: PlanningSourceUnit) => tidy(unit.normalized_text || '')

  const sections = blocks.filter(unit => /^\d+(?:\.\d+){1,4}\.\s+[^.]{3,95}$/.test(getText(unit)))
    .map(unit => ({ ...evidence(unit), text: getText(unit) })).slice(0, 40)

  const visionStart = blocks.findIndex(unit => /^\d+\.\d+\.\s+Visi$/i.test(getText(unit)))
  const missionStart = blocks.findIndex((unit, index) => index > visionStart && /^\d+\.\d+\.\s+Misi$/i.test(getText(unit)))
  let vision: EvidenceText | null = null
  if (visionStart >= 0) {
    const end = missionStart > visionStart ? missionStart : Math.min(blocks.length, visionStart + 120)
    for (let i = visionStart + 1; i < end; i++) {
      const first = getText(blocks[i])
      const combined = tidy(first + ' ' + (i + 1 < end ? getText(blocks[i + 1]) : ''))
      const quoted = combined.match(/^[“"]([^”"]{20,250})[”"](?:\s|$)/)
      if (quoted) {
        vision = { ...evidence(blocks[i]), text: tidy(quoted[1]) }
        break
      }
    }
  }

  // Mission enumeration often survives PDF extraction as a single text block
  // even when the preceding mission diagram itself is an image.
  const missionCandidates = blocks.filter((unit, index) =>
    (missionStart < 0 || index >= missionStart) &&
    /(?:^|\s)1\)\s+.{12,}?(?:\s)2\)\s+/.test(getText(unit)) &&
    /(?:\s)3\)\s+/.test(getText(unit)) &&
    getText(unit).length < 2200)
  let missions: EvidenceText[] = []
  for (const unit of missionCandidates) {
    const items = getText(unit).split(/\s+(?=\d+\)\s)/)
    if (items.length < 3 || items.length > 9 || !items[0].startsWith('1)')) continue
    const parsed = items.map((item, index) => ({
      ...evidence(unit), text: item.replace(new RegExp('^' + (index + 1) + '\\)\\s*'), '').trim(),
    }))
    if (parsed.every(item => item.text.length >= 12 && item.text.length <= 400)) {
      missions = parsed
      break
    }
  }

  const tableStart = blocks.findIndex(unit => /^Tabel\s+\d+\.\s+Rumusan Visi.*Indikator/i.test(getText(unit)))
  const tableEnd = tableStart < 0 ? -1 : blocks.findIndex((unit, index) =>
    index > tableStart && /^\d+\.\d+\.\s+(Strategi|Arah Kebijakan)/i.test(getText(unit)))
  const table = tableStart < 0 ? [] : blocks.slice(tableStart, tableEnd > tableStart ? tableEnd : tableStart + 300)
  const yearBlock = table.find(unit => {
    const years = getText(unit).match(/\b20\d{2}\b/g) || []
    return years.length >= 3 && years.length <= 8 && new Set(years).size === years.length
  })
  const years = (yearBlock ? getText(yearBlock).match(/\b20\d{2}\b/g) || [] : [])
  const indicators: Indicator[] = []
  const names = new Set<string>()
  const numeric = /\d+(?:[.,]\d+)?(?:\s*[-–]\s*\d+(?:[.,]\d+)?)?/g
  for (let i = 0; i < table.length; i++) {
    const unit = table[i]
    const line = getText(unit)
    const match = line.match(/^(.{6,110}?)\s+(Juta Rupiah\/Jiwa|Persen|Nilai|Tahun|Kasus)\s+(.+)$/i)
    if (!match || years.length < 3) continue
    let name = tidy(match[1])
    const values = match[3].match(numeric) || []
    if (values.length !== years.length) continue
    if (/^Terbuka\s*\(TPT\)/i.test(name)) {
      const prev = table.slice(Math.max(0, i - 4), i).find(item => /^Tingkat Pengangguran/i.test(getText(item)))
      if (prev) name = tidy(getText(prev) + ' ' + name)
    }
    if (names.has(name.toLowerCase())) continue
    names.add(name.toLowerCase())
    indicators.push({
      ...evidence(unit), name, unit: match[2],
      targets: Object.fromEntries(years.map((year, index) => [year, tidy(values[index])])),
    })
  }
  return { vision, missions, indicators, years, sections }
}

function SourceBadge({ page, sourceUnitId }: { page: number | null; sourceUnitId: string }) {
  return <small title={'SourceUnit ' + sourceUnitId} style={{ color: '#9eb0bf' }}>
    PDF hlm. {page ?? '—'} · sumber {sourceUnitId.slice(0, 8)}
  </small>
}

export default function PlanningInsights({
  units, documentTitle, documentHash,
}: { units: PlanningSourceUnit[]; documentTitle: string; documentHash: string }) {
  const analysis = useMemo(() => extractInsights(units), [units])
  const [query, setQuery] = useState('')
  const matches = useMemo(() => {
    const needle = tidy(query).toLocaleLowerCase('id-ID')
    if (needle.length < 2) return []
    return units.filter(unit => (unit.normalized_text || '').toLocaleLowerCase('id-ID').includes(needle))
      .slice(0, 30)
  }, [units, query])
  const firstYear = analysis.years[0]
  const lastYear = analysis.years[analysis.years.length - 1]

  function downloadFindings() {
    const payload = {
      schema: 'pi.local.planning-insights-candidate.v1',
      document_title: documentTitle,
      document_sha256: documentHash,
      generated_at: new Date().toISOString(),
      status: 'CANDIDATE_UNREVIEWED',
      extraction_method: 'DETERMINISTIC_TEXT_HEURISTICS',
      caution: 'Textual extraction is not a verified reconstruction of PDF tables or embedded diagrams.',
      ...analysis,
    }
    const url = URL.createObjectURL(new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' }))
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = 'pi_telaah_awal_' + documentHash.slice(0, 8) + '.json'
    anchor.click()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  }

  return <div className="panel">
    <h3>Telaah awal isi perencanaan</h3>
    <p>Hasil dari dokumen <strong>{documentTitle}</strong>. Bagian yang berhasil dikenali ditautkan ke unit dan halaman sumber. Ini kandidat ekstraksi, bukan keputusan penilaian atau pembandingan lintas dokumen.</p>
    <p>
      <strong>{analysis.missions.length}</strong> misi · <strong>{analysis.indicators.length}</strong> baris indikator bertarget · <strong>{analysis.sections.length}</strong> judul bagian
    </p>
    <button type="button" onClick={downloadFindings}>Unduh hasil telaah terstruktur (JSON)</button>
    <div className="panel" style={{ marginTop: 12 }}>
      <h4>Visi</h4>
      {analysis.vision ? <><p>{analysis.vision.text}</p><SourceBadge page={analysis.vision.page} sourceUnitId={analysis.vision.source_unit_id}/></>
        : <p>Belum dapat dipastikan dari pola teks. Periksa dokumen sumber.</p>}
    </div>
    <div className="panel">
      <h4>Misi</h4>
      {analysis.missions.length > 0 ? <ol>{analysis.missions.map((item, index) =>
        <li key={index} style={{ marginBottom: 8 }}>{item.text} <SourceBadge page={item.page} sourceUnitId={item.source_unit_id}/></li>
      )}</ol> : <p>Daftar misi belum ditemukan sebagai teks bernomor yang lengkap.</p>}
    </div>
    <div className="panel">
      <h4>Indikator dan target tahunan yang dikenali</h4>
      {analysis.indicators.length > 0 ? <div className="tablewrap"><table>
        <thead><tr><th>Indikator</th><th>Unit</th><th>{firstYear}</th><th>{lastYear}</th><th>Bukti</th></tr></thead>
        <tbody>{analysis.indicators.map(item => <tr key={item.source_unit_id}>
          <td>{item.name}</td><td>{item.unit}</td><td>{item.targets[firstYear]}</td><td>{item.targets[lastYear]}</td>
          <td><SourceBadge page={item.page} sourceUnitId={item.source_unit_id}/></td>
        </tr>)}</tbody>
      </table></div> : <p>Baris target belum bisa dibaca utuh; tabel mungkin perlu rekonstruksi berdasarkan koordinat PDF.</p>}
      <p><small>Nilai yang tampil adalah target dokumen, bukan realisasi. Baris yang terbagi ke beberapa blok PDF tidak dipaksakan masuk agar tidak salah memasangkan angka.</small></p>
    </div>
    <div className="panel">
      <h4>Telusuri isi RPJMD</h4>
      <input aria-label="Cari teks di dokumen" style={{ width: '100%' }} placeholder="Contoh: kemiskinan, pengangguran, sampah, pariwisata" value={query} onChange={event => setQuery(event.target.value)} />
      {tidy(query).length >= 2 && <><p>{matches.length === 30 ? '30 kecocokan pertama' : matches.length + ' kecocokan'} ditampilkan.</p>
        <div className="tablewrap"><table><thead><tr><th>Halaman</th><th>Cuplikan sumber</th></tr></thead><tbody>
          {matches.map(item => <tr key={item.id}><td>{item.page_start ?? '—'}</td><td>{tidy(item.normalized_text || '').slice(0, 450)}<br/><SourceBadge page={item.page_start} sourceUnitId={item.id}/></td></tr>)}
        </tbody></table></div></>}
    </div>
  </div>
}
