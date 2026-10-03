# Technical Benchmark Binding Contract ? V2

Version: V0.1A.4.1
Status: ACTIVE
Successor of: TECHNICAL_BENCHMARK_BINDING_CONTRACT.md

## 1. Purpose

This successor contract governs technical benchmark bindings that may resolve
against either an existing planning publication asset or an auxiliary
technical source asset.

The predecessor contract remains preserved as historical evidence of the
original TECH-TABLE qualification and must not be silently rewritten.

## 2. Identity Classes

A technical benchmark is a test-case identity.

It is not a planning-document identity.

It is not a physical-file identity.

A binding must declare exactly one `source_class`.

Allowed values are:

- `PLANNING_ASSET`
- `AUXILIARY_TECHNICAL_ASSET`

No other source class is valid under this contract.

## 3. Binding Registry Schema

The canonical binding registry remains:

`technical_benchmark_bindings.csv`

Required columns, in order:

- `technical_benchmark_id`
- `source_class`
- `source_benchmark_id`
- `source_asset_id`
- `technical_source_id`
- `technical_asset_id`
- `qualification_status`
- `qualification_method`
- `evidence_summary`
- `qualified_at`
- `notes`

## 4. PLANNING_ASSET Resolution

When `source_class` is `PLANNING_ASSET`:

- `source_benchmark_id` must be populated;
- `source_asset_id` must be populated;
- `technical_source_id` must be empty;
- `technical_asset_id` must be empty;
- the pair (`source_benchmark_id`, `source_asset_id`) must resolve to exactly
  one existing row in `benchmark_assets.csv`.

The resolved physical file must exist and its current SHA-256 must equal the
registered SHA-256.

Existing TECH-TABLE qualification is migrated to this source class without
changing its original benchmark ID, source benchmark ID, source asset ID,
qualification status, qualification method, evidence summary, qualification
date, or notes.

## 5. AUXILIARY_TECHNICAL_ASSET Resolution

When `source_class` is `AUXILIARY_TECHNICAL_ASSET`:

- `source_benchmark_id` must be empty;
- `source_asset_id` must be empty;
- `technical_source_id` must be populated;
- `technical_asset_id` must be populated;
- the pair (`technical_source_id`, `technical_asset_id`) must resolve to
  exactly one existing row in `technical_source_assets.csv`.

The resolved physical file must exist and its current SHA-256 must equal the
registered SHA-256.

Auxiliary technical source registration and technical benchmark qualification
remain separate evidence decisions.

## 6. Exclusive Source Identity

Exactly one source identity class may be populated for each binding.

A binding must never contain both planning-asset identity fields and auxiliary
technical-source identity fields.

A binding must never contain neither identity class.

`source_class` determines which resolver is authoritative.

No implicit fallback between resolvers is permitted.

## 7. Qualification

Allowed qualification states remain:

- `QUALIFIED`
- `REJECTED`

No evidence -> no qualification.

Qualification requires reproducible or deterministic evidence that the source
asset satisfies the technical characteristic required by the corresponding
technical benchmark slot.

Registration alone is not qualification.

## 8. Physical and Provenance Safety

A physical file must not be copied merely to create a second technical
benchmark identity.

Planning asset provenance remains in `benchmark_assets.csv`.

Auxiliary technical source provenance remains in
`technical_source_assets.csv`.

Technical benchmark binding provenance remains in
`technical_benchmark_bindings.csv`.

Physical provenance must not be silently overwritten.

Original source bytes must remain immutable.

## 9. Semantic Safety

A technical benchmark binding does not assert:

- planning hierarchy;
- semantic lineage;
- derivation;
- implementation;
- policy alignment;
- causal relation.

Technical qualification is evidence about a technical test characteristic,
not a planning-policy relationship.

No evidence -> no finding.

## 10. Historical Stability

The predecessor binding contract remains historical evidence and is not
retroactively modified.

Existing TECH-TABLE qualification retains its original substantive fields.

The migration adds only explicit source-class resolution semantics required to
support auxiliary technical assets.

Historical qualification evidence must not be silently reinterpreted.

## 11. Resolver Completion Gate

A qualified technical binding is valid only when:

1. its technical benchmark slot exists in `corpus_plan.csv`;
2. `source_class` is valid;
3. exactly one source identity class is populated;
4. the declared resolver returns exactly one source asset;
5. the physical source exists;
6. current SHA-256 matches registered SHA-256;
7. required file format matches the technical slot;
8. evidence demonstrates the required technical characteristic;
9. qualification status is `QUALIFIED`.

## 12. Migration Boundary

Migration from the predecessor schema to this schema does not itself:

- register an auxiliary technical source;
- qualify TECH-SCAN;
- add a TECH-SCAN binding;
- alter TECH-TABLE evidence;
- change planning benchmark identity;
- assert semantic lineage or causality.

Those actions require separate explicit milestones.
