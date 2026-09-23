<?php

function homenas_dashboard_sensor_catalog(): array
{
    return [
        'board.chipset' => ['label' => 'Chipset', 'unit' => 'C', 'family' => 'asus_ec', 'features' => ['Chipset']],
        'board.motherboard' => ['label' => 'Motherboard', 'unit' => 'C', 'family' => 'asus_ec', 'features' => ['MB Temp', 'Motherboard']],
        'board.vrm' => ['label' => 'VRM', 'unit' => 'C', 'family' => 'asus_ec', 'features' => ['VRM']],
        'board.t_sensor' => ['label' => 'T_Sensor', 'unit' => 'C', 'family' => 'asus_ec', 'features' => ['T_Sensor']],
        'board.ext_sensor1' => ['label' => 'EXT_Sensor1', 'unit' => 'C', 'family' => 'asus_ec', 'features' => ['EXT_Sensor1']],
        'board.ext_sensor2' => ['label' => 'EXT_Sensor2', 'unit' => 'C', 'family' => 'asus_ec', 'features' => ['EXT_Sensor2']],
        'board.ext_sensor3' => ['label' => 'EXT_Sensor3', 'unit' => 'C', 'family' => 'asus_ec', 'features' => ['EXT_Sensor3']],
        'cpu.package' => ['label' => 'CPU Package', 'unit' => 'C', 'family' => 'coretemp', 'features' => ['Package id 0', 'Package']],
        'storage.nvme.slot1' => ['label' => 'NVMe 1', 'unit' => 'C', 'family' => 'nvme', 'features' => ['Composite']],
        'storage.nvme.slot2' => ['label' => 'NVMe 2', 'unit' => 'C', 'family' => 'nvme', 'features' => ['Composite']],
        'board.nct.fan1' => ['label' => 'NCT Fan 1', 'unit' => 'RPM', 'family' => 'nct', 'feature' => 'fan1_input'],
        'board.nct.fan2' => ['label' => 'NCT Fan 2', 'unit' => 'RPM', 'family' => 'nct', 'feature' => 'fan2_input'],
        'board.nct.fan3' => ['label' => 'NCT Fan 3', 'unit' => 'RPM', 'family' => 'nct', 'feature' => 'fan3_input'],
        'board.nct.fan4' => ['label' => 'NCT Fan 4', 'unit' => 'RPM', 'family' => 'nct', 'feature' => 'fan4_input'],
        'board.nct.fan5' => ['label' => 'NCT Fan 5', 'unit' => 'RPM', 'family' => 'nct', 'feature' => 'fan5_input'],
        'board.nct.fan6' => ['label' => 'NCT Fan 6', 'unit' => 'RPM', 'family' => 'nct', 'feature' => 'fan6_input'],
        'board.nct.fan7' => ['label' => 'NCT Fan 7', 'unit' => 'RPM', 'family' => 'nct', 'feature' => 'fan7_input'],
        'board.cpu_opt_fan' => ['label' => 'CPU_OPT', 'unit' => 'RPM', 'family' => 'asus_ec', 'features' => ['CPU_Opt', 'CPU_OPT']],
        'board.ext_fan1' => ['label' => 'EXT_FAN1', 'unit' => 'RPM', 'family' => 'asus_ec', 'features' => ['EXT_FAN1']],
        'board.ext_fan2' => ['label' => 'EXT_FAN2', 'unit' => 'RPM', 'family' => 'asus_ec', 'features' => ['EXT_FAN2']],
        'board.ext_fan3' => ['label' => 'EXT_FAN3', 'unit' => 'RPM', 'family' => 'asus_ec', 'features' => ['EXT_FAN3']],
    ];
}

function homenas_dashboard_endpoint_sensor_ids(): array
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
        'cpu.package',
        'board.nct.fan1',
        'board.nct.fan2',
        'board.nct.fan3',
        'board.nct.fan4',
        'board.nct.fan5',
        'board.nct.fan6',
        'board.nct.fan7',
        'board.cpu_opt_fan',
        'board.ext_fan1',
        'board.ext_fan2',
        'board.ext_fan3',
    ];
}

function homenas_dashboard_airflow_groups(): array
{
    return [
        'CPU Cooling' => ['board.nct.fan2', 'board.cpu_opt_fan'],
        'Case / Chassis' => ['board.nct.fan1', 'board.nct.fan3', 'board.nct.fan4', 'board.nct.fan5'],
        'Pumps' => ['board.nct.fan6', 'board.nct.fan7'],
        'Fan Extension' => ['board.ext_fan1', 'board.ext_fan2', 'board.ext_fan3'],
    ];
}
