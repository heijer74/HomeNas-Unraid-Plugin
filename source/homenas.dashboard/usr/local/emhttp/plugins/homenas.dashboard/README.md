# homenas.dashboard runtime — Phase E

Phase B contains only the configuration and read-only data layer:

- generic, validated persistent settings in
  `/boot/config/plugins/homenas.dashboard/settings.json`;
- a stable sensor catalog independent of `hwmonX` numbering;
- a `sensors -j` reader and dynamic NVMe discovery;
- a JSON endpoint at `include/temperatures.php` for a later dashboard adapter.
- a Settings-page (`HomeNasDashboard.page`) and form action that use the same
  schema validation and atomic writer as the API configuration layer.
- a marker-guarded dashboard adapter and a Temperatures tile which fetches the
  JSON endpoint every five seconds;
- eleven plugin-labelled read-only fan RPM records, a grouped Airflow tile and
  a separate fail-closed Airflow adapter for the exact Unraid 7.3.2 block.

It contains no PWM/fan control, EC/NCT write, kernel-module code, or direct
port I/O.

Both tiles call the existing endpoint every five seconds. The endpoint reads
`sensors -j`, read-only `/sys/class/nvme` identity files and native hwmon fan
RPM/label files; neither tile invokes a sensor command. Native hwmon fan labels
identify EC channels but never become visible display labels. The latter come
only from the validated plugin `fan_labels` configuration, not `sensors.conf`.

The Settings page displays generic defaults when `settings.json` does not
exist, and does not create it until an explicit Save. The form sends Unraid's
native `$var['csrf_token']`; Unraid 7.3.2 `local_prepend.php` validates and
removes it before the standalone action runs. There is no plugin session token.
The page accepts only catalogued label fields and temperature-source IDs, and
never passes invalid input to the configuration writer. A successful Save
redirects back to `/Settings/HomeNasDashboard` with a confirmation notice.

`scripts/dashboard-overlay` only accepts an exact tested custom-tile anchor
pair and a complete, recognised HomeNas marker block. Any unknown layout,
partial marker or duplicate marker refuses to modify `DashStats.page`. The
tile itself performs no sensor command; it fetches `temperatures.php`.

`scripts/airflow-overlay.php` accepts only the exact official Unraid 7.3.2
Airflow block or three byte-defined variants made by the previous legacy
script. It replaces only that block, stores the original and a page-context
hash in its own marker, and refuses an unknown/changed layout or marker. Remove
restores the original bytes only when the complete marker and outside context
still verify. The legacy script and `/boot/config/go` are not changed here.
If an own marker cannot be restored, the remove hook exits with an error so
the plugin package remains installed instead of leaving a broken dashboard
include behind.
