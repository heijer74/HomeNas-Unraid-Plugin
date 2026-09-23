<?php

declare(strict_types=1);

require_once __DIR__ . '/settings-page.php';

function homenas_dashboard_render_airflow_tile(array $config): string
{
    $groupClasses = [
        'CPU Cooling' => ['cpu-cooling', 'two'],
        'Case / Chassis' => ['case-chassis', 'three'],
        'Pumps' => ['pumps', 'two'],
        'Fan Extension' => ['fan-extension', 'three'],
    ];
    $html = '<tbody title="Airflow" class="sortable homenas-dashboard-airflow-tile">';
    $html .= '<tr><td><link rel="stylesheet" href="/plugins/homenas.dashboard/assets/homenas-dashboard-airflow.css">';
    $html .= '<span class="tile-header"><span class="tile-header-left">';
    $html .= '<i class="icon-fan f32"></i><div class="section"><h3 class="tile-header-main">Airflow</h3></div></span>';
    $html .= '<span class="tile-header-right"><span class="tile-header-right-controls">';
    $html .= '<a href="/Settings/HomeNasDashboard" title="HomeNas Monitoring settings">';
    $html .= '<i class="fa fa-fw fa-cog control"></i></a></span></span></span></td></tr>';
    $html .= '<tr><td><div class="homenas-dashboard-airflow-layout">';

    foreach (homenas_dashboard_airflow_groups() as $heading => $sensorIds) {
        [$slug, $columns] = $groupClasses[$heading];
        $html .= '<section class="homenas-dashboard-airflow-group homenas-dashboard-airflow-' . $columns
            . '" data-group="' . $slug . '">';
        $html .= '<h4>' . homenas_dashboard_html($heading) . '</h4>';
        $html .= '<div class="homenas-dashboard-airflow-grid">';
        foreach ($sensorIds as $sensorId) {
            $html .= '<div class="homenas-dashboard-airflow-item" data-sensor-id="'
                . homenas_dashboard_html($sensorId) . '">';
            $html .= '<span class="homenas-dashboard-airflow-label">'
                . homenas_dashboard_html($config['fan_labels'][$sensorId]) . '</span>';
            $html .= '<span class="homenas-dashboard-airflow-value">N/A</span></div>';
        }
        $html .= '</div></section>';
    }

    $html .= '</div></td></tr></tbody>';
    $html .= '<script src="/plugins/homenas.dashboard/assets/homenas-dashboard-airflow.js"></script>';
    return $html;
}

$airflowConfig = homenas_dashboard_load_config()['config'];
echo homenas_dashboard_render_airflow_tile($airflowConfig);
