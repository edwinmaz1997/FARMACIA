<?php
require_farmacia();
$F = fid();
$a = g('a', 'lista');

if (is_post()) {
    csrf_check();
    $acc = p('accion');
    if ($acc === 'guardar') {
        $id = (int) p('id');
        $d = ['nombre' => p('nombre'), 'nit' => nulo(strtoupper(p('nit'))), 'dpi' => nulo(p('dpi')), 'telefono' => nulo(p('telefono')), 'email' => nulo(p('email')), 'direccion' => nulo(p('direccion')), 'notas' => nulo(p('notas'))];
        if ($d['nombre'] === '') {
            flash('error', 'El nombre es obligatorio.');
            back('clientes');
        }
        if ($id) update('clientes', $d, 'id = ? AND farmacia_id = ?', [$id, $F]);
        else {
            $d['farmacia_id'] = $F;
            $id = insert('clientes', $d);
        }
        flash('success', 'Cliente guardado.');
        redirect('clientes', ['a' => 'ver', 'id' => $id]);
    }
    if ($acc === 'eliminar' && is_admin()) {
        $id = (int) p('id');
        if (val('SELECT COUNT(*) FROM ventas WHERE cliente_id = ? AND farmacia_id = ?', [$id, $F])) {
            update('clientes', ['activo' => 0], 'id = ? AND farmacia_id = ?', [$id, $F]);
            flash('success', 'El cliente tiene ventas, se desactivó.');
        } else {
            q('DELETE FROM clientes WHERE id = ? AND farmacia_id = ?', [$id, $F]);
            flash('success', 'Cliente eliminado.');
        }
    }
    redirect('clientes');
}

if ($a === 'nuevo' || $a === 'editar') {
    $c = $a === 'editar' ? one('SELECT * FROM clientes WHERE id = ? AND farmacia_id = ?', [(int) g('id'), $F]) : null;
    $v = function ($k) use ($c) { return $c ? $c[$k] : ''; };
    layout_start($c ? 'Editar cliente' : 'Nuevo cliente', 'clientes');
    page_header($c ? 'Editar cliente' : 'Nuevo cliente', '', '<a href="' . url('clientes') . '" class="btn-light"><i data-lucide="arrow-left" class="w-4 h-4"></i> Clientes</a>');
    ?>
<form method="post" class="card p-6 max-w-3xl">
  <?= csrf_field() ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" value="<?= $c ? (int) $c['id'] : 0 ?>">
  <div class="grid sm:grid-cols-2 gap-4">
    <div class="sm:col-span-2"><label class="label">Nombre completo *</label><input name="nombre" value="<?= e($v('nombre')) ?>" class="input" required autofocus></div>
    <div><label class="label">NIT</label><input name="nit" value="<?= e($v('nit')) ?>" class="input" placeholder="CF"></div>
    <div><label class="label">DPI</label><input name="dpi" value="<?= e($v('dpi')) ?>" class="input"></div>
    <div><label class="label">Teléfono</label><input name="telefono" value="<?= e($v('telefono')) ?>" class="input"></div>
    <div><label class="label">Correo</label><input type="email" name="email" value="<?= e($v('email')) ?>" class="input"></div>
    <div class="sm:col-span-2"><label class="label">Dirección</label><input name="direccion" value="<?= e($v('direccion')) ?>" class="input"></div>
    <div class="sm:col-span-2"><label class="label">Notas (alergias, tratamientos frecuentes, etc.)</label><textarea name="notas" rows="3" class="input"><?= e($v('notas')) ?></textarea></div>
  </div>
  <button class="btn-primary mt-6"><i data-lucide="save" class="w-4 h-4"></i> Guardar</button>
</form>
<?php
    layout_end();
    return;
}

