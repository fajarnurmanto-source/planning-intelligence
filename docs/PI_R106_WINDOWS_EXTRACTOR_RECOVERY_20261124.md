# PI R106 Windows PDF extractor hotfix

Owner actual Windows QA failed at source 2a6f5ca39003cd53c80872860086de04330abb2b with PI_R106_PDFTOTEXT_UNAVAILABLE_OR_TEXT_LAYER_ABSTAIN. R105 local receipt and PDF SHA integrity had passed. This does NOT prove malformed PDF; R106 had no usable local Poppler engine. Old R106 hardcoded WSL Ubuntu-24.04.

Updated NativePdfAnnexIntelligence now tries native PATH, installed WinGet/Scoop/ProgramFiles Poppler, WSL default first, and named Ubuntu/Ubuntu-24.04 fallback with exact archive mapping. Missing engine reports bounded per-backend status rather than a generic PDF failure. The --pdftotext= absolute executable argument remains supported. No online installation, writes or cloud calls are performed by PHP.

Explicit owner-side opt-in Windows package recovery script (separately committed) will use existing engine first; only -Repair can invoke Microsoft WinGet exact ID oschwartz10612.Poppler after confirming extractor failure. This modifies an OS package, NOT PI source runtime 8820, DB, launcher, archived PDF or reviewer decisions.

Initial verification is source/CI only; local owner acceptance must be rerun against QA SHA. Existing R105 PASS remains preserved. Numeric admission ZERO, production DENIED, PR draft.

Owner QA recovery usage after a clean SHA-pinned branch checkout:
- Read-only probe and run: powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/pi-r106-windows-extractor-recovery.ps1 -ExpectedCommit <SHA>
- Explicit owner installation approval if no engine: add -Repair
- If Poppler is installed outside known discovery paths, provide -PdfToTextPath with its full pdftotext.exe path (no installer).

-Repair uses the exact WinGet ID oschwartz10612.Poppler. It is an explicit OS-package installation, not a silent PI application modification. It is skipped if the R106 engine is already functional. The helper refuses package installation for invalid source SHA, parsing discrepancies, or non-engine errors. Windows CI performs syntax-only checks and never invokes WinGet.


## Verified development CI / owner retry state
Last SOURCE-ONLY implementation SHA: ce769eb943b11670440c9a737782b11d2992b940. CI GREEN: R88 run 45346171153 (60 tests, 654 assertions), R73 run 45346171106, R74 run 45346171087, Windows run 45346171154 (PS5.1 safe recovery parse). Real owner Windows R106 acceptance AFTER this hotfix is NOT yet demonstrated. The script first uses an existing engine, and installs oschwartz10612.Poppler only with explicitly user-selected -Repair and only on an engine-absence failure. Original PI 8820, DB, APP_KEY, launcher, PDFs and receipts remain protected.


## Owner Windows R106 PASS — local QA acceptance (24 November 2026)

**Source pin used:** ce769eb943b11670440c9a737782b11d2992b940 (clean detached QA worktree). Owner provided complete PowerShell output of explicit -Repair command. The initial extractor probe produced PI_R106_PDF_ENGINE_HOLD:NATIVE_PATH_POPPLER_NOT_FOUND,WSL_DEFAULT_EXIT_127,WSL_Ubuntu_EXIT_127,WSL_Ubuntu-24.04_EXIT_127. Because this was an engine availability failure, the owner-approved WinGet installation of oschwartz10612.Poppler v26.09.0-0 (41.6 MB ZIP, WinGet installer SHA verified) ran successfully. PowerShell reported PATH changed for future shells. This is an OS package installation, not a no-change claim about the computer.

**Confirmed post-repair stdout:**
- PI_R106_R105_IMMUTABLE_PAIR_GUARD=PASS
- PI_R106_BASE_PAGE=384
- PI_R106_CHANGE3_PAGE=561
- PI_R106_FULL_ACCOUNT_CODES=6
- PI_R106_ACCOUNT=5.1.02.01.001.00025|18000000|18000000
- PI_R106_ACCOUNT=5.1.02.01.001.00026|48432250|48432250
- PI_R106_ACCOUNT=5.1.02.01.001.00052|27060000|27060000
- PI_R106_ACCOUNT=5.1.02.02.001.00086|48324000|48324000
- PI_R106_ACCOUNT=5.1.02.02.009.00013|75000000|75000000
- PI_R106_ACCOUNT=5.1.02.05.002.00001|803550000|803550000
- PI_R106_DERIVED_SIX_LEAF_IDR=1020366250
- PI_R106_NATIVE_TEXT_LAYER_RECONCILIATION=PASS_CANDIDATE_ONLY
- PI_R106_LATER_AMENDMENT_REVIEW=REQUIRED
- PI_R106_REVIEWER_APPROVAL=NOT_REQUESTED
- PI_R106_NUMERIC_ADMISSION=ZERO
- PI_R106_PRODUCTION_ADMISSION=DENIED
- PI_R106_NO_MUTATIONS=YES
- PI_R106_ENGINE_RECOVERY=PASS
- PI_R106_OWNER_QA=PASS
- PI_R106_ORIGINAL_PI_8820=UNTOUCHED

**R106_OWNER_LOCAL_QA_ACCEPTANCE=PASS_SCOPED_TO_TWO_PINNED_SOURCE_PDFS_AND_SUBACTIVITY.** No re-download, rehash run or further WinGet installation necessary. The read-only QA engine now reads these PDFs independently of ChatGPT; user no longer needs to upload files into conversation for THIS extraction flow.

**Not yet achieved:** in-place source-only guarded rollout to original active Laravel app at port 8820, opt-in PI UI/API acceptance, extraction of arbitrary annexes, scanned PDF OCR, complete legal authenticity, approval of fiscal numbers, Perwal 13/15 subsequent revisions, RKA/DPA and realization. Do not conflate QA with production admission. Preserve tenant/auth/APP_KEY/SQLite/session/launcher and existing R84 reviewer governance. PR #78 remains DRAFT, fiscal/production admission denied.

