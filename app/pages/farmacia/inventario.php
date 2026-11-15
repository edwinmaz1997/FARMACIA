<?php
require_farmacia();
$F = fid();
$farm = farmacia_actual();
$diasCfg = (int) $farm['dias_alerta_vencimiento'];

if (is_post() && p('accion') === 'ajuste') {
    csrf_check();
    if (!is_admin()) {
        flash('error', 'Solo un administrador puede ajustar inventario.');
        back('inventario');
    }
    $l = one('SELECT l.*, p.nombre FROM lotes l JOIN productos p ON p.id = l.producto_id WHERE l.id = ? AND l.farmacia_id = ?', [(int) p('lote_id'), $F]);
    $cant = (int) p('cantidad');
    $tipo = p('tipo') === 'entrada' ? 'entrada' : 'salida';
    if (!$l || $cant <= 0) {
        flash('error', 'Datos de ajuste inválidos.');
    } elseif ($tipo === 'salida' && $cant > $l['cantidad_actual']) {
        flash('error', 'No puedes sacar más de lo disponible en el lote (' . $l['cantidad_actual'] . ').');
    } else {
        $delta = $tipo === 'salida' ? -$cant : $cant;
        q('UPDATE lotes SET cantidad_actual = cantidad_actual + ? WHERE id = ?', [$delta, $l['id']]);
        registrar_movimiento($l['producto_id'], $l['id'], $tipo === 'salida' ? 'ajuste_salida' : 'ajuste_entrada', $delta, mb_substr(p('motivo') ?: 'Ajuste manual', 0, 120));
        flash('success', 'Ajuste aplicado a ' . $l['nombre'] . ' (lote ' . ($l['numero_lote'] ?: 's/n') . ').');
    }
    back('inventario');
}

$tab = g('tab', 'stock');
$tabs = ['stock' => 'Stock bajo', 'vencimientos' => 'Por vencer', 'vencidos' => 'Vencidos', 'lotes' => 'Todos los lotes'];
if (!isset($tabs[$tab])) $tab = 'stock';

$cStock = (int) val('SELECT COUNT(*) FROM (SELECT p.id FROM productos p LEFT JOIN lotes l ON l.producto_id = p.id AND l.cantidad_actual > 0 WHERE p.farmacia_id = ? AND p.activo = 1 GROUP BY p.id, p.stock_minimo HAVING COALESCE(SUM(l.cantidad_actual),0) <= p.stock_minimo) t', [$F]);
$cVenc = (int) val('SELECT COUNT(*) FROM lotes WHERE farmacia_id = ? AND cantidad_actual > 0 AND fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)', [$F, $diasCfg]);
$cVencidos = (int) val('SELECT COUNT(*) FROM lotes WHERE farmacia_id = ? AND cantidad_actual > 0 AND fecha_vencimiento < CURDATE()', [$F]);
$valVencidos = (float) val('SELECT COALESCE(SUM(cantidad_actual * costo_unitario),0) FROM lotes WHERE farmacia_id = ? AND cantidad_actual > 0 AND fecha_vencimiento < CURDATE()', [$F]);
$valInv = (float) val('SELECT COALESCE(SUM(cantidad_actual * costo_unitario),0) FROM lotes WHERE farmacia_id = ? AND cantidad_actual > 0', [$F]);

$dias = max(1, (int) g('dias', $diasCfg));
$t = g('q');
$filtroTxt = '';
$params = [];
if ($t !== '') {
    $filtroTxt = ' AND (p.nombre LIKE ? OR p.codigo = ? OR l.numero_lote LIKE ?)';
    $params = ["%$t%", $t, "%$t%"];
}

