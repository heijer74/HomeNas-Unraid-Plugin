<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$expectedDefaults = fixture_json('default-settings.json');
test_assert(homenas_dashboard_default_config() === $expectedDefaults, 'generic defaults changed unexpectedly');

$override = fixture_json('homenas-label-overrides.json');
[$valid, $config, $errors] = homenas_dashboard_validate_config($override);
test_assert($valid && $errors === [], 'HomeNas label fixture must be valid');
test_assert($config['labels']['board.t_sensor'] === '10GbE', 'T_Sensor override missing');
test_assert($config['labels']['board.ext_sensor1'] === 'Intake', 'EXT_Sensor1 override missing');
test_assert($config['labels']['board.ext_sensor2'] === 'RAM', 'EXT_Sensor2 override missing');
test_assert($config['labels']['board.ext_sensor3'] === 'Exhaust', 'EXT_Sensor3 override missing');
test_assert($config['case_delta']['enabled'] === true, 'Case Delta enable override missing');
test_assert(count($config['fan_labels']) === 11, 'older settings without fan_labels must gain eleven generic defaults');
test_assert($config['fan_labels']['board.nct.fan1'] === 'CHA_FAN1', 'generic physical fan label missing');
test_assert($config['fan_labels']['board.cpu_opt_fan'] === 'CPU_OPT', 'generic CPU_OPT label missing');

[$valid, $customFans] = homenas_dashboard_validate_config([
    'fan_labels' => ['board.nct.fan1' => 'Front intake', 'board.ext_fan3' => 'Rear extension'],
]);
test_assert($valid, 'custom fan labels must validate');
test_assert($customFans['fan_labels']['board.nct.fan1'] === 'Front intake', 'custom fan label lost');
test_assert($customFans['labels'] === homenas_dashboard_default_config()['labels'], 'fan labels must not change temperature labels');
[$invalid] = homenas_dashboard_validate_config(['fan_labels' => ['unknown.fan' => 'X']]);
test_assert(!$invalid, 'unknown fan ID must be rejected');
[$invalid] = homenas_dashboard_validate_config(['fan_labels' => ['board.nct.fan1' => '']]);
test_assert(!$invalid, 'empty fan label must be rejected');

[$invalid] = homenas_dashboard_validate_config([
    'case_delta' => ['intake_source' => 'not.a.sensor'],
]);
test_assert(!$invalid, 'unknown Case Delta source must be rejected');

[$invalid] = homenas_dashboard_validate_config([
    'case_delta' => [
        'intake_source' => 'board.ext_sensor1',
        'exhaust_source' => 'board.ext_sensor1',
    ],
]);
test_assert(!$invalid, 'identical Case Delta sources must be rejected');

$temporaryDir = sys_get_temp_dir() . '/homenas-dashboard-phase-b-' . bin2hex(random_bytes(6));
try {
    [$written, $writeErrors] = homenas_dashboard_write_config($override, $temporaryDir);
    test_assert($written && $writeErrors === [], 'validated configuration must be written atomically');
    $loaded = homenas_dashboard_load_config($temporaryDir);
    test_assert($loaded['status'] === 'loaded', 'written configuration must load');
    test_assert($loaded['config']['labels']['board.ext_sensor3'] === 'Exhaust', 'loaded configuration lost an override');
} finally {
    $settings = $temporaryDir . '/settings.json';
    if (is_file($settings)) {
        unlink($settings);
    }
    if (is_dir($temporaryDir)) {
        rmdir($temporaryDir);
    }
}
