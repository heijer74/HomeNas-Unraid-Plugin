<?php

declare(strict_types=1);

// Offline-reviewed Unraid 7.3.2 Airflow block, plus the exact variants made by
// the old, board-specific label script. Unknown layouts are never modified.
const HOMENAS_AIRFLOW_STOCK_SHA256 = 'ef8c74b2c0e14add48c456742a528dcd1ce443891c026e9352255566ba39510e';
const HOMENAS_AIRFLOW_BEGIN = '<!-- homenas.dashboard:airflow:begin v1 -->';
const HOMENAS_AIRFLOW_END = '<!-- homenas.dashboard:airflow:end -->';
const HOMENAS_AIRFLOW_INCLUDE = "<?php require_once '/usr/local/emhttp/plugins/homenas.dashboard/include/airflow-tile.php'; ?>";

function homenas_airflow_fail(string $reason): void
{
    throw new RuntimeException($reason);
}

function homenas_airflow_known_originals(): array
{
    $stock = file_get_contents(__DIR__ . '/../include/airflow-stock-7.3.2.txt');
    if (!is_string($stock) || hash('sha256', $stock) !== HOMENAS_AIRFLOW_STOCK_SHA256) {
        homenas_airflow_fail('stock Unraid 7.3.2 fixture is unavailable or changed');
    }
    $oldLabel = <<<'LABEL'
                        $label[$i][] = "<span{$class}>"._('FAN')." ".($fan+1)."</span>";
LABEL;
    $newLabel = <<<'LABEL'
                        $fan_labels = ["CHA_FAN1","CPU_FAN","CHA_FAN2","CHA_FAN3","H_AMP","W_PUMP+","AIO_PUMP","CPU_OPT","EXT_FAN1","EXT_FAN2","EXT_FAN3"]; $label[$i][] = "<span{$class}>".$fan_labels[$fan]."</span>";
LABEL;
    $oldLabel .= "\n";
    $newLabel .= "\n";
    if (substr_count($stock, $oldLabel) !== 1) {
        homenas_airflow_fail('stock fan label anchor changed');
    }

    $controlStart = "                                <?if (\$autofan):?>\n";
    $controlEnd = "                                <?endif;?>\n";
    $start = strpos($stock, $controlStart);
    $end = $start === false ? false : strpos($stock, $controlEnd, $start);
    if ($start === false || $end === false || substr_count($stock, $controlStart) !== 1) {
        homenas_airflow_fail('stock fan control header changed');
    }
    $oldControls = substr($stock, $start, $end + strlen($controlEnd) - $start);
    $indent = str_repeat(' ', 32);
    $newControls = $indent . "<span class='tile-header-right'>\n"
        . $indent . "    <span class='tile-header-right-controls'>\n"
        . $indent . "        <?if (\$autofan):?>\n"
        . $indent . "            <a href='/Dashboard/Settings/FanSettings' title=\"_(Go to fan settings)_\">\n"
        . $indent . "                <i class='fa fa-fw fa-cog control'></i>\n"
        . $indent . "            </a>\n"
        . $indent . "        <?endif;?>\n"
        . $indent . "    </span>\n"
        . $indent . "</span>\n";

    $labelsOnly = str_replace($oldLabel, $newLabel, $stock);
    $controlsOnly = str_replace($oldControls, $newControls, $stock);
    $both = str_replace($oldControls, $newControls, $labelsOnly);
    return array_values(array_unique([$stock, $labelsOnly, $controlsOnly, $both]));
}

function homenas_airflow_temperature_marker_valid(string $page): bool
{
    $count = substr_count($page, 'homenas.dashboard:temperatures:');
    if ($count === 0) {
        return true;
    }
    $known = [
        "/* homenas.dashboard:temperatures:begin v0 */\n" .
        "<?php require_once '/usr/local/emhttp/plugins/homenas.dashboard/include/temperature-tile.php'; ?>\n" .
        "/* homenas.dashboard:temperatures:end */\n",
        "/* homenas.dashboard:temperatures:begin v1 */\n" .
        "<?php require_once '/usr/local/emhttp/plugins/homenas.dashboard/include/temperature-tile.php'; ?>\n" .
        "/* homenas.dashboard:temperatures:end */\n",
    ];
    foreach ($known as $block) {
        if ($count === 2 && substr_count($page, $block) === 1) {
            return true;
        }
    }
    return false;
}

