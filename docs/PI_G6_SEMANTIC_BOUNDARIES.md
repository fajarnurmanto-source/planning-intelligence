# PI G6 — local semantic relevance; synthetic pre-review only

**Non-admission:** SourceUnit exact quote match (G3) + G5 orientation + local embedding similarity **do not establish** entailment, causality, legal compliance, policy alignment, or true program impact. All findings have review_required=true. No automated approval.

**Data:** Only G2–G5 synthetic reports admitted. No production PostgreSQL change; use existing local G3 SQLite archive and Windows DPAPI reviewer account. Read existing G5 archive, then archive G6 as new digest.

**Providers:** `disabled` (default; reports NO_MODEL_ABSTAIN), `ollama-local` (fixed 127.0.0.1:11434 `/api/embed`; locally installed `embeddinggemma` is example), `cached-sentence-transformer` (explicit local model directory, CPU, `local_files_only=True`, `trust_remote_code=False`). No network or billable remote model provider. `ollama-local` is loopback-only and does not pull/install a model itself. Local SentenceTransformer may require optional dependencies that must be installed independently.

**Benchmark:** Fixed 40 synthetic Indonesian sentence pairs, with a stratified 20-development/20-holdout split, 10 positive and 10 negative per split. Labels measure **topical relevance**, not truth, legal correctness, entailment, or causal coherence. Precision, recall, specificity, F1 are emitted only if an actual provider runs. Do not tune candidate threshold on holdout. It is not an accuracy claim for government documents.

**Failure modes:** No model -> abstention; model error -> error (not fallback); G3 source not verified -> blocked; G5 negation or reversed direction -> blocked; prospective -> not claimed as achieved. All output records retain source hash, locator and SourceUnit fingerprint, previous stage digests and model identity. Do not interpret score as a probability.

**Local launch:** `python scripts/start_pi_graph_g6.py` (port 8102), respecting shared DPAPI and archive. Default no model. For Ollama explicitly set `$env:PI_G6_PROVIDER='ollama-local'` and `$env:PI_G6_OLLAMA_MODEL='embeddinggemma'`; install/start Ollama and pull a suitable local model *only if you choose*. No API billing. `python scripts/benchmark_pi_g6.py` runs on configured local model without sharing data. Pilot benchmark using actual licensed documents, model pinning/hashes, non-English analysis and expert labels required before commercial readiness can be raised.
