<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/include/settings-page.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed');
}

// Unraid 7.3.2 local_prepend.php validates POST csrf_token and removes it from
// $_POST before this action runs. Require that native context before writing.
try {
    $csrfToken = homenas_dashboard_unraid_csrf_token($var ?? null);
} catch (RuntimeException $exception) {
    http_response_code(403);
    exit('Unraid CSRF context unavailable');
}

[$saved, $config, $errors] = homenas_dashboard_save_settings_from_post($_POST);
if ($saved) {
    header('Location: /Settings/HomeNasDashboard?saved=1', true, 303);
    exit;
}

$state = [
    'config' => $config,
    'status' => 'invalid',
    'errors' => $errors,
];

http_response_code(422);
header('Content-Type: text/html; charset=utf-8');
echo homenas_dashboard_render_settings_page($state, $csrfToken, $errors);
