<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$pagePath = $runtimeRoot . '/HomeNasDashboard.page';
$page = (string) file_get_contents($pagePath);
$parts = explode("---\n", $page);

test_assert(count($parts) === 2, '.page must have exactly one metadata separator');
test_assert(basename($pagePath, '.page') === 'HomeNasDashboard', 'page route must remain HomeNasDashboard');
test_assert(
    $parts[0] === "Menu=\"Utilities\"\nTitle=\"HomeNas Monitoring\"\nIcon=\"thermometer-half\"\nTag=\"temperature\"\nMarkdown=\"false\"\n",
    '.page must contain the expected static Dynamix metadata before ---'
);
test_assert(substr_count($parts[0], 'Markdown="false"') === 1, 'page must explicitly disable Markdown processing');
test_assert(strpos($page, '$display[') === false, '.page must not use PHP $display registration');
test_assert(strpos($parts[1], '__DIR__') === false, 'evaluated .page body must not resolve includes via evalContent.php __DIR__');
test_assert(
    is_file(dirname($runtimeRoot, 2) . '/plugins/homenas.dashboard/include/settings-page.php'),
    'Settings renderer must exist under the emhttp docroot'
);

$expectedPhp = <<<'PHP'
<?php

require_once $docroot . '/plugins/homenas.dashboard/include/settings-page.php';

$csrfToken = homenas_dashboard_unraid_csrf_token($var ?? null);
$notice = ($_GET['saved'] ?? null) === '1' ? 'Instellingen opgeslagen.' : null;
echo homenas_dashboard_render_settings_page(homenas_dashboard_load_config(), $csrfToken, [], $notice);
PHP;
test_assert($parts[1] === $expectedPhp . "\n", 'Settings PHP body differs from the expected renderer call');

$docroot = dirname($runtimeRoot, 2);
$var = ['csrf_token' => 'native-fixture-token'];
ob_start();
try {
    eval('?>' . $parts[1]);
    $rendered = (string) ob_get_clean();
} catch (Throwable $exception) {
    ob_end_clean();
    throw $exception;
}
test_assert(strpos($rendered, '<h1>HomeNas Monitoring</h1>') !== false, 'evaluated page must render Settings HTML');
test_assert(strpos($rendered, 'name="csrf_token" value="native-fixture-token"') !== false, 'evaluated page must use native Unraid token');
test_assert(strpos($rendered, '<?php') === false, 'evaluated page must not expose PHP source');

$_GET['saved'] = '1';
ob_start();
try {
    eval('?>' . $parts[1]);
    $savedPage = (string) ob_get_clean();
} catch (Throwable $exception) {
    ob_end_clean();
    throw $exception;
} finally {
    unset($_GET['saved']);
}
test_assert(strpos($savedPage, 'Instellingen opgeslagen.') !== false, 'successful Save must show notice on normal page');
