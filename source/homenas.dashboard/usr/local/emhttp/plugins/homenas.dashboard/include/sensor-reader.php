<?php

require_once __DIR__ . '/sensor-catalog.php';

function homenas_dashboard_unavailable_sensor(string $sensorId, array $definition, ?string $label = null): array
{
    return [
        'sensor_id' => $sensorId,
        'display_label' => $label ?? $definition['label'],
        'value' => null,
        'unit' => $definition['unit'],
        'available' => false,
        'source' => 'sensors -j',
        'chip' => null,
    ];
}

function homenas_dashboard_sensor_record(string $sensorId, array $definition, $value, string $chip, ?string $label = null, ?string $source = null): array
{
    if (!is_numeric($value)) {
        return homenas_dashboard_unavailable_sensor($sensorId, $definition, $label);
    }

    return [
        'sensor_id' => $sensorId,
        'display_label' => $label ?? $definition['label'],
        'value' => (float) $value,
        'unit' => $definition['unit'],
        'available' => true,
        'source' => $source ?? 'sensors -j',
        'chip' => $chip,
    ];
}

function homenas_dashboard_feature_input(array $feature)
{
    foreach ($feature as $key => $value) {
        if (preg_match('/_input$/', (string) $key) === 1 && is_numeric($value)) {
            return $value;
        }
    }
    return null;
}

function homenas_dashboard_chip_matches(string $chip, string $family): bool
{
    $chip = strtolower($chip);
    if ($family === 'asus_ec') {
        return strpos($chip, 'asus-ec') !== false || strpos($chip, 'asusec') !== false;
    }
    if ($family === 'coretemp') {
        return strpos($chip, 'coretemp') !== false;
    }
    if ($family === 'nct') {
        return strpos($chip, 'nct67') !== false;
    }
    return $family === 'nvme' && strpos($chip, 'nvme-pci-') === 0;
}

function homenas_dashboard_find_labelled_value(array $sensors, array $definition): ?array
{
    foreach ($sensors as $chip => $features) {
        if (!is_array($features) || !homenas_dashboard_chip_matches((string) $chip, $definition['family'])) {
            continue;
        }
        foreach ($definition['features'] as $featureName) {
            if (!isset($features[$featureName]) || !is_array($features[$featureName])) {
                continue;
            }
            $value = homenas_dashboard_feature_input($features[$featureName]);
            if ($value !== null) {
                return ['chip' => (string) $chip, 'value' => $value];
            }
        }
    }
    return null;
}

function homenas_dashboard_find_nct_value(array $sensors, string $feature): ?array
{
    foreach ($sensors as $chip => $features) {
        if (!is_array($features) || !homenas_dashboard_chip_matches((string) $chip, 'nct')) {
            continue;
        }
        foreach ($features as $featureGroup) {
            if (!is_array($featureGroup) || !isset($featureGroup[$feature])) {
                continue;
            }
            if (is_numeric($featureGroup[$feature])) {
                return ['chip' => (string) $chip, 'value' => $featureGroup[$feature]];
            }
        }
    }
    return null;
}

// Native hwmon labels are independent of Dynamix/libsensors sensors.conf labels.
// This only reads existing sysfs files; it never probes registers or writes hwmon.
function homenas_dashboard_hwmon_fan_inputs(string $hwmonRoot = '/sys/class/hwmon'): array
{
    $inputs = [];
    $ecLabels = [
        'CPU_OPT' => 'board.cpu_opt_fan',
        'EXT_FAN1' => 'board.ext_fan1',
        'EXT_FAN2' => 'board.ext_fan2',
        'EXT_FAN3' => 'board.ext_fan3',
    ];
    foreach (glob(rtrim($hwmonRoot, '/') . '/hwmon*') ?: [] as $path) {
        $nameFile = $path . '/name';
        if (!is_readable($nameFile)) {
            continue;
        }
        $name = trim((string) file_get_contents($nameFile));
        if (preg_match('/^nct6798(?:d)?$/i', $name) === 1) {
            foreach (range(1, 7) as $fan) {
                $inputFile = $path . '/fan' . $fan . '_input';
                $value = is_readable($inputFile) ? trim((string) file_get_contents($inputFile)) : null;
                if ($value !== null && ctype_digit($value)) {
                    $inputs['board.nct.fan' . $fan] = ['value' => $value, 'chip' => $name];
                }
            }
        } elseif (in_array($name, ['asus_ec_sensors', 'asus-ec-sensors'], true)) {
            foreach (glob($path . '/fan*_label') ?: [] as $labelFile) {
                if (preg_match('/\/fan([0-9]+)_label$/', $labelFile, $match) !== 1 || !is_readable($labelFile)) {
                    continue;
                }
                $nativeLabel = strtoupper(trim((string) file_get_contents($labelFile)));
                $sensorId = $ecLabels[$nativeLabel] ?? null;
                $inputFile = $path . '/fan' . $match[1] . '_input';
                $value = is_readable($inputFile) ? trim((string) file_get_contents($inputFile)) : null;
                if ($sensorId !== null && $value !== null && ctype_digit($value)) {
                    $inputs[$sensorId] = ['value' => $value, 'chip' => $name];
                }
            }
        }
    }
    return $inputs;
}

