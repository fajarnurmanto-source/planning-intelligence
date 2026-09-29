# V0.1A.4 Temporal Compatibility Contract

## Purpose

Planning Intelligence must not infer document lineage solely from hierarchy,
planning horizon, or period overlap.

A document may be relevant to another document without having been derived
from it.

## Core rules

1. HIERARCHY IS NOT LINEAGE
   Placement in a national-provincial-local hierarchy does not by itself prove
   DERIVED_FROM, GUIDED_BY, or IMPLEMENTS.

2. PERIOD OVERLAP IS NOT DERIVATION
   Overlapping planning periods indicate temporal coexistence only.

3. CHRONOLOGY MUST BE PRESERVED
   A document cannot be treated as derived from a document that was adopted
   later unless explicit legal or documentary evidence establishes the
   relationship.

4. SEMANTIC RELATIONS REQUIRE EVIDENCE
   Relations such as DERIVED_FROM, ALIGNED_WITH, GUIDED_BY, IMPLEMENTS,
   SUPERSEDED_BY, and HISTORICAL_BASIS require evidence.

5. NO EVIDENCE -> NO ASSERTED RELATION
   Unsupported relations remain CANDIDATE or UNKNOWN.

## Planning-period relations

The deterministic temporal layer may classify two planning periods as:

- SAME_PERIOD
- WITHIN
- CONTAINS
- PARTIAL_OVERLAP
- PRECEDES
- FOLLOWS
- UNKNOWN

These classifications describe time only and do not imply policy lineage.

## Semantic relation states

- CANDIDATE
- EVIDENCE_SUPPORTED
- REJECTED
- UNKNOWN

## Benchmark case: DIY medium-term versus long-term

PROV-5-01:
RPJMD DIY 2022-2027
Document year: 2023

PROV-20-01:
RPJPD DIY 2025-2045
Document year: 2024

The planning periods PARTIAL_OVERLAP during 2025-2027.

However, PROV-5-01 predates PROV-20-01 as a document.
Therefore Planning Intelligence MUST NOT automatically assert:

PROV-5-01 DERIVED_FROM PROV-20-01

Any semantic relationship between these documents requires explicit evidence.

## Benchmark case: annual plan inside medium-term period

PROV-1-01:
RKPD DIY 2026

PROV-5-01:
RPJMD DIY 2022-2027

The year 2026 is WITHIN the 2022-2027 planning period.

This temporal compatibility permits semantic relationship testing, but does
not by itself establish IMPLEMENTS or DERIVED_FROM. Evidence is still required.

## Future system fields

The institutional data model should ultimately support:

- planning_period_start
- planning_period_end
- adopted_at
- effective_from
- effective_to
- superseded_at
- relation_type
- relation_effective_from
- relation_effective_to
- temporal_context
- evidence_id
- confidence / validation status

## Principle

Temporal compatibility is deterministic metadata.
Policy lineage is an evidence-backed analytical assertion.
