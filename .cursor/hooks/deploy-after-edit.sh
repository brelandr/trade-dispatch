#!/usr/bin/env bash
# afterFileEdit: rsync Trade Dispatch to the hub when plugin code changes.
set -euo pipefail

PLUGIN_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DEPLOY="${PLUGIN_ROOT}/deploy-to-hub.sh"

input="$(cat)"

should_deploy="$(
	python3 -c '
import json, os, re, sys
raw = sys.stdin.read()
try:
    data = json.loads(raw) if raw.strip() else {}
except json.JSONDecodeError:
    data = {}

paths = []
if isinstance(data, dict):
    for key in ("file_path", "path", "file", "uri"):
        val = data.get(key)
        if isinstance(val, str):
            paths.append(val)
    file_info = data.get("file")
    if isinstance(file_info, dict):
        for key in ("path", "file_path", "uri"):
            val = file_info.get(key)
            if isinstance(val, str):
                paths.append(val)

joined = "\n".join(paths)
if not joined:
    # Fail open: deploy when the hook fired from this plugin workspace.
    print("yes")
    raise SystemExit(0)

skip = re.compile(r"(\.cursorrules$|\.cursor/|deploy-to-hub\.sh$|\.git/)", re.I)
code = re.compile(r"\.(php|js|css)$|readme\.txt$", re.I)
if skip.search(joined):
    print("no")
elif code.search(joined):
    print("yes")
else:
    print("no")
' <<< "${input}"
)"

if [[ "${should_deploy}" != "yes" ]]; then
	printf '%s\n' '{}'
	exit 0
fi

if [[ ! -x "${DEPLOY}" ]]; then
	chmod +x "${DEPLOY}" || true
fi

"${DEPLOY}" >/dev/null
printf '%s\n' '{}'
exit 0
