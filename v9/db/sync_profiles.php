<?php
/**
 * GeriApp — Perfiles de conexión para sincronización de BD
 *
 * Reads profiles from sync_profiles.json. If the file doesn't exist,
 * it creates it with the default profiles on first load.
 */

$jsonFile = dirname(__DIR__) . '/secretos/sync_profiles.json';

if (!file_exists($jsonFile)) {
    $defaults = [
        'produccion' => [
            'label'   => 'Hostinger (Producción)',
            'host'    => '193.203.166.205',
            'port'    => 3306,
            'user'    => 'u124132715_root',
            'pass'    => 'Pellu8aa1!',
            'db'      => 'u124132715_geriapp',
            'prefix'  => 'geriapp_i',
            'charset' => 'utf8mb4',
        ],
        'local' => [
            'label'   => 'XAMPP Local',
            'host'    => '127.0.0.1',
            'port'    => 3306,
            'user'    => 'root',
            'pass'    => '',
            'db'      => 'geriapp',
            'prefix'  => 'geriapp_i',
            'charset' => 'utf8mb4',
        ],
    ];
    file_put_contents($jsonFile, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

return json_decode(file_get_contents($jsonFile), true);
