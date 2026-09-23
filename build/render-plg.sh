#!/bin/bash
set -euo pipefail

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PLUGIN_ROOT=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
METADATA="$PLUGIN_ROOT/metadata.env"
TEMPLATE="$PLUGIN_ROOT/homenas.dashboard.plg.in"
DIST="$PLUGIN_ROOT/dist"

fail() {
    printf 'error: %s\n' "$*" >&2
    exit 1
}

sha256_file() {
    shasum -a 256 "$1" | awk '{print $1}'
}

md5_file() {
    if command -v md5sum >/dev/null 2>&1; then
        md5sum "$1" | awk '{print $1}'
    else
        md5 -q "$1"
    fi
}

validate_metadata_file() {
    [ -f "$METADATA" ] || fail 'metadata.env ontbreekt'
    local duplicates
    duplicates=$(awk -F= '/^[A-Z_][A-Z0-9_]*=/{ if (++seen[$1] > 1) print $1 }' "$METADATA")
    [ -z "$duplicates" ] || fail "dubbele metadata: $(printf '%s' "$duplicates" | tr '\n' ' ')"
}

validate_value() {
    local label=$1 value=$2 pattern=$3
    [ -n "$value" ] || fail "lege metadata: $label"
    [[ "$value" =~ $pattern ]] || fail "ongeldige metadata: $label"
    if [[ "$value" == *'<'* || "$value" == *'>'* || "$value" == *'&'* || "$value" == *'"'* || "$value" == *"'"* ]]; then
        fail "onveilige XML-metadata: $label"
    fi
}

validate_metadata_file
# shellcheck disable=SC1090
. "$METADATA"

for required in PLUGIN_NAME PLUGIN_AUTHOR PLUGIN_VERSION MIN_UNRAID_VERSION ARCH PACKAGE_RELEASE RELEASE_TAG PACKAGE_NAME PACKAGE_URL PLUGIN_URL; do
    [ -n "${!required:-}" ] || fail "ontbrekende metadata: $required"
done

validate_value PLUGIN_NAME "$PLUGIN_NAME" '^[a-z0-9][a-z0-9.-]*$'
validate_value PLUGIN_AUTHOR "$PLUGIN_AUTHOR" '^[A-Za-z0-9._ -]+$'
validate_value PLUGIN_VERSION "$PLUGIN_VERSION" '^[0-9][0-9A-Za-z._-]*$'
validate_value MIN_UNRAID_VERSION "$MIN_UNRAID_VERSION" '^[0-9][0-9.]*$'
validate_value ARCH "$ARCH" '^[a-z0-9_]+$'
validate_value PACKAGE_RELEASE "$PACKAGE_RELEASE" '^[0-9]+$'
validate_value RELEASE_TAG "$RELEASE_TAG" '^homenas\.dashboard-v[0-9][0-9A-Za-z._-]*$'

