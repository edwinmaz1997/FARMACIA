<?php
require_super();
$_SESSION['farmacia_id'] = 0;
$a = g('a', 'lista');
$ciclos = ['mensual' => 'Mensual', 'trimestral' => 'Trimestral', 'semestral' => 'Semestral', 'anual' => 'Anual'];

function farmacia_datos_post()
{
    return [
        'nombre' => p('nombre'),
        'nit' => nulo(p('nit')),
        'telefono' => nulo(p('telefono')),
        'email' => nulo(p('email')),
        'direccion' => nulo(p('direccion')),
        'responsable' => nulo(p('responsable')),
        'plan' => p('plan') ?: 'Básico',
        'precio_suscripcion' => (float) p('precio_suscripcion'),
        'ciclo' => in_array(p('ciclo'), ['mensual', 'trimestral', 'semestral', 'anual']) ? p('ciclo') : 'mensual',
        'notas' => nulo(p('notas')),
    ];
}

// ---------- Acciones POST ----------
if (is_post()) {
    csrf_check();
    $acc = p('accion');

    if ($acc === 'guardar') {
        $id = (int) p('id');
        $d = farmacia_datos_post();
        if ($d['nombre'] === '') {
            flash('error', 'El nombre es obligatorio.');
            back();
        }
        if ($id) {
            $d['fecha_vencimiento'] = p('fecha_vencimiento') ?: date('Y-m-d');
            update('farmacias', $d, 'id = ?', [$id]);
            if ($err = procesar_logo($id)) flash('error', $err);
            else flash('success', 'Farmacia actualizada.');
            redirect('admin_farmacias', ['a' => 'ver', 'id' => $id]);
        }
        $email = strtolower(p('admin_email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen(p('admin_pass')) < 8 || p('admin_nombre') === '') {
            flash('error', 'Completa los datos del usuario administrador (correo válido y contraseña de al menos 8 caracteres).');
            back();
        }
        if (val('SELECT COUNT(*) FROM usuarios WHERE email = ?', [$email])) {
            flash('error', 'Ya existe un usuario con ese correo.');
            back();
        }
        db()->beginTransaction();
        $inicio = p('fecha_inicio') ?: date('Y-m-d');
        $d['fecha_inicio'] = $inicio;
        $d['fecha_vencimiento'] = p('fecha_vencimiento') ?: sumar_periodo($inicio, $d['ciclo']);
        $id = insert('farmacias', $d);
        insert('usuarios', ['farmacia_id' => $id, 'nombre' => p('admin_nombre'), 'email' => $email, 'password_hash' => password_hash(p('admin_pass'), PASSWORD_DEFAULT), 'rol' => 'admin']);
        foreach (['Medicamentos', 'Genéricos', 'Vitaminas y suplementos', 'Cuidado personal', 'Bebés', 'Material de curación', 'Otros'] as $c) {
            insert('categorias', ['farmacia_id' => $id, 'nombre' => $c]);
        }
        if (p('registrar_pago') === '1' && $d['precio_suscripcion'] > 0) {
            insert('pagos_suscripcion', ['farmacia_id' => $id, 'monto' => $d['precio_suscripcion'], 'fecha_pago' => date('Y-m-d'), 'periodo_desde' => $inicio, 'periodo_hasta' => $d['fecha_vencimiento'], 'metodo' => 'Efectivo', 'notas' => 'Pago inicial']);
        }
        db()->commit();
        if ($err = procesar_logo($id)) flash('error', $err);
        flash('success', 'Farmacia creada. El administrador ya puede iniciar sesión con ' . $email . '.');
        redirect('admin_farmacias', ['a' => 'ver', 'id' => $id]);
    }

    $id = (int) p('id');
    $f = one('SELECT * FROM farmacias WHERE id = ?', [$id]);
    if (!$f) redirect('admin_farmacias');

    if ($acc === 'pago') {
        $monto = (float) p('monto');
        $desde = p('periodo_desde');
        $hasta = p('periodo_hasta');
        if ($monto <= 0 || !$desde || !$hasta) {
            flash('error', 'Monto y periodo son obligatorios.');
            back();
        }
        insert('pagos_suscripcion', ['farmacia_id' => $id, 'monto' => $monto, 'fecha_pago' => p('fecha_pago') ?: date('Y-m-d'), 'periodo_desde' => $desde, 'periodo_hasta' => $hasta, 'metodo' => p('metodo') ?: 'Efectivo', 'referencia' => nulo(p('referencia')), 'notas' => nulo(p('notas'))]);
        if ($hasta > $f['fecha_vencimiento']) {
            update('farmacias', ['fecha_vencimiento' => $hasta], 'id = ?', [$id]);
        }
        if (p('reactivar') === '1') update('farmacias', ['estado' => 'activa'], 'id = ?', [$id]);
        flash('success', 'Pago registrado. Suscripción vigente hasta ' . fdate(max($hasta, $f['fecha_vencimiento'])) . '.');
    } elseif ($acc === 'estado') {
        $nuevo = $f['estado'] === 'activa' ? 'suspendida' : 'activa';
        update('farmacias', ['estado' => $nuevo], 'id = ?', [$id]);
        flash('success', 'Farmacia ' . ($nuevo === 'activa' ? 'activada' : 'suspendida') . '.');
    } elseif ($acc === 'usuario') {
        $email = strtolower(p('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen(p('password')) < 8 || p('nombre') === '') {
            flash('error', 'Datos de usuario inválidos (contraseña mínimo 8 caracteres).');
        } elseif (val('SELECT COUNT(*) FROM usuarios WHERE email = ?', [$email])) {
            flash('error', 'Ese correo ya está registrado.');
        } else {
            insert('usuarios', ['farmacia_id' => $id, 'nombre' => p('nombre'), 'email' => $email, 'password_hash' => password_hash(p('password'), PASSWORD_DEFAULT), 'rol' => p('rol') === 'vendedor' ? 'vendedor' : 'admin']);
            flash('success', 'Usuario creado.');
        }
    } elseif ($acc === 'reset') {
        $uidr = (int) p('usuario_id');
        if (strlen(p('password')) < 8) {
            flash('error', 'La contraseña debe tener al menos 8 caracteres.');
        } else {
            q('UPDATE usuarios SET password_hash = ? WHERE id = ? AND farmacia_id = ?', [password_hash(p('password'), PASSWORD_DEFAULT), $uidr, $id]);
            flash('success', 'Contraseña restablecida.');
        }
    } elseif ($acc === 'toggle_usuario') {
        q('UPDATE usuarios SET activo = 1 - activo WHERE id = ? AND farmacia_id = ?', [(int) p('usuario_id'), $id]);
        flash('success', 'Usuario actualizado.');
    } elseif ($acc === 'reiniciar') {
        if (p('confirmar') !== $f['nombre']) {
            flash('error', 'Escribe el nombre exacto de la farmacia para reiniciar sus datos.');
            back();
        }
        $pdo = db();
        $pdo->beginTransaction();
        $borrar = function ($sql) use ($id) { return q($sql, [$id])->rowCount(); };
        $n = [];
        $n['ventas'] = $borrar('DELETE FROM ventas WHERE farmacia_id = ?');
        $borrar('DELETE FROM movimientos WHERE farmacia_id = ?');
        $borrar('DELETE FROM lotes WHERE farmacia_id = ?');
        $n['compras'] = $borrar('DELETE FROM compras WHERE farmacia_id = ?');
        $n['gastos'] = $borrar('DELETE FROM gastos WHERE farmacia_id = ?');
        if (p('productos')) {
            $n['productos'] = $borrar('DELETE FROM productos WHERE farmacia_id = ?');
            $borrar('DELETE FROM categorias WHERE farmacia_id = ?');
            foreach (['Medicamentos', 'Genéricos', 'Vitaminas y suplementos', 'Cuidado personal', 'Bebés', 'Material de curación', 'Otros'] as $c) {
                insert('categorias', ['farmacia_id' => $id, 'nombre' => $c]);
            }
        }
        if (p('clientes')) $n['clientes'] = $borrar('DELETE FROM clientes WHERE farmacia_id = ?');
        if (p('proveedores')) $n['proveedores'] = $borrar('DELETE FROM proveedores WHERE farmacia_id = ?');
        if (p('usuarios')) $n['vendedores'] = q("DELETE FROM usuarios WHERE farmacia_id = ? AND rol <> 'admin'", [$id])->rowCount();
        $pdo->commit();
        $txt = [];
        foreach ($n as $k => $v) $txt[] = $v . ' ' . $k;
        flash('success', 'Datos reiniciados: se eliminaron ' . implode(', ', $txt) . '. El inventario quedó en cero y la numeración de ventas vuelve a empezar en #1.');
    } elseif ($acc === 'eliminar') {
        if (p('confirmar') !== $f['nombre']) {
            flash('error', 'Escribe el nombre exacto de la farmacia para eliminarla.');
            back();
        }
        q('DELETE FROM farmacias WHERE id = ?', [$id]);
        flash('success', 'Farmacia eliminada con toda su información.');
        redirect('admin_farmacias');
    }
    redirect('admin_farmacias', ['a' => 'ver', 'id' => $id]);
}

// ---------- Formulario ----------
if ($a === 'nueva' || $a === 'editar') {
    $f = $a === 'editar' ? one('SELECT * FROM farmacias WHERE id = ?', [(int) g('id')]) : null;
    if ($a === 'editar' && !$f) redirect('admin_farmacias');
    $v = function ($k, $d = '') use ($f) { return $f ? $f[$k] : $d; };
    layout_start($f ? 'Editar farmacia' : 'Nueva farmacia', 'admin_farmacias');
    page_header($f ? 'Editar ' . $f['nombre'] : 'Nueva farmacia', 'Datos del negocio y de la suscripción.', '<a href="' . url('admin_farmacias') . '" class="btn-light"><i data-lucide="arrow-left" class="w-4 h-4"></i> Volver</a>');
    ?>
<form method="post" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <?= csrf_field() ?>
  <input type="hidden" name="accion" value="guardar">
  <input type="hidden" name="id" value="<?= $f ? (int) $f['id'] : 0 ?>">
  <div class="lg:col-span-2 space-y-6">
    <div class="card p-6">
      <h3 class="font-bold mb-4 flex items-center gap-2"><i data-lucide="building-2" class="w-4 h-4 text-brand-600"></i> Datos de la farmacia</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2"><label class="label">Nombre comercial *</label><input name="nombre" value="<?= e($v('nombre')) ?>" class="input" required></div>
        <div><label class="label">NIT</label><input name="nit" value="<?= e($v('nit')) ?>" class="input"></div>
        <div><label class="label">Responsable</label><input name="responsable" value="<?= e($v('responsable')) ?>" class="input"></div>
        <div><label class="label">Teléfono</label><input name="telefono" value="<?= e($v('telefono')) ?>" class="input"></div>
        <div><label class="label">Correo</label><input type="email" name="email" value="<?= e($v('email')) ?>" class="input"></div>
        <div class="sm:col-span-2"><label class="label">Dirección</label><input name="direccion" value="<?= e($v('direccion')) ?>" class="input"></div>
        <div class="sm:col-span-2"><label class="label">Notas internas</label><textarea name="notas" rows="2" class="input"><?= e($v('notas')) ?></textarea></div>
      </div>
      <div class="mt-5 pt-5 border-t border-slate-100"><label class="label">Logo</label><?php logo_field($f); ?></div>
    </div>
    <?php if (!$f): ?>
    <div class="card p-6">
      <h3 class="font-bold mb-4 flex items-center gap-2"><i data-lucide="user-cog" class="w-4 h-4 text-brand-600"></i> Usuario administrador de la farmacia</h3>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div><label class="label">Nombre *</label><input name="admin_nombre" class="input" required></div>
        <div><label class="label">Correo (usuario) *</label><input type="email" name="admin_email" class="input" required></div>
        <div><label class="label">Contraseña *</label><input name="admin_pass" class="input" minlength="8" required value="<?= e(substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 10)) ?>"></div>
      </div>
      <p class="text-xs text-slate-500 mt-3">Comparte estas credenciales con el dueño de la farmacia. Podrá cambiar su contraseña desde "Mi perfil".</p>
    </div>
    <?php endif; ?>
  </div>
  <div class="space-y-6">
    <div class="card p-6">
      <h3 class="font-bold mb-4 flex items-center gap-2"><i data-lucide="credit-card" class="w-4 h-4 text-brand-600"></i> Suscripción</h3>
      <div class="space-y-4">
        <div><label class="label">Plan</label><input name="plan" value="<?= e($v('plan', 'Básico')) ?>" class="input" list="planes"><datalist id="planes"><option>Básico</option><option>Profesional</option><option>Premium</option></datalist></div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="label">Valor (Q) *</label><input type="number" step="0.01" min="0" name="precio_suscripcion" value="<?= e($v('precio_suscripcion', '')) ?>" class="input" required></div>
          <div><label class="label">Ciclo</label><select name="ciclo" id="ciclo" class="input"><?php foreach ($ciclos as $k => $l): ?><option value="<?= $k ?>" <?= $v('ciclo', 'mensual') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        </div>
        <?php if (!$f): ?>
          <div><label class="label">Fecha de inicio</label><input type="date" name="fecha_inicio" id="fini" value="<?= date('Y-m-d') ?>" class="input"></div>
        <?php endif; ?>
        <div><label class="label">Vence el</label><input type="date" name="fecha_vencimiento" id="fven" value="<?= e($v('fecha_vencimiento', sumar_periodo(date('Y-m-d'), 'mensual'))) ?>" class="input"></div>
        <?php if (!$f): ?>
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="registrar_pago" value="1" checked class="rounded text-brand-600"> Registrar primer pago hoy</label>
        <?php endif; ?>
      </div>
    </div>
    <button class="btn-primary w-full py-3"><i data-lucide="save" class="w-4 h-4"></i> <?= $f ? 'Guardar cambios' : 'Crear farmacia' ?></button>
  </div>
</form>
<?php if (!$f): ?>
<script>
const meses = { mensual: 1, trimestral: 3, semestral: 6, anual: 12 };
function recalcular() {
  const d = new Date(fini.value + 'T00:00:00'); if (isNaN(d)) return;
  d.setMonth(d.getMonth() + meses[ciclo.value]);
  fven.value = d.toISOString().slice(0, 10);
}
fini.addEventListener('change', recalcular); ciclo.addEventListener('change', recalcular);
</script>
<?php endif; ?>
<?php
    layout_end();
    return;
}

// ---------- Detalle ----------
if ($a === 'ver') {
    $id = (int) g('id');
    $f = one('SELECT * FROM farmacias WHERE id = ?', [$id]);
    if (!$f) redirect('admin_farmacias');
    list($estTxt, $estCol) = estado_suscripcion($f);
    $pagos = all('SELECT * FROM pagos_suscripcion WHERE farmacia_id = ? ORDER BY fecha_pago DESC, id DESC', [$id]);
    $usuarios = all('SELECT * FROM usuarios WHERE farmacia_id = ? ORDER BY rol, nombre', [$id]);
    $st = one("SELECT
        (SELECT COUNT(*) FROM productos WHERE farmacia_id = ? AND activo=1) productos,
        (SELECT COUNT(*) FROM clientes WHERE farmacia_id = ?) clientes,
        (SELECT COALESCE(SUM(total),0) FROM ventas WHERE farmacia_id = ? AND estado='completada' AND fecha >= DATE_FORMAT(CURDATE(),'%Y-%m-01')) ventas_mes,
        (SELECT COUNT(*) FROM ventas WHERE farmacia_id = ? AND estado='completada') ventas_total,
        (SELECT MAX(fecha) FROM ventas WHERE farmacia_id = ?) ultima_venta,
        (SELECT MAX(ultimo_acceso) FROM usuarios WHERE farmacia_id = ?) ultimo_acceso", [$id, $id, $id, $id, $id, $id]);
    $pagado = array_sum(array_column($pagos, 'monto'));
    $sigDesde = $f['fecha_vencimiento'] < date('Y-m-d') && dias_hasta($f['fecha_vencimiento']) < -30 ? date('Y-m-d') : $f['fecha_vencimiento'];

    layout_start($f['nombre'], 'admin_farmacias');
    page_header($f['nombre'], badge($estTxt, $estCol) . ' <span class="ml-2">Plan ' . e($f['plan']) . ' · ' . money($f['precio_suscripcion'], 'Q') . ' ' . e($f['ciclo']) . '</span>',
        '<form method="post" action="' . url('admin_acceder') . '">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><button class="btn-primary"><i data-lucide="log-in" class="w-4 h-4"></i> Acceder a la farmacia</button></form>'
        . '<a href="' . url('admin_farmacias', ['a' => 'editar', 'id' => $id]) . '" class="btn-light"><i data-lucide="pencil" class="w-4 h-4"></i> Editar</a>'
        . '<form method="post" data-confirm="¿' . ($f['estado'] === 'activa' ? 'Suspender' : 'Activar') . ' esta farmacia?">' . csrf_field() . '<input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="' . $id . '"><button class="' . ($f['estado'] === 'activa' ? 'btn-danger' : 'btn-dark') . '"><i data-lucide="power" class="w-4 h-4"></i> ' . ($f['estado'] === 'activa' ? 'Suspender' : 'Activar') . '</button></form>');
    ?>
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
  <?php
  $dv = dias_hasta($f['fecha_vencimiento']);
  stat_card('Vence', fdate($f['fecha_vencimiento']), 'calendar-clock', $estCol, $dv < 0 ? 'Hace ' . abs($dv) . ' días' : 'En ' . $dv . ' días');
  stat_card('Ventas del mes', money($st['ventas_mes'], $f['moneda']), 'trending-up', 'brand', num($st['ventas_total']) . ' ventas históricas');
  stat_card('Productos / Clientes', num($st['productos']) . ' / ' . num($st['clientes']), 'pill', 'indigo');
  stat_card('Total pagado', money($pagado, 'Q'), 'banknote', 'emerald', 'Último acceso: ' . ($st['ultimo_acceso'] ? fdatetime($st['ultimo_acceso']) : 'nunca'));
  ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 space-y-6">
    <div class="card overflow-hidden">
      <div class="p-5 border-b border-slate-100 flex items-center justify-between"><h3 class="font-bold">Historial de pagos</h3></div>
      <div class="overflow-x-auto">
        <table class="tbl">
          <thead><tr><th>Fecha</th><th>Periodo</th><th>Método</th><th>Referencia</th><th class="text-right">Monto</th></tr></thead>
          <tbody>
          <?php foreach ($pagos as $pg): ?>
            <tr>
              <td><?= fdate($pg['fecha_pago']) ?></td>
              <td class="whitespace-nowrap"><?= fdate($pg['periodo_desde']) ?> → <?= fdate($pg['periodo_hasta']) ?></td>
              <td><?= e($pg['metodo']) ?></td>
              <td class="text-slate-500"><?= e($pg['referencia'] ?: '—') ?></td>
              <td class="text-right font-semibold"><?= money($pg['monto'], 'Q') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php if (!$pagos) echo empty_state('Sin pagos registrados', 'receipt'); ?>
      </div>
    </div>

    <div class="card overflow-hidden">
      <div class="p-5 border-b border-slate-100"><h3 class="font-bold">Usuarios de la farmacia</h3></div>
      <div class="overflow-x-auto">
        <table class="tbl">
          <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Último acceso</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($usuarios as $u): ?>
            <tr>
              <td class="font-semibold"><?= e($u['nombre']) ?> <?= $u['activo'] ? '' : badge('Inactivo', 'rose') ?></td>
              <td><?= e($u['email']) ?></td>
              <td><?= badge($u['rol'], $u['rol'] === 'admin' ? 'indigo' : 'slate') ?></td>
              <td class="text-slate-500 whitespace-nowrap"><?= fdatetime($u['ultimo_acceso']) ?></td>
              <td class="text-right whitespace-nowrap">
                <button type="button" title="Cambiar contraseña" class="btn-light btn-sm" onclick="resetPw(<?= (int) $u['id'] ?>, <?= e(json_encode($u['nombre'])) ?>)"><i data-lucide="key-round" class="w-3.5 h-3.5"></i></button>
                <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="accion" value="toggle_usuario"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>"><button class="btn-light btn-sm"><?= $u['activo'] ? 'Desactivar' : 'Activar' ?></button></form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <form method="post" class="p-5 border-t border-slate-100 grid sm:grid-cols-5 gap-3 items-end bg-slate-50/50">
        <?= csrf_field() ?><input type="hidden" name="accion" value="usuario"><input type="hidden" name="id" value="<?= $id ?>">
        <div><label class="label">Nombre</label><input name="nombre" class="input" required></div>
        <div><label class="label">Correo</label><input type="email" name="email" class="input" required></div>
        <div><label class="label">Contraseña</label><input name="password" class="input" minlength="8" required></div>
        <div><label class="label">Rol</label><select name="rol" class="input"><option value="admin">Admin</option><option value="vendedor">Vendedor</option></select></div>
        <button class="btn-dark"><i data-lucide="user-plus" class="w-4 h-4"></i> Agregar</button>
      </form>
    </div>
  </div>

  <div class="space-y-6">
    <form method="post" class="card p-6 space-y-4">
      <?= csrf_field() ?><input type="hidden" name="accion" value="pago"><input type="hidden" name="id" value="<?= $id ?>">
      <h3 class="font-bold flex items-center gap-2"><i data-lucide="banknote" class="w-4 h-4 text-brand-600"></i> Registrar pago</h3>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="label">Monto (Q)</label><input type="number" step="0.01" name="monto" value="<?= e($f['precio_suscripcion']) ?>" class="input" required></div>
        <div><label class="label">Fecha de pago</label><input type="date" name="fecha_pago" value="<?= date('Y-m-d') ?>" class="input"></div>
        <div><label class="label">Periodo desde</label><input type="date" name="periodo_desde" value="<?= e($sigDesde) ?>" class="input" required></div>
        <div><label class="label">Periodo hasta</label><input type="date" name="periodo_hasta" value="<?= e(sumar_periodo($sigDesde, $f['ciclo'])) ?>" class="input" required></div>
        <div><label class="label">Método</label><select name="metodo" class="input"><option>Transferencia</option><option>Depósito</option><option>Efectivo</option><option>Tarjeta</option></select></div>
        <div><label class="label">Referencia</label><input name="referencia" class="input"></div>
      </div>
      <input name="notas" class="input" placeholder="Notas (opcional)">
      <?php if ($f['estado'] === 'suspendida'): ?><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="reactivar" value="1" checked> Reactivar farmacia</label><?php endif; ?>
      <button class="btn-primary w-full"><i data-lucide="check" class="w-4 h-4"></i> Registrar pago</button>
    </form>

    <div class="card p-6 text-sm space-y-2">
      <h3 class="font-bold mb-2">Información</h3>
      <div class="flex justify-between gap-3"><span class="text-slate-500">NIT</span><span><?= e($f['nit'] ?: '—') ?></span></div>
      <div class="flex justify-between gap-3"><span class="text-slate-500">Responsable</span><span><?= e($f['responsable'] ?: '—') ?></span></div>
      <div class="flex justify-between gap-3"><span class="text-slate-500">Teléfono</span><span><?= e($f['telefono'] ?: '—') ?></span></div>
      <div class="flex justify-between gap-3"><span class="text-slate-500">Correo</span><span class="truncate"><?= e($f['email'] ?: '—') ?></span></div>
      <div class="flex justify-between gap-3"><span class="text-slate-500">Dirección</span><span class="text-right"><?= e($f['direccion'] ?: '—') ?></span></div>
      <div class="flex justify-between gap-3"><span class="text-slate-500">Cliente desde</span><span><?= fdate($f['fecha_inicio']) ?></span></div>
      <div class="flex justify-between gap-3"><span class="text-slate-500">Última venta</span><span><?= fdatetime($st['ultima_venta']) ?></span></div>
      <?php if ($f['notas']): ?><div class="pt-2 text-slate-600 bg-slate-50 rounded-lg p-3"><?= nl2br(e($f['notas'])) ?></div><?php endif; ?>
    </div>

    <form method="post" class="card p-6 border-amber-200 space-y-3" data-confirm="Se borrarán los datos seleccionados de esta farmacia y no se puede deshacer. ¿Continuar?">
      <?= csrf_field() ?><input type="hidden" name="accion" value="reiniciar"><input type="hidden" name="id" value="<?= $id ?>">
      <h3 class="font-bold text-amber-700 flex items-center gap-2"><i data-lucide="rotate-ccw" class="w-4 h-4"></i> Reiniciar datos</h3>
      <p class="text-xs text-slate-500">Deja la farmacia limpia para entregarla. Siempre se borran <b>ventas, compras, gastos, lotes e inventario</b>. Se conservan la farmacia, su suscripción, sus pagos y el usuario administrador.</p>
      <div class="space-y-2 text-sm">
        <label class="flex items-center gap-2"><input type="checkbox" name="productos" value="1" checked class="rounded text-amber-600"> Borrar también productos y categorías</label>
        <label class="flex items-center gap-2"><input type="checkbox" name="clientes" value="1" checked class="rounded text-amber-600"> Borrar también clientes</label>
        <label class="flex items-center gap-2"><input type="checkbox" name="proveedores" value="1" checked class="rounded text-amber-600"> Borrar también proveedores</label>
        <label class="flex items-center gap-2"><input type="checkbox" name="usuarios" value="1" class="rounded text-amber-600"> Borrar usuarios vendedores</label>
      </div>
      <p class="text-xs text-slate-500">Escribe <b><?= e($f['nombre']) ?></b> para confirmar.</p>
      <input name="confirmar" class="input" autocomplete="off" required>
      <button class="btn w-full bg-amber-500 text-white hover:bg-amber-600"><i data-lucide="rotate-ccw" class="w-4 h-4"></i> Reiniciar datos</button>
    </form>

    <form method="post" class="card p-6 border-rose-200 space-y-3" data-confirm="Esta acción elimina TODA la información de la farmacia y no se puede deshacer. ¿Continuar?">
      <?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $id ?>">
      <h3 class="font-bold text-rose-700">Zona de peligro</h3>
      <p class="text-xs text-slate-500">Escribe <b><?= e($f['nombre']) ?></b> para confirmar la eliminación definitiva.</p>
      <input name="confirmar" class="input" autocomplete="off">
      <button class="btn-danger w-full"><i data-lucide="trash-2" class="w-4 h-4"></i> Eliminar farmacia</button>
    </form>
  </div>
</div>

<div id="mReset" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm grid place-items-center p-4" onclick="if(event.target===this)this.classList.add('hidden')">
  <form method="post" id="fReset" class="card w-full max-w-sm p-6 space-y-4">
    <?= csrf_field() ?><input type="hidden" name="accion" value="reset"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="usuario_id">
    <div><h3 class="font-bold text-lg">Cambiar contraseña</h3><p id="rNombre" class="text-sm text-slate-500"></p></div>
    <div><label class="label">Nueva contraseña</label><input name="password" class="input" minlength="8" required autocomplete="off"></div>
    <div class="flex gap-2 justify-end"><button type="button" class="btn-light" onclick="mReset.classList.add('hidden')">Cancelar</button><button class="btn-primary">Guardar</button></div>
  </form>
</div>
<script>
function resetPw(id, nombre) {
  fReset.reset(); fReset.elements['usuario_id'].value = id; rNombre.textContent = nombre;
  mReset.classList.remove('hidden'); fReset.elements['password'].focus();
}
</script>
<?php
    layout_end();
    return;
}

// ---------- Lista ----------
$buscar = g('q');
$estado = g('estado');
$where = ['1=1'];
$params = [];
if ($buscar !== '') {
    $where[] = '(f.nombre LIKE ? OR f.nit LIKE ? OR f.responsable LIKE ? OR f.email LIKE ?)';
    $params = array_merge($params, array_fill(0, 4, '%' . $buscar . '%'));
}
if ($estado === 'al_dia') $where[] = "f.estado='activa' AND f.fecha_vencimiento >= CURDATE()";
if ($estado === 'vencida') $where[] = "f.estado='activa' AND f.fecha_vencimiento < CURDATE()";
if ($estado === 'suspendida') $where[] = "f.estado='suspendida'";

$lista = all('SELECT f.*, (SELECT COALESCE(SUM(total),0) FROM ventas v WHERE v.farmacia_id=f.id AND v.estado=\'completada\' AND v.fecha >= DATE_FORMAT(CURDATE(),\'%Y-%m-01\')) ventas_mes,
    (SELECT MAX(ultimo_acceso) FROM usuarios u WHERE u.farmacia_id=f.id) ultimo_acceso
    FROM farmacias f WHERE ' . implode(' AND ', $where) . ' ORDER BY f.nombre', $params);

if (g('csv')) {
    csv_download('farmacias.csv', ['Nombre', 'NIT', 'Responsable', 'Teléfono', 'Plan', 'Valor', 'Ciclo', 'Vence', 'Estado'], array_map(function ($f) {
        return [$f['nombre'], $f['nit'], $f['responsable'], $f['telefono'], $f['plan'], $f['precio_suscripcion'], $f['ciclo'], $f['fecha_vencimiento'], estado_suscripcion($f)[0]];
    }, $lista));
}

layout_start('Farmacias', 'admin_farmacias');
page_header('Farmacias', count($lista) . ' registradas', '<a href="' . url('admin_farmacias', array_merge($_GET, ['csv' => 1])) . '" class="btn-light"><i data-lucide="download" class="w-4 h-4"></i> CSV</a><a href="' . url('admin_farmacias', ['a' => 'nueva']) . '" class="btn-primary"><i data-lucide="plus" class="w-4 h-4"></i> Nueva farmacia</a>');
?>
<form class="card p-4 mb-6 flex flex-col sm:flex-row gap-3">
  <input type="hidden" name="p" value="admin_farmacias">
  <div class="relative flex-1"><i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i><input name="q" value="<?= e($buscar) ?>" placeholder="Buscar por nombre, NIT, responsable…" class="input pl-10"></div>
  <select name="estado" class="input sm:w-48" onchange="this.form.submit()">
    <option value="">Todos los estados</option>
    <option value="al_dia" <?= $estado === 'al_dia' ? 'selected' : '' ?>>Al día</option>
    <option value="vencida" <?= $estado === 'vencida' ? 'selected' : '' ?>>Vencidas</option>
    <option value="suspendida" <?= $estado === 'suspendida' ? 'selected' : '' ?>>Suspendidas</option>
  </select>
  <button class="btn-dark">Buscar</button>
</form>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
  <?php foreach ($lista as $f): list($t, $c) = estado_suscripcion($f); ?>
    <div class="card p-5 flex flex-col">
      <div class="flex items-start gap-3">
        <?php if ($lu = logo_url($f)): ?><div class="h-11 w-11 shrink-0 rounded-xl border border-slate-200 bg-white p-0.5 overflow-hidden"><img src="<?= e($lu) ?>" alt="" class="w-full h-full object-contain"></div>
        <?php else: ?><div class="h-11 w-11 rounded-xl bg-gradient-to-br from-brand-100 to-teal-100 text-brand-700 grid place-items-center font-extrabold"><?= e(strtoupper(mb_substr($f['nombre'], 0, 2))) ?></div><?php endif; ?>
        <div class="flex-1 min-w-0">
          <a href="<?= url('admin_farmacias', ['a' => 'ver', 'id' => $f['id']]) ?>" class="font-bold text-slate-900 hover:text-brand-700 block truncate"><?= e($f['nombre']) ?></a>
          <div class="text-xs text-slate-500 truncate"><?= e($f['responsable'] ?: 'Sin responsable') ?> · <?= e($f['telefono'] ?: 'Sin teléfono') ?></div>
        </div>
        <?= badge($t, $c) ?>
      </div>
      <div class="grid grid-cols-3 gap-2 mt-4 text-center">
        <div class="rounded-xl bg-slate-50 p-2"><div class="text-[10px] uppercase font-bold text-slate-400">Plan</div><div class="text-sm font-bold"><?= money($f['precio_suscripcion'], 'Q') ?></div><div class="text-[10px] text-slate-500"><?= e($f['ciclo']) ?></div></div>
        <div class="rounded-xl bg-slate-50 p-2"><div class="text-[10px] uppercase font-bold text-slate-400">Vence</div><div class="text-sm font-bold"><?= date('d/m/y', strtotime($f['fecha_vencimiento'])) ?></div></div>
        <div class="rounded-xl bg-slate-50 p-2"><div class="text-[10px] uppercase font-bold text-slate-400">Ventas mes</div><div class="text-sm font-bold"><?= money($f['ventas_mes'], $f['moneda']) ?></div></div>
      </div>
      <div class="flex gap-2 mt-4 pt-4 border-t border-slate-100">
        <a href="<?= url('admin_farmacias', ['a' => 'ver', 'id' => $f['id']]) ?>" class="btn-light btn-sm flex-1"><i data-lucide="eye" class="w-3.5 h-3.5"></i> Detalle</a>
        <form method="post" action="<?= url('admin_acceder') ?>" class="flex-1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>"><button class="btn-primary btn-sm w-full"><i data-lucide="log-in" class="w-3.5 h-3.5"></i> Acceder</button></form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php if (!$lista) echo '<div class="card">' . empty_state('No hay farmacias con esos filtros', 'building-2') . '</div>'; ?>
<?php layout_end();
