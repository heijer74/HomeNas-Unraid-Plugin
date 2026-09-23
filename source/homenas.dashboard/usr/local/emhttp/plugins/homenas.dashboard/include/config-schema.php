<?php
/*
 * Phase B configuration schema. No runtime hardware access.
 */

function homenas_dashboard_default_config(): array
{
    return [
        'schema_version' => 1,
        'labels' => [
            'board.chipset' => 'Chipset',
            'board.motherboard' => 'Motherboard',
            'board.vrm' => 'VRM',
            'board.t_sensor' => 'T_Sensor',
            'storage.nvme.slot1' => 'NVMe 1',
            'storage.nvme.slot2' => 'NVMe 2',
            'board.ext_sensor1' => 'EXT_Sensor1',
            'board.ext_sensor2' => 'EXT_Sensor2',
            'board.ext_sensor3' => 'EXT_Sensor3',
        ],
        'fan_labels' => homenas_dashboard_default_fan_labels(),
        'case_delta' => [
            'enabled' => false,
            'intake_source' => 'board.ext_sensor1',
            'exhaust_source' => 'board.ext_sensor3',
        ],
        'nvme_slots' => [
            'storage.nvme.slot1' => ['serial' => null, 'model' => null],
            'storage.nvme.slot2' => ['serial' => null, 'model' => null],
        ],
    ];
}

function homenas_dashboard_default_fan_labels(): array
{
    return [
        'board.nct.fan1' => 'CHA_FAN1',
        'board.nct.fan2' => 'CPU_FAN',
        'board.nct.fan3' => 'CHA_FAN2',
        'board.nct.fan4' => 'CHA_FAN3',
        'board.nct.fan5' => 'H_AMP',
        'board.nct.fan6' => 'W_PUMP+',
        'board.nct.fan7' => 'AIO_PUMP',
        'board.cpu_opt_fan' => 'CPU_OPT',
        'board.ext_fan1' => 'EXT_FAN1',
        'board.ext_fan2' => 'EXT_FAN2',
        'board.ext_fan3' => 'EXT_FAN3',
    ];
}

function homenas_dashboard_temperature_sensor_ids(): array
{
    return [
        'board.chipset',
        'board.motherboard',
        'board.vrm',
        'board.t_sensor',
        'board.ext_sensor1',
        'board.ext_sensor2',
        'board.ext_sensor3',
        'cpu.package',
        'storage.nvme.slot1',
        'storage.nvme.slot2',
    ];
}

function homenas_dashboard_label_sensor_ids(): array
{
    return array_keys(homenas_dashboard_default_config()['labels']);
}

function homenas_dashboard_valid_label($value): bool
{
    return is_string($value)
        && $value !== ''
        && strlen($value) <= 64
        && preg_match('/[\r\n\x00]/', $value) !== 1;
}

function homenas_dashboard_validate_config($candidate): array
{
    $defaults = homenas_dashboard_default_config();
    $errors = [];

    if (!is_array($candidate)) {
        return [false, $defaults, ['configuration must be an object']];
    }

    $config = $defaults;
    if (isset($candidate['schema_version']) && $candidate['schema_version'] !== 1) {
        $errors[] = 'unsupported schema_version';
    }

    if (isset($candidate['labels'])) {
        if (!is_array($candidate['labels'])) {
            $errors[] = 'labels must be an object';
        } else {
            foreach ($candidate['labels'] as $sensorId => $label) {
                if (!in_array($sensorId, homenas_dashboard_label_sensor_ids(), true)) {
                    $errors[] = 'unknown label sensor: ' . $sensorId;
                    continue;
                }
                if (!homenas_dashboard_valid_label($label)) {
                    $errors[] = 'invalid label for: ' . $sensorId;
                    continue;
                }
                $config['labels'][$sensorId] = $label;
            }
        }
    }

    if (isset($candidate['fan_labels'])) {
        if (!is_array($candidate['fan_labels'])) {
            $errors[] = 'fan_labels must be an object';
        } else {
            foreach ($candidate['fan_labels'] as $sensorId => $label) {
                if (!array_key_exists($sensorId, $defaults['fan_labels'])) {
                    $errors[] = 'unknown fan label sensor: ' . $sensorId;
                    continue;
                }
                if (!homenas_dashboard_valid_label($label)) {
                    $errors[] = 'invalid fan label for: ' . $sensorId;
                    continue;
                }
                $config['fan_labels'][$sensorId] = $label;
            }
        }
    }

    if (isset($candidate['case_delta'])) {
        if (!is_array($candidate['case_delta'])) {
            $errors[] = 'case_delta must be an object';
        } else {
            $caseDelta = $candidate['case_delta'];
            if (array_key_exists('enabled', $caseDelta)) {
                if (!is_bool($caseDelta['enabled'])) {
                    $errors[] = 'case_delta.enabled must be boolean';
                } else {
                    $config['case_delta']['enabled'] = $caseDelta['enabled'];
                }
            }
            foreach (['intake_source', 'exhaust_source'] as $field) {
                if (!array_key_exists($field, $caseDelta)) {
                    continue;
                }
                if (!is_string($caseDelta[$field])
                    || !in_array($caseDelta[$field], homenas_dashboard_temperature_sensor_ids(), true)) {
                    $errors[] = 'invalid case_delta.' . $field;
                    continue;
                }
                $config['case_delta'][$field] = $caseDelta[$field];
            }
        }
    }

    if ($config['case_delta']['intake_source'] === $config['case_delta']['exhaust_source']) {
        $errors[] = 'case_delta sources must differ';
    }

    if (isset($candidate['nvme_slots'])) {
        if (!is_array($candidate['nvme_slots'])) {
            $errors[] = 'nvme_slots must be an object';
        } else {
            foreach ($config['nvme_slots'] as $slot => $defaultsForSlot) {
                if (!array_key_exists($slot, $candidate['nvme_slots'])) {
                    continue;
                }
                $slotValue = $candidate['nvme_slots'][$slot];
                if (!is_array($slotValue)) {
                    $errors[] = 'invalid nvme slot: ' . $slot;
                    continue;
                }
                foreach (['serial', 'model'] as $field) {
                    if (!array_key_exists($field, $slotValue)) {
                        continue;
                    }
                    $value = $slotValue[$field];
                    if ($value !== null && (!is_string($value) || strlen($value) > 256)) {
                        $errors[] = 'invalid ' . $field . ' for: ' . $slot;
                        continue;
                    }
                    $config['nvme_slots'][$slot][$field] = $value;
                }
            }
        }
    }

    return [count($errors) === 0, $config, $errors];
}
