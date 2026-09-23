<?php

declare(strict_types=1);

require_once __DIR__ . '/settings-page.php';

function homenas_dashboard_tile_sensor_ids(): array
{
    return [
        'board.chipset',
        'board.motherboard',
        'board.vrm',
        'board.t_sensor',
        'storage.nvme.slot1',
        'storage.nvme.slot2',
        'board.ext_sensor1',
        'board.ext_sensor2',
        'board.ext_sensor3',
    ];
}

function homenas_dashboard_render_temperature_tile(array $config): string
{
    $catalog = homenas_dashboard_sensor_catalog();
    $html = '<tbody title="HomeNas Temperatures" class="sortable homenas-dashboard-temperature-tile">';
    $html .= '<tr><td><link rel="stylesheet" href="/plugins/homenas.dashboard/assets/homenas-dashboard-temperature.css">';
    $html .= '<span class="tile-header"><span class="tile-header-left">';
    $html .= '<i class="fa fa-thermometer-half f32"></i><div class="section">';
    $html .= '<h3 class="tile-header-main">Temperatures</h3></div></span>';
    $html .= '<span class="tile-header-right"><span class="tile-header-right-controls">';
    $html .= '<a href="/Settings/HomeNasDashboard" title="HomeNas Monitoring settings">';
    $html .= '<i class="fa fa-fw fa-cog control"></i></a>';
    $html .= '</span></span></span></td></tr><tr><td>';
    $html .= '<div class="homenas-dashboard-temperature-layout">';

    $groups = ['motherboard', 'pcie-storage', 'airflow'];
    foreach (array_chunk(homenas_dashboard_tile_sensor_ids(), 3) as $index => $sensorIds) {
        $html .= '<div class="homenas-dashboard-temperature-group" data-group="' . $groups[$index] . '">';
        foreach ($sensorIds as $sensorId) {
            $label = $config['labels'][$sensorId] ?? $catalog[$sensorId]['label'];
            $html .= '<div class="homenas-dashboard-temperature-item" data-sensor-id="'
                . homenas_dashboard_html($sensorId) . '">';
            $html .= '<span class="homenas-dashboard-temperature-label">' . homenas_dashboard_html($label) . '</span>';
            $html .= '<span class="homenas-dashboard-temperature-value">N/A</span></div>';
        }
        $html .= '</div>';
    }

    if ($config['case_delta']['enabled']) {
        $html .= '<div class="homenas-dashboard-temperature-item homenas-dashboard-case-delta" '
            . 'data-sensor-id="derived.case_delta_t">';
        $html .= '<span class="homenas-dashboard-temperature-label">Case ΔT</span>';
        $html .= '<span class="homenas-dashboard-temperature-value">N/A</span></div>';
    }

    $html .= '</div></td></tr></tbody>';
    $html .= '<script src="/plugins/homenas.dashboard/assets/homenas-dashboard-temperature.js"></script>';
    return $html;
}

$tileConfig = homenas_dashboard_load_config()['config'];
echo homenas_dashboard_render_temperature_tile($tileConfig);
