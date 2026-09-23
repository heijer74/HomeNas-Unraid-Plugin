'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(
    __dirname,
    '../source/homenas.dashboard/usr/local/emhttp/plugins/homenas.dashboard/assets/homenas-dashboard-temperature.js'
), 'utf8');

function makeItem(sensorId) {
    const value = { textContent: 'N/A' };
    const label = { textContent: 'default' };
    return {
        dataset: { sensorId },
        value,
        label,
        querySelector(selector) {
            return selector.endsWith('-value') ? value : label;
        },
    };
}

async function main() {
    const present = makeItem('board.chipset');
    const missing = makeItem('board.ext_sensor1');
    const delta = makeItem('derived.case_delta_t');
    const items = [present, missing, delta];
    const requests = [];
    const intervals = [];
    const payload = {
        sensors: [
            { sensor_id: 'board.chipset', display_label: 'Chipset', available: true, value: 47.25 },
            { sensor_id: 'board.ext_sensor1', display_label: 'Intake', available: false, value: null },
        ],
        case_delta_t: { display_label: 'Case ΔT', available: true, value: 4.5 },
    };

    vm.runInNewContext(source, {
        fetch(url, options) {
            requests.push({ url, options });
            return Promise.resolve({ ok: true, json: () => Promise.resolve(payload) });
        },
        document: { querySelectorAll: () => items },
        setInterval(callback, milliseconds) { intervals.push({ callback, milliseconds }); },
    });
    await new Promise((resolve) => setImmediate(resolve));

    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, '/plugins/homenas.dashboard/include/temperatures.php');
    assert.equal(requests[0].options.cache, 'no-store');
    assert.equal(intervals.length, 1);
    assert.equal(intervals[0].milliseconds, 5000);
    assert.equal(present.value.textContent, '47.3°C');
    assert.equal(present.label.textContent, 'Chipset');
    assert.equal(missing.value.textContent, 'N/A');
    assert.equal(missing.label.textContent, 'Intake');
    assert.equal(delta.value.textContent, '4.5°C');
    assert.equal(delta.label.textContent, 'Case ΔT');

    console.log('PASS temperature tile JS updates');
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
