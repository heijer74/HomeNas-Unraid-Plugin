# Local Phase E fixtures and tests

The fixtures are captured-shaped `sensors -j` JSON documents. They are never
read from HomeNas by the test runner and contain no live identifiers.

Run the PHP fixture suite when a PHP CLI is available:

```bash
php tests/run.php
node tests/test-temperature-tile-js.js
gjs tests/test-temperature-tile-gjs.js
gjs tests/test-airflow-tile-gjs.js
```

The suite exercises configuration validation, generic defaults, label
overrides, dynamic NVMe controller matching, unavailable values, Case Delta T,
stable JSON record fields, Settings-page rendering/metadata, safe save rejection, and
HTML escaping. `test-dashboard-overlay.sh` validates apply, replacement, exact
horizontal whitespace around anchors, byte-preserving removal, idempotence,
and fail-closed DashStats fixtures. The tests do not invoke
`sensors`, access sysfs, or perform hardware writes.
The JS fixtures check five-second polling, endpoint reuse, live labels,
temperature formatting and `N/A` without a browser or live sensors.
The Airflow fixture also verifies 0 RPM, CPU_OPT and EXT_FAN updates.
The PHP Airflow adapter tests cover all exact supported variants,
apply/idempotence/remove, unknown layouts and byte preservation outside the
own marker. Native hwmon fixture files prove that RPM reads do not depend on
custom labels in Dynamix `sensors.conf`.
On the Lenovo, the installed GJS runtime runs the equivalent JS fixture
without installing Node or another package.

`test-release-build.sh` copies the plugin source to a temporary directory and
on the canonical GNU-tar Linux environment checks a valid release render plus rejected package tampering, missing package,
version mismatch, empty SHA-256 entity and duplicate metadata. It neither
publishes nor installs an artifact.
The PLG-hook check extracts both CDATA blocks, validates concrete package,
hash and runtime values, and rejects XML entities or unresolved placeholders
inside either shell hook. Negative fixtures cover both install and remove,
including an otherwise valid XML PLG and an invalid source template.
The valid build also checks that `package_size_bytes` is a positive JSON
integer equal to the package size. A mocked `stat` returning filesystem text
must stop rendering without creating a manifest.

`test-package-cleanliness.sh` rejects both `._*` and `.DS_Store` fixtures at
staging-check time and again after they have been placed in a `.txz` fixture.