if ($tab === 'stock') {
    $rows = all("SELECT p.id, p.nombre, p.presentacion, p.laboratorio, p.stock_minimo, p.precio_compra, COALESCE(SUM(l.cantidad_actual),0) stock,
        (SELECT COALESCE(SUM(d.cantidad),0) FROM venta_detalle d JOIN ventas v ON v.id=d.venta_id WHERE d.producto_id=p.id AND v.estado='completada' AND v.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) vendido30,
        (SELECT pv.nombre FROM compra_detalle cd JOIN compras c ON c.id=cd.compra_id LEFT JOIN proveedores pv ON pv.id=c.proveedor_id WHERE cd.producto_id=p.id AND c.estado='registrada' ORDER BY c.fecha DESC LIMIT 1) ult_proveedor
        FROM productos p LEFT JOIN lotes l ON l.producto_id = p.id AND l.cantidad_actual > 0
        WHERE p.farmacia_id = ? AND p.activo = 1" . str_replace(' OR l.numero_lote LIKE ?', ' OR p.nombre LIKE ?', $filtroTxt) . "
        GROUP BY p.id HAVING stock <= p.stock_minimo ORDER BY stock, vendido30 DESC", array_merge([$F], $params));
} else {
    $w = 'l.farmacia_id = ? AND l.cantidad_actual > 0';
    $pp = [$F];
    if ($tab === 'vencimientos') { $w .= ' AND l.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)'; $pp[] = $dias; }
    if ($tab === 'vencidos') $w .= ' AND l.fecha_vencimiento < CURDATE()';
    $rows = all("SELECT l.*, p.nombre, p.presentacion, p.laboratorio, p.precio_venta FROM lotes l JOIN productos p ON p.id = l.producto_id
        WHERE $w $filtroTxt ORDER BY l.fecha_vencimiento IS NULL, l.fecha_vencimiento, p.nombre LIMIT 500", array_merge($pp, $params));
}

if (g('csv')) {
    if ($tab === 'stock') {
        csv_download('stock_bajo.csv', ['Producto', 'Presentación', 'Laboratorio', 'Stock', 'Mínimo', 'Vendido 30 días', 'Sugerido pedir', 'Último proveedor'], array_map(function ($r) {
            return [$r['nombre'], $r['presentacion'], $r['laboratorio'], $r['stock'], $r['stock_minimo'], $r['vendido30'], max($r['stock_minimo'] * 2, $r['vendido30']) - $r['stock'], $r['ult_proveedor']];
        }, $rows));
    }
    csv_download('lotes_' . $tab . '.csv', ['Producto', 'Presentación', 'Lote', 'Vencimiento', 'Días', 'Cantidad', 'Costo', 'Valor'], array_map(function ($r) {
        return [$r['nombre'], $r['presentacion'], $r['numero_lote'], $r['fecha_vencimiento'], dias_hasta($r['fecha_vencimiento']), $r['cantidad_actual'], $r['costo_unitario'], $r['cantidad_actual'] * $r['costo_unitario']];
    }, $rows));
}

layout_start('Inventario y alertas', 'inventario');
page_header('Inventario y alertas', 'Las alertas de vencimiento se muestran con ' . $diasCfg . ' días de anticipación' . (is_admin() ? ' (<a class="text-brand-700 font-semibold" href="' . url('configuracion') . '">cambiar</a>)' : '') . '.',
    '<a href="' . url('inventario', array_merge($_GET, ['csv' => 1])) . '" class="btn-light"><i data-lucide="download" class="w-4 h-4"></i> CSV</a><button onclick="print()" class="btn-light"><i data-lucide="printer" class="w-4 h-4"></i> Imprimir</button>');
?>
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
  <?php
  stat_card('Stock bajo', num($cStock), 'package-x', $cStock ? 'amber' : 'slate', 'Productos en o bajo el mínimo', url('inventario', ['tab' => 'stock']));
  stat_card('Lotes por vencer', num($cVenc), 'calendar-clock', $cVenc ? 'amber' : 'slate', 'Próximos ' . $diasCfg . ' días', url('inventario', ['tab' => 'vencimientos']));
  stat_card('Lotes vencidos', num($cVencidos), 'calendar-x', $cVencidos ? 'rose' : 'slate', is_admin() ? 'Pérdida ' . money($valVencidos) : 'Retirar de anaquel', url('inventario', ['tab' => 'vencidos']));
  if (is_admin()) stat_card('Inventario valorizado', money($valInv), 'coins', 'brand', 'A costo de compra', url('reportes', ['tab' => 'inventario']));
  ?>
</div>

<div class="flex flex-col lg:flex-row lg:items-center gap-3 mb-6 no-print">
  <div class="inline-flex gap-1 p-1 rounded-xl bg-slate-100 overflow-x-auto max-w-full">
    <?php foreach ($tabs as $k => $l): ?><a href="<?= url('inventario', ['tab' => $k]) ?>" class="tab <?= $tab === $k ? 'active' : '' ?>"><?= e($l) ?></a><?php endforeach; ?>
  </div>
  <form class="flex gap-2 lg:ml-auto">
    <input type="hidden" name="p" value="inventario"><input type="hidden" name="tab" value="<?= e($tab) ?>">
    <?php if ($tab === 'vencimientos'): ?><select name="dias" class="input w-36" onchange="this.form.submit()"><?php foreach ([30, 60, 90, 120, 180, 365] as $d): ?><option value="<?= $d ?>" <?= $dias === $d ? 'selected' : '' ?>><?= $d ?> días</option><?php endforeach; ?></select><?php endif; ?>
    <input name="q" value="<?= e($t) ?>" placeholder="Buscar producto o lote" class="input lg:w-64">
    <button class="btn-dark"><i data-lucide="search" class="w-4 h-4"></i></button>
  </form>
</div>

<div class="card overflow-hidden">
  <div class="overflow-x-auto">
  <?php if ($tab === 'stock'): ?>
    <table class="tbl">
      <thead><tr><th>Producto</th><th class="text-right">Stock</th><th class="text-right">Mínimo</th><th class="text-right">Vendido 30 d</th><th class="text-right">Sugerido pedir</th><th>Último proveedor</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $sug = max($r['stock_minimo'] * 2, $r['vendido30']) - $r['stock']; ?>
        <tr>
          <td><a href="<?= url('productos', ['a' => 'ver', 'id' => $r['id']]) ?>" class="font-semibold hover:text-brand-700"><?= e($r['nombre']) ?></a><div class="text-xs text-slate-500"><?= e(implode(' · ', array_filter([$r['presentacion'], $r['laboratorio']]))) ?></div></td>
          <td class="text-right"><?= badge(num($r['stock']), $r['stock'] <= 0 ? 'rose' : 'amber') ?></td>
          <td class="text-right"><?= num($r['stock_minimo']) ?></td>
          <td class="text-right"><?= num($r['vendido30']) ?></td>
          <td class="text-right font-bold text-brand-700"><?= num(max(0, $sug)) ?></td>
          <td class="text-slate-500"><?= e($r['ult_proveedor'] ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!$rows) echo empty_state('Ningún producto con stock bajo. ¡Excelente!', 'package-check'); ?>
  <?php else: ?>
    <table class="tbl">
      <thead><tr><th>Producto</th><th>Lote</th><th>Vencimiento</th><th class="text-right">Cantidad</th><?php if (is_admin()): ?><th class="text-right">Valor costo</th><th></th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $d = dias_hasta($r['fecha_vencimiento']); ?>
        <tr>
          <td><a href="<?= url('productos', ['a' => 'ver', 'id' => $r['producto_id']]) ?>" class="font-semibold hover:text-brand-700"><?= e($r['nombre']) ?></a><div class="text-xs text-slate-500"><?= e(implode(' · ', array_filter([$r['presentacion'], $r['laboratorio']]))) ?></div></td>
          <td><?= e($r['numero_lote'] ?: 's/n') ?></td>
          <td class="whitespace-nowrap"><?= fdate($r['fecha_vencimiento']) ?>
            <?php if ($d === null) echo badge('Sin fecha', 'slate');
            elseif ($d < 0) echo badge('Venció hace ' . abs($d) . ' d', 'rose');
            elseif ($d <= $diasCfg) echo badge($d === 0 ? 'Vence hoy' : 'En ' . $d . ' d', $d <= 30 ? 'rose' : 'amber');
            else echo badge('Vigente', 'emerald'); ?>
          </td>
          <td class="text-right font-bold"><?= num($r['cantidad_actual']) ?></td>
          <?php if (is_admin()): ?>
            <td class="text-right"><?= money($r['cantidad_actual'] * $r['costo_unitario']) ?></td>
            <td class="text-right"><button type="button" class="btn-light btn-sm" onclick='abrirAjuste(<?= json_encode(['id' => (int) $r['id'], 'nombre' => $r['nombre'], 'lote' => $r['numero_lote'] ?: 's/n', 'cant' => (int) $r['cantidad_actual'], 'vencido' => $d !== null && $d < 0], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i> Ajustar</button></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!$rows) echo empty_state('No hay lotes en esta vista', 'calendar-check'); ?>
  <?php endif; ?>
  </div>
</div>

<?php if (is_admin()): ?>
<div id="mAjuste" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm grid place-items-center p-4" onclick="if(event.target===this)this.classList.add('hidden')">
  <form method="post" class="card w-full max-w-md p-6 space-y-4">
    <?= csrf_field() ?><input type="hidden" name="accion" value="ajuste"><input type="hidden" name="lote_id" id="ajLote">
    <div><h3 class="font-bold text-lg">Ajustar inventario</h3><p id="ajInfo" class="text-sm text-slate-500"></p></div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="label">Tipo</label><select name="tipo" id="ajTipo" class="input"><option value="salida">Salida (merma)</option><option value="entrada">Entrada</option></select></div>
      <div><label class="label">Cantidad</label><input type="number" min="1" name="cantidad" id="ajCant" class="input" required></div>
    </div>
    <div><label class="label">Motivo</label><select name="motivo" id="ajMotivo" class="input"><option>Producto vencido</option><option>Producto dañado</option><option>Devolución a proveedor</option><option>Diferencia de conteo físico</option><option>Uso interno</option><option>Otro</option></select></div>
    <div class="flex gap-2 justify-end"><button type="button" class="btn-light" onclick="mAjuste.classList.add('hidden')">Cancelar</button><button class="btn-primary">Aplicar ajuste</button></div>
  </form>
</div>
<script>
function abrirAjuste(l) {
  ajLote.value = l.id; ajInfo.textContent = l.nombre + ' · Lote ' + l.lote + ' · Disponible ' + l.cant;
  ajTipo.value = 'salida'; ajCant.value = l.vencido ? l.cant : ''; ajMotivo.value = l.vencido ? 'Producto vencido' : 'Diferencia de conteo físico';
  mAjuste.classList.remove('hidden'); ajCant.focus();
}
</script>
<?php endif; ?>
<?php layout_end();
