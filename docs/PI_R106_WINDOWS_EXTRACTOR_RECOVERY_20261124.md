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
