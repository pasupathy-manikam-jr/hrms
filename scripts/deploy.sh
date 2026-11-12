#!/usr/bin/env bash
#
# Staging deploy (run ON the server): https://ui.staging.oriclabdev.com/hrms
#
#   Usage:  cd ~/hrms && bash scripts/deploy.sh
#           DEPLOY_BRANCH=deploy-root bash scripts/deploy.sh   (app served at a domain root)
#
# Pulls a CI-built branch (main + compiled public/build, see
# .github/workflows/deploy-assets.yml): `deploy` is built for the /hrms
# subfolder, `deploy-root` for a domain root. Migrates and refreshes caches.
# No npm/node on the server.
#
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

BRANCH="${DEPLOY_BRANCH:-deploy}"

# Stash hand-edits instead of silently destroying them with the reset below.
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    STAMP="pre-deploy-$(date +%Y%m%d-%H%M%S)"
    echo "==> Local changes found; stashing as '$STAMP'"
    git stash push -m "$STAMP"
fi

echo "==> Fetching $BRANCH branch"
git fetch origin "$BRANCH"

# public/build is untracked until the first switch to the deploy branch, which tracks it.
if [ "$(git branch --show-current)" != "$BRANCH" ]; then
    rm -rf public/build
fi

# The deploy branches are force-pushed by CI each build, so reset rather than pull/merge.
git checkout -B "$BRANCH" "origin/$BRANCH"
git reset --hard "origin/$BRANCH"

echo "==> Installing PHP dependencies"
composer install --no-interaction --no-progress --optimize-autoloader

echo "==> Migrating"
php artisan migrate --force

echo "==> Refreshing caches"
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan event:cache

echo "==> Deployed $(git log --oneline -1)"
