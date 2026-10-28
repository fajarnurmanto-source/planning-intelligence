import { useState } from 'react'

type DocumentRow = {
  id: string
  doc_type: string
  title: string
  original_name: string
  sha256: string
  latest_version_id: string | null
}

type ParseRun = {
  id: string
  status: string
  page_count: number | null
  source_unit_count: number
  input_sha256: string
  result_sha256: string | null
  parser_name: string
  parser_version: string
  reused: boolean
}

type SourceUnit = {
  id: string
  unit_type: string
  sequence_index: number
  page_start: number | null
  page_end: number | null
  section_path: string | null
  normalized_text: string
  locator: Record<string, unknown>
  unit_fingerprint: string
}

type Extraction = {
  document: DocumentRow
  run: ParseRun
  units: SourceUnit[]
}

async function jsonResponse<T>(url: string, options?: RequestInit): Promise<T> {
  const response = await fetch(url, options)
  if (!response.ok) {
    throw new Error('HTTP ' + response.status + ': ' + (await response.text()).slice(0, 700))
  }
  return response.json() as Promise<T>
}

export default function DocumentExtraction({ documents }: { documents: DocumentRow[] }) {
  const [busyId, setBusyId] = useState<string | null>(null)
  const [error, setError] = useState('')
  const [extraction, setExtraction] = useState<Extraction | null>(null)

  async function extract(document: DocumentRow) {
    if (!document.latest_version_id || busyId) return
    setBusyId(document.id)
    setError('')
    setExtraction(null)

    try {
      const version = encodeURIComponent(document.latest_version_id)
      const run = await jsonResponse<ParseRun>('/api/document-versions/' + version + '/parse', { method: 'POST' })
      if (run.status !== 'SUCCEEDED') {
        throw new Error('Parser belum menghasilkan status SUCCEEDED: ' + run.status)
      }
      if (run.input_sha256.toLowerCase() !== document.sha256.toLowerCase()) {
        throw new Error('Checksum dokumen tidak cocok dengan hasil parser.')
      }

      const units: SourceUnit[] = []
      const count = run.source_unit_count
      for (let offset = 0; offset < count; offset += 500) {
        const batch = await jsonResponse<SourceUnit[]>(
          '/api/parsing-runs/' + encodeURIComponent(run.id) + '/source-units?offset=' + offset + '&limit=500',
        )
        if (!Array.isArray(batch) || batch.length === 0) {
          throw new Error('Hasil ekstraksi terputus pada unit ' + offset)
        }
        units.push(...batch)
      }
      if (units.length !== count) {
        throw new Error('Jumlah source unit tidak sesuai dengan laporan parser.')
      }
      setExtraction({ document, run, units })
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err))
    } finally {
      setBusyId(null)
    }
  }

  function download() {
    if (!extraction) return
    const payload = {
      schema: 'pi.local.document-extraction.v1',
      generated_at: new Date().toISOString(),
      production_admission: 'DENIED',
      document: {
        id: extraction.document.id,
        title: extraction.document.title,
        doc_type: extraction.document.doc_type,
        original_name: extraction.document.original_name,
        sha256: extraction.document.sha256,
      },
      parser: extraction.run,
      source_units: extraction.units,
    }
    const filename = 'pi_ekstraksi_' + extraction.document.id.slice(0, 8) + '.json'
    const url = URL.createObjectURL(new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' }))
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    link.click()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  }

  return <div className="panel">
    <h3>Ekstraksi dokumen asli</h3>
    <p>Unggah PDF, DOCX, atau XLSX yang memang boleh Anda gunakan. Klik Ekstrak untuk menghasilkan unit teks beserta lokasi sumber dan fingerprint. Pemrosesan dilakukan oleh backend PI lokal; hasil ini belum merupakan analisis koherensi atau persetujuan produksi.</p>
    {documents.length === 0 ? <p>Belum ada dokumen. Simpan Project dan unggah dokumen pada formulir di atas.</p> :
      <div className="tablewrap"><table>
        <thead><tr><th>Dokumen</th><th>Versi</th><th>Aksi</th></tr></thead>
        <tbody>{documents.map(document => <tr key={document.id}>
          <td>{document.doc_type} — {document.title}</td>
          <td>{document.original_name}</td>
          <td><button type="button"
            disabled={busyId !== null || !document.latest_version_id}
            onClick={() => extract(document)}>
            {busyId === document.id ? 'Mengekstrak…' : 'Ekstrak dokumen'}
          </button></td>
        </tr>)}</tbody>
      </table></div>}
    {error && <p role="alert" style={{ color: '#b42318' }}>Ekstraksi gagal: {error}</p>}
    {extraction && <>
      <div className="panel">
        <p><strong>{extraction.document.title}</strong></p>
        <p>Hasil nyata: {extraction.run.source_unit_count} unit sumber; {extraction.run.page_count ?? '—'} halaman; parser {extraction.run.parser_name} {extraction.run.parser_version}.</p>
        <p><small>SHA-256 sumber: {extraction.run.input_sha256}. Hasil: {extraction.run.result_sha256 || '—'}.</small></p>
        <button type="button" onClick={download}>Unduh seluruh hasil ekstraksi (JSON)</button>
      </div>
      <h4>Cuplikan isi dokumen</h4>
      <div className="tablewrap"><table>
        <thead><tr><th>Urutan</th><th>Halaman</th><th>Jenis</th><th>Teks terekstraksi</th></tr></thead>
        <tbody>{extraction.units.slice(0, 30).map(unit => <tr key={unit.id}>
          <td>{unit.sequence_index}</td>
          <td>{unit.page_start ?? '—'}</td>
          <td>{unit.unit_type}</td>
          <td>{unit.normalized_text.slice(0, 300) || '—'}</td>
        </tr>)}</tbody>
      </table></div>
      {extraction.units.length > 30 && <p><small>30 unit pertama ditampilkan; seluruh {extraction.units.length} unit tersedia pada berkas JSON.</small></p>}
    </>}
  </div>
}
