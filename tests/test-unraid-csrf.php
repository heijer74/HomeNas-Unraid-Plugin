<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once $runtimeRoot . '/include/settings-page.php';

// Test-only model of the relevant Unraid 7.3.2 local_prepend.php gate:
// https://github.com/unraid/webgui/blob/7.3.2/emhttp/plugins/dynamix/include/local_prepend.php
// Production deliberately uses Unraid's gate, not this function.
function unraid_732_csrf_gate_fixture(array $post, string $expected): array
{
    $token = $post['csrf_token'] ?? null;
    if (!is_string($token) || !hash_equals($expected, $token)) {
        return [false, $post];
    }
    unset($post['csrf_token']);
    return [true, $post];
}

$settingsSource = (string) file_get_contents($runtimeRoot . '/include/settings-page.php');
$actionSource = (string) file_get_contents($runtimeRoot . '/actions/save-settings.php');
test_assert(strpos($settingsSource . $actionSource, 'session_start(') === false, 'plugin must not start a PHP session for CSRF');
test_assert(strpos($settingsSource . $actionSource, '$_SESSION') === false, 'plugin must not keep a private session token');
test_assert(strpos($settingsSource . $actionSource, 'homenas_dashboard_valid_csrf_token') === false, 'plugin must not revalidate a token removed by Unraid');
test_assert(strpos($actionSource, 'homenas_dashboard_unraid_csrf_token($var ?? null)') !== false, 'action must require native Unraid CSRF context');
test_assert(
    strpos($actionSource, 'homenas_dashboard_unraid_csrf_token($var ?? null)')
        < strpos($actionSource, 'homenas_dashboard_save_settings_from_post($_POST)'),
    'native context must be required before config write'
);
test_assert(strpos($actionSource, 'Location: /Settings/HomeNasDashboard?saved=1') !== false, 'successful Save must redirect to the normal Settings page');

$missingContextRejected = false;
try {
    homenas_dashboard_unraid_csrf_token(null);
} catch (RuntimeException $exception) {
    $missingContextRejected = true;
}
test_assert($missingContextRejected, 'missing native Unraid context must fail closed');

$nativeToken = homenas_dashboard_unraid_csrf_token(['csrf_token' => 'native-test-token']);
$state = ['config' => homenas_dashboard_default_config(), 'status' => 'defaults', 'errors' => []];
$form = homenas_dashboard_render_settings_page($state, $nativeToken);
test_assert(strpos($form, 'name="csrf_token" value="native-test-token"') !== false, 'form must submit native Unraid token');
test_assert(substr_count($form, 'style="margin: 0.75em 0"') === 3, 'Case ΔT controls must have three separated rows');

$temporaryDir = sys_get_temp_dir() . '/homenas-csrf-test-' . bin2hex(random_bytes(6));
$override = fixture_json('homenas-label-overrides.json');
$post = [
    'labels' => $override['labels'],
    'case_delta_enabled' => '1',
    'case_delta_intake_source' => 'board.ext_sensor1',
    'case_delta_exhaust_source' => 'board.ext_sensor3',
];

try {
    foreach (['missing' => $post, 'wrong' => $post + ['csrf_token' => 'wrong-token']] as $case => $candidate) {
        [$accepted, ] = unraid_732_csrf_gate_fixture($candidate, $nativeToken);
        test_assert(!$accepted, $case . ' token must be rejected by native gate');
        test_assert(!is_file($temporaryDir . '/settings.json'), $case . ' token must not write settings');
    }

    [$accepted, $filteredPost] = unraid_732_csrf_gate_fixture($post + ['csrf_token' => $nativeToken], $nativeToken);
    test_assert($accepted && !array_key_exists('csrf_token', $filteredPost), 'native gate must accept and consume valid token');
    [$saved, $config, $errors] = homenas_dashboard_save_settings_from_post($filteredPost, $temporaryDir);
    test_assert($saved && $errors === [], 'valid native token must permit normal validated save');
    test_assert($config['labels']['board.ext_sensor1'] === 'Intake', 'valid save lost label override');
    $savedBytes = (string) file_get_contents($temporaryDir . '/settings.json');

    foreach ([$post, $post + ['csrf_token' => 'wrong-token']] as $candidate) {
        [$accepted, ] = unraid_732_csrf_gate_fixture($candidate, $nativeToken);
        test_assert(!$accepted, 'rejected POST must not reach config writer');
        test_assert((string) file_get_contents($temporaryDir . '/settings.json') === $savedBytes, 'rejected POST changed settings');
    }
} finally {
    if (is_file($temporaryDir . '/settings.json')) {
        unlink($temporaryDir . '/settings.json');
    }
    if (is_dir($temporaryDir)) {
        rmdir($temporaryDir);
    }
}
