<?php
require_admin();
$F = fid();

if (is_post()) {
    csrf_check();
    $acc = p('accion');
    $id = (int) p('id');
    if ($acc === 'guardar') {
        $email = strtolower(p('email'));
        $rol = p('rol') === 'admin' ? 'admin' : 'vendedor';
        if (p('nombre') === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Nombre y correo válidos son obligatorios.');
        } elseif (val('SELECT COUNT(*) FROM usuarios WHERE email = ? AND id <> ?', [$email, $id])) {
            flash('error', 'Ese correo ya está en uso.');
        } elseif (!$id && strlen(p('password')) < 8) {
            flash('error', 'La contraseña debe tener al menos 8 caracteres.');
        } else {
            $d = ['nombre' => p('nombre'), 'email' => $email, 'rol' => $rol];
            if (p('password') !== '') {
                if (strlen(p('password')) < 8) {
                    flash('error', 'La contraseña debe tener al menos 8 caracteres.');
                    redirect('usuarios');
                }
                $d['password_hash'] = password_hash(p('password'), PASSWORD_DEFAULT);
            }
            if ($id) {
                if ($id === uid() && $rol !== 'admin') {
                    flash('error', 'No puedes quitarte el rol de administrador.');
                    redirect('usuarios');
                }
                update('usuarios', $d, "id = ? AND farmacia_id = ? AND rol <> 'superadmin'", [$id, $F]);
            } else {
                $d['farmacia_id'] = $F;
                insert('usuarios', $d);
            }
            flash('success', 'Usuario guardado.');
        }
    }
    if ($acc === 'toggle') {
        if ($id === uid()) flash('error', 'No puedes desactivar tu propio usuario.');
        else {
            q('UPDATE usuarios SET activo = 1 - activo WHERE id = ? AND farmacia_id = ?', [$id, $F]);
            flash('success', 'Usuario actualizado.');
        }
    }
    redirect('usuarios');
}

$lista = all("SELECT u.*, (SELECT COUNT(*) FROM ventas v WHERE v.usuario_id = u.id AND v.estado='completada' AND v.fecha >= DATE_FORMAT(CURDATE(),'%Y-%m-01')) ventas_mes
    FROM usuarios u WHERE u.farmacia_id = ? ORDER BY u.activo DESC, u.rol, u.nombre", [$F]);

layout_start('Usuarios', 'usuarios');
page_header('Usuarios', 'Administradores tienen acceso completo. Vendedores solo pueden vender, ver productos/inventario y gestionar clientes.', '<button onclick="editar(null)" class="btn-primary"><i data-lucide="user-plus" class="w-4 h-4"></i> Nuevo usuario</button>');
?>
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
  <?php foreach ($lista as $u): ?>
    <div class="card p-5 <?= $u['activo'] ? '' : 'opacity-60' ?>">
      <div class="flex items-center gap-3">
        <div class="h-11 w-11 rounded-full <?= $u['rol'] === 'admin' ? 'bg-indigo-600' : 'bg-brand-600' ?> text-white grid place-items-center font-bold"><?= e(strtoupper(mb_substr($u['nombre'], 0, 1))) ?></div>
        <div class="flex-1 min-w-0"><div class="font-bold truncate"><?= e($u['nombre']) ?></div><div class="text-xs text-slate-500 truncate"><?= e($u['email']) ?></div></div>
        <?= badge($u['rol'] === 'admin' ? 'Admin' : 'Vendedor', $u['rol'] === 'admin' ? 'indigo' : 'emerald') ?>
      </div>
      <div class="flex justify-between text-xs text-slate-500 mt-4"><span>Ventas este mes: <b class="text-slate-700"><?= num($u['ventas_mes']) ?></b></span><span>Último acceso: <?= $u['ultimo_acceso'] ? date('d/m H:i', strtotime($u['ultimo_acceso'])) : 'nunca' ?></span></div>
      <div class="flex gap-2 mt-4 pt-4 border-t border-slate-100">
        <button onclick='editar(<?= json_encode(['id' => $u['id'], 'nombre' => $u['nombre'], 'email' => $u['email'], 'rol' => $u['rol']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="btn-light btn-sm flex-1"><i data-lucide="pencil" class="w-3.5 h-3.5"></i> Editar</button>
        <?php if ((int) $u['id'] !== uid()): ?>
          <form method="post" class="flex-1"><?= csrf_field() ?><input type="hidden" name="accion" value="toggle"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn-light btn-sm w-full"><?= $u['activo'] ? 'Desactivar' : 'Activar' ?></button></form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div id="modal" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm grid place-items-center p-4" onclick="if(event.target===this)this.classList.add('hidden')">
  <form method="post" id="fu" class="card w-full max-w-md p-6 space-y-4">
    <?= csrf_field() ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id">
    <h3 class="font-bold text-lg" id="mTitulo">Usuario</h3>
    <div><label class="label">Nombre</label><input name="nombre" class="input" required></div>
    <div><label class="label">Correo (para iniciar sesión)</label><input type="email" name="email" class="input" required></div>
    <div><label class="label">Rol</label><select name="rol" class="input"><option value="vendedor">Vendedor</option><option value="admin">Administrador</option></select></div>
    <div><label class="label">Contraseña <span id="pwHint" class="font-normal text-slate-400"></span></label><input type="text" name="password" class="input" minlength="8" autocomplete="new-password"></div>
    <div class="flex gap-2 justify-end"><button type="button" class="btn-light" onclick="modal.classList.add('hidden')">Cancelar</button><button class="btn-primary">Guardar</button></div>
  </form>
</div>
<script>
function editar(u) {
  fu.reset(); fu.elements['id'].value = u ? u.id : ''; fu.nombre.value = u ? u.nombre : ''; fu.email.value = u ? u.email : ''; fu.rol.value = u ? u.rol : 'vendedor';
  fu.password.required = !u; pwHint.textContent = u ? '(dejar vacío para no cambiar)' : '(mínimo 8 caracteres)';
  mTitulo.textContent = u ? 'Editar usuario' : 'Nuevo usuario'; modal.classList.remove('hidden'); fu.nombre.focus();
}
</script>
<?php layout_end();
