#!/bin/bash
set -euo pipefail

TEST_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
SOURCE_PLUGIN=$(CDPATH= cd -- "$TEST_DIR/.." && pwd)
# shellcheck disable=SC1091
. "$SOURCE_PLUGIN/metadata.env"
WORK_DIR=$(mktemp -d "${TMPDIR:-/tmp}/homenas-package-cleanliness.XXXXXX")
FIXTURE_PLUGIN="$WORK_DIR/plugin"

cleanup() {
    rm -rf "$WORK_DIR"
}
trap cleanup EXIT

fail() {
    printf 'FAIL: %s\n' "$*" >&2
    exit 1
}

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

runtime_rel=usr/local/emhttp/plugins/homenas.dashboard

make_fixture
touch "$FIXTURE_PLUGIN/source/homenas.dashboard/$runtime_rel/._fixture"
expect_failure bash "$FIXTURE_PLUGIN/build/checks.sh"

make_fixture
touch "$FIXTURE_PLUGIN/source/homenas.dashboard/$runtime_rel/.DS_Store"
expect_failure bash "$FIXTURE_PLUGIN/build/checks.sh"

# Archive inspection is intentionally independent of the local operating
# system: both known Finder sidecar names must be rejected after archiving too.
make_fixture
package="$FIXTURE_PLUGIN/dist/$PACKAGE_NAME"
touch "$FIXTURE_PLUGIN/source/homenas.dashboard/$runtime_rel/._fixture"
(cd "$FIXTURE_PLUGIN/source/homenas.dashboard" && tar -cJf "$package" .)
expect_failure bash "$FIXTURE_PLUGIN/build/inspect-package.sh" "$package"

make_fixture
package="$FIXTURE_PLUGIN/dist/$PACKAGE_NAME"
touch "$FIXTURE_PLUGIN/source/homenas.dashboard/$runtime_rel/.DS_Store"
(cd "$FIXTURE_PLUGIN/source/homenas.dashboard" && tar -cJf "$package" .)
expect_failure bash "$FIXTURE_PLUGIN/build/inspect-package.sh" "$package"

printf 'PASS package-cleanliness fixtures\n'
