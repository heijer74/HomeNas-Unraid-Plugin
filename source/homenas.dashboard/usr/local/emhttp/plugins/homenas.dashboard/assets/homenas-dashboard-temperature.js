(() => {
    'use strict';

    const endpoint = '/plugins/homenas.dashboard/include/temperatures.php';
    const formatValue = (record) => record && record.available && Number.isFinite(Number(record.value))
        ? `${Number(record.value).toFixed(1)}°C`
        : 'N/A';

    const update = () => fetch(endpoint, { cache: 'no-store' })
        .then((response) => {
            if (!response.ok) {
                throw new Error('endpoint unavailable');
            }
            return response.json();
        })
        .then((payload) => {
            const records = {};
            (payload.sensors || []).forEach((record) => {
                records[record.sensor_id] = record;
            });

            document.querySelectorAll('.homenas-dashboard-temperature-item[data-sensor-id]').forEach((item) => {
                const sensorId = item.dataset.sensorId;
                const record = sensorId === 'derived.case_delta_t'
                    ? payload.case_delta_t
                    : records[sensorId];
                const value = item.querySelector('.homenas-dashboard-temperature-value');
                const label = item.querySelector('.homenas-dashboard-temperature-label');
                if (value) {
                    value.textContent = formatValue(record);
                }
                if (label && record && record.display_label) {
                    label.textContent = record.display_label;
                }
            });
        })
        .catch(() => {});

    update();
    setInterval(update, 5000);
})();
