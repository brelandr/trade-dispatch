#!/usr/bin/env bash
# Back-compat wrapper. Prefer ./create-both-plugins.sh
# This used to wipe Dist/ and write trade-dispatch.zip at the plugin root.
exec "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/create-both-plugins.sh" --free-only "$@"
