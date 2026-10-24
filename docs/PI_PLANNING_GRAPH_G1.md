# PI Planning Knowledge Graph G1 — synthetic-only functional candidate

**Adopt rather than rebuild:** NetworkX graph primitives; PI native parser;
VS1 normalization, evidence, and comparison; VS2 reviewer event log;
VS4 SQLAlchemy archive and Word/JSON exporter; VS5 synthetic upload boundary.

## What works

Select four synthetic documents by explicit role: RPJMD, RKPD, Renstra, Renja.
The graph models a *declared structural hypothesis* with edges RPJMD→RKPD,
RPJMD→Renstra, Renstra→Renja, extracted indicator observations and matched
indicator/year pairs. Cross-document target differences and missing matches
are candidate findings with original SourceUnit fingerprints and locators.

Candidates are archived, reopenable, reviewable under the existing local role
boundary, and exportable with the existing VS4/VS2 Word/JSON workflow.
NetworkX computes the directed graph and RPJMD→Renja multi-hop path.

## Not supported / not claimed

Regulatory/authority graph, causal inference, legal compliance, automatic
indicator ontology, inter-agency semantics, and production admission are
**not implemented**. File marker is not rights authorization. This feature
must never be turned on for official real documents without validating source
rights, authorized role/identity, graph rule versions and benchmarking.

## Local browser

Use the existing VS5 launcher mechanics from a separately cloned merged G1
branch and port **8097**. See `scripts/start_pi_graph_g1.py` in this overlay.
Do not run demo and candidate services against production PI DB; original
8080 and dirty I0 repository must remain untouched.
