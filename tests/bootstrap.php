<?php

declare(strict_types=1);

$testRoot = __DIR__;
$runtimeRoot = $testRoot . '/../source/homenas.dashboard/usr/local/emhttp/plugins/homenas.dashboard';

require_once $runtimeRoot . '/include/config-store.php';
require_once $runtimeRoot . '/include/sensor-reader.php';

function fixture_json(string $name): array
{
    $path = __DIR__ . '/fixtures/' . $name;
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid fixture JSON: ' . $name);
    }
    return $decoded;
}

function test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function test_sensor(array $snapshot, string $sensorId): array
{
    foreach ($snapshot['sensors'] as $sensor) {
        if ($sensor['sensor_id'] === $sensorId) {
            return $sensor;
        }
    }
    throw new RuntimeException('Sensor record not found: ' . $sensorId);
}

function fixture_nvme_inventory(): array
{
    return [
        ['controller' => 'nvme0', 'bdf_suffix' => '5d00', 'serial' => 'fixture-serial-a', 'model' => 'Fixture NVMe A'],
        ['controller' => 'nvme1', 'bdf_suffix' => 'ae00', 'serial' => 'fixture-serial-b', 'model' => 'Fixture NVMe B'],
    ];
}
