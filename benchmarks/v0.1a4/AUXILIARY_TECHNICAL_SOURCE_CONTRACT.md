# Auxiliary Technical Source Contract

Version: V0.1A.4.1
Status: ACTIVE

## 1. Purpose

This contract defines the provenance and identity layer for physical source
assets used by technical benchmarks when those physical assets are not already
represented in the planning benchmark registries.

This layer exists to preserve the semantic boundary between planning-document
identity and technical test-case source identity.

## 2. Existing Registry Meanings Remain Unchanged

The following existing registries retain their existing meanings:

- `manifest.csv` identifies logical planning benchmark documents.
- `benchmark_assets.csv` identifies immutable physical publication assets
  belonging to those logical planning benchmark documents.
- `corpus_plan.csv` identifies planned substantive and technical benchmark
  slots.
- `technical_benchmark_bindings.csv` binds qualified technical benchmark
  test cases to qualified source identities under the applicable binding
  contract.

An auxiliary technical source must not be inserted into `manifest.csv` or
`benchmark_assets.csv` merely to make a technical benchmark binding possible.

## 3. Auxiliary Technical Source Identity

An auxiliary technical source is provenance-bearing technical evidence used
for technical benchmark qualification when no suitable existing planning
benchmark asset identity exists.

`technical_source_id` identifies one auxiliary technical source provenance
container.

`technical_asset_id` identifies one immutable physical file belonging to that
auxiliary technical source.

Technical source identity is not planning-document identity.

Technical source identity is not technical benchmark identity.

Technical benchmark identity, technical source identity, and physical file
identity must remain distinct.

## 4. Registry

The canonical auxiliary technical source registry is:

`technical_source_assets.csv`

Required columns:

- `technical_source_id`
- `technical_asset_id`
- `source_role`
- `source_title`
- `jurisdiction_name`
- `institution_name`
- `source_status`
- `source_uri`
- `source_retrieved_at`
- `file_name`
- `relative_path`
- `file_format`
- `file_sha256`
- `expected_technical_characteristic`
- `confidentiality`
- `notes`

## 5. Identity Rules

Each `technical_source_id` must be explicit and stable.

Each `technical_asset_id` must be unique.

Each physical asset must have exactly one immutable SHA-256 value in this
registry.

A physical file must not be copied merely to create another identity.

The same physical hash must not be silently registered under multiple
technical asset identities.

A technical asset must not silently reuse the identity of a planning benchmark
asset.

If an asset already exists in `benchmark_assets.csv`, the existing planning
asset identity must be preferred instead of creating a duplicate auxiliary
technical asset.

## 6. Provenance Requirements

Every registered auxiliary technical asset must have provenance sufficient to
identify its origin.

At minimum:

- source title must be explicit;
- source status must be explicit;
- source URI must be explicit and must not be invented;
- retrieval date must be explicit;
- file name must be explicit;
- relative path must resolve to the physical file;
- file format must be explicit;
- current physical SHA-256 must match the registered SHA-256.

Official public-sector sources should use an explicit source status such as
`OFFICIAL_JDIH` or another accurate evidence-based status.

## 7. Technical Qualification Boundary

Registration in `technical_source_assets.csv` does not by itself qualify a
technical benchmark.

Technical source registration records provenance and immutable source identity.

Technical benchmark qualification remains a separate evidence decision.

No evidence -> no qualification.

A technical benchmark may only be qualified when its required technical
characteristic has been demonstrated by a reproducible or deterministic
method.

## 8. Planning and Semantic Safety

Auxiliary technical source registration does not assert:

- planning hierarchy;
- semantic lineage;
- derivation;
- implementation;
- policy alignment;
- causal relation;
- institutional endorsement beyond the evidenced source status.

Period overlap, same jurisdiction, same institution, similar title, or similar
content must not be promoted into semantic lineage.

## 9. Historical Stability

Once an auxiliary technical asset is used by a committed technical benchmark
binding, its historical identity and SHA-256 must not be silently overwritten.

If a later representation supersedes it, the historical source remains
attached to the historical qualification decision unless an explicit successor
decision is recorded.

## 10. Physical File Safety

Original downloaded source bytes must be preserved.

No silent OCR, conversion, recompression, page extraction, merge, or rewrite
may replace the registered physical source.

Derived representations may be created later only with explicit provenance
that preserves linkage to the immutable original source.

## 11. Binding Integration Boundary

This contract establishes the auxiliary technical source identity layer only.

It does not by itself modify the schema or resolution rules of
`technical_benchmark_bindings.csv`.

Any extension allowing technical benchmark bindings to resolve against
`technical_source_assets.csv` must be introduced by a separate explicit
binding-contract evolution milestone.

Until that binding-contract evolution is completed, auxiliary technical source
registration and technical benchmark binding remain separate operations.

## 12. Completion Condition

The auxiliary technical source foundation is valid when:

1. this contract exists;
2. `technical_source_assets.csv` exists;
3. the registry schema exactly matches this contract;
4. the registry is parseable;
5. no existing planning registry meaning has changed;
6. no existing technical benchmark binding meaning has changed.

At foundation creation time the registry may contain zero rows.

## 13. Non-Negotiable Separation

The system must preserve three distinct concepts:

1. logical planning benchmark document;
2. immutable physical planning publication asset;
3. auxiliary technical source asset.

Technical benchmark test-case identity remains a fourth distinct concept.

No silent merge between these identity classes is permitted.
