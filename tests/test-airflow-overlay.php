<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once $runtimeRoot . '/scripts/airflow-overlay.php';

$originals = homenas_airflow_known_originals();
test_assert(count($originals) === 4, 'stock and three exact legacy Airflow variants must be recognised');
test_assert(hash('sha256', $originals[0]) === HOMENAS_AIRFLOW_STOCK_SHA256, 'stock block must match official Unraid 7.3.2');

$workDir = sys_get_temp_dir() . '/homenas-airflow-overlay-' . bin2hex(random_bytes(6));
mkdir($workDir, 0700);
$pagePath = $workDir . '/DashStats.page';
$lockDir = $workDir . '/lock';
$temperatureBlock = "/* homenas.dashboard:temperatures:begin v1 */\n"
    . "<?php require_once '/usr/local/emhttp/plugins/homenas.dashboard/include/temperature-tile.php'; ?>\n"
    . "/* homenas.dashboard:temperatures:end */\n";

function airflow_test_page(string $block, string $temperature = ''): string
{
    return "<div class='frame'><table class='dashboard'>\n"
        . $block . "\n" . $temperature
        . "                <?customTiles('column1');?>\n"
        . "                <?customTiles('column2');?>\n"
        . "</table></div>\n"
        . "<?if (\$fans):?>\n"; // Unraid 7.3.2 has another fans guard in its JS section.
}

function airflow_test_refuse(string $page, string $path, string $lock): void
{
    file_put_contents($path, $page);
    try {
        homenas_airflow_run('apply', $path, $lock);
        test_assert(false, 'unsafe Airflow layout was accepted');
    } catch (RuntimeException $expected) {
        test_assert(file_get_contents($path) === $page, 'refused Airflow layout must remain byte-identical');
    }
}

try {
    foreach ($originals as $index => $original) {
        $before = airflow_test_page($original, $temperatureBlock);
        file_put_contents($pagePath, $before);
        test_assert(homenas_airflow_run('status', $pagePath, $lockDir) === 'disabled', 'native variant must be recognised');
        test_assert(homenas_airflow_run('apply', $pagePath, $lockDir) === 'applied', 'known variant must apply');
        $after = (string) file_get_contents($pagePath);
        $context = substr_replace($before, '', strpos($before, $original), strlen($original));
        $ownBlock = homenas_airflow_make_block($original, hash('sha256', $context));
        test_assert(substr_count($after, $ownBlock) === 1, 'exactly one Airflow marker block required');
        test_assert(str_replace($ownBlock, $original, $after) === $before, 'apply changed bytes outside its own block');
        test_assert(homenas_airflow_run('status', $pagePath, $lockDir) === 'active', 'applied variant must be active');
        test_assert(homenas_airflow_run('apply', $pagePath, $lockDir) === 'active', 'repeat apply must be idempotent');
        test_assert(file_get_contents($pagePath) === $after, 'repeat apply changed bytes');
        if ($index === 0) {
            $changedContext = str_replace("<div class='frame'>", "<div class='changed-frame'>", $after);
            file_put_contents($pagePath, $changedContext);
            try {
                homenas_airflow_run('remove', $pagePath, $lockDir);
                test_assert(false, 'changed page outside marker must block remove');
            } catch (RuntimeException $expected) {
                test_assert(file_get_contents($pagePath) === $changedContext, 'refused remove altered changed page');
            }
            file_put_contents($pagePath, $after);
        }
        test_assert(homenas_airflow_run('remove', $pagePath, $lockDir) === 'removed', 'remove must restore original');
        test_assert(file_get_contents($pagePath) === $before, 'remove did not restore exact original bytes');
        test_assert(!is_dir($lockDir), 'adapter lock leaked for variant ' . $index);
    }

    $stock = $originals[0];
    $base = airflow_test_page($stock);
    file_put_contents($pagePath, $base);
    try {
        homenas_airflow_replace_page($pagePath, 'replacement', 'outdated source');
        test_assert(false, 'stale page content must prevent replacement');
    } catch (RuntimeException $expected) {
        test_assert(file_get_contents($pagePath) === $base, 'stale replacement changed DashStats page');
    }
    $unknownUnmodified = str_replace('_(Airflow)_', '_(Changed)_', $base);
    file_put_contents($pagePath, $unknownUnmodified);
    test_assert(homenas_airflow_run('remove', $pagePath, $lockDir) === 'not-present', 'unmodified unknown layout must not block uninstall');
    test_assert(file_get_contents($pagePath) === $unknownUnmodified, 'remove without own marker changed unknown layout');
    airflow_test_refuse(str_replace("                <?customTiles('column1');?>\n", '', $base), $pagePath, $lockDir);
    airflow_test_refuse(str_replace("                <?customTiles('column1');?>\n", "                <?customTiles('column1');?>\n                <?customTiles('column1');?>\n", $base), $pagePath, $lockDir);
    airflow_test_refuse(str_replace("                <?customTiles('column2');?>\n", "                <?customTiles('column2');?>\n                <?customTiles('column2');?>\n", $base), $pagePath, $lockDir);
    airflow_test_refuse(str_replace("                <?customTiles('column1');?>\n", "                <?customTiles('column1');?> extra\n", $base), $pagePath, $lockDir);
    airflow_test_refuse(str_replace('_(Airflow)_', '_(Changed)_', $base), $pagePath, $lockDir);
    airflow_test_refuse(str_replace("                <?customTiles('column1');?>\n", "<!-- homenas.dashboard:airflow:begin v9 -->\n                <?customTiles('column1');?>\n", $base), $pagePath, $lockDir);
    $partialMarker = str_replace("                <?customTiles('column1');?>\n", "<!-- homenas.dashboard:airflow:begin v9 -->\n                <?customTiles('column1');?>\n", $base);
    file_put_contents($pagePath, $partialMarker);
    try {
        homenas_airflow_run('remove', $pagePath, $lockDir);
        test_assert(false, 'partial own marker must block uninstall');
    } catch (RuntimeException $expected) {
        test_assert(file_get_contents($pagePath) === $partialMarker, 'partial marker remove changed page');
    }
    airflow_test_refuse(str_replace("                <?customTiles('column1');?>\n", "<!-- homenas.dashboard:unknown:begin -->\n                <?customTiles('column1');?>\n", $base), $pagePath, $lockDir);
    airflow_test_refuse(str_replace("                <?customTiles('column1');?>\n", "<!-- homenas.dashboard:airflow:begin v1 -->\n<!-- homenas.dashboard:airflow:begin v1 -->\n                <?customTiles('column1');?>\n", $base), $pagePath, $lockDir);
    airflow_test_refuse(str_replace("                <?customTiles('column1');?>\n", "unknown content\n                <?customTiles('column1');?>\n", $base), $pagePath, $lockDir);
    airflow_test_refuse(airflow_test_page($stock, "/* homenas.dashboard:temperatures:begin v9 */\n"), $pagePath, $lockDir);
} finally {
    if (is_file($pagePath)) {
        unlink($pagePath);
    }
    if (is_dir($lockDir)) {
        rmdir($lockDir);
    }
    rmdir($workDir);
}
