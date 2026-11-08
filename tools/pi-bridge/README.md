# Planning Intelligence — ChatGPT ↔ GitHub ↔ Laptop pilot

Pilot initiated: 8 November 2026. Repository: `fajarnurmanto-source/planning-intelligence` (private).

## Why a bridge branch is required

The GitHub default branch `v0.1-provenance-ingestion` was last updated on
28 October 2026 and does not yet contain the local Laravel R70G tree. The
Windows application at port 8891 is more recent. Do **not** replace the
Windows application with the old GitHub tree.

This pilot branch deliberately begins as a separate copy of the old GitHub
branch; the first local step publishes only the current Laravel source code.
It does not merge or deploy back into the current application.

## One-time local publication (Ubuntu WSL)

IMPORTANT: The paths `/mnt/d/...` belong to Ubuntu WSL, **not** Windows
PowerShell or Git Bash. When the prompt begins with `PS`, prefix every Linux
command with `wsl.exe -d Ubuntu-24.04 --` as shown below. Running plain
`gh repo clone ... /mnt/d/...` inside PowerShell can clone to an unexpected
Windows location.

Preferred commands **from Windows PowerShell** (the task is disabled until
baseline and first pull succeed):

```powershell
schtasks.exe /Change /TN "PI_LARAVEL_GITHUB_BRIDGE" /Disable
wsl.exe -d Ubuntu-24.04 -- gh auth status
wsl.exe -d Ubuntu-24.04 -- gh repo clone fajarnurmanto-source/planning-intelligence /mnt/d/Research/PlanningIntelligence/PI_BRIDGE_BOOTSTRAP_WSL_20261108 -- --branch pilot/pi-laravel-github-bridge-20261108 --single-branch
wsl.exe -d Ubuntu-24.04 -- bash /mnt/d/Research/PlanningIntelligence/PI_BRIDGE_BOOTSTRAP_WSL_20261108/tools/pi-bridge/publish-laravel-baseline.sh
```

Alternatively, when already **inside the Ubuntu WSL prompt**, run:

```bash
gh auth status
gh repo clone fajarnurmanto-source/planning-intelligence \
  /mnt/d/Research/PlanningIntelligence/PI_BRIDGE_BOOTSTRAP_TEMP -- \
  --branch pilot/pi-laravel-github-bridge-20261108 --single-branch
bash /mnt/d/Research/PlanningIntelligence/PI_BRIDGE_BOOTSTRAP_TEMP/tools/pi-bridge/publish-laravel-baseline.sh
```

The temporary bootstrap checkout remains untouched. The publication script
creates its own isolated checkout at
`D:\Research\PlanningIntelligence\PI_GITHUB_BRIDGE_20261108`.

After successful publication, report `PI_BRIDGE_BASELINE=PASS` and the
commit SHA in the conversation. That source baseline allows ChatGPT to
implement the next Laravel modules on the same branch and create a PR.

## One-time local auto-pull activation (after baseline PASS)

The auto-apply helper is designed for **code-only** updates. It checks the
last applied GitHub commit, verifies each local file still matches the old
version, writes a backup, then replaces only changed files. A mismatch
stops the whole batch without replacing existing local work.

First run once interactively in WSL:

```bash
bash /mnt/d/Research/PlanningIntelligence/PI_GITHUB_BRIDGE_20261108/tools/pi-bridge/apply-laravel-updates.sh
```

When using Windows PowerShell instead, call:

```powershell
wsl.exe -d Ubuntu-24.04 -- bash /mnt/d/Research/PlanningIntelligence/PI_GITHUB_BRIDGE_20261108/tools/pi-bridge/apply-laravel-updates.sh
```

If that reports `UP_TO_DATE` or `PASS`, register a Windows Task Scheduler
job from regular PowerShell (every 15 minutes while Windows is running):

