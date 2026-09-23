<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
ob_start();
require_once $runtimeRoot . '/include/airflow-tile.php';
ob_end_clean();

$defaults = homenas_dashboard_default_config();
$html = homenas_dashboard_render_airflow_tile($defaults);
$css = (string) file_get_contents($runtimeRoot . '/assets/homenas-dashboard-airflow.css');
$js = (string) file_get_contents($runtimeRoot . '/assets/homenas-dashboard-airflow.js');

preg_match_all('/data-group="([^"]+)"/', $html, $groups);
test_assert($groups[1] === ['cpu-cooling', 'case-chassis', 'pumps', 'fan-extension'], 'Airflow group order changed');
preg_match_all('/data-sensor-id="([^"]+)"/', $html, $sensors);
$expected = [];
foreach (homenas_dashboard_airflow_groups() as $ids) {
    array_push($expected, ...$ids);
}
test_assert($sensors[1] === $expected && count($expected) === 11, 'Airflow must show all eleven stable fan IDs in group order');
foreach (homenas_dashboard_default_fan_labels() as $physicalName) {
    test_assert(strpos($html, '>' . homenas_dashboard_html($physicalName) . '<') !== false, 'physical default label missing: ' . $physicalName);
}
test_assert(substr_count($html, 'class="homenas-dashboard-airflow-item"') === 11, 'Airflow must always render all eleven cells');
test_assert(substr_count($html, '<span class="homenas-dashboard-airflow-value">N/A</span>') === 11, 'all Airflow values must initialise as N/A');
test_assert(substr_count($html, 'fa-cog') === 1, 'plugin must render exactly one Settings cog');
test_assert(strpos($html, 'fa-chevron') === false, 'Unraid must render the only Show/Hide chevron');
test_assert(strpos($html, 'homenas-dashboard-airflow.js') !== false, 'Airflow must load its read-only update asset');
test_assert(strpos($css, 'repeat(2, minmax(0, 1fr))') !== false, 'CPU/Pump groups require two equal columns');
test_assert(strpos($css, 'repeat(3, minmax(0, 1fr))') !== false, 'Chassis/Extension groups require three equal columns');
test_assert(strpos($css, '@media (max-width: 480px)') !== false, 'Airflow requires a narrow-screen fallback');
test_assert(strpos($js, "'/plugins/homenas.dashboard/include/temperatures.php'") !== false, 'Airflow must use the existing JSON endpoint');
test_assert(strpos($js, 'setInterval(update, 5000)') !== false, 'Airflow must update every five seconds');
test_assert(strpos($js, 'homenas-dashboard-chevron') === false, 'Airflow must not bind a duplicate chevron');

[$valid, $custom] = homenas_dashboard_validate_config([
    'fan_labels' => ['board.nct.fan1' => 'Front intake', 'board.ext_fan3' => 'Auxiliary'],
]);
test_assert($valid, 'custom fan label fixture must validate');
$customHtml = homenas_dashboard_render_airflow_tile($custom);
test_assert(strpos($customHtml, '>Front intake<') !== false, 'Airflow must use configured NCT label');
test_assert(strpos($customHtml, '>Auxiliary<') !== false, 'Airflow must use configured EXT label');

$escaped = $custom;
$escaped['fan_labels']['board.nct.fan1'] = '<img src=x onerror=alert(1)>';
$escapedHtml = homenas_dashboard_render_airflow_tile($escaped);
test_assert(strpos($escapedHtml, '<img src=x') === false, 'fan label HTML must be escaped');
