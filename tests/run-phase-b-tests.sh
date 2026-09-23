#!/bin/bash
set -euo pipefail

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)

if ! command -v php >/dev/null 2>&1; then
    printf 'SKIP: PHP CLI is not available; fixture tests were not run.\n' >&2
    exit 77
fi

exec php "$SCRIPT_DIR/run.php"