if ($a === 'ver') {
    $c = one('SELECT * FROM clientes WHERE id = ? AND farmacia_id = ?', [(int) g('id'), $F]);
    if (!$c) redirect('clientes');
    $st = one("SELECT COUNT(*) n, COALESCE(SUM(total),0) t, MAX(fecha) ult FROM ventas WHERE cliente_id = ? AND farmacia_id = ? AND estado='completada'", [$c['id'], $F]);
    $ventas = all('SELECT * FROM ventas WHERE cliente_id = ? AND farmacia_id = ? ORDER BY id DESC LIMIT 50', [$c['id'], $F]);
    $prods = all("SELECT p.nombre, SUM(d.cantidad) c, MAX(v.fecha) ult FROM venta_detalle d JOIN ventas v ON v.id=d.venta_id JOIN productos p ON p.id=d.producto_id
        WHERE v.cliente_id = ? AND v.farmacia_id = ? AND v.estado='completada' GROUP BY p.id, p.nombre ORDER BY c DESC LIMIT 10", [$c['id'], $F]);
    layout_start($c['nombre'], 'clientes');
    page_header($c['nombre'], 'NIT ' . e($c['nit'] ?: 'CF') . ($c['telefono'] ? ' · ' . e($c['telefono']) : ''), '<a href="' . url('clientes') . '" class="btn-light"><i data-lucide="arrow-left" class="w-4 h-4"></i> Clientes</a><a href="' . url('clientes', ['a' => 'editar', 'id' => $c['id']]) . '" class="btn-dark"><i data-lucide="pencil" class="w-4 h-4"></i> Editar</a>');
    ?>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
  <?php stat_card('Total comprado', money($st['t']), 'wallet', 'brand'); stat_card('Compras', num($st['n']), 'receipt', 'indigo', 'Ticket promedio ' . money($st['n'] ? $st['t'] / $st['n'] : 0)); stat_card('Última compra', $st['ult'] ? fdate($st['ult']) : '—', 'calendar', 'amber'); ?>
</div>
<?php if ($c['notas']): ?><div class="card p-4 mb-6 bg-amber-50/50 border-amber-200 text-sm flex gap-2"><i data-lucide="sticky-note" class="w-4 h-4 text-amber-600 shrink-0"></i><?= nl2br(e($c['notas'])) ?></div><?php endif; ?>
<div class="grid lg:grid-cols-3 gap-6">
  <div class="card overflow-hidden lg:col-span-2">
    <div class="p-5 border-b border-slate-100"><h3 class="font-bold">Historial de compras</h3></div>
    <div class="overflow-x-auto"><table class="tbl">
      <thead><tr><th>No.</th><th>Fecha</th><th>Método</th><th class="text-right">Total</th><th></th></tr></thead>
      <tbody><?php foreach ($ventas as $v): ?>
        <tr class="<?= $v['estado'] === 'anulada' ? 'opacity-50' : '' ?>"><td class="font-bold">#<?= $v['numero'] ?></td><td><?= fdatetime($v['fecha']) ?></td><td class="capitalize"><?= e($v['metodo_pago']) ?></td><td class="text-right font-semibold"><?= money($v['total']) ?></td><td class="text-right"><a class="btn-light btn-sm" href="<?= url('ventas', ['a' => 'ver', 'id' => $v['id']]) ?>">Ver</a></td></tr>
      <?php endforeach; ?></tbody>
    </table><?php if (!$ventas) echo empty_state('Sin compras registradas', 'receipt'); ?></div>
  </div>
  <div class="card overflow-hidden">
    <div class="p-5 border-b border-slate-100"><h3 class="font-bold">Productos frecuentes</h3></div>
    <div class="divide-y divide-slate-100"><?php foreach ($prods as $p): ?>
      <div class="px-5 py-3 flex justify-between gap-3 text-sm"><span class="truncate"><?= e($p['nombre']) ?></span><span class="text-slate-500 shrink-0"><?= num($p['c']) ?> u</span></div>
    <?php endforeach; ?><?php if (!$prods) echo empty_state('—', 'pill'); ?></div>
  </div>
</div>
<?php if (is_admin()): ?>
<form method="post" class="mt-6 text-right" data-confirm="¿Eliminar este cliente?"><?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn-danger btn-sm"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Eliminar cliente</button></form>
<?php endif; ?>
<?php
    layout_end();
    return;
}

$t = g('q');
$w = 'c.farmacia_id = ? AND c.activo = 1';
$params = [$F];
if ($t !== '') { $w .= ' AND (c.nombre LIKE ? OR c.nit LIKE ? OR c.telefono LIKE ? OR c.dpi LIKE ?)'; array_push($params, "%$t%", "%$t%", "%$t%", "%$t%"); }
$total = (int) val("SELECT COUNT(*) FROM clientes c WHERE $w", $params);
$sql = "SELECT c.*, (SELECT COUNT(*) FROM ventas v WHERE v.cliente_id=c.id AND v.estado='completada') compras,
    (SELECT COALESCE(SUM(total),0) FROM ventas v WHERE v.cliente_id=c.id AND v.estado='completada') total,
    (SELECT MAX(fecha) FROM ventas v WHERE v.cliente_id=c.id AND v.estado='completada') ultima
    FROM clientes c WHERE $w ORDER BY c.nombre";
if (g('csv')) csv_download('clientes.csv', ['Nombre', 'NIT', 'DPI', 'Teléfono', 'Correo', 'Dirección', 'Compras', 'Total', 'Última compra'], array_map(function ($r) {
    return [$r['nombre'], $r['nit'], $r['dpi'], $r['telefono'], $r['email'], $r['direccion'], $r['compras'], $r['total'], $r['ultima']];
}, all($sql, $params)));
list($pag, $paginas, $off, $lim) = paginar($total, 30);
$lista = all("$sql LIMIT $lim OFFSET $off", $params);

layout_start('Clientes', 'clientes');
page_header('Clientes', num($total) . ' clientes', '<a href="' . url('clientes', array_merge($_GET, ['csv' => 1])) . '" class="btn-light"><i data-lucide="download" class="w-4 h-4"></i> CSV</a><a href="' . url('clientes', ['a' => 'nuevo']) . '" class="btn-primary"><i data-lucide="user-plus" class="w-4 h-4"></i> Nuevo cliente</a>');
?>
<form class="card p-4 mb-6 flex gap-3"><input type="hidden" name="p" value="clientes">
  <div class="relative flex-1"><i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i><input name="q" value="<?= e($t) ?>" placeholder="Nombre, NIT, DPI o teléfono" class="input pl-10"></div>
  <button class="btn-dark">Buscar</button>
</form>
<div class="card overflow-hidden">
  <div class="overflow-x-auto"><table class="tbl">
    <thead><tr><th>Cliente</th><th>NIT</th><th>Teléfono</th><th class="text-right">Compras</th><th class="text-right">Total</th><th>Última</th><th></th></tr></thead>
    <tbody><?php foreach ($lista as $c): ?>
      <tr>
        <td><a href="<?= url('clientes', ['a' => 'ver', 'id' => $c['id']]) ?>" class="font-semibold hover:text-brand-700"><?= e($c['nombre']) ?></a><?php if ($c['email']): ?><div class="text-xs text-slate-500"><?= e($c['email']) ?></div><?php endif; ?></td>
        <td><?= e($c['nit'] ?: 'CF') ?></td>
        <td><?= $c['telefono'] ? '<a class="text-brand-700" href="https://wa.me/502' . e(preg_replace('/\D/', '', $c['telefono'])) . '" target="_blank">' . e($c['telefono']) . '</a>' : '—' ?></td>
        <td class="text-right"><?= num($c['compras']) ?></td>
        <td class="text-right font-semibold"><?= money($c['total']) ?></td>
        <td class="text-slate-500 whitespace-nowrap"><?= fdate($c['ultima']) ?></td>
        <td class="text-right whitespace-nowrap"><a href="<?= url('clientes', ['a' => 'ver', 'id' => $c['id']]) ?>" class="btn-light btn-sm">Ver</a> <a href="<?= url('clientes', ['a' => 'editar', 'id' => $c['id']]) ?>" class="btn-light btn-sm"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></a></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table><?php if (!$lista) echo empty_state('No hay clientes registrados', 'users'); ?></div>
  <?php pagination($pag, $paginas); ?>
</div>
<?php layout_end();
