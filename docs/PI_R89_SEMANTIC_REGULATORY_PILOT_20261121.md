> **SUPERSEDED 21 NOVEMBER 2026:** Owner changed R89 from local Ollama to institution-configured hosted APIs (DeepSeek, OpenAI, Gemini, Groq, OpenRouter) with SHA-pinned user-approved metadata preview. This historical document is NOT the active architecture. See [R89 Cloud BYOK mandate](PI_R89_CLOUD_BYOK_SEMANTIC_MANDATE_20261121.md).

# R89 — Semantic Regulatory Reasoning: evidence-bound local pilot

**Date:** 21 November 2026 · **Governing mandate:** PI-HYBRID-001 · **Base:** R88.1 draft PR #75 · **Production admission:** DENIED

## Scope actually implemented in R89 pilot

R89 adds **on-demand terminology analysis** inside the R88 PI Superior crosswalk, using the same canonical evidence graph. A local Ollama model can propose semantic relations between the observed subactivity label and output-indicator wording. The model receives only bounded labels, dates and R88 documentary evidence locators/statuses, **not the verified full legal text of provisions**. This scope intentionally **does not** claim legal entailment, harmonization, verified indicator-equivalence, an operational definition, or fiscal coherence.

- Laravel endpoint: POST /api/qa-superior-r89/semantic-candidates (authenticated, tenant-scoped, CSRF protected, throttled 2/minute, explicit consent).
- Evidence input: versioned R88 crosswalk and R88 operational-definition registry; its two documented R85 edges E05/E12 are checked as an uninterrupted directed source-to-target chain by the R88.1 validator **before** any provider call.
- Provider: replaceable SemanticCandidateProvider binding to OllamaLocalSemanticCandidateProvider, cloud providers deliberately absent; URL is **fixed** to http://127.0.0.1:11434/api/chat. No model pull, autonomous discovery, retry jobs or monetary spend.
- Provider output may contain only semantic_relation, semantic_confidence label, explanation; all source/target IDs, supporting edges, tenant, year, model provenance and gates are reconstructed **on the server**. Extra keys, unknown relations, invalid dates and unavailable model reject/abstain.
- Statuses: default ABSTAIN_PROVIDER_NOT_CONFIGURED, absent year/case ABSTAIN_NO_VERSIONED_CASE, provider error ABSTAIN_LOCAL_PROVIDER_UNAVAILABLE, invalid AI proposal ABSTAIN_INVALID_SEMANTIC_PROPOSAL; valid structure CANDIDATE_REVIEW_REQUIRED only.
- Output: PI_R89_EVIDENCE_BOUND_SEMANTIC_ANALYSIS_V1; R88 panel displays the candidate and can include its validated envelope in the existing R88 JSON export. No AI output is committed to the R85 graph or R84 human decision ledger.

## Default: no inference or transmissions

The PI_R89_SEMANTIC_LOCAL_ENABLED environment variable defaults to false and PI_R89_SEMANTIC_LOCAL_MODEL defaults to empty. **Do not activate it as part of code deployment.** The source path is never browser-selected. Even when enabled, a POST with affirmative consent is required; non-local / non-testing Laravel runtime abstains. The only model API is localhost. The full prompt is not persisted.

After **separate explicit operator decision** and installation of a locally available Ollama model, QA-only environment values can include:

    PI_R89_SEMANTIC_LOCAL_ENABLED=true
    PI_R89_SEMANTIC_LOCAL_MODEL=gemma3:1b

The model name above is an **example only** and does not imply an installed/downloaded model. If unavailable, the UI must state ABSTAIN; never downgrade to an unapproved cloud service. The user must log in, run R88 2026, check consent, and click the R89 analysis button. On 2027 there is no model call, and cross-year equality remains ABSTAIN.

## Guardrails and acceptance

1. R88 2026 and 2027 unchanged; model cannot promote legal status, amount, review approval or equivalence; relation_promotion=NOT_PERFORMED, numeric_comparison=BLOCKED, legal_validation_status=NOT_ASSESSED, production_admission=DENIED.
2. API tests must prove guest 401, foreign tenant 403, no consent 422, no provider by default, 2027 no provider call, valid local candidate 200 and anchored E05/E12, hallucinated relation blocked, provider 503/partial output fail-closed, missing source fails closed.
3. Source prompt is marked documentary metadata only, never described as primary provision-level truth. context_sha256 permits detecting changed candidate input without storing source text.
4. Cloud/Gemini/OpenAI adapter, verified legal-text entailment, source rights/retention review, counterevidence pipeline, 2027 version registry, real-model benchmarking and operational acceptance **are not implemented** in this slice.
5. Windows QA acceptance on port 8822 requires a **separately created isolated R89 worktree/runtime**, not checkout/reset of existing R88 worktree or auto-apply to main. Do not modify R88/main environment or SQLite, and do not overwrite existing R87 untracked benchmarking outputs.

## Next acceptance milestones

- R89_PHASE1_SOURCE=IMPLEMENTED only after commits.
- R89_CI=PASS only when the final commit's Actions are green.
- R89_LOCAL_LIVE_PROVIDER=NOT_RUN until a real local Ollama model is available and permitted source content/consent is checked.
- R89_EVIDENCE_TEXT_ENTAILMENT=NOT_IMPLEMENTED; provision-level text and real semantic benchmarks remain requirements before R90.
- PR stays DRAFT and stacked on R88.1. Do not merge until upstream R88/R88.1 and the security/data-rights gates are accepted.
