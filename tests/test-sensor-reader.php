<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

[$valid, $config] = homenas_dashboard_validate_config(fixture_json('homenas-label-overrides.json'));
test_assert($valid, 'fixture config must validate');

$snapshot = homenas_dashboard_snapshot(
    fixture_json('sensors-all-present.json'),
    $config,
    fixture_nvme_inventory()
);

$nvme1 = test_sensor($snapshot, 'storage.nvme.slot1');
$nvme2 = test_sensor($snapshot, 'storage.nvme.slot2');
test_assert($nvme1['available'] && $nvme1['value'] === 36.25, 'first NVMe must match dynamic BDF 5d00');
test_assert($nvme2['available'] && $nvme2['value'] === 39.75, 'second NVMe must match dynamic BDF ae00');
test_assert($nvme1['source'] === 'storage.nvme.serial.' . substr(hash('sha256', 'fixture-serial-a'), 0, 16), 'NVMe source ID must be serial-stable');
test_assert(
    homenas_dashboard_find_parent_bdf('/sys/devices/pci0000:00/0000:00:1c.0/0000:5d:00.0/nvme/nvme0') === '5d00',
    'NVMe BDF must be found by walking the read-only sysfs parent path'
);
test_assert(test_sensor($snapshot, 'board.nct.fan1')['value'] === 795.0, 'nested NCT fan RPM must be read');
test_assert(test_sensor($snapshot, 'board.ext_fan3')['value'] === 830.0, 'EXT_FAN RPM must be catalogued when present');
test_assert(test_sensor($snapshot, 'board.cpu_opt_fan')['value'] === 920.0, 'CPU_OPT must have a stable endpoint record');
test_assert(test_sensor($snapshot, 'board.cpu_opt_fan')['display_label'] === 'CPU_OPT', 'CPU_OPT must use plugin-owned default label');

$allFanIds = array_keys(homenas_dashboard_default_fan_labels());
test_assert(count($allFanIds) === 11, 'the endpoint must define eleven physical fan IDs');
foreach ($allFanIds as $sensorId) {
    test_assert(test_sensor($snapshot, $sensorId)['available'], 'all-present fixture fan missing: ' . $sensorId);
}

$zeroSensors = fixture_json('sensors-all-present.json');
$zeroSensors['nct6798-isa-0290']['fan1']['fan1_input'] = 0;
$zeroSensors['asus-ec-isa-0000']['EXT_FAN1']['fan1_input'] = 0;
$zeroSnapshot = homenas_dashboard_snapshot($zeroSensors, $config, []);
test_assert(test_sensor($zeroSnapshot, 'board.nct.fan1')['available'] && test_sensor($zeroSnapshot, 'board.nct.fan1')['value'] === 0.0, 'NCT 0 RPM must stay available');
test_assert(test_sensor($zeroSnapshot, 'board.ext_fan1')['available'] && test_sensor($zeroSnapshot, 'board.ext_fan1')['value'] === 0.0, 'EXT 0 RPM must stay available');

$hwmonRoot = sys_get_temp_dir() . '/homenas-hwmon-fans-' . bin2hex(random_bytes(6));
mkdir($hwmonRoot, 0700);
mkdir($hwmonRoot . '/hwmon0');
mkdir($hwmonRoot . '/hwmon1');
$hwmonFiles = [
    'hwmon0/name' => "nct6798\n",
    'hwmon0/fan1_input' => "0\n",
    'hwmon0/fan2_input' => "1050\n",
    'hwmon1/name' => "asus_ec_sensors\n",
    'hwmon1/fan1_label' => "CPU_Opt\n",
    'hwmon1/fan1_input' => "930\n",
    'hwmon1/fan3_label' => "EXT_FAN1\n",
    'hwmon1/fan3_input' => "0\n",
    'hwmon1/fan4_label' => "EXT_FAN2\n",
    'hwmon1/fan4_input' => "840\n",
];
try {
    foreach ($hwmonFiles as $path => $contents) {
        file_put_contents($hwmonRoot . '/' . $path, $contents);
    }
    $nativeFans = homenas_dashboard_hwmon_fan_inputs($hwmonRoot);
    test_assert($nativeFans['board.nct.fan1']['value'] === '0', 'native NCT sysfs 0 RPM must be preserved');
    test_assert($nativeFans['board.cpu_opt_fan']['value'] === '930', 'native CPU_OPT label must be recognised');
    test_assert($nativeFans['board.ext_fan1']['value'] === '0', 'native EXT_FAN1 sysfs 0 RPM must be preserved');
    $renamedSensors = fixture_json('sensors-all-present.json');
    $renamedSensors['asus-ec-isa-0000']['FAN8'] = $renamedSensors['asus-ec-isa-0000']['CPU_Opt'];
    unset($renamedSensors['asus-ec-isa-0000']['CPU_Opt']);
    $renamedSensors['asus-ec-isa-0000']['Custom fan label'] = $renamedSensors['asus-ec-isa-0000']['EXT_FAN1'];
    unset($renamedSensors['asus-ec-isa-0000']['EXT_FAN1']);
    $nativeSnapshot = homenas_dashboard_snapshot($renamedSensors, $config, [], $nativeFans);
    test_assert(test_sensor($nativeSnapshot, 'board.cpu_opt_fan')['value'] === 930.0, 'native CPU_OPT reading must survive sensors.conf label changes');
    test_assert(test_sensor($nativeSnapshot, 'board.ext_fan1')['available'] && test_sensor($nativeSnapshot, 'board.ext_fan1')['value'] === 0.0, 'native EXT_FAN1 reading must survive sensors.conf label changes');
    test_assert(test_sensor($nativeSnapshot, 'board.ext_fan1')['source'] === 'hwmon sysfs', 'native RPM source must be identified');
} finally {
    foreach (array_keys($hwmonFiles) as $path) {
        if (is_file($hwmonRoot . '/' . $path)) {
            unlink($hwmonRoot . '/' . $path);
        }
    }
    rmdir($hwmonRoot . '/hwmon0');
    rmdir($hwmonRoot . '/hwmon1');
    rmdir($hwmonRoot);
}

$missingExt = homenas_dashboard_snapshot(fixture_json('sensors-ext2-missing.json'), $config, []);
$ext2 = test_sensor($missingExt, 'board.ext_sensor2');
test_assert(!$ext2['available'] && $ext2['value'] === null, 'missing EXT sensor must be unavailable, not zero');

$noNvme = homenas_dashboard_snapshot(fixture_json('sensors-no-nvme.json'), $config, []);
test_assert(!test_sensor($noNvme, 'storage.nvme.slot1')['available'], 'missing NVMe slot 1 must be unavailable');
test_assert(!test_sensor($noNvme, 'storage.nvme.slot2')['available'], 'missing NVMe slot 2 must be unavailable');

$unknown = homenas_dashboard_snapshot(fixture_json('sensors-unknown-extra.json'), $config, []);
foreach ($unknown['sensors'] as $sensor) {
    test_assert(strpos($sensor['sensor_id'], 'mystery') === false, 'unknown sensor must not create a catalog record');
}