function homenas_airflow_anchor_offset(string $page, string $column): ?int
{
    $expression = "<?customTiles('" . $column . "');?>";
    $raw = preg_match_all('/customTiles[ \t]*\([^\r\n]*' . preg_quote($column, '/') . '[^\r\n]*\)/', $page);
    $valid = preg_match_all('/^[ \t]*' . preg_quote($expression, '/') . '[ \t]*$/m', $page, $matches, PREG_OFFSET_CAPTURE);
    return $raw === 1 && $valid === 1 ? $matches[0][0][1] : null;
}

function homenas_airflow_context_valid(string $page, int $blockEnd, int $column1Offset): bool
{
    if ($blockEnd >= $column1Offset) {
        return false;
    }
    $between = trim(substr($page, $blockEnd, $column1Offset - $blockEnd));
    if ($between === '') {
        return true;
    }
    $knownTemperature = "/* homenas.dashboard:temperatures:begin v1 */\n"
        . "<?php require_once '/usr/local/emhttp/plugins/homenas.dashboard/include/temperature-tile.php'; ?>\n"
        . '/* homenas.dashboard:temperatures:end */';
    $legacyTemperature = str_replace('begin v1', 'begin v0', $knownTemperature);
    return $between === $knownTemperature || $between === $legacyTemperature;
}

function homenas_airflow_make_block(string $original, string $contextHash): string
{
    $hash = hash('sha256', $original);
    return HOMENAS_AIRFLOW_BEGIN . "\n"
        . HOMENAS_AIRFLOW_INCLUDE . "\n"
        . '<!-- homenas.dashboard:airflow:original sha256=' . $hash
        . ' context_sha256=' . $contextHash
        . ' b64=' . base64_encode($original) . " -->\n"
        . HOMENAS_AIRFLOW_END . "\n";
}

function homenas_airflow_existing_block(string $page, array $originals): ?array
{
    $markerCount = substr_count($page, 'homenas.dashboard:airflow:');
    if ($markerCount === 0) {
        return null;
    }
    if ($markerCount !== 3 || substr_count($page, HOMENAS_AIRFLOW_BEGIN) !== 1
        || substr_count($page, HOMENAS_AIRFLOW_END) !== 1) {
        homenas_airflow_fail('broken or duplicate Airflow markers');
    }
    $pattern = '/'. preg_quote(HOMENAS_AIRFLOW_BEGIN, '/') . '\n'
        . preg_quote(HOMENAS_AIRFLOW_INCLUDE, '/') . '\n'
        . '<!-- homenas\.dashboard:airflow:original sha256=([0-9a-f]{64}) context_sha256=([0-9a-f]{64}) b64=([A-Za-z0-9+\/=]+) -->\n'
        . preg_quote(HOMENAS_AIRFLOW_END, '/') . '\n/';
    if (preg_match($pattern, $page, $matches, PREG_OFFSET_CAPTURE) !== 1) {
        homenas_airflow_fail('unrecognised Airflow marker block');
    }
    $original = base64_decode($matches[3][0], true);
    $context = substr_replace($page, '', $matches[0][1], strlen($matches[0][0]));
    if (!is_string($original) || hash('sha256', $original) !== $matches[1][0]
        || !in_array($original, $originals, true)
        || hash('sha256', $context) !== $matches[2][0]
        || homenas_airflow_make_block($original, $matches[2][0]) !== $matches[0][0]) {
        homenas_airflow_fail('Airflow marker payload failed verification');
    }
    return ['position' => $matches[0][1], 'block' => $matches[0][0], 'original' => $original];
}

function homenas_airflow_native_block(string $page, array $originals): ?array
{
    if (substr_count($page, '_(Airflow)_') !== 1
        || substr_count($page, '<tbody title="_(Fan Information)_">') !== 1) {
        return null;
    }
    $matches = [];
    foreach ($originals as $original) {
        if (substr_count($page, $original) === 1) {
            $matches[] = ['position' => strpos($page, $original), 'block' => $original];
        }
    }
    return count($matches) === 1 ? $matches[0] : null;
}

