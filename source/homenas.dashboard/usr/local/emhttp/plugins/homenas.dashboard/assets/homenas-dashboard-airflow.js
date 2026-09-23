(() => {
    'use strict';

    const endpoint = '/plugins/homenas.dashboard/include/temperatures.php';
    const formatRpm = (record) => record && record.available
        && record.value !== null && record.value !== undefined
        && Number.isFinite(Number(record.value))
        ? `${Math.round(Number(record.value))} RPM`
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
            document.querySelectorAll('.homenas-dashboard-airflow-item[data-sensor-id]').forEach((item) => {
                const record = records[item.dataset.sensorId];
                const value = item.querySelector('.homenas-dashboard-airflow-value');
                const label = item.querySelector('.homenas-dashboard-airflow-label');
                if (value) {
                    value.textContent = formatRpm(record);
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
