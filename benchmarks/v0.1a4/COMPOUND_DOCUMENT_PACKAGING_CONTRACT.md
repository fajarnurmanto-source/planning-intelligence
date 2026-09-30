# V0.1A.4 Compound Document Packaging Contract

## Purpose

Planning Intelligence distinguishes a logical planning document from the
physical files used to publish that document.

One benchmark document may consist of one or many official source assets.

## Core model

BENCHMARK DOCUMENT
    |
    +-- ASSET 1
    +-- ASSET 2
    +-- ASSET N

The benchmark_id identifies the logical planning document.

The asset_id identifies one immutable physical file belonging to that document.

## Rules

1. ONE DOCUMENT MAY HAVE MANY FILES

A planning document must not be treated as incomplete merely because its
official publication is split across multiple PDFs.

2. ASSET ORDER MUST BE PRESERVED

Where official annexes have an explicit order, the system must preserve it.

3. ASSET ROLE MUST BE EXPLICIT

Examples:

- BASE_REGULATION
- MAIN_NARRATIVE
- LAMPIRAN_I
- LAMPIRAN_II
- MATRIX
- SUPPORTING_ANNEX
- OTHER_OFFICIAL_ASSET

4. EACH PHYSICAL FILE HAS ITS OWN SHA256

A logical document checksum must never replace the checksum of its physical
source files.

5. ALL REQUIRED ASSETS MUST BE PRESENT

A compound benchmark is COMPLETE only when all required official assets needed
for the benchmark are registered and verified.

6. PROVENANCE IS ASSET-SPECIFIC

Each asset may have its own source URI, file name, retrieval record, checksum,
and provenance status.

7. DOCUMENT IDENTITY IS NOT FILE IDENTITY

Replacing, adding, or superseding a physical publication asset does not
silently create or destroy the logical planning-document identity.

8. NO SILENT MERGE

Planning Intelligence must not concatenate multiple PDFs and discard their
original provenance.

A derived combined representation may be produced later, but all original
asset lineage must remain available.

## Benchmark registry boundary

manifest.csv represents logical benchmark documents.

benchmark_assets.csv represents physical files belonging to those documents.

Existing file fields in manifest.csv remain temporarily backward-compatible
during V0.1A.4 migration but must not be assumed to support compound documents.

## Example

NAT-5-01
Rencana Pembangunan Jangka Menengah Nasional Tahun 2025-2029

Possible physical assets:

- Perpres Nomor 12 Tahun 2025
- Lampiran I
- Lampiran II
- Lampiran III
- Lampiran IV
- other official annex files where applicable

All belong to NAT-5-01 but retain separate physical-file provenance.

## Principle

Logical planning-document identity and physical publication packaging are
different layers of institutional evidence.
