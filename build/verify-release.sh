#!/bin/bash
set -euo pipefail

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PLUGIN_ROOT=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
# shellcheck disable=SC1090
. "$PLUGIN_ROOT/metadata.env"

DIST="$PLUGIN_ROOT/dist"
PACKAGE="$DIST/$PACKAGE_NAME"
PLG="$DIST/$PLUGIN_NAME.plg"
PUBLIC_PLG="$PLUGIN_ROOT/$PLUGIN_NAME.plg"
MANIFEST="$DIST/$PLUGIN_NAME-release-manifest.json"

fail() {
    printf 'error: %s\n' "$*" >&2
    exit 1
}

[ -s "$PACKAGE" ] || fail 'package ontbreekt of is leeg'
[ -s "$PLG" ] || fail 'PLG ontbreekt of is leeg'
[ -s "$PUBLIC_PLG" ] || fail 'publieke PLG ontbreekt of is leeg'
[ -f "$PUBLIC_PLG" ] && cmp -s "$PLG" "$PUBLIC_PLG" || fail 'publieke PLG wijkt af van gerenderde PLG'
[ -s "$MANIFEST" ] || fail 'release manifest ontbreekt of is leeg'

bash "$SCRIPT_DIR/inspect-package.sh" "$PACKAGE"

SHA256=$(shasum -a 256 "$PACKAGE" | awk '{print $1}')
[[ "$SHA256" =~ ^[0-9a-f]{64}$ ]] || fail 'ongeldige SHA-256'
grep -Fq "<!ENTITY sha256 \"$SHA256\">" "$PLG" || fail 'PLG SHA-256 komt niet overeen'
grep -Fq "\"sha256\": \"$SHA256\"" "$MANIFEST" || fail 'manifest SHA-256 komt niet overeen'
grep -Fq "\"package_filename\": \"$PACKAGE_NAME\"" "$MANIFEST" || fail 'manifest package filename komt niet overeen'
grep -Fq "\"release_tag\": \"$RELEASE_TAG\"" "$MANIFEST" || fail 'manifest release tag komt niet overeen'
ACTUAL_SIZE=$(wc -c < "$PACKAGE" | tr -d '[:space:]')
[[ "$ACTUAL_SIZE" =~ ^[1-9][0-9]*$ ]] || fail 'werkelijke packagegrootte is ongeldig'
grep -Fxq "  \"package_size_bytes\": $ACTUAL_SIZE," "$MANIFEST" || fail 'manifest packagegrootte is ongeldig of komt niet overeen'
grep -Fq "<!ENTITY package_url \"$PACKAGE_URL\">" "$PLG" || fail 'PLG package URL komt niet overeen'
grep -Fq "<SHA256>&sha256;</SHA256>" "$PLG" || fail 'PLG SHA-256 veld ontbreekt'
xmllint --noout "$PLG" >/dev/null 2>&1 || fail 'ongeldige PLG XML'
printf 'release verification passed\n'
