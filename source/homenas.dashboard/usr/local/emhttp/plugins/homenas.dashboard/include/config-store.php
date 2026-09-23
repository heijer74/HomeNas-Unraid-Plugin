<?php

require_once __DIR__ . '/config.php';

function homenas_dashboard_write_config(array $candidate, ?string $configDir = null): array
{
    [$valid, $config, $errors] = homenas_dashboard_validate_config($candidate);
    if (!$valid) {
        return [false, $errors];
    }

    $directory = homenas_dashboard_config_dir($configDir);
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        return [false, ['cannot create config directory']];
    }

    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return [false, ['cannot encode configuration']];
    }

    $temporary = tempnam($directory, '.settings.');
    if ($temporary === false) {
        return [false, ['cannot create temporary configuration']];
    }

    $written = file_put_contents($temporary, $json . PHP_EOL);
    if ($written === false || !chmod($temporary, 0600) || !rename($temporary, homenas_dashboard_config_path($directory))) {
        @unlink($temporary);
        return [false, ['cannot save configuration']];
    }

    return [true, []];
}

function homenas_dashboard_initialize_config(?string $configDir = null): array
{
    $path = homenas_dashboard_config_path($configDir);
    if (is_file($path)) {
        $loaded = homenas_dashboard_load_config($configDir);
        return [$loaded['status'] !== 'invalid', $loaded['errors']];
    }

    return homenas_dashboard_write_config(homenas_dashboard_default_config(), $configDir);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (($argv[1] ?? '') !== 'init') {
        fwrite(STDERR, "usage: config-store.php init\n");
        exit(64);
    }

    [$ok, $errors] = homenas_dashboard_initialize_config();
    if (!$ok) {
        fwrite(STDERR, implode("; ", $errors) . "\n");
        exit(1);
    }
}
