#!/usr/bin/env python3
"""Validate the rendered Unraid PLG shell hooks without executing them."""

import re
import subprocess
import sys
import xml.etree.ElementTree as ET
from pathlib import Path


def fail(message: str) -> None:
    raise SystemExit(f"invalid PLG hooks: {message}")


if len(sys.argv) != 5:
    fail("expected PLG path, plugin name, package filename and SHA-256")

plg_path, plugin_name, package_name, sha256 = sys.argv[1:]
if not re.fullmatch(r"[a-z0-9][a-z0-9.-]*", plugin_name):
    fail("invalid plugin name")
if not re.fullmatch(r"[a-z0-9][a-z0-9._-]*\.txz", package_name):
    fail("invalid package filename")
if not re.fullmatch(r"[0-9a-f]{64}", sha256):
    fail("invalid SHA-256")

try:
    raw_plg = Path(plg_path).read_text(encoding="utf-8")
    root = ET.fromstring(raw_plg)
except (OSError, UnicodeError, ET.ParseError) as error:
    fail(f"cannot parse PLG: {error}")

if root.tag != "PLUGIN":
    fail("unexpected XML root")
for xml_field in ("<URL>&package_url;</URL>", "<MD5>&md5;</MD5>",
                  "<SHA256>&sha256;</SHA256>"):
    if xml_field not in raw_plg:
        fail(f"missing XML entity outside CDATA: {xml_field}")

hooks = {}
for file_element in root.findall("FILE"):
    inline = file_element.find("INLINE")
    if inline is None:
        continue
    if file_element.get("Run") != "/bin/bash":
        fail("unexpected INLINE runner")
    method = file_element.get("Method")
    role = "remove" if method == "remove" else "install" if method is None else None
    if role is None or role in hooks:
        fail("unknown or duplicate INLINE hook")
    hooks[role] = inline.text or ""

if set(hooks) != {"install", "remove"}:
    fail("expected exactly one install and one remove hook")

for role, script in hooks.items():
    if re.search(r"&[A-Za-z_][A-Za-z0-9_.-]*;", script):
        fail(f"XML entity remains inside {role} CDATA")
    if re.search(r"@[A-Z_]+@", script):
        fail(f"build placeholder remains inside {role} CDATA")
    result = subprocess.run(["bash", "-n"], input=script, text=True,
                            capture_output=True, check=False)
    if result.returncode:
        fail(f"{role} hook has invalid shell syntax: {result.stderr.strip()}")


def require_line(role: str, line: str) -> None:
    if sum(actual.strip() == line for actual in hooks[role].splitlines()) != 1:
        fail(f"{role} hook lacks exactly one expected line: {line}")


package_basename = package_name[:-4]
require_line("install", f'package="/boot/config/plugins/{plugin_name}/{package_name}"')
require_line("install", f'expected_sha256="{sha256}"')
require_line("install", 'upgradepkg --install-new --reinstall "$package"')
require_line("install", f"/usr/local/emhttp/plugins/{plugin_name}/scripts/install")
require_line("remove", f'runtime="/usr/local/emhttp/plugins/{plugin_name}"')
require_line("remove", '"$runtime/scripts/remove"')
require_line("remove", f'removepkg "{package_basename}"')

print("PLG install/remove hooks validated")
