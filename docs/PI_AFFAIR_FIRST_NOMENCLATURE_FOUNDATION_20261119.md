# PI Affair-First / Incremental Nomenclature — Parallel Candidate (19 Nov 2026)

This is an **isolated branch**, based on accepted R85 SHA `62415afa23af4dbeb81e861e1d0bda219f23e5a0`; not a change to Work's R86 acceptance PR #69. No R86 runtime/route/DB/bridge marker modifications.

## Implemented
- Complete **32 concurrent affair top-level categories** transcribed from official text UU 23/2014 Pasal 12 (6 basic, 18 non-basic, 8 optional). PI IDs are internal, not official code numbers. Modern amendment consolidation and *specific* competence remain review-required.
- **6 observed local nomenclature rows** from the official Yogyakarta 2026 document for environment/waste: 2.11, 2.11.11, 2.11.11.2.01, .0004, .0008, .0017; labels, source URL and observation scope. 2.11 is prefix-derived, not verified directly.
- Read-only PHP registry classifier; result statuses exact/local, parent-prefix candidate, out-of-year review, unmapped abstain. Safe, non-persistent `stageBatch` reports collisions. Sectoral UU 18/2008 and PP 81/2012 are discovery candidates only; no legal judgement.
- Artisan `php artisan pi:affairs-inspect [code] --year=2026`, isolated PHPUnit tests.

## Current evidence provenance and limits
- https://jdih.kemenkeu.go.id/api/download/fulltext/2014/23TAHUN2014UU.htm (UU 23/2014, Pasal 9–16; verified textual categories, amendment chain not fully adjudicated).
- https://peraturan.bpk.go.id/Details/139075/permendagri-no-90-tahun-2019 (structural reference).
- https://bkad.kepriprov.go.id/pengumuman/kodefikasi-dan-nomenklatur-keuangan-daerah-kembali-dimutakhirkan (official news reference to Kepmendagri 900.1-861/2026; **2026 annex not ingested**).
- https://jdih.jogjakota.go.id/asset/naskah/2026pw3405004.pdf (municipal document observations; source bytes not hash-pinned).

**The 32-category law classification and the evolving Permendagri 90/2019 program-code taxonomy are separate models.** Support/oversight/general government functional classifications are NOT included in 32 concurrent affairs. The seed does not assert any specific OPD's competence.

## Next incremental batches (reviewed; no time estimates)
W1 ingest the official source annex for base Permendagri 90/2019 and 2021/2023/2024/2025/2026 update chain, preserve historical code versions rather than overwrite. W2 expand affair→field→program→activity→subactivity per sector with immutable source checksum and as-of/planning-year applicability. W3 link OPD mandates and actual plans/budgets. W4 add sector-trigger conditions + reverse legal verification for real cases. W5 generalize multi-Pemda and use shared graph contract with R86 once accepted.

## Acceptance commands
```
cd laravel
php artisan pi:affairs-inspect 2.11.11.2.01.0004 --year=2026
php artisan pi:affairs-inspect 2.11.11.2.01.0004 --year=2027
php artisan test --filter=PiAffairsNomenclatureTest
```

**Gates:** LEGAL_HARMONIZATION=NOT_ASSESSED; NUMERIC_COMPARISON=BLOCKED; REVIEW_DECISION=NO_ACTIVE_DECISION; PRODUCTION_ADMISSION=DENIED. Do not merge automatically. Unit tests/CI on this branch still require running in environment with Laravel dependencies.
