<?php
require_super();
$_SESSION['farmacia_id'] = 0;

if (is_post()) {
    csrf_check();
    $acc = p('accion');
    if ($acc === 'crear') {
        $email = strtolower(p('email'));
        if (p('nombre') === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen(p('password')) < 8) {
            flash('error', 'Datos inválidos (contraseña mínimo 8 caracteres).');
        } elseif (val('SELECT COUNT(*) FROM usuarios WHERE email = ?', [$email])) {
            flash('error', 'Ese correo ya existe.');
        } else {
            insert('usuarios', ['farmacia_id' => null, 'nombre' => p('nombre'), 'email' => $email, 'password_hash' => password_hash(p('password'), PASSWORD_DEFAULT), 'rol' => 'superadmin']);
            flash('success', 'Superadministrador creado.');
        }
    } elseif ($acc === 'toggle') {
        $id = (int) p('id');
        if ($id === uid()) {
            flash('error', 'No puedes desactivarte a ti mismo.');
        } else {
            q("UPDATE usuarios SET activo = 1 - activo WHERE id = ? AND rol = 'superadmin'", [$id]);
            flash('success', 'Usuario actualizado.');
        }
    }
    redirect('admin_usuarios');
}

$lista = all("SELECT * FROM usuarios WHERE rol = 'superadmin' ORDER BY nombre");
layout_start('Superadministradores', 'admin_usuarios');
page_header('Superadministradores', 'Usuarios con acceso total a la plataforma.');
?>
<div class="grid lg:grid-cols-3 gap-6">
  <div class="card overflow-hidden lg:col-span-2">
    <div class="overflow-x-auto">
      <table class="tbl">
        <thead><tr><th>Nombre</th><th>Correo</th><th>Último acceso</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $u): ?>
          <tr>
            <td class="font-semibold"><?= e($u['nombre']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td class="text-slate-500"><?= fdatetime($u['ultimo_acceso']) ?></td>
            <td><?= $u['activo'] ? badge('Activo', 'emerald') : badge('Inactivo', 'rose') ?></td>
            <td class="text-right"><?php if ((int) $u['id'] !== uid()): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="toggle"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn-light btn-sm"><?= $u['activo'] ? 'Desactivar' : 'Activar' ?></button></form><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <form method="post" class="card p-6 space-y-4 h-fit">
    <?= csrf_field() ?><input type="hidden" name="accion" value="crear">
    <h3 class="font-bold">Nuevo superadmin</h3>
    <div><label class="label">Nombre</label><input name="nombre" class="input" required></div>
    <div><label class="label">Correo</label><input type="email" name="email" class="input" required></div>
    <div><label class="label">Contraseña</label><input type="password" name="password" class="input" minlength="8" required></div>
    <button class="btn-primary w-full"><i data-lucide="user-plus" class="w-4 h-4"></i> Crear</button>
  </form>
</div>
<?php layout_end();
