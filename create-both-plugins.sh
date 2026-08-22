#!/usr/bin/env bash
# Build WordPress-installable zips for Trade Dispatch (free) and Trade Dispatch Pro.
#
# Pattern matches wedding-party-rsvp/create-both-plugin-zips.sh and
# wedding-party-rsvp/create-plugin-zip.sh: rsync into a temp slug folder (no
# hidden files), zip with a single top-level folder named after the plugin slug,
# write versioned archives into Dist/. WordPress.org Plugin Check rejects
# hidden_files, .sh, vendor-without-composer, VCS metadata, and nested zips.
#
# Usage (from this folder):
#   ./create-both-plugins.sh
#   ./create-both-plugins.sh --free-only
#   ./create-both-plugins.sh --pro-only
#
# Output:
#   Dist/trade-dispatch-{version}.zip
#   Dist/trade-dispatch-pro-{version}.zip
# Folder inside each zip is the slug only (no version suffix).
#
# Free zip is WP.org-safe. Pro zip is customer-install safe (no .github-pat.local,
# no docs/, no Cursor/git/deploy scripts).

set -euo pipefail

FREE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PRO_ROOT="$(cd "${FREE_ROOT}/../TradeDispatchPro" && pwd)"
DIST_DIR="${FREE_ROOT}/Dist"

FREE_SLUG="trade-dispatch"
PRO_SLUG="trade-dispatch-pro"
FREE_MAIN="${FREE_ROOT}/trade-dispatch.php"
PRO_MAIN="${PRO_ROOT}/trade-dispatch-pro.php"

BUILD_FREE=1
BUILD_PRO=1
for arg in "$@"; do
	case "${arg}" in
		--free-only)
			BUILD_PRO=0
			;;
		--pro-only)
			BUILD_FREE=0
			;;
		-h|--help)
			sed -n '2,24p' "${BASH_SOURCE[0]}"
			exit 0
			;;
		*)
			echo "Unknown option: ${arg}" >&2
			echo "Usage: $0 [--free-only|--pro-only]" >&2
			exit 1
			;;
	esac
done

if ! command -v rsync >/dev/null 2>&1; then
	echo "Error: rsync is required." >&2
	exit 1
fi
if ! command -v zip >/dev/null 2>&1; then
	echo "Error: zip is required." >&2
	exit 1
fi

# Shared exclusions: never ship tooling, VCS, secrets, docs, or archives.
# --exclude='.[!.]*' drops every hidden path (Plugin Check: hidden_files).
RSYNC_EXCLUDES=(
	--exclude='.[!.]*'
	--exclude='.??*'
	--exclude='Dist/'
	--exclude='dist/'
	--exclude='.zip-temp-*'
	--exclude='node_modules/'
	--exclude='vendor/'
	--exclude='tests/'
	--exclude='docs/'
	--exclude='svn-checkout/'
	--exclude='wporg-assets/'
	--exclude='assets/src/'
	--exclude='*.DS_Store'
	--exclude='Thumbs.db'
	--exclude='*.log'
	--exclude='*.tmp'
	--exclude='*.temp'
	--exclude='*.swp'
	--exclude='*.swo'
	--exclude='*~'
	--exclude='*.zip'
	--exclude='*.sh'
	--exclude='*.md'
	--exclude='*.map'
	--exclude='composer.json'
	--exclude='composer.lock'
	--exclude='package.json'
	--exclude='package-lock.json'
	--exclude='phpcs.xml'
	--exclude='phpcs.xml.dist'
	--exclude='phpcs-security.xml'
	--exclude='phpunit.xml'
	--exclude='phpunit.xml.dist'
	--exclude='webpack.config.js'
	--exclude='eas.json'
	--exclude='app.json'
)

trdsp_plugin_version() {
	local main_file="$1"
	local ver
	ver="$(
		grep -m1 -E '^\s*\*?\s*Version:' "${main_file}" \
			| sed -E 's/.*Version:[[:space:]]*//' \
			| tr -d '\r' \
			| awk '{print $1}'
	)"
	if [[ -z "${ver}" ]]; then
		echo "Error: could not read Version from ${main_file}" >&2
		exit 1
	fi
	printf '%s\n' "${ver}"
}

trdsp_zip_listing() {
	local zip_path="$1"
	if command -v zipinfo >/dev/null 2>&1; then
		zipinfo -1 "${zip_path}"
	else
		unzip -Z1 "${zip_path}"
	fi
}

