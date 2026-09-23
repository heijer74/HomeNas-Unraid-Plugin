<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sensor-reader.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$configState = homenas_dashboard_load_config();
echo json_encode(
    homenas_dashboard_live_snapshot($configState['config']),
    JSON_UNESCAPED_SLASHES
);
