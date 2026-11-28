# PI intelligence acceleration — actual qualification, 28 November 2026

Development branch codex/pi-single-runtime-windows-app-20261122, starting HEAD fed19ebfec6d65d96b0802a01eb5d9a59304a712. PR78 stays draft; no auto-merge. This batch extends existing Laravel engines and UI. No new application, dependency installation, cloud inference, account or database migration.

## Real corpus and independent sample

Eight existing byte-pinned PDFs, 1,119 physical pages, zero network requests and zero document holds. Original first-pass denominator remains415 provision candidates /282 table candidates /422 format-review pages. After improvement95/422 have a more specific nonvisual candidate route (before88);327 still need alternative/visual review. All4,186 returned hierarchy/account locators reconcile against inventory page/line hashes; no unbound locator. This verifies binding of returned fragments, not missing-content completeness or semantic correctness.

Two independent AI agents reviewed96 physical pages (12/document) and direct native text before predictions. Labels are AI-assisted, not human-certified gold. Definitions distinguish nonfiscal tables from account rows and native text insufficiency from visual readability. Frozen hash-rank ranks1–6/document development; ranks7–12 holdout. Only development labels informed fixes. Source freeze recorded before first holdout evaluation; no classifier changes followed holdout access.

| Sample | Before correct | After correct | After mismatch | After routing abstention |
|---|---:|---:|---:|---:|
| Development48 |10 (20.83%)|26 (54.17%)|1|21|
| Holdout48 |8 (16.67%)|17 (35.42%)|6|25|
| Combined96 (descriptive)|18 (18.75%)|43 (44.79%)|7|46|

Combined routing coverage50/96;15 successful VISUAL routes are still extraction abstentions. Deterministic candidate-route coverage35/96; extraction-route abstentions61/96. PROVISION precision28/29 and recall28/31; VISUAL precision15/15 and recall15/18. OTHER remains46 routing abstentions. HEADING has0 true positives,6 false positives and1 missed reference. ACCOUNT_FRAGMENT/MIXED have zero independent support; their accuracy is not assessed. Critical false fiscal routes0 is only a small-sample observation. Routing scores do not measure cell, hierarchy, numerical or legal accuracy. G1 remains OPEN; this holdout is now consumed and cannot be reused as unseen tuning evidence.

Measured defects and focused fixes: native header/footer-only or fragmented glyph text is explicitly held for visual work; first-pass routes preserved. Article-specific explanatory detail has a separate review route, without inventing operative hierarchy parents. Summaries retain document/page denominator, source hashes, returned-locator checks and missing-format coverage. No OCR or automatic legal promotion was added.

## HRIA on actual pinned UU18/2008 and PP81/2012

Initial native Windows extraction produced CRLF; anchored headings matched zero, causing0/7 mandate results. Boundary normalization retains physical form-feed pages; CRLF/LF regression verifies equal context hashes. Actual batch now has7/7 mandate classifications and2/2 negative controls,7 bounded operative previews, no provider request. UU21 is negative only for citation in PP81 preamble: UU21(2) does delegate incentive/disincentive provisions to PP. It must not be labelled no-delegation.

Existing bounded lexical retrieval now uses the full uniquely headed UU article and at most four local references, capped12,000 bytes, then selects complete single-page PP articles/paragraphs with exact excerpt hashes. At most two candidates and6,000 encoded context bytes remain. Duplicate or cross-page incomplete spans abstain. No article-to-article gold mapping is hardcoded.

Independent AI-assisted reference was frozen before predictions. Initial retrieval after CRLF repair had0/5 exact candidate article overlap. Final overlap6/14 across6/7 mandate cases; UU22 still has no aligned candidate. This is limited article-set overlap, not semantic correctness or proof of implementation. Subject/object/authority/time/exceptions are preserved in the independent reference; engine validation of those dimensions remains NOT_ASSESSED. No current-validity, amendment or harmonization determination.

