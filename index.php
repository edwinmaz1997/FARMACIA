<?php
if (!file_exists(__DIR__ . '/config/config.local.php')) {
    header('Location: install.php');
    exit;
}

require __DIR__ . '/app/bootstrap.php';

$routes = [
    // Público
    'login' => 'auth/login.php',
    'logout' => 'auth/logout.php',
    'perfil' => 'auth/perfil.php',
    // Farmacia
    'dashboard' => 'farmacia/dashboard.php',
    'pos' => 'farmacia/pos.php',
    'ventas' => 'farmacia/ventas.php',
    'ticket' => 'farmacia/ticket.php',
    'productos' => 'farmacia/productos.php',
    'inventario' => 'farmacia/inventario.php',
    'kardex' => 'farmacia/kardex.php',
    'clientes' => 'farmacia/clientes.php',
    'proveedores' => 'farmacia/proveedores.php',
    'compras' => 'farmacia/compras.php',
    'gastos' => 'farmacia/gastos.php',
    'reportes' => 'farmacia/reportes.php',
    'usuarios' => 'farmacia/usuarios.php',
    'configuracion' => 'farmacia/configuracion.php',
    'api' => 'farmacia/api.php',
    // Superadmin
    'admin' => 'admin/dashboard.php',
    'admin_farmacias' => 'admin/farmacias.php',
    'admin_pagos' => 'admin/pagos.php',
    'admin_reportes' => 'admin/reportes.php',
    'admin_usuarios' => 'admin/usuarios.php',
    'admin_acceder' => 'admin/acceder.php',
    'admin_salir' => 'admin/salir.php',
];

$page = g('p', '');
if ($page === '') {
    if (!uid()) $page = 'login';
    elseif (fid()) $page = 'dashboard';
    elseif (is_super()) $page = 'admin';
    else $page = 'logout';
}

if (!isset($routes[$page])) {
    http_response_code(404);
    $page = uid() ? (fid() ? 'dashboard' : 'admin') : 'login';
}

try {
    require __DIR__ . '/app/pages/' . $routes[$page];
} catch (Throwable $ex) {
    error_log($ex);
    http_response_code(500);
    if ($page === 'api') {
        json_out(['ok' => false, 'error' => $GLOBALS['config']['debug'] ? $ex->getMessage() : 'Error interno del servidor'], 500);
    }
    echo '<div style="font-family:system-ui;padding:40px;max-width:640px;margin:auto"><h2>Ocurrió un error</h2><p>Intenta de nuevo. Si persiste, contacta a soporte.</p>';
    if ($GLOBALS['config']['debug']) echo '<pre style="white-space:pre-wrap;background:#f1f5f9;padding:12px;border-radius:8px">' . e($ex->getMessage() . "\n" . $ex->getFile() . ':' . $ex->getLine()) . '</pre>';
    echo '<p><a href="index.php">Volver al inicio</a></p></div>';
}
