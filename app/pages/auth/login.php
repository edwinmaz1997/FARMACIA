<?php
if (uid()) redirect(fid() ? 'dashboard' : 'admin');

$error = '';
if (is_post()) {
    csrf_check();
    $_SESSION['login_intentos'] = isset($_SESSION['login_intentos']) ? $_SESSION['login_intentos'] : 0;
    $_SESSION['login_bloqueo'] = isset($_SESSION['login_bloqueo']) ? $_SESSION['login_bloqueo'] : 0;
    if ($_SESSION['login_bloqueo'] > time()) {
        $error = 'Demasiados intentos. Espera un minuto e intenta de nuevo.';
    } else {
        $email = strtolower(p('email'));
        $u = one('SELECT * FROM usuarios WHERE email = ? LIMIT 1', [$email]);
        if (!$u || !password_verify(p('password'), $u['password_hash'])) {
            $_SESSION['login_intentos']++;
            if ($_SESSION['login_intentos'] >= 5) {
                $_SESSION['login_bloqueo'] = time() + 60;
                $_SESSION['login_intentos'] = 0;
            }
            $error = 'Correo o contraseña incorrectos.';
        } elseif (!$u['activo']) {
            $error = 'Tu usuario está desactivado.';
        } else {
            $farm = $u['farmacia_id'] ? one('SELECT * FROM farmacias WHERE id = ?', [$u['farmacia_id']]) : null;
            if ($u['rol'] !== 'superadmin' && (!$farm || $farm['estado'] === 'suspendida')) {
                $error = 'El acceso de esta farmacia está suspendido. Contacta a soporte.';
            } else {
                session_regenerate_id(true);
                $_SESSION['uid'] = (int) $u['id'];
                $_SESSION['nombre'] = $u['nombre'];
                $_SESSION['email'] = $u['email'];
                $_SESSION['rol'] = $u['rol'];
                $_SESSION['farmacia_id'] = $u['rol'] === 'superadmin' ? 0 : (int) $u['farmacia_id'];
                unset($_SESSION['login_intentos'], $_SESSION['login_bloqueo']);
                q('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?', [$u['id']]);
                redirect($u['rol'] === 'superadmin' ? 'admin' : 'dashboard');
            }
        }
    }
}

head_html('Iniciar sesión');
?>
<body class="min-h-screen bg-slate-50 font-sans antialiased">
<div class="min-h-screen grid lg:grid-cols-2">
  <div class="hidden lg:flex relative overflow-hidden bg-gradient-to-br from-brand-600 via-brand-700 to-teal-900 p-12 flex-col justify-between text-white">
    <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-white/10 blur-2xl"></div>
    <div class="absolute bottom-0 left-0 h-72 w-72 rounded-full bg-teal-300/20 blur-3xl"></div>
    <div class="relative flex items-center gap-3">
      <div class="h-11 w-11 rounded-2xl bg-white/15 backdrop-blur grid place-items-center"><i data-lucide="cross" class="w-6 h-6"></i></div>
      <span class="text-xl font-extrabold tracking-tight"><?= e($GLOBALS['config']['app_name']) ?></span>
    </div>
    <div class="relative max-w-md">
      <h1 class="text-4xl font-extrabold leading-tight tracking-tight">Tu farmacia, ordenada y bajo control.</h1>
      <p class="mt-4 text-brand-100">Ventas, inventario por lotes, alertas de vencimiento y reportes en tiempo real desde cualquier dispositivo.</p>
      <div class="mt-8 grid grid-cols-3 gap-3 text-sm">
        <div class="rounded-2xl bg-white/10 p-4"><i data-lucide="scan-barcode" class="w-5 h-5 mb-2"></i>Punto de venta</div>
        <div class="rounded-2xl bg-white/10 p-4"><i data-lucide="calendar-clock" class="w-5 h-5 mb-2"></i>Control de lotes</div>
        <div class="rounded-2xl bg-white/10 p-4"><i data-lucide="line-chart" class="w-5 h-5 mb-2"></i>Reportes</div>
      </div>
    </div>
    <div class="relative text-xs text-brand-200">© <?= date('Y') ?> <?= e($GLOBALS['config']['app_name']) ?></div>
  </div>
  <div class="flex items-center justify-center p-6">
    <div class="w-full max-w-sm">
      <div class="lg:hidden flex items-center gap-3 mb-8">
        <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-brand-400 to-brand-600 grid place-items-center text-white"><i data-lucide="cross" class="w-5 h-5"></i></div>
        <span class="text-lg font-extrabold"><?= e($GLOBALS['config']['app_name']) ?></span>
      </div>
      <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Bienvenido de nuevo</h2>
      <p class="text-sm text-slate-500 mt-1 mb-8">Ingresa tus credenciales para continuar.</p>
      <?php if ($error): ?>
        <div class="mb-5 flex items-center gap-2 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800"><i data-lucide="circle-x" class="w-4 h-4"></i><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post" class="space-y-4">
        <?= csrf_field() ?>
        <div>
          <label class="label">Correo electrónico</label>
          <input type="email" name="email" value="<?= e(p('email')) ?>" class="input" required autofocus>
        </div>
        <div>
          <label class="label">Contraseña</label>
          <div class="relative">
            <input type="password" name="password" id="pw" class="input pr-11" required>
            <button type="button" onclick="pw.type = pw.type === 'password' ? 'text' : 'password'" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"><i data-lucide="eye" class="w-4 h-4"></i></button>
          </div>
        </div>
        <button class="btn-primary w-full py-3">Ingresar <i data-lucide="arrow-right" class="w-4 h-4"></i></button>
      </form>
      <button data-instalar onclick="instalarApp()" class="hidden btn-light w-full mt-3"><i data-lucide="smartphone" class="w-4 h-4"></i> Instalar como app</button>
    </div>
  </div>
</div>
<?php install_ui(); ?>
<script>lucide.createIcons();</script>
</body>
</html>
