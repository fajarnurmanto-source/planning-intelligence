# TECHNICAL BENCHMARK BINDING CONTRACT

Version: V0.1A.4.1
Status: ACTIVE

## 1. Purpose

This contract defines how a technical benchmark test case may reuse an
already-registered physical benchmark asset without creating a duplicate
planning-document identity or duplicating the physical file.

Technical benchmark identity is test-case identity.
It is not planning-document identity.

## 2. Registry boundary

The existing registries retain their current meanings:

- manifest.csv represents logical planning benchmark documents.
- benchmark_assets.csv represents immutable physical files belonging to those
  logical planning documents.
- corpus_plan.csv defines required substantive, alignment, and technical
  benchmark slots.
- technical_benchmark_bindings.csv binds a technical benchmark slot to an
  existing registered physical asset for a specific technical qualification
  purpose.

A technical binding must not create a second logical planning-document record
for the same document solely to represent a technical stress-test role.

## 3. Physical asset reuse

A technical benchmark MAY reference an existing asset_id from
benchmark_assets.csv when that asset demonstrably satisfies the technical
benchmark characteristic.

The physical file must not be copied merely to create a technical benchmark.

The authoritative physical provenance remains the referenced
benchmark_assets.csv row, including:

- source URI
- relative path
- file format
- SHA-256
- physical asset identity

technical_benchmark_bindings.csv must reference these identities rather than
silently creating a new physical-file identity.

## 4. Required binding identity

Every binding row must identify:

- technical_benchmark_id
- source_benchmark_id
- source_asset_id
- qualification_status
- qualification_method
- evidence_summary
- qualified_at
- notes

technical_benchmark_id must exist in corpus_plan.csv with corpus_group=technical.

source_benchmark_id and source_asset_id must resolve to one existing row in
benchmark_assets.csv.

The source_asset_id must belong to source_benchmark_id.

## 5. Qualification status

Allowed qualification_status values:

- QUALIFIED
- REJECTED

QUALIFIED means the physical asset has been examined against the
required_characteristic in corpus_plan.csv and evidence is sufficient to use
that asset as the technical benchmark.

REJECTED means the candidate was examined and found unsuitable.

Absence of a binding row means no technical qualification decision has yet
been registered.

## 6. Evidence rule

No evidence -> no qualification.

A QUALIFIED binding must contain a concise evidence_summary describing the
observed technical characteristics and the deterministic or reproducible
method used to establish them.

Qualification does not alter the substantive meaning of the source planning
document.

Qualification does not assert hierarchy, semantic lineage, derivation,
implementation, causal relation, or policy alignment.

## 7. Provenance rule

Physical provenance remains asset-specific and authoritative in
benchmark_assets.csv.

A technical binding must not overwrite, replace, reinterpret, or duplicate
that provenance.

If the referenced asset is later superseded by a distinct document version,
the historical technical binding remains attached to the original immutable
asset_id unless a new qualification decision is explicitly registered.

## 8. No silent merge

Technical benchmark binding is a reference relationship only.

It must not:

- merge logical planning-document identities;
- concatenate or rewrite source files;
- silently substitute one asset for another;
- create a new file identity for an unchanged physical file.

## 9. Completion rule

A technical benchmark slot is considered qualified for V0.1A.4.1 only when:

1. the slot exists in corpus_plan.csv;
2. a binding row exists in technical_benchmark_bindings.csv;
3. the referenced benchmark_id and asset_id resolve successfully;
4. the referenced physical asset exists;
5. its current SHA-256 matches benchmark_assets.csv;
6. the required format matches;
7. evidence supports the required_characteristic;
8. qualification_status is QUALIFIED.

Technical qualification is separate from planning-document completeness.

## 10. Current architectural principle

Logical planning-document identity, physical publication identity, and
technical benchmark test-case identity are three separate layers.

They must remain explicitly linked but must not be collapsed.
