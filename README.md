# HomeNas Monitoring for Unraid

Publieke broncode voor `homenas.dashboard` v0.1.8. De plugin biedt een
configureerbare Temperatures-tegel en een Airflow-tegel met elf fan-RPMkanalen
voor Unraid 7.3.2. Beide tegels lezen uitsluitend het plugin-JSON-endpoint;
er is geen fanregeling, PWM-write of kernelmodule inbegrepen.

## Inhoud

- `metadata.env`: enige bron voor versie, releasetag, pakketnaam en download-URL's.
- `source/homenas.dashboard/`: de runtimebestanden voor het Unraid-`.txz`-pakket.
- `homenas.dashboard.plg.in`: template voor de installeerbare PLG.
- `homenas.dashboard.plg`: door de release-build gegenereerde publieke PLG.
- `build/package-manifest.txt`: expliciete lijst van toegestane pakketbestanden.
- `build/`: controles, package-build, PLG-render en releaseverificatie.
- `tests/`: lokale fixtures en regressietests.
- `dist/`: lokale buildoutput; het pakket en release-manifest worden door
  `dist/.gitignore` uitgesloten van Git.

## Functionaliteit

De Settings-pagina biedt generieke labels voor bordtemperaturen, NVMe-sensoren
en alle elf fanheaders. Case ΔT is optioneel en gebruikt configureerbare,
verschillende intake- en exhaust-temperatuurbronnen. HomeNas-specifieke labels
zijn niet in de pluginlogica vastgelegd.

De Temperatures-tegel toont Chipset, Motherboard, VRM, T_Sensor, twee dynamisch
gevonden NVMe-sensoren en EXT_Sensor1/2/3. Case ΔT verschijnt indien ingeschakeld.
De Airflow-tegel groepeert CPU_FAN/CPU_OPT, CHA_FAN1/2/3/H_AMP,
W_PUMP+/AIO_PUMP en EXT_FAN1/2/3. Ook 0 RPM blijft zichtbaar; ontbrekende
metingen worden `N/A`. Beide tegels verversen iedere vijf seconden.

Dashboardadapters wijzigen alleen een bekende, gevalideerde Unraid 7.3.2-layout.
Bij onbekende anchors of beschadigde markers stoppen ze zonder gedeeltelijke
patch. De plugin levert per tegel alleen de Settings-cog; Unraid verzorgt de
Show/Hide-chevron en Tile Management. Airflow-labels komen uit de eigen
`settings.json`, niet uit Dynamix `sensors.conf`.

## Hardware support and kernel requirements

De HomeNas Dashboard-plugin maakt zelf geen hardware-sensoren aan. Hij toont
uitsluitend sensoren die Linux/Unraid al via hwmon en lm-sensors beschikbaar
maakt. Ook zonder aangepaste driver blijft de plugin bruikbaar: beschikbare
sensoren worden getoond en ontbrekende metingen verschijnen als unavailable
of `N/A`.

Op de ASUS ROG Maximus XI Hero die voor de ontwikkeling is gebruikt, vereisen
de Fan Extension Card-kanalen `EXT_FAN1`, `EXT_FAN2`, `EXT_FAN3`,
`EXT_Sensor1`, `EXT_Sensor2` en `EXT_Sensor3` momenteel een aangepaste
`asus_ec_sensors`-kernelmodule. Zonder die kernelondersteuning kunnen deze
kanalen ontbreken of als unavailable / `N/A` verschijnen. Dit zegt niets over
automatische ondersteuning van andere ASUS-borden.

| Situatie | Plugin | Aangepaste kernelondersteuning | Resultaat |
| --- | --- | --- | --- |
| Gewone Unraid-machine | Ja | Nee | Alleen reeds beschikbare sensoren |
| Maximus XI Hero met Extension Card, zonder patch | Ja | Nee | EXT-kanalen mogelijk niet beschikbaar |
| HomeNas-ontwikkelopstelling | Ja | Ja | EXT_FAN- en EXT_Sensor-kanalen beschikbaar |

De aangepaste module wordt niet met deze plugin meegeleverd: een kernelmodule
moet bij de specifieke Unraid/Linux-kernelversie passen. Het langetermijndoel
is ondersteuning voor de Fan Extension Card upstream in Linux
`asus_ec_sensors` op te nemen.

## Canonieke v0.1.8-releasebuild

Bouw een release uitsluitend op de Lenovo met Ubuntu/Linux en GNU tar. De
build weigert AppleDouble-bestanden, `.DS_Store`, resource forks en extended
attributes. macOS is geen ondersteunde release-buildomgeving.

Voer vanuit de root van deze publieke repo op de Lenovo uit:

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

`build/build-release.sh` controleert eerst de staging tree tegen het manifest,
bouwt daarna een timestamp-genormaliseerde `.txz`, inspecteert het archief,
berekent de hashes en rendert de PLG. De publieke root-PLG en
`dist/homenas.dashboard.plg` worden uit dezelfde template gegenereerd en
moeten byte-identiek zijn. `build/verify-release.sh` controleert ook de
pakketgrootte, SHA-256, URL's en PLG-XML.

De v0.1.8-build produceert `dist/homenas.dashboard-0.1.8-x86_64-1.txz`,
`dist/homenas.dashboard-release-manifest.json` en de twee identieke PLG's.
`homenas.dashboard.plg` is het publieke installatiebestand; de package-URL
verwijst naar de nog te publiceren tag `homenas.dashboard-v0.1.8` in
[`heijer74/HomeNas-Unraid-Plugin`](https://github.com/heijer74/HomeNas-Unraid-Plugin).
Een lokale build is geen publicatie of live installatie. Publiceer of installeer
pas na afzonderlijke review van de artefacten.

## Runtime en configuratie

Unraid plaatst de runtime onder
`/usr/local/emhttp/plugins/homenas.dashboard/`. De persistente configuratie
staat onder `/boot/config/plugins/homenas.dashboard/settings.json`. Bij een
ontbrekend bestand toont Settings de generieke defaults; de configuratie wordt
pas na expliciet opslaan aangemaakt. Invoer wordt gevalideerd en atomair
opgeslagen. Uninstall bewaart de configuratie.

De sensorreader gebruikt read-only `sensors -j` en waar nodig read-only
hwmon-/NVMe-identiteit. De plugin doet geen SMART-wakeup, directe EC-/SIO-
of NCT-registertoegang, sysfs-writes of fan-control. De Settings-save gebruikt
Unraids native CSRF-preflight; er is geen eigen PHP-session-token.

De oude losse HomeNas-dashboard- en Airflow-scripts maken geen deel uit van
deze publieke repo. Hun eventuele verwijdering van een bestaande Unraid-host
is een afzonderlijke migratiestap, niet onderdeel van de pakketbuild.
