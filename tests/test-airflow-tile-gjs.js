'use strict';

const GLib = imports.gi.GLib;
const ByteArray = imports.byteArray;
const sourcePath = GLib.build_filenamev([
    GLib.get_current_dir(),
    'source/homenas.dashboard/usr/local/emhttp/plugins/homenas.dashboard/assets/homenas-dashboard-airflow.js',
]);
const [, bytes] = GLib.file_get_contents(sourcePath);
const source = ByteArray.toString(bytes);

function assert(condition, message) {
    if (!condition)
        throw new Error(message);
}

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

new Function(source);
const ids = [
    'board.nct.fan2', 'board.cpu_opt_fan',
    'board.nct.fan1', 'board.nct.fan3', 'board.nct.fan4', 'board.nct.fan5',
    'board.nct.fan6', 'board.nct.fan7',
    'board.ext_fan1', 'board.ext_fan2', 'board.ext_fan3',
];
const items = ids.map(makeItem);
const requests = [];
const intervals = [];
let payload = {
    sensors: [
        { sensor_id: 'board.nct.fan1', display_label: 'Front intake', available: true, value: 0 },
        { sensor_id: 'board.cpu_opt_fan', display_label: 'CPU_OPT', available: true, value: 920 },
        { sensor_id: 'board.ext_fan1', display_label: 'EXT_FAN1', available: false, value: null },
    ],
};
globalThis.fetch = (url, options) => {
    requests.push({ url, options });
    return Promise.resolve({ ok: true, json: () => Promise.resolve(payload) });
};
globalThis.document = { querySelectorAll: () => items };
globalThis.setInterval = (callback, milliseconds) => intervals.push({ callback, milliseconds });

eval(source);
const loop = new GLib.MainLoop(null, false);
let failure = null;
GLib.timeout_add(GLib.PRIORITY_DEFAULT, 50, () => {
    try {
        assert(requests.length === 1 && requests[0].url === '/plugins/homenas.dashboard/include/temperatures.php', 'Airflow endpoint changed');
        assert(requests[0].options.cache === 'no-store', 'Airflow fetch must bypass cache');
        assert(intervals.length === 1 && intervals[0].milliseconds === 5000, 'Airflow refresh interval changed');
        assert(items[2].value.textContent === '0 RPM', '0 RPM must remain visible');
        assert(items[2].label.textContent === 'Front intake', 'custom label must update');
        assert(items[1].value.textContent === '920 RPM', 'CPU_OPT RPM missing');
        assert(items[8].value.textContent === 'N/A', 'unavailable EXT_FAN must show N/A');
        payload = { sensors: [
            { sensor_id: 'board.cpu_opt_fan', display_label: 'CPU_OPT', available: true, value: 0 },
            { sensor_id: 'board.ext_fan1', display_label: 'EXT_FAN1', available: true, value: 1234 },
        ] };
        intervals[0].callback();
    } catch (error) {
        failure = error;
        loop.quit();
        return GLib.SOURCE_REMOVE;
    }
    GLib.timeout_add(GLib.PRIORITY_DEFAULT, 50, () => {
        try {
            assert(requests.length === 2, 'poll callback did not refresh endpoint');
            assert(items[1].value.textContent === '0 RPM', 'CPU_OPT 0 RPM must remain visible');
            assert(items[8].value.textContent === '1234 RPM', 'EXT_FAN1 RPM did not update');
            assert(items[2].value.textContent === 'N/A', 'missing record must show N/A');
            assert(!source.includes('homenas-dashboard-chevron'), 'plugin chevron handler remains');
        } catch (error) {
            failure = error;
        }
        loop.quit();
        return GLib.SOURCE_REMOVE;
    });
    return GLib.SOURCE_REMOVE;
});
loop.run();
if (failure)
    throw failure;
print('PASS Airflow tile GJS updates');
