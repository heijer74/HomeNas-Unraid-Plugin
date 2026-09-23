<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

ob_start();
require_once $runtimeRoot . '/include/temperature-tile.php';
ob_end_clean();

$defaults = homenas_dashboard_default_config();
$defaultHtml = homenas_dashboard_render_temperature_tile($defaults);
$css = (string) file_get_contents($runtimeRoot . '/assets/homenas-dashboard-temperature.css');
$js = (string) file_get_contents($runtimeRoot . '/assets/homenas-dashboard-temperature.js');
test_assert(strpos($defaultHtml, 'T_Sensor') !== false, 'generic tile must display generic T_Sensor label');
test_assert(strpos($defaultHtml, 'EXT_Sensor1') !== false, 'generic tile must display generic EXT_Sensor1 label');
test_assert(strpos($defaultHtml, 'Case ΔT') === false, 'disabled Case Delta must not render a Case Delta row');
test_assert(strpos($defaultHtml, 'homenas-dashboard-temperature.js') !== false, 'tile must use the plugin update asset');
test_assert(strpos($defaultHtml, 'homenas-dashboard-temperature.css') !== false, 'tile must load its scoped stylesheet');
test_assert(substr_count($defaultHtml, 'fa-cog') === 1, 'tile must include exactly one Settings cog');
test_assert(strpos($defaultHtml, 'fa-chevron') === false, 'Unraid must provide the only Show/Hide chevron');
test_assert(strpos($defaultHtml, 'homenas-dashboard-chevron') === false, 'tile must not provide its own chevron');
test_assert(strpos($js, 'homenas-dashboard-chevron') === false, 'tile JS must not bind a duplicate chevron');
test_assert(strpos($js, "setInterval(update, 5000)") !== false, 'tile must retain the five-second update interval');
test_assert(strpos($js, "'/plugins/homenas.dashboard/include/temperatures.php'") !== false, 'tile must retain its read-only JSON endpoint');
test_assert(strpos($css, 'grid-template-columns: repeat(3, minmax(0, 1fr))') !== false, 'each sensor group needs three equal columns');
test_assert(strpos($css, '@media (max-width: 480px)') !== false, 'grid needs a narrow-screen fallback');
test_assert(strpos($css, 'overflow-wrap: anywhere') !== false, 'long labels must not overlap');

preg_match_all('/data-group="([^"]+)"/', $defaultHtml, $groupMatches);
test_assert($groupMatches[1] === ['motherboard', 'pcie-storage', 'airflow'], 'tile groups must follow motherboard, PCIe/storage and airflow order');
preg_match_all('/data-sensor-id="([^"]+)"/', $defaultHtml, $sensorMatches);
test_assert($sensorMatches[1] === homenas_dashboard_tile_sensor_ids(), 'tile sensor order must remain the proven three-by-three order');
test_assert(substr_count($defaultHtml, 'class="homenas-dashboard-temperature-group"') === 3, 'tile must render three sensor rows');
foreach ($groupMatches[1] as $index => $groupName) {
    $start = strpos($defaultHtml, 'data-group="' . $groupName . '"');
    $end = $index < 2 ? strpos($defaultHtml, 'data-group="' . $groupMatches[1][$index + 1] . '"') : strpos($defaultHtml, '</td></tr></tbody>');
    test_assert(substr_count(substr($defaultHtml, $start, $end - $start), 'data-sensor-id=') === 3, $groupName . ' must contain exactly three sensors');
}
test_assert(preg_match('/homenas-dashboard-temperature-label[^>]*>[^<]+<\/span><span class="homenas-dashboard-temperature-value">N\/A<\/span>/', $defaultHtml) === 1, 'each cell must put label above its initial N/A value');

[$valid, $overrides] = homenas_dashboard_validate_config(fixture_json('homenas-label-overrides.json'));
test_assert($valid, 'HomeNas label fixture must validate');
$overrideHtml = homenas_dashboard_render_temperature_tile($overrides);
test_assert(strpos($overrideHtml, '>10GbE<') !== false, 'tile must use configured T_Sensor label');
test_assert(strpos($overrideHtml, '>Intake<') !== false, 'tile must use configured EXT_Sensor1 label');
test_assert(strpos($overrideHtml, '>RAM<') !== false, 'tile must use configured EXT_Sensor2 label');
test_assert(strpos($overrideHtml, '>Exhaust<') !== false, 'tile must use configured EXT_Sensor3 label');
test_assert(strpos($overrideHtml, 'Case ΔT') !== false, 'enabled Case Delta must render a Case Delta row');
test_assert(strpos($overrideHtml, 'data-sensor-id="derived.case_delta_t"') > strpos($overrideHtml, 'data-sensor-id="board.ext_sensor3"'), 'Case Delta must follow the airflow row');
test_assert(substr_count($overrideHtml, 'fa-cog') === 1 && strpos($overrideHtml, 'fa-chevron') === false, 'label overrides must not duplicate tile controls');

$snapshot = homenas_dashboard_snapshot(fixture_json('sensors-ext2-missing.json'), $overrides, []);
$missingExt = test_sensor($snapshot, 'board.ext_sensor2');
test_assert(!$missingExt['available'] && $missingExt['value'] === null, 'missing sensor must remain unavailable for tile endpoint data');
test_assert(strpos($defaultHtml, '>N/A<') !== false, 'tile must initialise missing values as N/A');
