import { useEffect, useState } from 'react'

type Evidence = { unit_fingerprint: string; excerpt: string; locator: Record<string, unknown> }
type Entity = { id: string; kind: string; title: string }
type Pipeline = {
  report_sha256: string
  source_unit_index?: Record<string, Evidence[]>
  documents: Record<string, { entities: Entity[] }>
}
type RelationType = { from_kind: string; to_kind: string; relation: string }
type RelationFinding = {
  id: string; from_title: string; to_title: string; relation: string; status: string
  grounding_status: string; lexical_status: string; lexical_reason: string
  direction_status: string; direction_reason: string; relationship_verification: string
  source_evidence: Evidence | null
}
type Result = {
  source_m2_report_sha256: string; report_sha256: string
  production_admission: string; regulatory_taxonomy: string
  findings: RelationFinding[]
  metrics: { verified_substantive_relationships: number; confirmed_contradictions: number }
}
type OriginalFinding = {
  id: string; original_source_status: string; m32_candidate_status: string
  original_source_evidence: { document_sha256: string; unit_fingerprint: string; locator: string } | null
}
type OriginalResult = {
  source_m2_report_sha256: string; source_m32_report_sha256: string
  report_sha256: string; production_admission: string
  findings: OriginalFinding[]
  metrics: { exact_original_source_quotes_located: number; original_source_gaps: number
    verified_substantive_relationships: number }
}