```powershell
schtasks.exe /Create /F /SC MINUTE /MO 15 /TN "PI_LARAVEL_GITHUB_BRIDGE" /TR 'wsl.exe -d Ubuntu-24.04 -- bash /mnt/d/Research/PlanningIntelligence/PI_GITHUB_BRIDGE_20261108/tools/pi-bridge/apply-laravel-updates.sh'
```

If the task already exists from the earlier setup, enable it after a successful
first pull rather than creating it again:

```powershell
schtasks.exe /Change /TN "PI_LARAVEL_GITHUB_BRIDGE" /Enable
```

To suspend automatic pulls without deleting anything:

```powershell
schtasks.exe /Change /TN "PI_LARAVEL_GITHUB_BRIDGE" /Disable
```

No remote edits take effect on Windows until this one-time task is installed.
This does not configure ChatGPT itself to run perpetually; new code
developments still require an authorized ChatGPT session or a successfully
configured event-triggered automation.

## Safety and activation

- `.env`, SQLite databases, credentials, `vendor`, node modules, caches,
  personal files, production and test documents are excluded.
- Current Windows Laravel code, data and users are not edited by publication.
- GitHub is currently a *source exchange*, not an automatic local deployer.
- A recurring local pull/apply service requires **one-time installation on
  the Windows computer**; it is activated only after the real R70G baseline
  has reached this branch.
- Changes requiring Composer/npm installs or database migrations are not
  applied silently by the background task.
- No production admission, automatic legal judgment, or numeric consolidation
  is implied by R70G UI success.

## One-time UI + canonical roadmap import (8 Nov 2026)

The source-only Laravel baseline deliberately excluded the existing static
`public/` interface. The first bootstrap captured `public/index.html`, which
is only the institutional home; Regulatory Intelligence is a separate static
HTML page with JavaScript and CSS. The updated guarded bootstrap registers all
existing **top-level** `public/*.html`, `public/*.js` and `public/*.css` source
files without touching the installed copies, so subsequent GitHub UI edits can
be made safely. It also looks for exactly one local master-roadmap
candidate outside the artifact/archive tree; if multiple files match, it prints
their paths and does **not** guess which one governs.

**Run the following three commands once from Windows PowerShell** (not inside
Git Bash). Do not paste any tokens, `.env` contents or database records:

```powershell
wsl.exe -d Ubuntu-24.04 -- bash /mnt/d/Research/PlanningIntelligence/PI_GITHUB_BRIDGE_20261108/tools/pi-bridge/apply-laravel-updates.sh
wsl.exe -d Ubuntu-24.04 -- git -C /mnt/d/Research/PlanningIntelligence/PI_GITHUB_BRIDGE_20261108 pull --ff-only origin pilot/pi-laravel-github-bridge-20261108
wsl.exe -d Ubuntu-24.04 -- bash /mnt/d/Research/PlanningIntelligence/PI_GITHUB_BRIDGE_20261108/tools/pi-bridge/publish-local-ui-and-roadmap.sh
```

Expected: `PI_UI_ROADMAP_BOOTSTRAP=PASS`,
`LOCAL_UI_UNCHANGED=YES`, `STATIC_UI_FILES_INSPECTED=...` and a new `COMMIT=`. If the script reports
`ROADMAP=NEEDS_EXPLICIT_PATH_..._CANDIDATES`, share only the candidate
**paths** so the authoritative file can be selected; the UI may still have
been published safely.

This import updates the bridge's local applied-SHA marker *only* after a
successful GitHub push, because the HTML already exists byte-for-byte on the
laptop. The updated scheduled applier subsequently accepts drift-checked
modifications to `public/index.html` and creates backups. It does not
delete or overwrite `.env`, SQLite, login records or other runtime data.

Daily ChatGPT milestone development is a **separate automation**. It authors
source-only GitHub changes and reports real capability outcomes; it does not
imply that GitHub commits have run locally until a local apply result exists.
See `docs/PI_MILESTONE_EXECUTION_CONTROL_20261108.md`.
