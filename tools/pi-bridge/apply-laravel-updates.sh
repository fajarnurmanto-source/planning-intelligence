#!/usr/bin/env bash
# PI bridge: apply source-only GitHub updates to the existing Laravel app.
# No file deletions, migrations, dependency installs, restarts, or database writes.
set -euo pipefail

branch='pilot/pi-laravel-github-bridge-20261108'
pi_root='/mnt/d/Research/PlanningIntelligence'
bridge_root="$pi_root/PI_GITHUB_BRIDGE_20261108"
app_root="$pi_root/planning-intelligence-v0.0-starter/planning-intelligence-v0.0/laravel"
state_file="$bridge_root/.pi_bridge_last_applied.sha"
lock_dir="$bridge_root/.pi_bridge_apply.lock"

stop() { printf 'PI_BRIDGE_APPLY=STOP: %s\n' "$*" >&2; exit 1; }

# The only separately audited schema FILE permitted for source staging.
# SHA is the Git blob SHA of the merged PR #59 file, not a database state.
r84_migration_path='laravel/database/migrations/2026_11_15_150000_create_regulatory_evidence_reviews_table.php'
r84_migration_blob='2588a8f6356fd5eafad5a867f2948f77689dc2e8'
stage_r84_source=0
r84_source_staged=0

r84_source_allowed() {
  local path="$1" status="$2" blob="$3" opted_in="$4"
  [[ "$path" == "$r84_migration_path" &&
     "$status" == 'A' &&
     "$blob" == "$r84_migration_blob" &&
     "$opted_in" == '1' ]]
}

