#!/bin/bash
set -euo pipefail

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PLUGIN_ROOT=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
# shellcheck disable=SC1091
. "$PLUGIN_ROOT/metadata.env"

PACKAGE_MANIFEST="$SCRIPT_DIR/package-manifest.txt"
PACKAGE=${1:-"$PLUGIN_ROOT/dist/$PACKAGE_NAME"}

fail() {
    printf 'error: %s\n' "$*" >&2
    exit 1
}

[ -s "$PACKAGE" ] || fail "package missing or empty: $PACKAGE"
[ -f "$PACKAGE_MANIFEST" ] || fail 'package manifest missing'

expected=$(LC_ALL=C sort "$PACKAGE_MANIFEST")
actual=$(tar -tJf "$PACKAGE" | sed 's#^\./##' | sed '/^$/d' | LC_ALL=C sort) || fail 'cannot list txz contents'
[ "$actual" = "$expected" ] || fail 'txz members do not exactly match package manifest'

if tar -tJf "$PACKAGE" | grep -Eq '(^|/)\._[^/]*$|(^|/)\.DS_Store$|(^|/)\.AppleDouble(/|$)|(^|/)\.LSOverride$|(^|/)\.\.?namedfork(/|$)'; then
    fail 'txz contains an AppleDouble, resource-fork or Finder metadata entry'
fi

command -v xz >/dev/null 2>&1 || fail 'xz is required to inspect txz metadata'
if xz -dc "$PACKAGE" | LC_ALL=C grep -aEq 'LIBARCHIVE\.xattr|SCHILY\.(xattr|acl)|com\.apple\.|AppleDouble|\.DS_Store'; then
    fail 'txz contains extended-attribute, ACL or macOS metadata'
fi

printf 'package inspection passed: %s\n' "$PACKAGE"
