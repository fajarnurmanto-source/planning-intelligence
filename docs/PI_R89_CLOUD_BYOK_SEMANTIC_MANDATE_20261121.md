# PI-CLOUD-SEMANTIC-002 / R89 — User-configurable hosted AI by provider API

**Owner directive:** 21 November 2026 (WIB). **Status:** MANDATORY_R89_CLOUD_FIRST. Supersedes the local-Ollama recommendation of the original R89 pilot, without erasing its history. PI must run on ordinary user hardware; hosted inference executes on the selected provider infrastructure, not user CPU/GPU.

## Architecture and intended user journey

PI Superior/R88 crosswalk -> Laravel R89 canonical evidence validation -> preview exact data and SHA-256 -> human selects DeepSeek, OpenAI, Gemini, Groq or OpenRouter -> explicit approval of that metadata and the provider's possible charges -> Laravel resolves tenant-isolated encrypted credential managed by R69A AI Settings -> fixed HTTPS provider endpoint -> bounded JSON semantic candidate -> deterministic R88.1 HybridSemanticReasoner -> visible review candidate/ABSTAIN and optional existing R88 JSON export.

**Reuse instead of duplication:** Existing /ai-settings.html, AiProviderCredentialVault, AiProviderSettingsController and five supported provider IDs. Institution administrator (institution_admin or superadmin) saves/revokes provider API keys plus model names with password re-verification. Keys are encrypted at rest, server side only. Ordinary authorized users choose configured provider per R89 analysis. No API key is returned to browser.

**No local AI hardware requirement.** OllamaLocalSemanticCandidateProvider and R89 local settings are retired from the cloud branch. Old R69A local inference code may remain for legacy reasons but is not invoked by R89.

## Data boundary, consent and technical caps

- GET /api/qa-superior-r89/semantic-preview never calls AI. It displays exactly the data which would be transmitted: year, as-of, official code, two documentary labels, statement that labels are NOT verified legal provision text, bounded evidence edge IDs/locators/provenance flags. No tenant ID, API key, internal PDF bytes, login details, full documents or R84 reviewer decisions are in the cloud prompt.
- POST /api/qa-superior-r89/semantic-candidates requires same-tenant authentication, CSRF, allowlisted provider ID, affirmative consent, explicit approval of the displayed metadata and possible charges, and SHA-256 of reviewed context. Laravel recomputes and rejects stale digest with HTTP 409 before calling provider.
- Fixed HTTPS endpoint per supported provider; no user-supplied base URLs, tools, web browsing, file retrieval or automatic provider failover. Provider response has three strict keys only: semantic_relation, semantic_confidence and explanation. Any extra key, invalid format/relation, truncation, or error fails closed.
- Technical caps: route throttle 2/min, max_tokens=400, context <=6 KB, provider response <=16 KB, timeout 25 seconds, no automatic retries or batch calls. These are NOT a financial spend ceiling. A real enterprise deployment additionally needs billing limits, cost analytics, privacy processing/retention review and procurement checks.
- Global kill switch PI_R89_CLOUD_ENABLED=false. When no provider API key/model is configured, return ABSTAIN with no network call. A provider can only run after an admin configures credentials and user approves per request.
- Because R88 currently contains documentary QA labels and locators only, interpretations are CANDIDATE-only. No provision-text entailment, legal harmonization, indicator formula/unit equivalence, fiscal coherence or automatic R84 reviewer decisions.

## Supported providers

| Provider | Existing AI Settings ID | Fixed HTTPS service |
| --- | --- | --- |
| DeepSeek | deepseek | api.deepseek.com/chat/completions |
| OpenAI | openai | api.openai.com/v1/chat/completions |
| Gemini | gemini | generativelanguage.googleapis.com/v1beta/models/...:generateContent |
| Groq | groq | api.groq.com/openai/v1/chat/completions |
| OpenRouter | openrouter | openrouter.ai/api/v1/chat/completions |

The administrator chooses a model currently supported by that provider. Provider prices, model names and privacy terms can change. No promise of free usage. Adding a provider requires a reviewed fixed-endpoint adapter, never arbitrary user-controlled URL.

## Acceptance and fail-closed statuses

- Missing key/model: ABSTAIN_PROVIDER_NOT_CONFIGURED; no outbound request.
- Unverified year/2027: ABSTAIN_NO_VERSIONED_CASE; no outbound request.
- Source/graph mismatch: ABSTAIN_INVALID_EVIDENCE_PATH or source 503; no outbound request.
- Stale preview digest: ABSTAIN_PREVIEW_STALE, HTTP 409; no outbound request.
- Admin disable: ABSTAIN_CLOUD_DISABLED_BY_ADMIN; no outbound request.
- Provider failure/truncation/unexpected response: ABSTAIN_CLOUD_PROVIDER_UNAVAILABLE; no fallback.
- Structurally valid AI proposal: CANDIDATE_REVIEW_REQUIRED, with separate semantic-confidence, evidence-confidence and legal-validation dimensions.
- Mandatory gates: LEGAL_HARMONIZATION=NOT_ASSESSED, NUMERIC_COMPARISON=BLOCKED, REVIEW_DECISION=NO_ACTIVE_DECISION, RELATION_PROMOTION=NOT_PERFORMED, PRODUCTION_ADMISSION=DENIED.

## Rollout and limitations

Implementation is an isolated stacked branch above R89 PR #76, R88.1 PR #75 and R88 PR #74. Do not merge, change main, or alter Windows QA port 8822 without isolated acceptance. CI uses mocked HTTP and encrypted test-only keys; never real billing credentials. Live provider behavior, expert benchmark, cross-tenant deployment security, lawful data transfer, provider DPA and budget require subsequent acceptance before general rollout.
