<?php

declare(strict_types=1);

require_once __DIR__ . '/config-store.php';
require_once __DIR__ . '/sensor-catalog.php';

function homenas_dashboard_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function homenas_dashboard_settings_label_definitions(): array
{
    $catalog = homenas_dashboard_sensor_catalog();
    $definitions = [];
    foreach (homenas_dashboard_label_sensor_ids() as $sensorId) {
        $definitions[$sensorId] = $catalog[$sensorId];
    }
    return $definitions;
}

function homenas_dashboard_temperature_source_definitions(array $config): array
{
    $catalog = homenas_dashboard_sensor_catalog();
    $sources = [];
    foreach (homenas_dashboard_temperature_sensor_ids() as $sensorId) {
        $definition = $catalog[$sensorId];
        $sources[$sensorId] = $config['labels'][$sensorId] ?? $definition['label'];
    }
    return $sources;
}

function homenas_dashboard_settings_candidate_from_post(array $post): array
{
    $candidate = ['labels' => [], 'fan_labels' => [], 'case_delta' => []];
    $postedLabels = is_array($post['labels'] ?? null) ? $post['labels'] : [];

    foreach (homenas_dashboard_label_sensor_ids() as $sensorId) {
        if (!array_key_exists($sensorId, $postedLabels)) {
            continue;
        }
        $value = $postedLabels[$sensorId];
        $candidate['labels'][$sensorId] = is_string($value) ? trim($value) : $value;
    }

    $postedFanLabels = is_array($post['fan_labels'] ?? null) ? $post['fan_labels'] : [];
    foreach (array_keys(homenas_dashboard_default_fan_labels()) as $sensorId) {
        if (!array_key_exists($sensorId, $postedFanLabels)) {
            continue;
        }
        $value = $postedFanLabels[$sensorId];
        $candidate['fan_labels'][$sensorId] = is_string($value) ? trim($value) : $value;
    }

    $candidate['case_delta']['enabled'] = isset($post['case_delta_enabled'])
        && $post['case_delta_enabled'] === '1';
    foreach (['intake_source', 'exhaust_source'] as $field) {
        $postField = 'case_delta_' . $field;
        if (array_key_exists($postField, $post)) {
            $candidate['case_delta'][$field] = $post[$postField];
        }
    }
    return $candidate;
}

function homenas_dashboard_save_settings_from_post(array $post, ?string $configDir = null): array
{
    $candidate = homenas_dashboard_settings_candidate_from_post($post);
    [$valid, $config, $errors] = homenas_dashboard_validate_config($candidate);
    if (!$valid) {
        return [false, $config, $errors];
    }

    [$written, $writeErrors] = homenas_dashboard_write_config($config, $configDir);
    return [$written, $config, $writeErrors];
}

function homenas_dashboard_unraid_csrf_token($unraidVar): string
{
    $token = is_array($unraidVar) ? ($unraidVar['csrf_token'] ?? null) : null;
    if (!is_string($token) || $token === '') {
        throw new RuntimeException('Unraid CSRF token unavailable');
    }
    return $token;
}

function homenas_dashboard_render_settings_page(array $state, string $csrfToken, array $errors = [], ?string $notice = null): string
{
    if ($csrfToken === '') {
        throw new RuntimeException('Unraid CSRF token unavailable');
    }
    $config = $state['config'];
    $labels = homenas_dashboard_settings_label_definitions();
    $sources = homenas_dashboard_temperature_source_definitions($config);
    $html = '<div class="homenas-dashboard-settings">';
    $html .= '<h1>HomeNas Monitoring</h1>';
    $html .= '<p>Configureer alleen zichtbare labels en de optionele, read-only Case ΔT-berekening.</p>';

    if ($state['status'] === 'defaults') {
        $html .= '<div class="notice">Er is nog geen settings.json. Generieke defaults worden getoond en pas opgeslagen na Save.</div>';
    }
    if ($notice !== null) {
        $html .= '<div class="notice">' . homenas_dashboard_html($notice) . '</div>';
    }
    if ($errors !== []) {
        $html .= '<div class="warning"><strong>Niet opgeslagen:</strong><ul>';
        foreach ($errors as $error) {
            $html .= '<li>' . homenas_dashboard_html((string) $error) . '</li>';
        }
        $html .= '</ul></div>';
    }

    $html .= '<form method="post" action="/plugins/homenas.dashboard/actions/save-settings.php">';
    $html .= '<input type="hidden" name="csrf_token" value="' . homenas_dashboard_html($csrfToken) . '">';
    $html .= '<fieldset><legend>Sensorlabels</legend>';
    foreach ($labels as $sensorId => $definition) {
        $label = $config['labels'][$sensorId];
        $html .= '<label for="label-' . homenas_dashboard_html($sensorId) . '">'
            . homenas_dashboard_html($definition['label']) . '</label>';
        $html .= '<input id="label-' . homenas_dashboard_html($sensorId) . '" type="text" maxlength="64" '
            . 'name="labels[' . homenas_dashboard_html($sensorId) . ']" value="'
            . homenas_dashboard_html($label) . '">';
    }
    $html .= '</fieldset>';

    $html .= '<fieldset><legend>Fan labels</legend>';
    foreach (homenas_dashboard_airflow_groups() as $groupName => $sensorIds) {
        $html .= '<h3 style="margin: 1em 0 0.5em">' . homenas_dashboard_html($groupName) . '</h3>';
        $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(14em,1fr));gap:0.75em 1.25em">';
        foreach ($sensorIds as $sensorId) {
            $physicalName = homenas_dashboard_default_fan_labels()[$sensorId];
            $html .= '<div><label for="fan-label-' . homenas_dashboard_html($sensorId) . '">'
                . homenas_dashboard_html($physicalName) . '</label><br>';
            $html .= '<input id="fan-label-' . homenas_dashboard_html($sensorId) . '" type="text" maxlength="64" '
                . 'name="fan_labels[' . homenas_dashboard_html($sensorId) . ']" value="'
                . homenas_dashboard_html($config['fan_labels'][$sensorId]) . '"></div>';
        }
        $html .= '</div>';
    }
    $html .= '</fieldset>';

    $checked = $config['case_delta']['enabled'] ? ' checked' : '';
    $html .= '<fieldset><legend>Case ΔT</legend>';
    $html .= '<p style="margin: 0.75em 0"><label><input type="checkbox" name="case_delta_enabled" value="1"' . $checked . '> Inschakelen</label></p>';
    foreach (['intake_source' => 'Intake-bron', 'exhaust_source' => 'Exhaust-bron'] as $field => $caption) {
        $html .= '<p style="margin: 0.75em 0"><label for="case-delta-' . $field . '">' . $caption . '</label> ';
        $html .= '<select id="case-delta-' . $field . '" name="case_delta_' . $field . '">';
        foreach ($sources as $sensorId => $displayLabel) {
            $selected = $config['case_delta'][$field] === $sensorId ? ' selected' : '';
            $html .= '<option value="' . homenas_dashboard_html($sensorId) . '"' . $selected . '>'
                . homenas_dashboard_html($displayLabel) . '</option>';
        }
        $html .= '</select></p>';
    }
    $html .= '</fieldset>';
    $html .= '<button type="submit">Save</button>';
    $html .= '</form></div>';
    return $html;
}