function homenas_airflow_state(string $page, array $originals): array
{
    if (!homenas_airflow_temperature_marker_valid($page)) {
        homenas_airflow_fail('broken or unknown Temperature marker');
    }
    if (preg_match('/homenas\.dashboard:(?!temperatures:|airflow:)/', $page) === 1) {
        homenas_airflow_fail('unknown HomeNas marker');
    }
    $column1 = homenas_airflow_anchor_offset($page, 'column1');
    $column2 = homenas_airflow_anchor_offset($page, 'column2');
    if ($column1 === null || $column2 === null) {
        homenas_airflow_fail('missing, duplicate or changed customTiles anchor');
    }
    $active = homenas_airflow_existing_block($page, $originals);
    if ($active !== null) {
        if (!homenas_airflow_context_valid($page, $active['position'] + strlen($active['block']), $column1)) {
            homenas_airflow_fail('Airflow marker context changed');
        }
        return ['status' => 'active'] + $active;
    }
    $native = homenas_airflow_native_block($page, $originals);
    if ($native === null || !homenas_airflow_context_valid($page, $native['position'] + strlen($native['block']), $column1)) {
        homenas_airflow_fail('unsupported Unraid 7.3.2 Airflow layout');
    }
    return ['status' => 'disabled'] + $native;
}

function homenas_airflow_replace_page(string $path, string $content, string $expected): void
{
    $directory = dirname($path);
    $temporary = tempnam($directory, '.DashStats.homenas-airflow.');
    if ($temporary === false) {
        homenas_airflow_fail('cannot create temporary DashStats page');
    }
    $mode = fileperms($path);
    if ($mode === false || file_put_contents($temporary, $content) !== strlen($content)
        || !chmod($temporary, $mode & 0777)
        || file_get_contents($path) !== $expected
        || !rename($temporary, $path)) {
        @unlink($temporary);
        homenas_airflow_fail('DashStats changed during adapter run or could not be replaced');
    }
}

function homenas_airflow_run(string $command, string $path, string $lockDir): string
{
    $originals = homenas_airflow_known_originals();
    if (!is_readable($path)) {
        homenas_airflow_fail('DashStats page unavailable');
    }
    if ($command !== 'status' && !mkdir($lockDir, 0700)) {
        homenas_airflow_fail('dashboard adapter busy or lock unavailable');
    }
    try {
        $page = file_get_contents($path);
        if (!is_string($page)) {
            homenas_airflow_fail('cannot read DashStats page');
        }
        // No own marker means there is nothing to restore. An unfamiliar
        // Unraid page must not block uninstall when we never changed it.
        if ($command === 'remove' && strpos($page, 'homenas.dashboard:airflow:') === false) {
            return 'not-present';
        }
        $state = homenas_airflow_state($page, $originals);
        if ($command === 'status') {
            return $state['status'];
        }
        if ($command === 'apply' && $state['status'] === 'disabled') {
            $context = substr_replace($page, '', $state['position'], strlen($state['block']));
            $new = substr_replace($page, homenas_airflow_make_block($state['block'], hash('sha256', $context)),
                $state['position'], strlen($state['block']));
            homenas_airflow_state($new, $originals);
            homenas_airflow_replace_page($path, $new, $page);
            return 'applied';
        }
        if ($command === 'remove' && $state['status'] === 'active') {
            $new = substr_replace($page, $state['original'], $state['position'], strlen($state['block']));
            homenas_airflow_state($new, $originals);
            homenas_airflow_replace_page($path, $new, $page);
            return 'removed';
        }
        return $state['status'];
    } finally {
        if ($command !== 'status') {
            rmdir($lockDir);
        }
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $command = $argv[1] ?? 'status';
    if (!in_array($command, ['status', 'apply', 'remove'], true)) {
        fwrite(STDERR, "usage: airflow-overlay.php {status|apply|remove}\n");
        exit(64);
    }
    try {
        $result = homenas_airflow_run(
            $command,
            getenv('HOMENAS_DASHBOARD_PAGE') ?: '/usr/local/emhttp/plugins/dynamix/DashStats.page',
            getenv('HOMENAS_DASHBOARD_LOCK_DIR') ?: '/var/lock/homenas.dashboard-overlay.lock'
        );
        fwrite(STDOUT, $result . "\n");
    } catch (Throwable $error) {
        fwrite(STDERR, 'homenas.dashboard: Airflow ' . $command . ' refused: ' . $error->getMessage() . "\n");
        exit(1);
    }
}
