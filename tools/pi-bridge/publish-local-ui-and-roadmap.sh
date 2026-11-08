#!/usr/bin/env bash
# One-time PI bridge source import. Publishes the already-installed local UI
# as baseline, so subsequent UI modifications can be safely auto-applied.
set -euo pipefail

pi_root='/mnt/d/Research/PlanningIntelligence'
branch='pilot/pi-laravel-github-bridge-20261108'
bridge="$pi_root/PI_GITHUB_BRIDGE_20261108"
source="$pi_root/planning-intelligence-v0.0-starter/planning-intelligence-v0.0/laravel/public/index.html"
ui_dir="$(dirname "$source")"
state="$bridge/.pi_bridge_last_applied.sha"
lock_dir="$bridge/.pi_bridge_apply.lock"
target='laravel/public/index.html'
roadmap_status='NOT_PUBLISHED'

stop() { printf 'PI_UI_ROADMAP_BOOTSTRAP=STOP: %s\n' "$*" >&2; exit 1; }
command -v git >/dev/null || stop 'git missing'
command -v gh >/dev/null || stop 'gh missing'
[[ -f "$source" && ! -L "$source" ]] || stop 'Canonical local public/index.html is missing or symlinked'
[[ -d "$bridge/.git" && -f "$state" ]] || stop 'Bridge checkout or apply marker missing'
[[ $(wc -c < "$source") -gt 0 && $(wc -c < "$source") -le 2097152 ]] || stop 'UI size outside reviewed limit'
mkdir "$lock_dir" 2>/dev/null || stop 'Local auto-updater is already running; retry after it finishes'
trap 'rmdir "$lock_dir" 2>/dev/null || true' EXIT

cd "$bridge"
[[ $(git branch --show-current) == "$branch" ]] || stop 'Unexpected local branch'
[[ -z $(git status --porcelain --untracked-files=no) ]] || stop 'Tracked changes in bridge; no automatic overwrite'
git fetch --quiet origin "$branch" || stop 'GitHub fetch failed'
remote_sha=$(git rev-parse "refs/remotes/origin/$branch")
applied_sha=$(< "$state")
[[ "$remote_sha" == "$applied_sha" ]] || stop "Local apply marker $applied_sha differs from remote $remote_sha; run the guarded updater and retry"
git merge-base --is-ancestor HEAD "$remote_sha" || stop 'Local bridge branch is ahead or divergent; no overwrite'
git merge --ff-only "$remote_sha" >/dev/null || stop 'Fast-forward failed'

# Avoid accidentally publishing credentials embedded in an HTML file.
if grep -Eiq -- 'APP_KEY=base64:[A-Za-z0-9+/=]{20,}|-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----|github_pat_[A-Za-z0-9_]{20,}|gh[pousr]_[A-Za-z0-9]{20,}' "$source"; then
  stop 'Potential embedded credential in local UI; nothing staged'
fi

if git cat-file -e "HEAD:$target" 2>/dev/null; then
  git show "HEAD:$target" | cmp -s - "$source" || stop 'Local UI differs from an existing GitHub UI baseline; no overwrite'
else
  install -D -m 644 -- "$source" "$target"
  git add -- "$target"
fi

# The institutional home is only one page. The Regulatory Intelligence screen
# and its scripts/styles are separate static files. Register those *existing*
# sources as well; otherwise the GitHub worker cannot safely edit the R70G UI.
# Deliberately exclude PDFs, source datasets, JSON, images, compiled bundles,
# upload/storage content, and package dependencies.
mapfile -d '' ui_files < <(find "$ui_dir" -maxdepth 1 -type f \
  \( -iname '*.html' -o -iname '*.js' -o -iname '*.css' \) \
  ! -iname '*.min.js' ! -iname '*.min.css' -print0)