## Local AI resources

A context above the adapter's6,000 encoded UTF-8 byte limit previously consumed a local reservation before being rejected. Budget now holds before mutation; recovery reports LOCAL_AI_CONTEXT_TOO_LARGE and requires a reduced newly approved preview. Evidence is never silently truncated. Receipts expose remaining local request/unit policy. Fake HTTP tests cover exact byte boundary, multibyte overflow and subsequent valid request retaining quota. Local scheduling units are not provider tokens, billing or verified Google quota. Broader result reuse/model capability/live savings remain open.

## Approved owner delta and remaining UI acceptance

After explicit owner approval on28 November, R106Diagnostic1 applied only public/native-annex-r106.js from pinned4c7adadc over its49d07c95 baseline. Executor read-only preflight, pre/post Tinker, served SHA, login/CSRF, anonymous R106/PDF401, protected files PASS; original PHP PID5704 preserved. Repeat apply NO_CHANGE_ALREADY_MATCHED. Backup APR_R106Diagnostic1_20261128-110501-481-75325568 retained under the original launcher backups. No other development changes deployed. Owner authenticated UI exercise of the diagnostic delta remains NOT_RUN; prior browser was intentionally logged out, and no cookie/password was reused.

P1 actual product HTML/JS browser interception16/16 PASS at1440/390 (catalog503/invalid-contract explicit reload, session expiry,403, foreign-tenant200, normal graph audit interaction), zero page errors/external requests. Tenant mismatch is now held; existing API adds authenticated tenant_id. Mobile rotated arrow hitbox no longer intercepts the audit disclosure. Evidence is recorded separately in pi-residual-ui-qualification-20261128.json. Browser fixtures are not owner authenticated acceptance. Baseline before evidence is retained, including failed cross-tenant contract display and incorrect test selector. Backend remains authoritative for tenant authorization.

## Reproduction and evidence

Use the existing locked dependencies in the development checkout. Run scripts/pi-rf04-corpus-inventory.php with local RAI repository and --adaptive-local-pins; evaluate scripts/pi-rf05-page-review-benchmark.php with the independent reference. Optional --summary returns binding/denominator summary. Run scripts/pi-hria-delegation-benchmark.php with the existing R81 snapshot root. No download or paid request is needed. All original raw receipts and PNGs remain in qualification/intelligence-20261127; exact sizes/SHA256 are recorded by pi-intelligence-local-evidence-manifest-20261128.json.

Receipts: pi-rf05-real-corpus-20261128.json, pi-rf05-independent-reference-20261128.json, pi-hria-real-benchmark-20261128.json, pi-hria-independent-candidate-reference-20261127.json, pi-p2-quota-preflight-20261128.json. Test and CI final outcomes are recorded by the batch receipt/state capsule after completion. Local final backend244/244 tests,2,216 assertions PASS. Full frontend plus regulatory JS87/87 PASS. Published CI results belong to their exact implementation commit and are available on PR78; they do not turn partial intelligence metrics into acceptance. Source admission remains candidate-only.

LEGAL_HARMONIZATION=NOT_ASSESSED; LEGAL_EFFECT=NOT_ASSESSED; NUMERIC_COMPARISON=BLOCKED; FISCAL_ADMISSION=ZERO; REVIEW_DECISION=NO_ACTIVE_DECISION; RELATION_PROMOTION=NOT_PERFORMED; PRODUCTION_ADMISSION=DENIED. All G1–G7 OPEN. Provisional14/40 (35%) remains unchanged; no synthetic milestone credit.

Reproduce the browser fixture with scripts/pi-intelligence-residual-ui.cjs and existing playwright-core/Edge; output defaults to a fresh timestamped qualification folder and refuses existing receipt overwrite. It starts only an ephemeral static fixture server, blocks external requests and keeps the browser sandbox active. This runner does not log into or contact8820.
