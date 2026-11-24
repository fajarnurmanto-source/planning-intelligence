# PI R106 Windows PDF extractor hotfix

Owner actual Windows QA failed at source 2a6f5ca39003cd53c80872860086de04330abb2b with PI_R106_PDFTOTEXT_UNAVAILABLE_OR_TEXT_LAYER_ABSTAIN. R105 local receipt and PDF SHA integrity had passed. This does NOT prove malformed PDF; R106 had no usable local Poppler engine. Old R106 hardcoded WSL Ubuntu-24.04.

Updated NativePdfAnnexIntelligence now tries native PATH, installed WinGet/Scoop/ProgramFiles Poppler, WSL default first, and named Ubuntu/Ubuntu-24.04 fallback with exact archive mapping. Missing engine reports bounded per-backend status rather than a generic PDF failure. The --pdftotext= absolute executable argument remains supported. No online installation, writes or cloud calls are performed by PHP.

Explicit owner-side opt-in Windows package recovery script (separately committed) will use existing engine first; only -Repair can invoke Microsoft WinGet exact ID oschwartz10612.Poppler after confirming extractor failure. This modifies an OS package, NOT PI source runtime 8820, DB, launcher, archived PDF or reviewer decisions.

Initial verification is source/CI only; local owner acceptance must be rerun against QA SHA. Existing R105 PASS remains preserved. Numeric admission ZERO, production DENIED, PR draft.