export default function PlanningRelationsM3({ pipeline }: { pipeline: Pipeline }) {
  const [ontology, setOntology] = useState<RelationType[]>([])
  const [ontologyError, setOntologyError] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [relation, setRelation] = useState('')
  const [source, setSource] = useState('')
  const [quote, setQuote] = useState('')
  const [result, setResult] = useState<Result | null>(null)
  const [originalResult, setOriginalResult] = useState<OriginalResult | null>(null)
  const [originalError, setOriginalError] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let active = true
    fetch('/api/m3/relation-types')
      .then(async response => {
        if (!response.ok) throw new Error(`HTTP ${response.status}`)
        return response.json()
      })
      .then((items: RelationType[]) => { if (active) setOntology(items) })
      .catch((err: Error) => { if (active) setOntologyError(err.message) })
    return () => { active = false }
  }, [])

  const roles = ['RPJMD', 'RKPD', 'RENSTRA', 'RENJA']
  const entities = roles.flatMap(role => (pipeline.documents[role]?.entities || []).map(entity => ({
    key: `${role}|${entity.id}`, role, entity,
  })))
  const sourceUnits = roles.flatMap(role => (pipeline.source_unit_index?.[role] || []).map(item => ({
    key: `${role}|${item.unit_fingerprint}`, role, item,
  })))
  const left = entities.find(item => item.key === from)
  const right = entities.find(item => item.key === to)
  const allowed = ontology.filter(item => item.from_kind === left?.entity.kind && item.to_kind === right?.entity.kind)
  const validRelation = allowed.some(item => item.relation === relation)
  const selectedSource = sourceUnits.find(item => item.key === source)

  async function evaluate() {
    if (!left || !right || !validRelation || !selectedSource || !quote.trim()) return
    setBusy(true); setResult(null); setOriginalResult(null); setError(''); setOriginalError('')
    try {
      const request = {
        pipeline_report_sha256: pipeline.report_sha256,
        claims: [{
          from_role: left.role, from_entity_id: left.entity.id,
          to_role: right.role, to_entity_id: right.entity.id, relation,
          source_role: selectedSource.role,
          source_unit_fingerprint: selectedSource.item.unit_fingerprint,
          quote: quote.trim(),
        }],
      }
      const response = await fetch('/api/m3/synthetic-relation-review', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(request),
      })
      if (!response.ok) throw new Error(`Review ${response.status}: ${await response.text()}`)
      const output: Result = await response.json()
      if (output.source_m2_report_sha256 !== pipeline.report_sha256) {
        throw new Error('M3_M2_SOURCE_DIGEST_MISMATCH')
      }
      setResult(output)
      try {
        const originalResponse = await fetch('/api/m3/synthetic-source-reverification', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(request),
        })
        if (!originalResponse.ok) throw new Error(`Verifikasi sumber ${originalResponse.status}: ${await originalResponse.text()}`)
        const verified: OriginalResult = await originalResponse.json()
        if (verified.source_m2_report_sha256 !== pipeline.report_sha256 ||
            verified.source_m32_report_sha256 !== output.report_sha256) {
          throw new Error('M33_SOURCE_LINEAGE_MISMATCH')
        }
        setOriginalResult(verified)
      } catch (err) { setOriginalError(err instanceof Error ? err.message : String(err)) }
    } catch (err) { setError(err instanceof Error ? err.message : String(err)) }
    finally { setBusy(false) }
  }

  function download() {
    if (!result) return
    const url = URL.createObjectURL(new Blob([JSON.stringify({ m32: result, m33: originalResult }, null, 2)], { type: 'application/json' }))
    const link = document.createElement('a')
    link.href = url; link.download = 'pi_m33_synthetic_relation_evidence_review.json'; link.click()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  }

  return <section className="panel">
    <h3>M3.2–M3.3 — Periksa klaim hubungan dan sumber asli</h3>
    <p>Pilih sendiri dua entitas, relasi, dan kutipan sintetis. M3.2 memeriksa pernyataan, sedangkan M3.3 membuat ulang empat berkas sintetis dan memverifikasi teks SourceUnit melalui parser dan locator G3. Ini bukan verifikasi hubungan substantif.</p>
    {ontologyError && <p role="alert">Daftar relasi G2 belum tersedia: {ontologyError}</p>}
    <div className="gridform">
      <label>Entitas asal
        <select value={from} onChange={event => { setFrom(event.target.value); setRelation(''); setResult(null); setOriginalResult(null) }}>
          <option value="">Pilih entitas asal</option>
          {entities.map(item => <option value={item.key} key={item.key}>{item.role} · {item.entity.kind} · {item.entity.title}</option>)}
        </select>
      </label>
      <label>Entitas tujuan
        <select value={to} onChange={event => { setTo(event.target.value); setRelation(''); setResult(null); setOriginalResult(null) }}>
          <option value="">Pilih entitas tujuan</option>
          {entities.map(item => <option value={item.key} key={item.key}>{item.role} · {item.entity.kind} · {item.entity.title}</option>)}
        </select>
      </label>
      <label>Jenis hubungan (ontologi G2)
        <select value={validRelation ? relation : ''} onChange={event => { setRelation(event.target.value); setResult(null); setOriginalResult(null) }}>
          <option value="">{allowed.length ? 'Pilih relasi' : 'Tidak ada relasi untuk jenis entitas ini'}</option>
          {allowed.map(item => <option key={item.relation} value={item.relation}>{item.relation}</option>)}
        </select>
      </label>
      <label>Kutipan sumber terpilih
        <select value={source} onChange={event => {
          const next = sourceUnits.find(item => item.key === event.target.value)
          setSource(event.target.value); setQuote(next?.item.excerpt || ''); setResult(null); setOriginalResult(null)
        }}>
          <option value="">Pilih unit sumber</option>
          {sourceUnits.map(item => <option key={item.key} value={item.key}>{item.role} · {item.item.excerpt.slice(0,90)}</option>)}
        </select>
      </label>
    </div>
    <p><label htmlFor="m32-quote">Kutipan asli (harus cocok persis dengan unit sumber)</label></p>
    <textarea id="m32-quote" rows={3} maxLength={800} style={{ width: '100%' }} value={quote}
      onChange={event => { setQuote(event.target.value); setResult(null); setOriginalResult(null) }} />
    <p><button disabled={busy || !left || !right || !validRelation || !selectedSource || !quote.trim()}
      onClick={evaluate}>{busy ? 'Memeriksa…' : 'Periksa klaim dan sumber sintetis'}</button></p>
    {error && <p role="alert">{error}</p>}
    {result && <>
      <p><small>SHA-256: <code>{result.report_sha256}</code> · Production: {result.production_admission} · Regulatory: {result.regulatory_taxonomy}</small></p>
      {result.findings.map(item => <div className="panel" key={item.id}>
        <p><strong>{item.status}</strong>: {item.from_title} → {item.to_title} ({item.relation})</p>
        <p>Bukti: {item.grounding_status}; frasa: {item.lexical_status}; arah: {item.direction_status}.</p>
        <small>{item.lexical_reason} {item.direction_reason}</small>
        <details><summary>Jejak kutipan sumber</summary>
          <p><small>Locator: {JSON.stringify(item.source_evidence?.locator || null)} · Fingerprint: {item.source_evidence?.unit_fingerprint || 'TIDAK TERVERIFIKASI'}</small></p>
        </details>
      </div>)}
      <p><strong>Relasi terverifikasi: {result.metrics.verified_substantive_relationships}; kontradiksi terkonfirmasi: {result.metrics.confirmed_contradictions}.</strong> Keduanya tetap nol tanpa verifikasi substantif dan otorisasi reviewer.</p>
      {originalError && <p role="alert">M3.3 gagal atau ditolak: {originalError}. Status kutipan asli belum diverifikasi.</p>}
      {originalResult && <div className="panel">
        <h4>M3.3 — Verifikasi ulang teks terhadap berkas asli sintetis</h4>
        <p>Kutipan SourceUnit tepat: {originalResult.metrics.exact_original_source_quotes_located}; belum terverifikasi: {originalResult.metrics.original_source_gaps}.</p>
        {originalResult.findings.map(finding => <p key={finding.id}>
          <strong>{finding.original_source_status}</strong> · {finding.original_source_evidence?.locator || 'Tidak ada locator asli yang cocok'}
          <small> · Fingerprint: {finding.original_source_evidence?.unit_fingerprint || 'TIDAK TERVERIFIKASI'}</small>
        </p>)}
        <p><small>M3.3 SHA-256: <code>{originalResult.report_sha256}</code>. Relasi substantif terverifikasi: {originalResult.metrics.verified_substantive_relationships}.</small></p>
      </div>}
      <button onClick={download}>Unduh hasil M3.2–M3.3 (JSON)</button>
    </>}
  </section>
}
