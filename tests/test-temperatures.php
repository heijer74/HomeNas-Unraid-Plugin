<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

[$valid, $config] = homenas_dashboard_validate_config(fixture_json('homenas-label-overrides.json'));
test_assert($valid, 'fixture config must validate');

$snapshot = homenas_dashboard_snapshot(fixture_json('sensors-all-present.json'), $config, fixture_nvme_inventory());
$requiredFields = ['sensor_id', 'display_label', 'value', 'unit', 'available', 'source', 'chip'];
foreach ($snapshot['sensors'] as $sensor) {
    foreach ($requiredFields as $field) {
        test_assert(array_key_exists($field, $sensor), 'sensor record lacks stable field: ' . $field);
    }
}
test_assert($snapshot['schema_version'] === 1, 'snapshot schema version changed');
test_assert($snapshot['case_delta_t']['available'], 'Case Delta must be available with both fixture inputs');
test_assert($snapshot['case_delta_t']['value'] === 7.5, 'Case Delta must be exhaust minus intake with one decimal');

$missingIntake = homenas_dashboard_snapshot(fixture_json('sensors-intake-missing.json'), $config, []);
test_assert(!$missingIntake['case_delta_t']['available'], 'missing intake must disable Case Delta');
test_assert($missingIntake['case_delta_t']['value'] === null, 'missing intake must yield null Case Delta');

$missingExhaust = homenas_dashboard_snapshot(fixture_json('sensors-exhaust-missing.json'), $config, []);
test_assert(!$missingExhaust['case_delta_t']['available'], 'missing exhaust must disable Case Delta');
test_assert($missingExhaust['case_delta_t']['value'] === null, 'missing exhaust must yield null Case Delta');
