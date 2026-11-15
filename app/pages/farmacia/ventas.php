<?php
require_farmacia();
$F = fid();
$a = g('a', 'lista');

if (is_post() && p('accion') === 'anular') {
    csrf_check();
    if (!is_admin()) {
        flash('error', 'Solo un administrador puede anular ventas.');
        back('ventas');
    }
    $v = one("SELECT * FROM ventas WHERE id = ? AND farmacia_id = ? AND estado = 'completada'", [(int) p('id'), $F]);
    if ($v) {
        db()->beginTransaction();
        $lotes = all('SELECT dl.lote_id, dl.cantidad, d.producto_id FROM venta_detalle_lotes dl JOIN venta_detalle d ON d.id = dl.venta_detalle_id WHERE d.venta_id = ?', [$v['id']]);
        foreach ($lotes as $l) {
            q('UPDATE lotes SET cantidad_actual = cantidad_actual + ? WHERE id = ? AND farmacia_id = ?', [$l['cantidad'], $l['lote_id'], $F]);
            registrar_movimiento($l['producto_id'], $l['lote_id'], 'anulacion_venta', $l['cantidad'], 'Anulación venta #' . $v['numero']);
        }
        $motivo = p('motivo');
        update('ventas', ['estado' => 'anulada', 'notas' => trim(($v['notas'] ? $v['notas'] . ' | ' : '') . 'Anulada: ' . $motivo)], 'id = ?', [$v['id']]);
        db()->commit();
        flash('success', 'Venta #' . $v['numero'] . ' anulada. El inventario fue devuelto a sus lotes.');
    }
    redirect('ventas', ['a' => 'ver', 'id' => (int) p('id')]);
}

