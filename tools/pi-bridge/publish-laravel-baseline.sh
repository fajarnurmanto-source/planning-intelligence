#!/usr/bin/env bash
# PI bridge — publish the current local Laravel source to a dedicated GitHub pilot branch.
# This script NEVER changes the current Planning Intelligence application directory.
set -euo pipefail

repo='fajarnurmanto-source/planning-intelligence'
branch='pilot/pi-laravel-github-bridge-20261108'
pi_root='/mnt/d/Research/PlanningIntelligence'
source_root="$pi_root/planning-intelligence-v0.0-starter/planning-intelligence-v0.0/laravel"
bridge_root="$pi_root/PI_GITHUB_BRIDGE_20261108"

die() { printf 'PI_BRIDGE=STOP: %s\n' "$*" >&2; exit 1; }
command -v git >/dev/null || die 'git not installed in WSL'
command -v gh >/dev/null || die 'GitHub CLI (gh) not installed in WSL'
gh auth status >/dev/null 2>&1 || die 'Run gh auth login in WSL first'
[[ -d "$source_root/app" && -f "$source_root/artisan" && -f "$source_root/composer.json" ]] || die 'Canonical Laravel folder is incomplete; nothing sent'
[[ ! -e "$bridge_root" ]] || die "Bridge directory already exists: $bridge_root (no overwrite)"

printf 'SOURCE=%s\nREPO=%s\nBRANCH=%s\n' "$source_root" "$repo" "$branch"
gh auth setup-git >/dev/null
gh repo clone "$repo" "$bridge_root" -- --branch "$branch" --single-branch >/dev/null

cd "$bridge_root"
[[ "$(git branch --show-current)" == "$branch" ]] || die 'Unexpected branch'
[[ "$(git remote get-url origin)" == *'/planning-intelligence.git' ]] || die 'Unexpected remote'
mkdir -p laravel

# Explicit source-code allowlist. Never copy .env, SQLite, runtime storage,
# Composer vendor, node_modules, compiled assets, backups, logs or test corpora.
directories=(
  app config resources routes tests
  database/migrations database/factories database/seeders
)
files=(
  artisan composer.json composer.lock package.json package-lock.json
  vite.config.js vite.config.ts phpunit.xml
  bootstrap/app.php bootstrap/providers.php
  public/index.php public/.htaccess
)

for rel in "${directories[@]}"; do
  [[ -d "$source_root/$rel" ]] || continue
  [[ -z "$(find "$source_root/$rel" -type l -print -quit)" ]] || die "Symbolic link found in source folder: $rel"
  mkdir -p "laravel/$rel"
  cp -a "$source_root/$rel/." "laravel/$rel/"
done

for rel in "${files[@]}"; do
  [[ -f "$source_root/$rel" ]] || continue
  [[ ! -L "$source_root/$rel" ]] || die "Symbolic link found: $rel"
  mkdir -p "laravel/$(dirname "$rel")"
  cp -a "$source_root/$rel" "laravel/$rel"
done

# Stop if any secret or runtime file slipped into the selected code paths.
unsafe_path="$(find laravel -type f \( -name '.env' -o -name '.env.*' -o -iname '*.sqlite*' -o -iname '*.db' -o -iname '*.pem' -o -iname '*.p12' -o -iname '*.key' -o -iname '*.pfx' -o -iname 'id_rsa*' \) -print -quit)"
[[ -z "$unsafe_path" ]] || die "Refusing to publish sensitive-looking file: $unsafe_path"

if command -v rg >/dev/null; then
  if rg -l --hidden --glob '!*.lock' -e 'APP_KEY=base64:[A-Za-z0-9+/=]{20,}' -e '-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----' laravel >/dev/null; then
    die 'Potential embedded secret detected in Laravel source; nothing committed'
  fi
fi

git add -- laravel
if git diff --cached --quiet; then
  printf 'PI_BRIDGE=NO_CHANGES\nBRANCH=%s\n' "$branch"
  exit 0
fi

# Final path gate before commit. Stages only files under laravel/.
if git diff --cached --name-only | grep -Ev '^laravel/' >/dev/null; then
  die 'Unexpected path outside laravel; nothing committed'
fi

git -c user.name='PI Local Bridge' -c user.email='fajar.nurmanto@gmail.com' \
  commit -m 'feat(pi): import verified local Laravel R70G source baseline' >/dev/null
git push -u origin "$branch" >/dev/null

printf 'PI_BRIDGE_BASELINE=PASS\nREPO=%s\nBRANCH=%s\nCOMMIT=%s\n' \
  "$repo" "$branch" "$(git rev-parse HEAD)"
printf 'CANONICAL_LARAVEL_UNCHANGED=YES\n'
