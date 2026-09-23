#!/bin/bash
set -euo pipefail

TEST_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PLUGIN_DIR=$(CDPATH= cd -- "$TEST_DIR/.." && pwd)
OVERLAY="$PLUGIN_DIR/source/homenas.dashboard/usr/local/emhttp/plugins/homenas.dashboard/scripts/dashboard-overlay"
FIXTURES="$TEST_DIR/fixtures/dashstats"
WORK_DIR=$(mktemp -d "${TMPDIR:-/tmp}/homenas-dashboard-overlay.XXXXXX")

cleanup() {
    rm -rf "$WORK_DIR"
}
trap cleanup EXIT

fail() {
    printf 'FAIL: %s\n' "$*" >&2
    exit 1
}

run_overlay() {
    HOMENAS_DASHBOARD_PAGE=$1 \
    HOMENAS_DASHBOARD_LOCK_DIR="$WORK_DIR/lock" \
    HOMENAS_DASHBOARD_NO_LOGGER=1 \
    "$OVERLAY" "$2"
}

copy_fixture() {
    local fixture=$1 target=$2
    cp "$FIXTURES/$fixture" "$target"
}

exercise_supported() {
    local source=$1 name=$2 target="$WORK_DIR/$2.page"
    cp "$source" "$target"
    cp "$target" "$target.before"
    [ "$(run_overlay "$target" status)" = disabled ] || fail "unexpected initial status for $name"
    run_overlay "$target" apply
    [ "$(run_overlay "$target" status)" = active ] || fail "tile did not become active for $name"

    python3 - "$target.before" "$target" <<'PY' || fail "apply altered content outside its marker block or tile position for $name"
import pathlib
import re
import sys

before = pathlib.Path(sys.argv[1]).read_bytes()
after = pathlib.Path(sys.argv[2]).read_bytes()
block = (
    b"/* homenas.dashboard:temperatures:begin v1 */\n"
    b"<?php require_once '/usr/local/emhttp/plugins/homenas.dashboard/include/temperature-tile.php'; ?>\n"
    b"/* homenas.dashboard:temperatures:end */\n"
)
assert after.count(block) == 1
assert after.replace(block, b"", 1) == before
assert re.search(
    re.escape(block) + rb"[ \t]*<\?customTiles\('column1'\);\?>[ \t]*(?:\r?\n|$)",
    after,
)
PY

    cp "$target" "$target.after-first"
    run_overlay "$target" apply
    cmp -s "$target.after-first" "$target" || fail "repeat apply was not idempotent for $name"
    run_overlay "$target" remove
    cmp -s "$target.before" "$target" || fail "remove did not restore $name byte-for-byte"
}

exercise_supported "$FIXTURES/supported.page" no-indent
exercise_supported "$FIXTURES/indented16.page" indented16
exercise_supported "$FIXTURES/tabs.page" tabs

awk '/<\?customTiles\('\''column[12]'\''\);\?>/ { print $0 " \t"; next } { print }' \
    "$FIXTURES/supported.page" > "$WORK_DIR/trailing-whitespace-fixture.page"
exercise_supported "$WORK_DIR/trailing-whitespace-fixture.page" trailing-whitespace

source_size=$(wc -c < "$FIXTURES/supported.page" | tr -d '[:space:]')
dd if="$FIXTURES/supported.page" of="$WORK_DIR/no-final-newline-fixture.page" \
    bs=1 count="$((source_size - 1))" 2>/dev/null
exercise_supported "$WORK_DIR/no-final-newline-fixture.page" no-final-newline

for fixture in unknown-layout.page missing-anchor.page double-marker.page extra-text.page double-anchor.page malformed-php.page mixed-expression.page broken-marker.page; do
    target="$WORK_DIR/$fixture"
    copy_fixture "$fixture" "$target"
    cp "$target" "$target.before"
    if run_overlay "$target" apply; then
        fail "apply unexpectedly accepted $fixture"
    fi
    cmp -s "$target.before" "$target" || fail "failed apply changed $fixture"
done

old="$WORK_DIR/old-own-tile.page"
copy_fixture old-own-tile.page "$old"
run_overlay "$old" apply
grep -Fqx '/* homenas.dashboard:temperatures:begin v1 */' "$old" || fail 'old marker was not upgraded'
if grep -Fqx '/* homenas.dashboard:temperatures:begin v0 */' "$old"; then
    fail 'old marker remained after update'
fi

printf 'PASS dashboard overlay fixtures\n'
