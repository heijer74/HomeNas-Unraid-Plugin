# HomeNas Monitoring for Unraid

HomeNas Monitoring is a monitoring-only Unraid plugin with configurable
Temperatures and Airflow dashboard tiles. It displays values already exposed
by Linux/Unraid; it does not add sensor drivers or control fans.

## Installation

The first publicly live-tested release is **v0.1.9**. Open the Unraid WebGUI,
go to **Plugins → Install Plugin**, paste this public `.plg` URL, and install:

```text
https://github.com/heijer74/HomeNas-Unraid-Plugin/releases/download/homenas.dashboard-v0.1.9/homenas.dashboard.plg
```

Unraid downloads the `.txz` package referenced by the PLG and verifies its
package hash. After installation, open **Settings → User Utilities → HomeNas
Monitoring** to configure sensor and fan labels and, optionally, Case ΔT.
The Temperatures and Airflow tiles then appear on the Dashboard. The plugin
does not require the old standalone dashboard-patch scripts.

## Features

- **Temperatures:** chipset, motherboard, VRM, T_Sensor, two dynamically
  discovered NVMe sensors and EXT_Sensor1/2/3 when available. Display labels
  are configurable.
- **Case ΔT:** optional exhaust temperature minus intake temperature, using
  two configurable temperature sources. It shows `N/A` when either source is
  unavailable.
- **Airflow:** stable slots for all eleven headers: CPU_FAN, CPU_OPT,
  CHA_FAN1/2/3, H_AMP, W_PUMP+, AIO_PUMP and EXT_FAN1/2/3. A measured 0 RPM
  stays visible as 0 RPM; a missing sensor shows `N/A`. Fan labels are
  configurable independently of Dynamix `sensors.conf`.
- Both tiles refresh every five seconds and retain Unraid tile controls and
  Tile Management compatibility. Settings survive a reboot.

## Hardware support and kernel requirements

The plugin **does not create hwmon sensors**. It only displays measurements
that Linux/Unraid already exposes through hwmon or lm-sensors. Missing
measurements are unavailable or `N/A`; available sensors continue to work.

On the ASUS ROG Maximus XI Hero (Z390) used for development, the Fan Extension
Card channels `EXT_FAN1`, `EXT_FAN2`, `EXT_FAN3`, `EXT_Sensor1`, `EXT_Sensor2`
and `EXT_Sensor3` currently require a separate, customized `asus_ec_sensors`
kernel module. That module is **not included** in this plugin because kernel
modules must match the specific Unraid/Linux kernel version. Without it, the
EXT channels may be unavailable, but the plugin remains useful for sensors
that the kernel does expose. Support for other ASUS boards is not implied.
The longer-term goal is to upstream Fan Extension Card support into Linux
`asus_ec_sensors`.

| System | Plugin | Custom kernel support | Expected result |
| --- | --- | --- | --- |
| Ordinary Unraid system | Yes | No | Displays sensors already exposed by Linux |
| Maximus XI Hero with Extension Card, without the patch | Yes | No | EXT channels may be unavailable |
| HomeNas development setup | Yes | Yes | EXT_FAN and EXT_Sensor channels available |

## Tested on

- Unraid **7.3.2**, Linux **6.18.38-Unraid**.
- ASUS ROG Maximus XI Hero (Z390) development/test board.
- Plugin **v0.1.9**: public PLG download, Unraid package download and hash
  verification, installation/upgrade, configuration persistence and reboot
  were tested. Both dashboard adapters reported `active` after reboot.

Other Unraid versions have **not** been live-verified. The dashboard adapters
are fail-closed: if an expected WebGUI layout is not recognized, they leave
that dashboard page unchanged rather than applying a partial patch.

## Safety and persistence

This plugin is read-only monitoring software: it performs no fan control,
PWM writes or EC/NCT control-register writes. Unavailable sensors show `N/A`
rather than a fabricated temperature or RPM value. The plugin reads sensor
data through its own JSON endpoint; dashboard tiles do not run `sensors`
themselves.

Settings are stored persistently on the Unraid flash drive at
`/boot/config/plugins/homenas.dashboard/settings.json`. On the tested setup,
the tiles and settings remained active after reboot. The old standalone
dashboard-patch scripts are not needed for plugin-managed tiles.

## Version status

- **v0.1.9:** first public release live-tested for installation and reboot
  persistence on the system listed above.
- **v0.1.8: NO-GO — do not install.** Its PLG used XML entities inside CDATA
  in the install/remove hooks. Those entities remained literal shell text,
  causing installation to fail after the package download. v0.1.9 renders
  concrete, validated hook values and checks both hooks during release builds.

## Repository and release builds

`metadata.env` is the version and release-URL source. `source/homenas.dashboard/`
contains the packaged runtime, `homenas.dashboard.plg.in` is the PLG template,
and the root `homenas.dashboard.plg` is the generated public installation
file. `build/package-manifest.txt` lists every allowed package member;
`tests/` contains fixtures and regression tests. Local packages and manifests
under `dist/` are excluded from Git.

Release packages must be built on Linux with GNU tar, not on macOS, to avoid
AppleDouble files, resource forks and extended-attribute metadata. From a
clean checkout on a Linux build machine with PHP, GJS, Python 3 and xmllint:

```bash
bash -n build/*.sh tests/*.sh
php tests/run.php
gjs tests/test-temperature-tile-gjs.js
gjs tests/test-airflow-tile-gjs.js
bash tests/test-dashboard-overlay.sh
bash tests/test-package-cleanliness.sh
bash tests/test-release-build.sh
bash build/build-release.sh
bash build/inspect-package.sh "dist/$(sed -n 's/^PACKAGE_NAME=//p' metadata.env)"
```

The build validates the staging tree, creates and inspects a normalized `.txz`,
checks its hashes and size, and renders byte-identical root and `dist/` PLGs.
It also checks that both shell hooks contain concrete values and no XML
entities inside CDATA. The package and PLG are published together under the
release tag specified in `metadata.env`.
