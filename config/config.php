<?php
// Configuración base. Los datos reales se guardan en config.local.php (lo crea install.php y no se sube por el deploy).
$config = [
    'db_host' => 'localhost',
    'db_name' => '',
    'db_user' => '',
    'db_pass' => '',
    'app_name' => 'FarmaPro',
    'timezone' => 'America/Guatemala',
    'debug' => false,
];
if (file_exists(__DIR__ . '/config.local.php')) {
    $local = require __DIR__ . '/config.local.php';
    if (is_array($local)) {
        $config = array_merge($config, $local);
    }
}
return $config;
