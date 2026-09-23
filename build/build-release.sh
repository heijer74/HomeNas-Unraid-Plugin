#!/bin/bash
set -euo pipefail

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)

bash "$SCRIPT_DIR/checks.sh"
bash "$SCRIPT_DIR/build-txz.sh"
bash "$SCRIPT_DIR/render-plg.sh"
bash "$SCRIPT_DIR/verify-release.sh"
