'use strict';

const GLib = imports.gi.GLib;
const ByteArray = imports.byteArray;
const sourcePath = GLib.build_filenamev([
    GLib.get_current_dir(),
    'source/homenas.dashboard/usr/local/emhttp/plugins/homenas.dashboard/assets/homenas-dashboard-temperature.js',
]);
const [, bytes] = GLib.file_get_contents(sourcePath);
const source = ByteArray.toString(bytes);

function assert(condition, message) {
    if (!condition)
        throw new Error(message);
}

function item(sensorId) {
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

new Function(source); // Parse the shipped asset before running it.
const present = item('board.chipset');
const missing = item('board.ext_sensor1');
const delta = item('derived.case_delta_t');
const requests = [];
const intervals = [];
const payload = {
    sensors: [
        { sensor_id: 'board.chipset', display_label: 'Chipset', available: true, value: 47.25 },
        { sensor_id: 'board.ext_sensor1', display_label: 'Intake', available: false, value: null },
    ],
    case_delta_t: { display_label: 'Case ΔT', available: true, value: 4.5 },
};

globalThis.fetch = (url, options) => {
    requests.push({ url, options });
    return Promise.resolve({ ok: true, json: () => Promise.resolve(payload) });
};
globalThis.document = { querySelectorAll: () => [present, missing, delta] };
globalThis.setInterval = (callback, milliseconds) => intervals.push({ callback, milliseconds });

eval(source);
const loop = new GLib.MainLoop(null, false);
let failure = null;
GLib.timeout_add(GLib.PRIORITY_DEFAULT, 50, () => {
    try {
        assert(requests.length === 1, 'one initial endpoint request expected');
        assert(requests[0].url === '/plugins/homenas.dashboard/include/temperatures.php', 'endpoint changed');
        assert(requests[0].options.cache === 'no-store', 'fetch cache policy changed');
        assert(intervals.length === 1 && intervals[0].milliseconds === 5000, 'five-second refresh changed');
        assert(present.value.textContent === '47.3°C', 'available temperature not formatted');
        assert(present.label.textContent === 'Chipset', 'available label not refreshed');
        assert(missing.value.textContent === 'N/A', 'missing temperature must remain N/A');
        assert(missing.label.textContent === 'Intake', 'configured label not refreshed');
        assert(delta.value.textContent === '4.5°C', 'Case Delta not formatted');
        assert(delta.label.textContent === 'Case ΔT', 'Case Delta label changed');
        assert(!source.includes('homenas-dashboard-chevron'), 'plugin chevron handler remains');
    } catch (error) {
        failure = error;
    }
    loop.quit();
    return GLib.SOURCE_REMOVE;
});
loop.run();
if (failure)
    throw failure;
print('PASS temperature tile GJS updates');