EXPECTED_PACKAGE_NAME="$PLUGIN_NAME-$PLUGIN_VERSION-$ARCH-$PACKAGE_RELEASE.txz"
[ "$PACKAGE_NAME" = "$EXPECTED_PACKAGE_NAME" ] || fail 'package filename komt niet overeen met name/version/arch/release'
[ "$RELEASE_TAG" = "$PLUGIN_NAME-v$PLUGIN_VERSION" ] || fail 'release tag komt niet overeen met pluginnaam en versie'
[[ "$PACKAGE_URL" == https://*"/$PACKAGE_NAME" ]] || fail 'PACKAGE_URL moet exact op de package filename eindigen'
[[ "$PLUGIN_URL" == https://*"/$PLUGIN_NAME.plg" ]] || fail 'PLUGIN_URL moet op de PLG filename eindigen'
[[ "$PACKAGE_URL" != *'&'* && "$PLUGIN_URL" != *'&'* ]] || fail 'URL met XML-special character niet toegestaan'
[ -f "$TEMPLATE" ] || fail 'PLG-template ontbreekt'

PACKAGE="$DIST/$PACKAGE_NAME"
OUTPUT="$DIST/$PLUGIN_NAME.plg"
PUBLIC_PLG="$PLUGIN_ROOT/$PLUGIN_NAME.plg"
MANIFEST="$DIST/$PLUGIN_NAME-release-manifest.json"
[ -s "$PACKAGE" ] || fail 'package ontbreekt of is leeg'

case $(uname -s) in
    Linux)
        PACKAGE_SIZE=$(stat -c '%s' "$PACKAGE") || fail 'packagegrootte bepalen met GNU stat mislukt'
        ;;
    Darwin|FreeBSD|NetBSD|OpenBSD)
        PACKAGE_SIZE=$(stat -f '%z' "$PACKAGE") || fail 'packagegrootte bepalen met BSD stat mislukt'
        ;;
    *)
        fail 'onbekend platform voor packagegrootte'
        ;;
esac
[[ "$PACKAGE_SIZE" =~ ^[1-9][0-9]*$ ]] || fail 'packagegrootte is geen positief geheel getal'

SHA256=$(sha256_file "$PACKAGE")
MD5=$(md5_file "$PACKAGE")
[[ "$SHA256" =~ ^[0-9a-f]{64}$ ]] || fail 'ontbrekende of ongeldige SHA-256'
[[ "$MD5" =~ ^[0-9a-f]{32}$ ]] || fail 'ontbrekende of ongeldige MD5'
[ "$SHA256" != "$(printf '0%.0s' {1..64})" ] || fail 'SHA-256 placeholder niet toegestaan'

PACKAGE_BASENAME=${PACKAGE_NAME%.txz}
TEMP_PLG=$(mktemp "$DIST/.${PLUGIN_NAME}.plg.XXXXXX")
TEMP_PUBLIC_PLG=$(mktemp "$PLUGIN_ROOT/.${PLUGIN_NAME}.plg.XXXXXX")
TEMP_MANIFEST=$(mktemp "$DIST/.${PLUGIN_NAME}.manifest.XXXXXX")
cleanup() {
    rm -f "$TEMP_PLG" "$TEMP_PUBLIC_PLG" "$TEMP_MANIFEST"
}
trap cleanup EXIT

sed \
    -e "s|@PLUGIN_NAME@|$PLUGIN_NAME|g" \
    -e "s|@PLUGIN_AUTHOR@|$PLUGIN_AUTHOR|g" \
    -e "s|@PLUGIN_VERSION@|$PLUGIN_VERSION|g" \
    -e "s|@MIN_UNRAID_VERSION@|$MIN_UNRAID_VERSION|g" \
    -e "s|@PACKAGE_NAME@|$PACKAGE_NAME|g" \
    -e "s|@PACKAGE_BASENAME@|$PACKAGE_BASENAME|g" \
    -e "s|@PACKAGE_URL@|$PACKAGE_URL|g" \
    -e "s|@PLUGIN_URL@|$PLUGIN_URL|g" \
    -e "s|@MD5@|$MD5|g" \
    -e "s|@SHA256@|$SHA256|g" \
    -e "s|@HOOK_PLUGIN_NAME@|$PLUGIN_NAME|g" \
    -e "s|@HOOK_PACKAGE_NAME@|$PACKAGE_NAME|g" \
    -e "s|@HOOK_PACKAGE_BASENAME@|$PACKAGE_BASENAME|g" \
    -e "s|@HOOK_SHA256@|$SHA256|g" \
    "$TEMPLATE" > "$TEMP_PLG"

if grep -Fq '@' "$TEMP_PLG"; then
    fail 'onvervangen PLG placeholder'
fi
xmllint --noout "$TEMP_PLG" >/dev/null 2>&1 || fail 'ongeldige PLG XML'
python3 "$SCRIPT_DIR/check-plg-hooks.py" "$TEMP_PLG" "$PLUGIN_NAME" "$PACKAGE_NAME" "$SHA256" \
    || fail 'ongeldige of niet-gerenderde PLG shell-hooks'
cp "$TEMP_PLG" "$TEMP_PUBLIC_PLG"

printf '{\n  "plugin_version": "%s",\n  "release_tag": "%s",\n  "package_filename": "%s",\n  "package_size_bytes": %s,\n  "sha256": "%s"\n}\n' \
    "$PLUGIN_VERSION" "$RELEASE_TAG" "$PACKAGE_NAME" "$PACKAGE_SIZE" "$SHA256" > "$TEMP_MANIFEST"

chmod 644 "$TEMP_PLG" "$TEMP_PUBLIC_PLG" "$TEMP_MANIFEST"
mv -f "$TEMP_PLG" "$OUTPUT"
mv -f "$TEMP_PUBLIC_PLG" "$PUBLIC_PLG"
mv -f "$TEMP_MANIFEST" "$MANIFEST"
printf 'rendered %s\n' "$OUTPUT"
printf 'rendered %s\n' "$PUBLIC_PLG"
printf 'rendered %s\n' "$MANIFEST"
