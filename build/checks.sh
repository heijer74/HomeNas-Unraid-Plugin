#!/bin/bash
set -euo pipefail

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PLUGIN_ROOT=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
# shellcheck disable=SC1091
. "$PLUGIN_ROOT/metadata.env"

PLG="$PLUGIN_ROOT/homenas.dashboard.plg"
STAGE="$PLUGIN_ROOT/source/homenas.dashboard"
RUNTIME="$STAGE/usr/local/emhttp/plugins/homenas.dashboard"
PACKAGE_MANIFEST="$SCRIPT_DIR/package-manifest.txt"

fail() {
    printf 'error: %s\n' "$*" >&2
    exit 1
}

[ -f "$PLG" ] || fail '.plg skeleton missing'
[ -f "$PLUGIN_ROOT/metadata.env" ] || fail 'metadata missing'
[ -d "$RUNTIME" ] || fail 'runtime staging tree missing'
[ -f "$PACKAGE_MANIFEST" ] || fail 'package manifest missing'
[ -x "$RUNTIME/scripts/install" ] || fail 'install script not executable'
[ -x "$RUNTIME/scripts/remove" ] || fail 'remove script not executable'

EXPECTED_RUNTIME_FILES=(
    HomeNasDashboard.page
    README.md
    actions/save-settings.php
    assets/homenas-dashboard-airflow.css
    assets/homenas-dashboard-airflow.js
    assets/homenas-dashboard-temperature.css
    assets/homenas-dashboard-temperature.js
    include/airflow-stock-7.3.2.txt
    include/airflow-tile.php
    include/config-schema.php
    include/config-store.php
    include/config.php
    include/sensor-catalog.php
    include/sensor-reader.php
    include/settings-page.php
    include/temperature-tile.php
    include/temperatures.php
    scripts/airflow-overlay.php
    scripts/dashboard-overlay
    scripts/install
    scripts/remove
    scripts/refresh-dashboard
)

actual_files=$(cd "$RUNTIME" && find . -type f -print | sed 's#^./##' | sort)
expected_files=$(printf '%s\n' "${EXPECTED_RUNTIME_FILES[@]}" | sort)
if [ "$actual_files" != "$expected_files" ]; then
    fail 'unexpected Phase D runtime file set'
fi

manifest_files=$(LC_ALL=C sort "$PACKAGE_MANIFEST")
manifest_unique=$(LC_ALL=C sort -u "$PACKAGE_MANIFEST")
[ "$manifest_files" = "$manifest_unique" ] || fail 'package manifest contains duplicate paths'
if grep -Eq '(^/|(^|/)\.\.(/|$)|^$)' "$PACKAGE_MANIFEST"; then
    fail 'package manifest contains an unsafe path'
fi

actual_package_files=$(cd "$STAGE" && find usr -type f -print | LC_ALL=C sort)
if [ "$actual_package_files" != "$manifest_files" ]; then
    fail 'staging tree does not exactly match package manifest'
fi

unsafe_entries=$(find "$STAGE" \( -name '._*' -o -name '.DS_Store' -o -name '.AppleDouble' -o -name '.LSOverride' -o -name '..namedfork' \) -print)
[ -z "$unsafe_entries" ] || fail "macOS metadata entry in staging: $unsafe_entries"

check_xattrs() {
    local file attrs
    while IFS= read -r -d '' file; do
        if command -v xattr >/dev/null 2>&1; then
            attrs=$(xattr -l "$file" 2>/dev/null || true)
            [ -z "$attrs" ] || fail "extended attribute in staging: $file"
        fi
        if command -v getfattr >/dev/null 2>&1; then
            attrs=$(getfattr --absolute-names -d -m - "$file" 2>/dev/null || true)
            if printf '%s\n' "$attrs" | grep -Eq '^(user|security|trusted|system)\.'; then
                fail "extended attribute in staging: $file"
            fi
        fi
    done < <(find "$STAGE" -print0)
}

# This plugin has no legitimate extended attributes. Reject all of them before
# archiving so macOS provenance and resource-fork metadata cannot enter a txz.
check_xattrs

if rg -n 'HomeNasTemps|patch-airflow|pwm[0-9]|WHWM|RHWM|wmidev' \
    "$RUNTIME" >/dev/null 2>&1; then
    fail 'runtime contains legacy scripts or hardware control integration'
fi

printf 'Phase E package checks passed\n'
