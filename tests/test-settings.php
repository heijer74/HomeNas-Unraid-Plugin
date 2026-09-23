<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once $runtimeRoot . '/include/settings-page.php';

$temporaryDir = sys_get_temp_dir() . '/homenas-dashboard-settings-' . bin2hex(random_bytes(6));
$csrfToken = homenas_dashboard_unraid_csrf_token(['csrf_token' => 'native-fixture-token']);
try {
    $missingState = homenas_dashboard_load_config($temporaryDir);
    test_assert($missingState['status'] === 'defaults', 'missing settings.json must use defaults');
    $defaultHtml = homenas_dashboard_render_settings_page($missingState, $csrfToken);
    test_assert(strpos($defaultHtml, 'name="csrf_token" value="native-fixture-token"') !== false, 'form must carry the native Unraid token');
    test_assert(strpos($defaultHtml, 'T_Sensor') !== false, 'default settings page must show generic T_Sensor label');
    test_assert(strpos($defaultHtml, 'EXT_Sensor1') !== false, 'default settings page must show generic EXT_Sensor1 label');
    test_assert(strpos($defaultHtml, '10GbE') === false, 'default settings page must not contain HomeNas-specific labels');
    test_assert(strpos($defaultHtml, '<legend>Fan labels</legend>') !== false, 'Settings must have a separate Fan labels section');
    foreach (['CPU Cooling', 'Case / Chassis', 'Pumps', 'Fan Extension'] as $group) {
        test_assert(strpos($defaultHtml, $group) !== false, 'Settings fan group missing: ' . $group);
    }
    test_assert(substr_count($defaultHtml, 'name="fan_labels[') === 11, 'Settings must show all eleven fan label inputs');
    test_assert(strpos($defaultHtml, 'value="cpu.package"') !== false, 'temperature dropdown must include a catalogued CPU temperature');
    test_assert(strpos($defaultHtml, 'value="board.nct.fan1"') === false, 'temperature dropdown must not include fan RPM sources');
    test_assert(!is_file($temporaryDir . '/settings.json'), 'rendering defaults must not create settings.json');

    $override = fixture_json('homenas-label-overrides.json');
    [$valid, $overrideConfig] = homenas_dashboard_validate_config($override);
    test_assert($valid, 'override fixture must validate');
    $overrideHtml = homenas_dashboard_render_settings_page(['config' => $overrideConfig, 'status' => 'loaded', 'errors' => []], $csrfToken);
    test_assert(strpos($overrideHtml, 'value="10GbE"') !== false, 'settings page must render label overrides');
    test_assert(strpos($overrideHtml, 'value="Intake"') !== false, 'settings page must render EXT label overrides');

    $validPost = [
        'labels' => $overrideConfig['labels'],
        'fan_labels' => array_merge($overrideConfig['fan_labels'], ['board.nct.fan1' => 'Front intake']),
        'case_delta_enabled' => '1',
        'case_delta_intake_source' => 'board.ext_sensor1',
        'case_delta_exhaust_source' => 'board.ext_sensor3',
    ];
    [$saved, $savedConfig, $saveErrors] = homenas_dashboard_save_settings_from_post($validPost, $temporaryDir);
    test_assert($saved && $saveErrors === [], 'valid settings form must save');
    test_assert($savedConfig['labels']['board.ext_sensor3'] === 'Exhaust', 'valid save lost label override');
    test_assert($savedConfig['fan_labels']['board.nct.fan1'] === 'Front intake', 'valid save lost fan label override');
    $beforeInvalidSave = (string) file_get_contents($temporaryDir . '/settings.json');

    $invalidPost = $validPost;
    $invalidPost['case_delta_exhaust_source'] = 'board.ext_sensor1';
    [$saved, , $saveErrors] = homenas_dashboard_save_settings_from_post($invalidPost, $temporaryDir);
    test_assert(!$saved, 'equal intake and exhaust must reject save');
    test_assert($saveErrors !== [], 'invalid save must return an error');
    test_assert(
        (string) file_get_contents($temporaryDir . '/settings.json') === $beforeInvalidSave,
        'invalid settings form must not overwrite existing configuration'
    );

    $invalidFanPost = $validPost;
    $invalidFanPost['fan_labels']['board.nct.fan1'] = '';
    [$saved] = homenas_dashboard_save_settings_from_post($invalidFanPost, $temporaryDir);
    test_assert(!$saved, 'invalid fan label must reject Save');
    test_assert((string) file_get_contents($temporaryDir . '/settings.json') === $beforeInvalidSave, 'rejected fan label must not overwrite settings');

    $escapedConfig = $overrideConfig;
    $escapedConfig['labels']['board.t_sensor'] = '<img src=x onerror=alert(1)>';
    $escapedHtml = homenas_dashboard_render_settings_page(['config' => $escapedConfig, 'status' => 'loaded', 'errors' => []], $csrfToken);
    test_assert(strpos($escapedHtml, '<img src=x') === false, 'label HTML must not render as markup');
    test_assert(strpos($escapedHtml, '&lt;img src=x onerror=alert(1)&gt;') !== false, 'label HTML must be escaped');
} finally {
    $settings = $temporaryDir . '/settings.json';
    if (is_file($settings)) {
        unlink($settings);
    }
    if (is_dir($temporaryDir)) {
        rmdir($temporaryDir);
    }
}