function homenas_dashboard_bdf_suffix(string $bdf): ?string
{
    if (preg_match('/^(?:[0-9a-fA-F]{4}:)?([0-9a-fA-F]{2}):([0-9a-fA-F]{2})\.[0-7]$/', $bdf, $match) !== 1) {
        return null;
    }
    return strtolower($match[1] . $match[2]);
}

function homenas_dashboard_find_parent_bdf(string $path): ?string
{
    $current = $path;
    while ($current !== '/' && $current !== '.') {
        $candidate = homenas_dashboard_bdf_suffix(basename($current));
        if ($candidate !== null) {
            return $candidate;
        }
        $parent = dirname($current);
        if ($parent === $current) {
            break;
        }
        $current = $parent;
    }
    return null;
}

function homenas_dashboard_nvme_inventory(string $sysfsRoot = '/sys'): array
{
    $inventory = [];
    foreach (glob(rtrim($sysfsRoot, '/') . '/class/nvme/nvme*') ?: [] as $path) {
        $controller = basename($path);
        if (preg_match('/^nvme[0-9]+$/', $controller) !== 1) {
            continue;
        }
        $device = realpath($path . '/device');
        $serial = is_readable($path . '/serial') ? trim((string) file_get_contents($path . '/serial')) : null;
        $model = is_readable($path . '/model') ? trim((string) file_get_contents($path . '/model')) : null;
        $inventory[] = [
            'controller' => $controller,
            'bdf_suffix' => is_string($device) ? homenas_dashboard_find_parent_bdf($device) : null,
            'serial' => $serial === '' ? null : $serial,
            'model' => $model === '' ? null : $model,
        ];
    }
    usort($inventory, static function (array $a, array $b): int {
        return strnatcmp($a['controller'], $b['controller']);
    });
    return $inventory;
}

function homenas_dashboard_nvme_candidates(array $sensors, array $inventory): array
{
    $bySuffix = [];
    foreach ($inventory as $item) {
        if (!empty($item['bdf_suffix'])) {
            $bySuffix[strtolower($item['bdf_suffix'])] = $item;
        }
    }

    $candidates = [];
    foreach ($sensors as $chip => $features) {
        if (!is_array($features) || !homenas_dashboard_chip_matches((string) $chip, 'nvme')) {
            continue;
        }
        if (!isset($features['Composite']) || !is_array($features['Composite'])) {
            continue;
        }
        $value = homenas_dashboard_feature_input($features['Composite']);
        if ($value === null) {
            continue;
        }

        $suffix = strtolower(substr((string) $chip, strlen('nvme-pci-')));
        $candidate = $bySuffix[$suffix] ?? [
            'controller' => null,
            'bdf_suffix' => $suffix,
            'serial' => null,
            'model' => null,
        ];
        $candidate['chip'] = (string) $chip;
        $candidate['value'] = $value;
        $candidate['source_id'] = !empty($candidate['serial'])
            ? 'storage.nvme.serial.' . substr(hash('sha256', (string) $candidate['serial']), 0, 16)
            : 'storage.nvme.chip.' . preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $chip));
        $candidates[] = $candidate;
    }

    usort($candidates, static function (array $a, array $b): int {
        return strnatcmp($a['controller'] ?? $a['chip'], $b['controller'] ?? $b['chip']);
    });
    return $candidates;
}

function homenas_dashboard_take_nvme_slot(array &$candidates, array $slotConfig): ?array
{
    $matchIndex = null;
    if (is_string($slotConfig['serial'] ?? null) && $slotConfig['serial'] !== '') {
        foreach ($candidates as $index => $candidate) {
            if ($candidate['serial'] === $slotConfig['serial']
                && (($slotConfig['model'] ?? null) === null || $candidate['model'] === $slotConfig['model'])) {
                $matchIndex = $index;
                break;
            }
        }
    } elseif (count($candidates) > 0) {
        $matchIndex = 0;
    }

    if ($matchIndex === null) {
        return null;
    }
    $candidate = $candidates[$matchIndex];
    array_splice($candidates, $matchIndex, 1);
    return $candidate;
}

function homenas_dashboard_label_for(array $config, string $sensorId, array $definition): string
{
    if (array_key_exists($sensorId, $config['fan_labels'] ?? [])) {
        return $config['fan_labels'][$sensorId];
    }
    return $config['labels'][$sensorId] ?? $definition['label'];
}

