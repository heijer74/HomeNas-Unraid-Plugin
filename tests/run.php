<?php

declare(strict_types=1);

$tests = [
    'test-config.php',
    'test-sensor-reader.php',
    'test-temperatures.php',
    'test-settings.php',
    'test-unraid-csrf.php',
    'test-page-metadata.php',
    'test-temperature-tile.php',
    'test-airflow-tile.php',
    'test-airflow-overlay.php',
];

try {
    $passed = [];
    foreach ($tests as $test) {
        require __DIR__ . '/' . $test;
        $passed[] = $test;
    }
    foreach ($passed as $test) {
        fwrite(STDOUT, "PASS " . $test . PHP_EOL);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
