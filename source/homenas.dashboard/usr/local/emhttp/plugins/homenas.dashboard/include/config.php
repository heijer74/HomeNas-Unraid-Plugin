<?php

require_once __DIR__ . '/config-schema.php';

function homenas_dashboard_config_dir(?string $override = null): string
{
    return $override !== null
        ? rtrim($override, '/')
        : '/boot/config/plugins/homenas.dashboard';
}

function homenas_dashboard_config_path(?string $configDir = null): string
{
    return homenas_dashboard_config_dir($configDir) . '/settings.json';
}

function homenas_dashboard_load_config(?string $configDir = null): array
{
    $path = homenas_dashboard_config_path($configDir);
    if (!is_file($path) || !is_readable($path)) {
        return [
            'config' => homenas_dashboard_default_config(),
            'status' => 'defaults',
            'errors' => [],
        ];
    }

    $raw = file_get_contents($path);
    $candidate = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($candidate)) {
        return [
            'config' => homenas_dashboard_default_config(),
            'status' => 'invalid',
            'errors' => ['settings.json is not valid JSON'],
        ];
    }

    [$valid, $config, $errors] = homenas_dashboard_validate_config($candidate);
    return [
        'config' => $config,
        'status' => $valid ? 'loaded' : 'invalid',
        'errors' => $errors,
    ];
}