# Fail if a built zip still contains anything WordPress.org (or a customer site) must not receive.
trdsp_verify_zip() {
	local zip_path="$1"
	local slug="$2"
	local main_php="$3"
	local listing forbidden

	if [[ ! -f "${zip_path}" ]]; then
		echo "Error: missing zip ${zip_path}" >&2
		exit 1
	fi

	listing="$(trdsp_zip_listing "${zip_path}")"

	if ! printf '%s\n' "${listing}" | grep -qF "${slug}/${main_php}"; then
		echo "Error: ${slug}/${main_php} is not at the zip root folder (wrong install layout)." >&2
		exit 1
	fi

	if printf '%s\n' "${listing}" | grep -qE "^${slug}-${slug}|^${slug}-[0-9]"; then
		echo "Error: zip install folder must be ${slug}/ with no version suffix." >&2
		exit 1
	fi

	forbidden="$(
		printf '%s\n' "${listing}" | grep -E \
			'(^|/)\.[^/]+|\.git/|\.cursor/|\.cursorrules|\.github-pat\.local|\.distignore|\.env|\.svn/|svn-checkout/|(^|/ )Dist/|(^|/)docs/|(^|/)vendor/|(^|/)node_modules/|(^|/)tests/|\.sh$|\.md$|\.zip$|phpcs\.xml|phpunit\.xml|composer\.(json|lock)$|package(-lock)?\.json$|deploy-to-hub\.sh|create-.*\.sh' \
			|| true
	)"
	if [[ -n "${forbidden}" ]]; then
		echo "Error: excluded paths are still in ${zip_path}:" >&2
		printf '%s\n' "${forbidden}" >&2
		exit 1
	fi

	if [[ "${slug}" == "${FREE_SLUG}" ]]; then
		for must in \
			"${slug}/readme.txt" \
			"${slug}/uninstall.php" \
			"${slug}/includes/class-trdsp-plugin.php" \
			"${slug}/assets/js/trdsp-booking.js" \
			"${slug}/assets/js/trdsp-admin-confirm.js" \
			"${slug}/assets/css/trdsp-admin.css" \
			"${slug}/assets/blueprints/blueprint.json" \
			"${slug}/blueprint.json" \
			"${slug}/languages/trade-dispatch.pot"
		do
			if ! printf '%s\n' "${listing}" | grep -qF "${must}"; then
				echo "Error: required free file missing from zip: ${must}" >&2
				exit 1
			fi
		done
	fi

	if [[ "${slug}" == "${PRO_SLUG}" ]]; then
		for must in \
			"${slug}/readme.txt" \
			"${slug}/uninstall.php" \
			"${slug}/includes/class-trdsp-pro-plugin.php" \
			"${slug}/assets/js/trdsp-pro-admin-qr.js" \
			"${slug}/assets/lib/leaflet/leaflet.css"
		do
			if ! printf '%s\n' "${listing}" | grep -qF "${must}"; then
				echo "Error: required Pro file missing from zip: ${must}" >&2
				exit 1
			fi
		done
	fi

	echo "OK: ${zip_path} ($(ls -lh "${zip_path}" | awk '{print $5}'))"
}

trdsp_build_zip() {
	local source_dir="$1"
	local slug="$2"
	local main_php="$3"
	local version="$4"
	local zip_name="${slug}-${version}.zip"
	local zip_path="${DIST_DIR}/${zip_name}"
	local temp_dir
	local staged

	if [[ ! -f "${source_dir}/${main_php}" ]]; then
		echo "Error: ${source_dir}/${main_php} not found." >&2
		exit 1
	fi

	temp_dir="$(mktemp -d "${TMPDIR:-/tmp}/trdsp-zip.XXXXXX")"
	staged="${temp_dir}/${slug}"
	# shellcheck disable=SC2064
	trap "rm -rf '${temp_dir}'" RETURN

	mkdir -p "${staged}"
	rsync -a "${RSYNC_EXCLUDES[@]}" "${source_dir}/" "${staged}/"

	# Belt-and-suspenders: never nest archives or leftover secrets.
	find "${staged}" -type f \( -name '*.zip' -o -name '.github-pat.local' -o -name '.env' -o -name '.env.local' \) -delete
	find "${staged}" \( -name '.DS_Store' -o -name 'Thumbs.db' \) -delete

	rm -f "${zip_path}"
	(
		cd "${temp_dir}" || exit 1
		zip -qr "${zip_path}" "${slug}"
	)

	rm -rf "${temp_dir}"
	trap - RETURN

	trdsp_verify_zip "${zip_path}" "${slug}" "${main_php}"
}

mkdir -p "${DIST_DIR}"
# Dist is zip-only. Drop leftover unpacked trees from older create-plugin-zip.sh runs.
rm -rf "${DIST_DIR}/${FREE_SLUG}" "${DIST_DIR}/${PRO_SLUG}"

echo "=========================================="
echo "Trade Dispatch distribution zips"
echo "Output: ${DIST_DIR}"
echo "=========================================="

FREE_ZIP=""
PRO_ZIP=""

if [[ "${BUILD_FREE}" -eq 1 ]]; then
	if [[ ! -f "${FREE_MAIN}" ]]; then
		echo "Error: free plugin main file missing: ${FREE_MAIN}" >&2
		exit 1
	fi
	FREE_VERSION="$(trdsp_plugin_version "${FREE_MAIN}")"
	FREE_ZIP="${DIST_DIR}/${FREE_SLUG}-${FREE_VERSION}.zip"
	echo ""
	echo "==> Free ${FREE_SLUG} ${FREE_VERSION}"
	trdsp_build_zip "${FREE_ROOT}" "${FREE_SLUG}" "trade-dispatch.php" "${FREE_VERSION}"
fi

if [[ "${BUILD_PRO}" -eq 1 ]]; then
	if [[ ! -d "${PRO_ROOT}" || ! -f "${PRO_MAIN}" ]]; then
		echo "Error: Pro plugin not found at ${PRO_ROOT}" >&2
		echo "Expected sibling folder: .../TradeDispatch/Wordpress/TradeDispatchPro" >&2
		exit 1
	fi
	PRO_VERSION="$(trdsp_plugin_version "${PRO_MAIN}")"
	PRO_ZIP="${DIST_DIR}/${PRO_SLUG}-${PRO_VERSION}.zip"
	echo ""
	echo "==> Pro ${PRO_SLUG} ${PRO_VERSION}"
	trdsp_build_zip "${PRO_ROOT}" "${PRO_SLUG}" "trade-dispatch-pro.php" "${PRO_VERSION}"
fi

echo ""
echo "Done. Use these files (do not zip the repo folders):"
if [[ -n "${FREE_ZIP}" ]]; then
	echo "  WP.org / free: ${FREE_ZIP}"
fi
if [[ -n "${PRO_ZIP}" ]]; then
	echo "  Pro install:   ${PRO_ZIP}"
fi
