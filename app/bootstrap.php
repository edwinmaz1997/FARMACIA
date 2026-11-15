<?php
// Núcleo: configuración, sesión, base de datos y utilidades. Compatible con PHP 7.4+.
$GLOBALS['config'] = require __DIR__ . '/../config/config.php';
date_default_timezone_set($GLOBALS['config']['timezone']);

if ($GLOBALS['config']['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('FARMASESS');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/layout.php';