ui_count=0
ui_bytes=0
for ui_file in "${ui_files[@]}"; do
  ui_rel="${ui_file#"$ui_dir"/}"
  case "$ui_rel" in
    .*|*/.*|*/../*|storage/*|uploads/*|vendor/*|build/*|node_modules/*) continue ;;
  esac
  [[ ! -L "$ui_file" ]] || stop "Symlinked UI source: $ui_rel"
  bytes=$(wc -c < "$ui_file")
  (( bytes <= 1048576 )) || stop "Oversize static source: $ui_rel"
  ui_count=$((ui_count + 1))
  ui_bytes=$((ui_bytes + bytes))
  (( ui_count <= 120 && ui_bytes <= 12582912 )) || stop 'Static source batch exceeds reviewed count/size budget'
  if grep -Eiq -- 'APP_KEY=base64:[A-Za-z0-9+/=]{20,}|-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----|github_pat_[A-Za-z0-9_]{20,}|gh[pousr]_[A-Za-z0-9]{20,}' "$ui_file"; then
    stop "Potential credential in static UI: $ui_rel"
  fi
  ui_repo_path="laravel/public/$ui_rel"
  if git cat-file -e "HEAD:$ui_repo_path" 2>/dev/null; then
    git show "HEAD:$ui_repo_path" | cmp -s - "$ui_file" || stop "Existing GitHub UI source differs: $ui_rel"
  fi
done

for ui_file in "${ui_files[@]}"; do
  ui_rel="${ui_file#"$ui_dir"/}"
  case "$ui_rel" in
    .*|*/.*|*/../*|storage/*|uploads/*|vendor/*|build/*|node_modules/*) continue ;;
  esac
  ui_repo_path="laravel/public/$ui_rel"
  if ! git cat-file -e "HEAD:$ui_repo_path" 2>/dev/null; then
    install -D -m 644 -- "$ui_file" "$ui_repo_path"
    git add -- "$ui_repo_path"
  fi
done

# Provide an explicit path as PI_MASTER_ROADMAP_PATH when discovery is ambiguous.
if [[ -n ${PI_MASTER_ROADMAP_PATH:-} ]]; then
  roadmap="$PI_MASTER_ROADMAP_PATH"
else
  mapfile -d '' candidates < <(find "$pi_root" -maxdepth 5 \
    \( -name PI_DEVELOPMENT_ARTIFACTS -o -name .git -o -name node_modules -o -name vendor \) -prune -o \
    -type f \( -iname '*master*roadmap*.md' -o -iname '*master*roadmap*.txt' \) -print0)
  if (( ${#candidates[@]} == 1 )); then
    roadmap="${candidates[0]}"
  else
    roadmap=''
    roadmap_status="NEEDS_EXPLICIT_PATH_${#candidates[@]}_CANDIDATES"
    for candidate in "${candidates[@]}"; do printf 'ROADMAP_CANDIDATE=%s\n' "$candidate"; done
  fi
fi

if [[ -n "$roadmap" ]]; then
  [[ -f "$roadmap" && ! -L "$roadmap" ]] || stop 'Roadmap path is missing or symlinked'
  real_roadmap=$(realpath -- "$roadmap")
  [[ "$real_roadmap" == "$pi_root"/* ]] || stop 'Roadmap must be inside the PI root'
  [[ $(wc -c < "$roadmap") -le 524288 ]] || stop 'Roadmap exceeds safe publication size'
  grep -qi 'roadmap' "$roadmap" || stop 'Selected file does not appear to be a roadmap'
  if grep -Eiq -- 'APP_KEY=base64:[A-Za-z0-9+/=]{20,}|-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----|github_pat_[A-Za-z0-9_]{20,}|gh[pousr]_[A-Za-z0-9]{20,}' "$roadmap"; then
    stop 'Potential credential in roadmap; nothing committed'
  fi
  if git cat-file -e 'HEAD:docs/bridge-inputs/PI_LOCAL_MASTER_ROADMAP.md' 2>/dev/null; then
    git show 'HEAD:docs/bridge-inputs/PI_LOCAL_MASTER_ROADMAP.md' | cmp -s - "$roadmap" || stop 'Existing published roadmap differs; reconcile versions manually'
  fi
  install -D -m 644 -- "$roadmap" 'docs/bridge-inputs/PI_LOCAL_MASTER_ROADMAP.md'
  git add -- 'docs/bridge-inputs/PI_LOCAL_MASTER_ROADMAP.md'
  roadmap_status='PUBLISHED'
fi

if git diff --cached --quiet; then
  printf 'PI_UI_ROADMAP_BOOTSTRAP=UP_TO_DATE\nCOMMIT=%s\nROADMAP=%s\n' "$remote_sha" "$roadmap_status"
  exit 0
fi
while IFS= read -r changed_path; do
  case "$changed_path" in
    laravel/public/*.html|laravel/public/*.css|laravel/public/*.js|docs/bridge-inputs/PI_LOCAL_MASTER_ROADMAP.md) ;;
    *) stop "Staged file outside allowed paths: $changed_path" ;;
  esac
done < <(git diff --cached --name-only)

git -c user.name='PI Local Bridge' -c user.email='pi-local-bridge@users.noreply.github.com' \
  commit -m 'chore(pi): register current local UI and master roadmap for safe GitHub bridge' >/dev/null
git push origin "$branch" >/dev/null || stop 'GitHub push failed; local apply marker not advanced'
new_sha=$(git rev-parse HEAD)

# This commit only *registers* the existing UI plus documentation. No local
# application files were changed. Mark the UI as already installed so the
# 15-minute auto-updater will later accept only a true modification (M).
printf '%s\n' "$new_sha" > "$state"
printf 'PI_UI_ROADMAP_BOOTSTRAP=PASS\nCOMMIT=%s\nLOCAL_UI_UNCHANGED=YES\nSTATIC_UI_FILES_INSPECTED=%s\nROADMAP=%s\n' \
  "$new_sha" "$ui_count" "$roadmap_status"