if [[ "${1-}" == '--self-test' && $# -eq 1 ]]; then
  r84_source_allowed "$r84_migration_path" 'A' "$r84_migration_blob" '1' ||
    stop 'Self-test of allowlisted source failed'
  if r84_source_allowed "$r84_migration_path" 'A' "$r84_migration_blob" '0' ||
    r84_source_allowed "$r84_migration_path" 'M' "$r84_migration_blob" '1' ||
    r84_source_allowed 'laravel/database/migrations/unsafe.php' 'A' "$r84_migration_blob" '1' ||
    r84_source_allowed "$r84_migration_path" 'A' 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' '1'; then
    stop 'Self-test failed to reject non-approved migration source'
  fi
  printf 'PI_R84_SOURCE_STAGING_OFFLINE_SELFTEST=PASS\n'
  printf 'PI_R84_ACTIVE_DATABASE_MUTATION=NONE\n'
  exit 0
elif [[ "${1-}" == '--stage-r84-migration-source' && $# -eq 1 ]]; then
  stage_r84_source=1
elif (( $# != 0 )); then
  stop 'Usage: apply-laravel-updates.sh [--stage-r84-migration-source|--self-test]'
fi

[[ -d "$bridge_root/.git" && -f "$app_root/artisan" ]] || stop 'Bridge checkout or canonical Laravel missing'
mkdir "$lock_dir" 2>/dev/null || { printf 'PI_BRIDGE_APPLY=ALREADY_RUNNING\n'; exit 0; }
trap 'rmdir "$lock_dir" 2>/dev/null || true' EXIT

cd "$bridge_root"
git fetch --quiet origin "refs/heads/$branch:refs/remotes/origin/$branch" || stop 'GitHub fetch failed'
remote_sha="$(git rev-parse "refs/remotes/origin/$branch")"

if [[ -f "$state_file" ]]; then
  applied_sha="$(<"$state_file")"
else
  applied_sha="$(git rev-parse HEAD)"
  [[ "$(git log -1 --format=%s "$applied_sha")" == 'feat(pi): import verified local Laravel R70G source baseline' ]] || \
    stop 'Local Laravel source baseline not published yet'
fi

git cat-file -e "$applied_sha^{commit}" || stop 'Invalid applied commit marker'
git merge-base --is-ancestor "$applied_sha" "$remote_sha" || stop 'Remote branch has diverged; local code untouched'

if [[ "$applied_sha" == "$remote_sha" ]]; then
  printf 'PI_BRIDGE_APPLY=UP_TO_DATE\nCOMMIT=%s\n' "$remote_sha"
  exit 0
fi

declare -a paths=()
declare -a statuses=()
while IFS= read -r -d '' status && IFS= read -r -d '' path; do
  [[ "$status" == 'A' || "$status" == 'M' ]] || stop "Deletion/rename not automatically applied: $path ($status)"
  [[ "$path" == laravel/* && "$path" != *'/../'* && "$path" != *'//'* ]] || stop "Unexpected source path: $path"
  case "$path" in
    laravel/app/*|laravel/config/*|laravel/resources/*|laravel/routes/*|laravel/tests/*|\
    laravel/database/migrations/*|laravel/database/factories/*|laravel/database/seeders/*|\
    laravel/artisan|laravel/composer.json|laravel/composer.lock|\
    laravel/package.json|laravel/package-lock.json|laravel/phpunit.xml|\
    laravel/vite.config.js|laravel/vite.config.ts|\
    laravel/bootstrap/app.php|laravel/bootstrap/providers.php|\
    laravel/public/index.php|laravel/public/*.html|laravel/public/*.css|laravel/public/*.js|laravel/public/.htaccess) ;;
    *) stop "Outside approved Laravel source paths: $path" ;;
  esac
  case "$path" in
    laravel/public/*)
      public_basename="${path#laravel/public/}"
      [[ "$public_basename" != */* ]] || stop "Nested public path is not auto-deployable: $path"
      ;;
  esac
  case "$path" in */.env|*/.env.*|*.sqlite*|*.db|*.pem|*.key|*.p12|*.pfx) stop "Sensitive path: $path";; esac
  case "$path" in
    laravel/composer.json|laravel/composer.lock|laravel/package.json|laravel/package-lock.json)
      stop "Dependency change requires a separate safe deployment: $path" ;;
    laravel/database/migrations/*)
      # Source staging is NOT running a schema migration. Default remains
      # fail-closed; explicit mode permits exactly one immutable new file.
      blob="$(git rev-parse "$remote_sha:$path")" || stop "Cannot resolve migration source blob: $path"
      if ! r84_source_allowed "$path" "$status" "$blob" "$stage_r84_source"; then
        stop "Dependency/schema change requires a separate safe deployment: $path"
      fi
      r84_source_staged=$((r84_source_staged + 1))
      ;;
  esac
  mode="$(git ls-tree "$remote_sha" -- "$path" | cut -d ' ' -f 1)"
  [[ "$mode" == 100644 || "$mode" == 100755 ]] || stop "Non-regular file or symlink refused: $path"
  paths+=("$path")
  statuses+=("$status")
done < <(git diff --name-status -z --no-renames "$applied_sha" "$remote_sha" -- laravel)

if (( ${#paths[@]} == 0 )); then
  printf '%s\n' "$remote_sha" > "$state_file"
  printf 'PI_BRIDGE_APPLY=NO_LARAVEL_CHANGES\nCOMMIT=%s\n' "$remote_sha"
  exit 0
fi

stage_dir="$(mktemp -d "$bridge_root/.pi_stage_XXXXXX")"
timestamp="$(date +%Y%m%d_%H%M%S)"
backup_dir="$pi_root/PI_GITHUB_BRIDGE_BACKUPS/$timestamp"
declare -a deployed_indices=()
current_temp=''

on_exit() {
  exit_code=$?
  if (( exit_code != 0 && ${#deployed_indices[@]} > 0 )); then
    for i in "${deployed_indices[@]}"; do
      rel="${paths[$i]#laravel/}"
      target="$app_root/$rel"
      if [[ "${statuses[$i]}" == M && -f "$backup_dir/$rel" ]]; then
        cp -p -- "$backup_dir/$rel" "$target"
      elif [[ "${statuses[$i]}" == A ]]; then
        rm -f -- "$target"
      fi
    done
    printf 'PI_BRIDGE_APPLY=ROLLED_BACK_AFTER_ERROR\nBACKUP=%s\n' "$backup_dir" >&2
  fi
  [[ -z "$current_temp" ]] || rm -f -- "$current_temp"
  rm -r -- "$stage_dir"
  rmdir "$lock_dir" 2>/dev/null || true
}
trap on_exit EXIT

# Preflight EVERY changed file before touching the canonical application.
for i in "${!paths[@]}"; do
  path="${paths[$i]}"
  rel="${path#laravel/}"
  target="$app_root/$rel"
  [[ ! -L "$target" ]] || stop "Local symlink found: $rel"
  if [[ "${statuses[$i]}" == M ]]; then
    [[ -f "$target" ]] || stop "Existing local source missing: $rel"
    if ! git show "$applied_sha:$path" | cmp -s - "$target"; then
      stop "SOURCE_DRIFT: $rel (local edit preserved; nothing applied)"
    fi
  else
    [[ ! -e "$target" ]] || stop "New upstream file collides with local file: $rel"
  fi
  mkdir -p "$stage_dir/$(dirname "$path")"
  git show "$remote_sha:$path" > "$stage_dir/$path"
done

mkdir -p "$backup_dir"
for i in "${!paths[@]}"; do
  path="${paths[$i]}"
  rel="${path#laravel/}"
  target="$app_root/$rel"
  if [[ "${statuses[$i]}" == M ]]; then
    mkdir -p "$backup_dir/$(dirname "$rel")"
    cp -p -- "$target" "$backup_dir/$rel"
  fi
done

# Store a staged copy beside each target first, then atomically replace it.
# No runtime config, database or protected source is removed.
for i in "${!paths[@]}"; do
  path="${paths[$i]}"
  rel="${path#laravel/}"
  target="$app_root/$rel"
  mkdir -p "$(dirname "$target")"
  current_temp="$target.pi-bridge-next.$$"
  [[ ! -e "$current_temp" ]] || stop "Temporary file collision: $rel"
  install -m 644 "$stage_dir/$path" "$current_temp"
  mv -f -- "$current_temp" "$target"
  current_temp=''
  deployed_indices+=("$i")
done

printf '%s\n' "$remote_sha" > "$state_file"
printf 'PI_BRIDGE_APPLY=PASS\nFILES=%s\nCOMMIT=%s\nBACKUP=%s\n' \
  "${#paths[@]}" "$remote_sha" "$backup_dir"
printf 'NOTE=Source-only; no database migrations, composer/npm install, build, or restart executed\n'
if (( r84_source_staged > 0 )); then
  printf 'PI_R84_MIGRATION_SOURCE=STAGED_ONLY\n'
  printf 'PI_R84_DATABASE_MIGRATION=NOT_EXECUTED\n'
fi
