# V0.0 Technical Architecture

Source blueprint: v1.2. This package implements only the local foundation while preparing the schema for later modules.

## Runtime
Browser → Nginx/React (`localhost:8080`) → FastAPI → PostgreSQL/pgvector.
Raw documents/regulations are bind-mounted under `./data` on the host drive.

## Trust properties implemented now
- Raw file preserved locally.
- SHA-256 per uploaded file.
- Append-style Audit Ledger with chained `previous_hash` → `event_hash`.
- PostgreSQL persistent storage on host folder.
- No AI calls in V0.0 (`AI_PROVIDER=NO_AI`).
- Backup package includes DB dump, raw files, rules/prompts/docs, config, manifest and ZIP checksum.

## Schema-ready V1.2 entities
Core operational tables plus sources, rules/rule_versions, graph nodes/edges, evidence, problems/causes, population groups, locations, indicators/observations, assumptions, interventions, risks, decisions/actions/milestones/dependencies, findings, model_calls, research_runs, scenarios, lessons_learned.

These are intentionally not all exposed in the V0.0 UI.
