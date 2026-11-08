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

Use WSL Ubuntu-24.04 with an already signed-in `gh` account. Run:

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

If that reports `UP_TO_DATE` or `PASS`, register a Windows Task Scheduler
job from regular PowerShell (every 15 minutes while Windows is running):

```powershell
schtasks.exe /Create /F /SC MINUTE /MO 15 /TN "PI_LARAVEL_GITHUB_BRIDGE" /TR 'wsl.exe -d Ubuntu-24.04 -- bash /mnt/d/Research/PlanningIntelligence/PI_GITHUB_BRIDGE_20261108/tools/pi-bridge/apply-laravel-updates.sh'
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