if ($a === 'ver') {
    $v = one('SELECT v.*, c.nombre cliente, c.nit, c.telefono, u.nombre vendedor FROM ventas v LEFT JOIN clientes c ON c.id = v.cliente_id LEFT JOIN usuarios u ON u.id = v.usuario_id WHERE v.id = ? AND v.farmacia_id = ?', [(int) g('id'), $F]);
    if (!$v) redirect('ventas');
    $det = all('SELECT d.*, p.nombre, p.presentacion, p.codigo,
        (SELECT GROUP_CONCAT(CONCAT(COALESCE(l.numero_lote,\'s/n\'), \' (\', dl.cantidad, \')\') SEPARATOR \', \') FROM venta_detalle_lotes dl JOIN lotes l ON l.id = dl.lote_id WHERE dl.venta_detalle_id = d.id) lotes
        FROM venta_detalle d JOIN productos p ON p.id = d.producto_id WHERE d.venta_id = ?', [$v['id']]);
    layout_start('Venta #' . $v['numero'], 'ventas');
    page_header('Venta #' . $v['numero'], fdatetime($v['fecha']) . ' · ' . ($v['estado'] === 'anulada' ? badge('Anulada', 'rose') : badge('Completada', 'emerald')),
        '<a href="' . url('ventas') . '" class="btn-light"><i data-lucide="arrow-left" class="w-4 h-4"></i> Ventas</a><a target="_blank" href="' . url('ticket', ['id' => $v['id']]) . '" class="btn-dark"><i data-lucide="printer" class="w-4 h-4"></i> Ticket</a>');
    ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="card overflow-hidden lg:col-span-2">
    <div class="overflow-x-auto">
      <table class="tbl">
        <thead><tr><th>Producto</th><th>Lotes</th><th class="text-right">Cant.</th><th class="text-right">Precio</th><th class="text-right">Subtotal</th></tr></thead>
        <tbody>
        <?php foreach ($det as $d): ?>
          <tr>
            <td><div class="font-semibold"><?= e($d['nombre']) ?></div><div class="text-xs text-slate-500"><?= e($d['presentacion']) ?></div></td>
            <td class="text-xs text-slate-500"><?= e($d['lotes']) ?></td>
            <td class="text-right"><?= num($d['cantidad']) ?></td>
            <td class="text-right"><?= money($d['precio_unitario']) ?></td>
            <td class="text-right font-semibold"><?= money($d['subtotal']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="p-5 border-t border-slate-100 flex justify-end">
      <div class="w-full sm:w-72 space-y-1.5 text-sm">
        <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span><?= money($v['subtotal']) ?></span></div>
        <div class="flex justify-between"><span class="text-slate-500">Descuento</span><span>− <?= money($v['descuento']) ?></span></div>
        <div class="flex justify-between text-lg font-extrabold pt-1"><span>Total</span><span><?= money($v['total']) ?></span></div>
        <?php if (is_admin()): ?><div class="flex justify-between text-xs pt-2 border-t border-slate-100"><span class="text-slate-500">Costo / Utilidad</span><span><?= money($v['costo_total']) ?> / <b class="text-emerald-600"><?= money($v['total'] - $v['costo_total']) ?></b></span></div><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="space-y-6">
    <div class="card p-6 text-sm space-y-2.5">
      <div class="flex justify-between"><span class="text-slate-500">Cliente</span><span class="font-semibold text-right"><?= e($v['cliente'] ?: 'Consumidor final') ?></span></div>
      <div class="flex justify-between"><span class="text-slate-500">NIT</span><span><?= e($v['nit'] ?: 'CF') ?></span></div>
      <div class="flex justify-between"><span class="text-slate-500">Vendedor</span><span><?= e($v['vendedor']) ?></span></div>
      <div class="flex justify-between"><span class="text-slate-500">Método de pago</span><span class="capitalize"><?= e($v['metodo_pago']) ?></span></div>
      <div class="flex justify-between"><span class="text-slate-500">Recibido</span><span><?= money($v['monto_recibido']) ?></span></div>
      <div class="flex justify-between"><span class="text-slate-500">Cambio</span><span><?= money($v['cambio']) ?></span></div>
      <?php if ($v['notas']): ?><div class="bg-slate-50 rounded-lg p-3 text-slate-600"><?= e($v['notas']) ?></div><?php endif; ?>
    </div>
    <?php if ($v['estado'] === 'completada' && is_admin()): ?>
      <form method="post" class="card p-6 space-y-3" data-confirm="¿Anular esta venta? El inventario regresará a sus lotes.">
        <?= csrf_field() ?><input type="hidden" name="accion" value="anular"><input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
        <h3 class="font-bold text-rose-700">Anular venta</h3>
        <input name="motivo" class="input" placeholder="Motivo de la anulación" required>
        <button class="btn-danger w-full"><i data-lucide="ban" class="w-4 h-4"></i> Anular</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php
    layout_end();
    return;
}

// Lista
list($desde, $hasta) = rango_fechas(date('Y-m-d'), date('Y-m-d'));
$w = 'v.farmacia_id = ? AND DATE(v.fecha) BETWEEN ? AND ?';
$params = [$F, $desde, $hasta];
if (g('metodo')) { $w .= ' AND v.metodo_pago = ?'; $params[] = g('metodo'); }
if (g('estado')) { $w .= ' AND v.estado = ?'; $params[] = g('estado'); }
if (g('q') !== '') { $w .= ' AND (c.nombre LIKE ? OR c.nit LIKE ? OR v.numero = ?)'; array_push($params, '%' . g('q') . '%', '%' . g('q') . '%', (int) g('q')); }

$res = one("SELECT COUNT(*) n, COALESCE(SUM(CASE WHEN v.estado='completada' THEN v.total END),0) t,
    COALESCE(SUM(CASE WHEN v.estado='completada' THEN v.total - v.costo_total END),0) u, SUM(v.estado='anulada') anuladas
    FROM ventas v LEFT JOIN clientes c ON c.id = v.cliente_id WHERE $w", $params);
list($pag, $paginas, $off, $lim) = paginar($res['n'], 30);
$sqlLista = "SELECT v.*, c.nombre cliente, u.nombre vendedor, (SELECT SUM(cantidad) FROM venta_detalle d WHERE d.venta_id = v.id) items
    FROM ventas v LEFT JOIN clientes c ON c.id = v.cliente_id LEFT JOIN usuarios u ON u.id = v.usuario_id WHERE $w ORDER BY v.id DESC";

if (g('csv')) {
    csv_download('ventas_' . $desde . '_' . $hasta . '.csv', ['No.', 'Fecha', 'Cliente', 'Vendedor', 'Método', 'Subtotal', 'Descuento', 'Total', 'Estado'], array_map(function ($v) {
        return [$v['numero'], $v['fecha'], $v['cliente'] ?: 'Consumidor final', $v['vendedor'], $v['metodo_pago'], $v['subtotal'], $v['descuento'], $v['total'], $v['estado']];
    }, all($sqlLista, $params)));
}
$ventas = all($sqlLista . " LIMIT $lim OFFSET $off", $params);

layout_start('Ventas', 'ventas');
page_header('Ventas', fdate($desde) . ($desde !== $hasta ? ' al ' . fdate($hasta) : ''), '<a href="' . url('ventas', array_merge($_GET, ['csv' => 1])) . '" class="btn-light"><i data-lucide="download" class="w-4 h-4"></i> CSV</a><a href="' . url('pos') . '" class="btn-primary"><i data-lucide="plus" class="w-4 h-4"></i> Nueva venta</a>');
$extra = '<div class="sm:w-44"><label class="label">Buscar</label><input name="q" value="' . e(g('q')) . '" placeholder="No., cliente, NIT" class="input"></div>'
    . '<div class="sm:w-36"><label class="label">Método</label><select name="metodo" class="input"><option value="">Todos</option>'
    . implode('', array_map(function ($m) { return '<option ' . (g('metodo') === $m ? 'selected' : '') . ' value="' . $m . '">' . ucfirst($m) . '</option>'; }, ['efectivo', 'tarjeta', 'transferencia'])) . '</select></div>'
    . '<div class="sm:w-36"><label class="label">Estado</label><select name="estado" class="input"><option value="">Todos</option><option value="completada" ' . (g('estado') === 'completada' ? 'selected' : '') . '>Completadas</option><option value="anulada" ' . (g('estado') === 'anulada' ? 'selected' : '') . '>Anuladas</option></select></div>';
date_filter_form('ventas', $extra, [], date('Y-m-d'), date('Y-m-d'));
?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php
  stat_card('Total vendido', money($res['t']), 'banknote', 'brand');
  stat_card('Transacciones', num($res['n'] - $res['anuladas']), 'receipt', 'indigo', num($res['anuladas']) . ' anuladas');
  stat_card('Ticket promedio', money(($res['n'] - $res['anuladas']) ? $res['t'] / ($res['n'] - $res['anuladas']) : 0), 'shopping-basket', 'amber');
  if (is_admin()) stat_card('Utilidad bruta', money($res['u']), 'trending-up', 'emerald', $res['t'] > 0 ? 'Margen ' . num($res['u'] / $res['t'] * 100, 1) . '%' : '');
  ?>
</div>
<div class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>No.</th><th>Fecha</th><th class="hidden sm:table-cell">Cliente</th><th class="hidden lg:table-cell">Vendedor</th><th class="hidden md:table-cell">Método</th><th class="hidden md:table-cell text-right">Items</th><th class="text-right">Total</th><th class="hidden sm:table-cell"></th></tr></thead>
      <tbody>
      <?php foreach ($ventas as $v): ?>
        <tr class="<?= $v['estado'] === 'anulada' ? 'opacity-60' : '' ?>">
          <td class="font-bold"><a href="<?= url('ventas', ['a' => 'ver', 'id' => $v['id']]) ?>" class="text-brand-700">#<?= $v['numero'] ?></a></td>
          <td class="whitespace-nowrap"><?= fdatetime($v['fecha']) ?><div class="sm:hidden text-xs text-slate-500 truncate max-w-[150px]"><?= e($v['cliente'] ?: 'Consumidor final') ?></div></td>
          <td class="hidden sm:table-cell"><?= e($v['cliente'] ?: 'Consumidor final') ?></td>
          <td class="hidden lg:table-cell text-slate-500"><?= e($v['vendedor']) ?></td>
          <td class="hidden md:table-cell"><?= badge(ucfirst($v['metodo_pago']), $v['metodo_pago'] === 'efectivo' ? 'emerald' : ($v['metodo_pago'] === 'tarjeta' ? 'indigo' : 'sky')) ?></td>
          <td class="hidden md:table-cell text-right"><?= num($v['items']) ?></td>
          <td class="text-right font-bold <?= $v['estado'] === 'anulada' ? 'line-through' : '' ?>"><?= money($v['total']) ?></td>
          <td class="hidden sm:table-cell text-right whitespace-nowrap">
            <a href="<?= url('ventas', ['a' => 'ver', 'id' => $v['id']]) ?>" class="btn-light btn-sm">Ver</a>
            <a target="_blank" href="<?= url('ticket', ['id' => $v['id']]) ?>" class="btn-light btn-sm"><i data-lucide="printer" class="w-3.5 h-3.5"></i></a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!$ventas) echo empty_state('No hay ventas en este periodo', 'receipt'); ?>
  </div>
  <?php pagination($pag, $paginas); ?>
</div>
<?php layout_end();