function homenas_dashboard_case_delta(array $records, array $config): array
{
    $case = $config['case_delta'];
    $intake = $records[$case['intake_source']] ?? null;
    $exhaust = $records[$case['exhaust_source']] ?? null;
    $available = $case['enabled']
        && is_array($intake) && is_array($exhaust)
        && $intake['available'] && $exhaust['available'];

    return [
        'sensor_id' => 'derived.case_delta_t',
        'display_label' => 'Case ΔT',
        'value' => $available ? round($exhaust['value'] - $intake['value'], 1) : null,
        'unit' => 'C',
        'available' => $available,
        'source' => 'derived',
        'chip' => null,
        'intake_sensor_id' => $case['intake_source'],
        'exhaust_sensor_id' => $case['exhaust_source'],
    ];
}

function homenas_dashboard_snapshot(array $sensors, array $config, array $nvmeInventory = [], array $hwmonFanInputs = []): array
{
    $catalog = homenas_dashboard_sensor_catalog();
    $records = [];

    foreach (homenas_dashboard_endpoint_sensor_ids() as $sensorId) {
        $definition = $catalog[$sensorId];
        if ($definition['family'] === 'nvme' || $definition['family'] === 'nct') {
            continue;
        }
        $label = homenas_dashboard_label_for($config, $sensorId, $definition);
        $match = homenas_dashboard_find_labelled_value($sensors, $definition);
        $records[$sensorId] = $match === null
            ? homenas_dashboard_unavailable_sensor($sensorId, $definition, $label)
            : homenas_dashboard_sensor_record($sensorId, $definition, $match['value'], $match['chip'], $label);
    }

    foreach (range(1, 7) as $fan) {
        $sensorId = 'board.nct.fan' . $fan;
        $definition = $catalog[$sensorId];
        $match = homenas_dashboard_find_nct_value($sensors, $definition['feature']);
        $label = homenas_dashboard_label_for($config, $sensorId, $definition);
        $records[$sensorId] = $match === null
            ? homenas_dashboard_unavailable_sensor($sensorId, $definition, $label)
            : homenas_dashboard_sensor_record($sensorId, $definition, $match['value'], $match['chip'], $label);
    }

    $candidates = homenas_dashboard_nvme_candidates($sensors, $nvmeInventory);
    foreach (['storage.nvme.slot1', 'storage.nvme.slot2'] as $slot) {
        $definition = $catalog[$slot];
        $label = homenas_dashboard_label_for($config, $slot, $definition);
        $candidate = homenas_dashboard_take_nvme_slot($candidates, $config['nvme_slots'][$slot]);
        $records[$slot] = $candidate === null
            ? homenas_dashboard_unavailable_sensor($slot, $definition, $label)
            : homenas_dashboard_sensor_record($slot, $definition, $candidate['value'], $candidate['chip'], $label, $candidate['source_id']);
    }

    // Prefer native, read-only hwmon RPM files for fans. The visible labels
    // always come from settings.json, never from sensors.conf or sysfs labels.
    foreach ($hwmonFanInputs as $sensorId => $input) {
        if (!array_key_exists($sensorId, $config['fan_labels'])
            || !is_array($input) || !isset($input['value'], $input['chip'])) {
            continue;
        }
        $records[$sensorId] = homenas_dashboard_sensor_record(
            $sensorId,
            $catalog[$sensorId],
            $input['value'],
            (string) $input['chip'],
            $config['fan_labels'][$sensorId],
            'hwmon sysfs'
        );
    }

    foreach ($sensors as $chip => $features) {
        if (!is_array($features) || !homenas_dashboard_chip_matches((string) $chip, 'coretemp')) {
            continue;
        }
        foreach ($features as $featureName => $feature) {
            if (!is_array($feature) || preg_match('/^Core ([0-9]+)$/', (string) $featureName, $matches) !== 1) {
                continue;
            }
            $value = homenas_dashboard_feature_input($feature);
            if ($value === null) {
                continue;
            }
            $sensorId = 'cpu.core.' . $matches[1];
            $records[$sensorId] = homenas_dashboard_sensor_record(
                $sensorId,
                ['label' => 'CPU Core ' . $matches[1], 'unit' => 'C'],
                $value,
                (string) $chip
            );
        }
    }

    ksort($records);
    return [
        'schema_version' => 1,
        'sensors' => array_values($records),
        'case_delta_t' => homenas_dashboard_case_delta($records, $config),
    ];
}

function homenas_dashboard_read_live_sensors(): array
{
    foreach (['/usr/sbin/sensors', '/usr/bin/sensors', '/sbin/sensors'] as $binary) {
        if (!is_executable($binary)) {
            continue;
        }
        $output = [];
        $exitCode = 1;
        exec(escapeshellarg($binary) . ' -j 2>/dev/null', $output, $exitCode);
        if ($exitCode !== 0) {
            continue;
        }
        $decoded = json_decode(implode("\n", $output), true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

function homenas_dashboard_live_snapshot(array $config): array
{
    return homenas_dashboard_snapshot(
        homenas_dashboard_read_live_sensors(),
        $config,
        homenas_dashboard_nvme_inventory(),
        homenas_dashboard_hwmon_fan_inputs()
    );
}
