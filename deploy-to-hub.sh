#!/usr/bin/env bash
# Sync this plugin to tradedispatch.app. Excluded from WP.org zips (*.sh).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
REMOTE="tradedispatch:/home/tradedispatch/public_html/wp-content/plugins/trade-dispatch/"

echo "Deploying Trade Dispatch → ${REMOTE}"

# Includes first so a live require_once cannot race a missing class file.
rsync -az \
	--exclude '.DS_Store' \
	"${ROOT}/includes/" "${REMOTE}includes/"

rsync -az --delete \
	--exclude '.git/' \
	--exclude '.cursor/' \
	--exclude '.cursorrules' \
	--exclude '.gitignore' \
	--exclude '.gitattributes' \
	--exclude '.gitkeep' \
	--exclude '.DS_Store' \
	--exclude 'node_modules/' \
	--exclude 'vendor/' \
	--exclude 'tests/' \
	--exclude 'phpunit.xml' \
	--exclude 'phpcs.xml' \
	--exclude 'phpcs-security.xml' \
	--exclude '*.zip' \
	--exclude 'deploy-to-hub.sh' \
	"${ROOT}/" "${REMOTE}"

echo "Hub deploy complete: /home/tradedispatch/public_html/wp-content/plugins/trade-dispatch/"
