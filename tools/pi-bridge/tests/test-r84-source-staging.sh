#!/usr/bin/env bash
# Synthetic full-bridge acceptance: default refuses R84 migration,
# explicit staging copies only its immutable source without running migrations.
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
bridge_script="$project_root/tools/pi-bridge/apply-laravel-updates.sh"
r84_file='2026_11_15_150000_create_regulatory_evidence_reviews_table.php'
source_file="$project_root/laravel/database/migrations/$r84_file"
blob_pin='2588a8f6356fd5eafad5a867f2948f77689dc2e8'

bash -n "$bridge_script"
bash "$bridge_script" --self-test | grep -F 'PI_R84_SOURCE_STAGING_OFFLINE_SELFTEST=PASS'
[[ "$(git hash-object "$source_file")" == "$blob_pin" ]] || {
  echo 'R84_TEST_MIGRATION_GIT_BLOB_DRIFT'; exit 1;
}

sandbox="$(mktemp -d)"
trap 'rm -rf -- "$sandbox"' EXIT
pi_root="$sandbox/pi"
bridge_root="$pi_root/PI_GITHUB_BRIDGE_20261108"
app_root="$pi_root/planning-intelligence-v0.0-starter/planning-intelligence-v0.0/laravel"
branch='pilot/pi-laravel-github-bridge-20261108'

mkdir -p "$pi_root" "$app_root/app"
git init --bare -q "$sandbox/origin.git"
git clone -q "$sandbox/origin.git" "$bridge_root"
git -C "$bridge_root" config user.name 'PI QA Bot'
git -C "$bridge_root" config user.email 'pi-qa@example.invalid'
git -C "$bridge_root" switch -q -c "$branch"

mkdir -p "$bridge_root/laravel/app"
printf 'baseline\n' > "$bridge_root/laravel/app/Example.php"
printf 'baseline already\n' > "$bridge_root/laravel/app/Already.php"
git -C "$bridge_root" add laravel
git -C "$bridge_root" commit -qm 'synthetic applied baseline'
baseline="$(git -C "$bridge_root" rev-parse HEAD)"
git -C "$bridge_root" push -q -u origin "$branch"

printf 'changed\n' > "$bridge_root/laravel/app/Example.php"
printf 'new upstream already\n' > "$bridge_root/laravel/app/Already.php"
mkdir -p "$bridge_root/laravel/database/migrations"
cp "$source_file" "$bridge_root/laravel/database/migrations/$r84_file"
git -C "$bridge_root" add laravel
git -C "$bridge_root" commit -qm 'synthetic R84 PHP source and pinned migration file'
target="$(git -C "$bridge_root" rev-parse HEAD)"
git -C "$bridge_root" push -q origin "$branch"

# Rewind only the synthetic test checkout, never a real operator repository.
git -C "$bridge_root" reset -q --hard "$baseline"
printf '%s\n' "$baseline" > "$bridge_root/.pi_bridge_last_applied.sha"
printf 'baseline\n' > "$app_root/app/Example.php"
# Simulate a manually deployed PR file already byte-identical to remote HEAD.
# Its local drift from the old state marker is benign and must be preserved.
printf 'new upstream already\n' > "$app_root/app/Already.php"
: > "$app_root/artisan"

# Full branch/source logic executes unchanged with only synthetic PI root.
sed "s#^pi_root='/mnt/d/Research/PlanningIntelligence'#pi_root='$pi_root'#" \
    "$bridge_script" > "$sandbox/apply-test.sh"
grep -F -q "pi_root='$pi_root'" "$sandbox/apply-test.sh"

if bash "$sandbox/apply-test.sh" >"$sandbox/default.log" 2>&1; then
  echo 'R84_TEST_DEFAULT_GUARD_IMPROPERLY_ACCEPTED_MIGRATION'
  exit 1
fi
grep -F -q 'PI_BRIDGE_APPLY=STOP: Dependency/schema change requires a separate safe deployment' "$sandbox/default.log"
[[ ! -e "$app_root/database/migrations/$r84_file" ]]
[[ "$(<"$app_root/app/Example.php")" == 'baseline' ]]
[[ "$(<"$app_root/app/Already.php")" == 'new upstream already' ]]
[[ "$(<"$bridge_root/.pi_bridge_last_applied.sha")" == "$baseline" ]]
echo 'PI_R84_DEFAULT_DEPLOY_FAIL_CLOSED=PASS'

bash "$sandbox/apply-test.sh" --stage-r84-migration-source >"$sandbox/approved.log"
grep -F -q 'PI_BRIDGE_APPLY=PASS' "$sandbox/approved.log"
grep -F -q 'PI_R84_MIGRATION_SOURCE=STAGED_ONLY' "$sandbox/approved.log"
grep -F -q 'PI_R84_DATABASE_MIGRATION=NOT_EXECUTED' "$sandbox/approved.log"
grep -F -q 'PI_BRIDGE_ALREADY_UPSTREAM=app/Already.php' "$sandbox/approved.log"
grep -F -q 'PI_BRIDGE_ALREADY_UPSTREAM_FILES=1' "$sandbox/approved.log"
grep -F -q 'FILES=2' "$sandbox/approved.log"
cmp -s "$source_file" "$app_root/database/migrations/$r84_file"
[[ "$(<"$app_root/app/Example.php")" == 'changed' ]]
[[ "$(<"$app_root/app/Already.php")" == 'new upstream already' ]]
[[ "$(<"$bridge_root/.pi_bridge_last_applied.sha")" == "$target" ]]
echo 'PI_BRIDGE_ALREADY_UPSTREAM_RECONCILE=PASS'
echo 'PI_R84_SOURCE_ONLY_INSTALL=PASS'

bash "$sandbox/apply-test.sh" --stage-r84-migration-source >"$sandbox/idempotent.log"
grep -F -q 'PI_BRIDGE_APPLY=UP_TO_DATE' "$sandbox/idempotent.log"
echo 'PI_R84_SOURCE_ONLY_IDEMPOTENCE=PASS'

# A genuinely divergent local edit is never silently overwritten, even with
# the explicit R84 source-only flag. No marker or other source may change.
# Refresh ONLY the synthetic Git checkout to its already deployed target
# before creating the next fast-forward commit; never reset operator files.
git -C "$bridge_root" reset -q --hard "$target"
printf 'new remote edit\n' > "$bridge_root/laravel/app/Example.php"
git -C "$bridge_root" add laravel/app/Example.php
git -C "$bridge_root" commit -qm 'synthetic next remote source revision'
git -C "$bridge_root" push -q origin "$branch"
printf 'operator local custom edit\n' > "$app_root/app/Example.php"
if bash "$sandbox/apply-test.sh" --stage-r84-migration-source >"$sandbox/drift.log" 2>&1; then
  echo 'PI_BRIDGE_REAL_SOURCE_DRIFT_IMPROPERLY_ACCEPTED'
  exit 1
fi
grep -F -q 'PI_BRIDGE_APPLY=STOP: SOURCE_DRIFT: app/Example.php' "$sandbox/drift.log"
[[ "$(<"$app_root/app/Example.php")" == 'operator local custom edit' ]]
[[ "$(<"$bridge_root/.pi_bridge_last_applied.sha")" == "$target" ]]
echo 'PI_BRIDGE_REAL_SOURCE_DRIFT_FAIL_CLOSED=PASS'
echo 'PI_R84_ACTIVE_DATABASE_MUTATION=NONE'
