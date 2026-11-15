<?php
require_login();
$u = one('SELECT * FROM usuarios WHERE id = ?', [uid()]);

if (is_post()) {
    csrf_check();
    $nombre = p('nombre');
    if ($nombre === '') {
        flash('error', 'El nombre es obligatorio.');
        redirect('perfil');
    }
    update('usuarios', ['nombre' => $nombre], 'id = ?', [uid()]);
    $_SESSION['nombre'] = $nombre;
    if (p('nueva') !== '') {
        if (!password_verify(p('actual'), $u['password_hash'])) {
            flash('error', 'La contraseña actual no es correcta.');
            redirect('perfil');
        }
        if (strlen(p('nueva')) < 8 || p('nueva') !== p('confirmar')) {
            flash('error', 'La nueva contraseña debe tener al menos 8 caracteres y coincidir con la confirmación.');
            redirect('perfil');
        }
        update('usuarios', ['password_hash' => password_hash(p('nueva'), PASSWORD_DEFAULT)], 'id = ?', [uid()]);
    }
    flash('success', 'Perfil actualizado.');
    redirect('perfil');
}

layout_start('Mi perfil', 'perfil');
page_header('Mi perfil', 'Actualiza tu nombre y contraseña.');
?>
<form method="post" class="card p-6 max-w-xl space-y-4">
  <?= csrf_field() ?>
  <div><label class="label">Nombre</label><input name="nombre" value="<?= e($u['nombre']) ?>" class="input" required></div>
  <div><label class="label">Correo</label><input value="<?= e($u['email']) ?>" class="input bg-slate-50" disabled></div>
  <div class="pt-2 text-xs font-bold uppercase tracking-wider text-slate-400">Cambiar contraseña</div>
  <div><label class="label">Contraseña actual</label><input type="password" name="actual" class="input"></div>
  <div class="grid sm:grid-cols-2 gap-4">
    <div><label class="label">Nueva contraseña</label><input type="password" name="nueva" class="input" minlength="8"></div>
    <div><label class="label">Confirmar</label><input type="password" name="confirmar" class="input"></div>
  </div>
  <button class="btn-primary"><i data-lucide="save" class="w-4 h-4"></i> Guardar</button>
</form>
<?php layout_end();
