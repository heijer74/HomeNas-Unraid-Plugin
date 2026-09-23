#!/bin/bash
set -euo pipefail

TEST_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
SOURCE_PLUGIN=$(CDPATH= cd -- "$TEST_DIR/.." && pwd)
# shellcheck disable=SC1091
. "$SOURCE_PLUGIN/metadata.env"
WORK_DIR=$(mktemp -d "${TMPDIR:-/tmp}/homenas-release-test.XXXXXX")
FIXTURE_PLUGIN="$WORK_DIR/plugin"

cleanup() {
    rm -rf "$WORK_DIR"
}
trap cleanup EXIT

fail() {
    printf 'FAIL: %s\n' "$*" >&2
    exit 1
}

if [ "$(uname -s)" != Linux ] || ! tar --version 2>/dev/null | head -n 1 | grep -Fq 'GNU tar'; then
    printf 'SKIP: canonical release-build test requires GNU tar on the Lenovo Linux build machine\n'
    exit 0
fi

expect_failure() {
    if "$@" >/dev/null 2>&1; then
        fail "command unexpectedly succeeded: $*"
    fi
}

make_fixture() {
    rm -rf "$FIXTURE_PLUGIN"
    cp -R "$SOURCE_PLUGIN" "$FIXTURE_PLUGIN"
    find "$FIXTURE_PLUGIN/dist" -mindepth 1 -maxdepth 1 ! -name .gitignore ! -name .gitkeep -exec rm -f {} +
}

make_fixture
(cd "$FIXTURE_PLUGIN" && bash build/build-release.sh)
(cd "$FIXTURE_PLUGIN" && bash build/verify-release.sh)
package="$FIXTURE_PLUGIN/dist/$PACKAGE_NAME"
plg="$FIXTURE_PLUGIN/dist/homenas.dashboard.plg"
public_plg="$FIXTURE_PLUGIN/homenas.dashboard.plg"
manifest="$FIXTURE_PLUGIN/dist/homenas.dashboard-release-manifest.json"
[ -s "$package" ] && [ -s "$plg" ] && [ -s "$manifest" ] || fail 'valid build artefacts ontbreken'
cmp -s "$plg" "$public_plg" || fail 'publieke PLG wijkt af van gerenderde PLG'
python3 - "$package" "$manifest" <<'PY' || fail 'manifest package_size_bytes is geen exact JSON-getal'
import json
import os
import sys

with open(sys.argv[2], encoding="utf-8") as stream:
    manifest = json.load(stream)
size = manifest.get("package_size_bytes")
assert type(size) is int and size > 0
assert size == os.stat(sys.argv[1]).st_size
PY

sed -i.bak 's/"package_size_bytes": [0-9][0-9]*/"package_size_bytes": "invalid"/' "$manifest"
rm -f "$manifest.bak"
expect_failure bash "$FIXTURE_PLUGIN/build/verify-release.sh"

make_fixture
(cd "$FIXTURE_PLUGIN" && bash build/build-txz.sh)
stat() { printf 'unexpected filesystem information\n'; }
export -f stat
expect_failure bash "$FIXTURE_PLUGIN/build/render-plg.sh"
unset -f stat
[ ! -e "$FIXTURE_PLUGIN/dist/homenas.dashboard-release-manifest.json" ] || fail 'ongeldige grootte leverde toch een manifest op'

make_fixture
(cd "$FIXTURE_PLUGIN" && bash build/build-release.sh)
package="$FIXTURE_PLUGIN/dist/$PACKAGE_NAME"
printf 'tamper\n' >> "$package"
expect_failure bash "$FIXTURE_PLUGIN/build/verify-release.sh"

make_fixture
expect_failure bash "$FIXTURE_PLUGIN/build/render-plg.sh"

make_fixture
sed -i.bak 's/^PLUGIN_VERSION=.*/PLUGIN_VERSION=9.9.9/' "$FIXTURE_PLUGIN/metadata.env"
rm -f "$FIXTURE_PLUGIN/metadata.env.bak"
expect_failure bash "$FIXTURE_PLUGIN/build/build-release.sh"

make_fixture
(cd "$FIXTURE_PLUGIN" && bash build/build-release.sh)
hash_line=$(grep -n '<!ENTITY sha256 ' "$FIXTURE_PLUGIN/dist/homenas.dashboard.plg" | cut -d: -f1)
[ -n "$hash_line" ] || fail 'SHA-256 entity ontbreekt in geldige PLG'
awk '
    /<!ENTITY sha256 / { print "<!ENTITY sha256 \"\">"; next }
    { print }
' "$FIXTURE_PLUGIN/dist/homenas.dashboard.plg" > "$FIXTURE_PLUGIN/dist/homenas.dashboard.plg.empty"
mv "$FIXTURE_PLUGIN/dist/homenas.dashboard.plg.empty" "$FIXTURE_PLUGIN/dist/homenas.dashboard.plg"
expect_failure bash "$FIXTURE_PLUGIN/build/verify-release.sh"

make_fixture
printf '\nPLUGIN_NAME=duplicate\n' >> "$FIXTURE_PLUGIN/metadata.env"
expect_failure bash "$FIXTURE_PLUGIN/build/render-plg.sh"

printf 'PASS release build fixtures\n'
