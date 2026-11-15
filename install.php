<?php
// Instalador: crea config/config.local.php, las tablas y el primer superadministrador.
// Se bloquea automáticamente una vez instalado.
$localFile = __DIR__ . '/config/config.local.php';
$error = '';
$ok = false;

if (file_exists($localFile)) {
    http_response_code(403);
    exit('<div style="font-family:system-ui;padding:40px;text-align:center">El sistema ya está instalado. <a href="index.php">Ir al inicio</a></div>');
}

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['db_host'] ?? 'localhost');
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass'] ?? '';
    $aNombre = trim($_POST['admin_nombre'] ?? '');
    $aEmail = strtolower(trim($_POST['admin_email'] ?? ''));
    $aPass = $_POST['admin_pass'] ?? '';
    try {
        if ($name === '' || $user === '') throw new Exception('Completa los datos de la base de datos.');
        if ($aNombre === '' || !filter_var($aEmail, FILTER_VALIDATE_EMAIL)) throw new Exception('Nombre y correo válidos del superadmin son obligatorios.');
        if (strlen($aPass) < 8) throw new Exception('La contraseña debe tener al menos 8 caracteres.');

        $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $sql = file_get_contents(__DIR__ . '/database/schema.sql');
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            $pdo->exec($stmt);
        }
        $st = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ?');
        $st->execute([$aEmail]);
        if ((int) $st->fetchColumn() === 0) {
            $pdo->prepare("INSERT INTO usuarios (farmacia_id, nombre, email, password_hash, rol) VALUES (NULL, ?, ?, ?, 'superadmin')")
                ->execute([$aNombre, $aEmail, password_hash($aPass, PASSWORD_DEFAULT)]);
        }
        $conf = "<?php\nreturn " . var_export([
            'db_host' => $host,
            'db_name' => $name,
            'db_user' => $user,
            'db_pass' => $pass,
            'debug' => false,
        ], true) . ";\n";
        if (@file_put_contents($localFile, $conf) === false) {
            throw new Exception('No se pudo escribir config/config.local.php. Revisa permisos de la carpeta config.');
        }
        $ok = true;
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalación · FarmaPro</title>
<link rel="stylesheet" href="assets/app.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-emerald-50 via-white to-teal-50 grid place-items-center p-4 font-sans">
<div class="w-full max-w-xl bg-white rounded-3xl shadow-xl border border-slate-100 p-8">
  <h1 class="text-2xl font-extrabold text-slate-900">Instalación de FarmaPro</h1>
  <p class="text-sm text-slate-500 mt-1 mb-6">Conecta la base de datos MySQL y crea tu usuario superadministrador.</p>
  <?php if ($ok): ?>
    <div class="rounded-xl bg-emerald-50 text-emerald-800 p-4 text-sm mb-4">Instalación completada. El instalador quedó bloqueado.</div>
    <a href="index.php" class="block text-center rounded-xl bg-emerald-600 text-white font-semibold py-3">Iniciar sesión</a>
  <?php else: ?>
    <?php if ($error): ?><div class="rounded-xl bg-rose-50 text-rose-800 p-4 text-sm mb-4"><?= h($error) ?></div><?php endif; ?>
    <form method="post" class="space-y-4">
      <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Base de datos</div>
      <div class="grid sm:grid-cols-2 gap-3">
        <input name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>" placeholder="Host" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
        <input name="db_name" value="<?= h($_POST['db_name'] ?? '') ?>" placeholder="Nombre de la base" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm" required>
        <input name="db_user" value="<?= h($_POST['db_user'] ?? '') ?>" placeholder="Usuario" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm" required>
        <input name="db_pass" type="password" placeholder="Contraseña" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
      </div>
      <div class="text-xs font-bold uppercase tracking-wider text-slate-400 pt-2">Superadministrador</div>
      <input name="admin_nombre" value="<?= h($_POST['admin_nombre'] ?? '') ?>" placeholder="Nombre" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" required>
      <input name="admin_email" type="email" value="<?= h($_POST['admin_email'] ?? '') ?>" placeholder="Correo" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" required>
      <input name="admin_pass" type="password" placeholder="Contraseña (mín. 8)" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm" required>
      <button class="w-full rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3">Instalar</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
