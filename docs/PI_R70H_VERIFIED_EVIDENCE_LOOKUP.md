# PI R70H — Laravel verified evidence lookup

This is a **source-backed review feature**, not legal interpretation. It
extends the working R70G Laravel read model without a new database table,
external API call, Python service, model request, or dependency change.

Endpoint (existing authenticated PI tenant session required):

`GET /api/qa-regulatory-r70h/evidence/{sourceId}/{excerptSha256}`

Parameters are the `source_id` and `excerpt_sha256` returned within the R70G
document evidence references. The endpoint returns the original excerpt text
and its PDF page plus explicit source and integrity references. Multiple
occurrences of the same verified excerpt hash are returned as separate
`matches` with their document, target or date context.

Security and QA constraints:

- Both source JSON files are loaded from local `config('pi.regulatory.*')`,
  never from an arbitrary path supplied by the browser.
- The existing R70G projector first verifies all source, ledger, scope,
  evidence and cross-file hash contracts; the source bytes are then pinned
  again before excerpts are read.
- Request must be authenticated and satisfy `pi.tenant` middleware.
- Unknown source/hash returns 404; source corruption returns non-disclosing
  503; successful response includes `no-store, private` cache policy.
- Reviewer remains required. Numeric comparison stays BLOCKED, legal
  harmonization NOT_ASSESSED, and production DENIED.

Focused feature tests cover authorization, tenant separation, one real
source-shaped fixture excerpt, unknown hash and source corruption. To validate
on the local Laravel environment after the source files are applied:

`php artisan test --filter=RegulatoryEvidenceTest`

No migration, Composer/npm install, frontend build or restart is required for
the PHP API itself. UI integration into the existing static PI page is a
follow-on after that page's source is published to the GitHub bridge.
