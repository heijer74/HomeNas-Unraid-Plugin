#!/bin/bash
set -euo pipefail

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PLUGIN_ROOT=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
# shellcheck disable=SC1091
. "$PLUGIN_ROOT/metadata.env"

STAGE="$PLUGIN_ROOT/source/homenas.dashboard"
DIST="$PLUGIN_ROOT/dist"
OUTPUT="$DIST/$PACKAGE_NAME"
PACKAGE_MANIFEST="$SCRIPT_DIR/package-manifest.txt"
mkdir -p "$DIST"
TEMP_OUTPUT=$(mktemp "$DIST/.${PACKAGE_NAME}.XXXXXX")

cleanup() {
    rm -f "$TEMP_OUTPUT"
}
trap cleanup EXIT

fail() {
    printf 'error: %s\n' "$*" >&2
    exit 1
}

require_linux_gnu_tar() {
    [ "$(uname -s)" = Linux ] || fail 'canonical releases must be built on the Lenovo Linux build machine'
    tar --version 2>/dev/null | head -n 1 | grep -Fq 'GNU tar' || fail 'GNU tar is required for canonical releases'
    for option in --no-xattrs --no-acls --no-selinux --sort; do
        tar --help 2>/dev/null | grep -Fq -- "$option" || fail "GNU tar lacks required option: $option"
    done
}

[ -f "$PACKAGE_MANIFEST" ] || fail 'package manifest is missing'
bash "$SCRIPT_DIR/checks.sh"
require_linux_gnu_tar

SOURCE_DATE_EPOCH=${SOURCE_DATE_EPOCH:-0}
[[ "$SOURCE_DATE_EPOCH" =~ ^[0-9]+$ ]] || fail 'SOURCE_DATE_EPOCH must be a non-negative integer'

# Archive only the reviewed manifest. GNU tar is explicitly instructed not to
# store xattrs, ACLs, SELinux labels or PAX atime/ctime metadata.
tar --format=posix --sort=name --mtime="@$SOURCE_DATE_EPOCH" \
    --owner=0 --group=0 --numeric-owner \
    --no-xattrs --no-acls --no-selinux \
    --pax-option=delete=atime,delete=ctime \
    --verbatim-files-from --no-recursion \
    -cJf "$TEMP_OUTPUT" -C "$STAGE" -T "$PACKAGE_MANIFEST"
chmod 644 "$TEMP_OUTPUT"
mv -f "$TEMP_OUTPUT" "$OUTPUT"
bash "$SCRIPT_DIR/inspect-package.sh" "$OUTPUT"
printf 'wrote %s\n' "$OUTPUT"
