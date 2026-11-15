<?php
require_super();
$_SESSION['farmacia_id'] = 0;

if (is_post()) {
    csrf_check();
    if (p('accion') === 'eliminar') {
        q('DELETE FROM pagos_suscripcion WHERE id = ?', [(int) p('id')]);
        flash('success', 'Pago eliminado. Revisa la fecha de vencimiento de la farmacia si es necesario.');
    }
    back('admin_pagos');
}

list($desde, $hasta) = rango_fechas(date('Y-01-01'));
$farm = (int) g('farmacia');
$params = [$desde, $hasta];
$w = 'p.fecha_pago BETWEEN ? AND ?';
if ($farm) {
    $w .= ' AND p.farmacia_id = ?';
    $params[] = $farm;
}
$pagos = all("SELECT p.*, f.nombre farmacia FROM pagos_suscripcion p JOIN farmacias f ON f.id = p.farmacia_id WHERE $w ORDER BY p.fecha_pago DESC, p.id DESC", $params);
$total = array_sum(array_column($pagos, 'monto'));

if (g('csv')) {
    csv_download('pagos_suscripcion.csv', ['Fecha', 'Farmacia', 'Desde', 'Hasta', 'Método', 'Referencia', 'Monto'], array_map(function ($r) {
        return [$r['fecha_pago'], $r['farmacia'], $r['periodo_desde'], $r['periodo_hasta'], $r['metodo'], $r['referencia'], $r['monto']];
    }, $pagos));
}

$farmacias = all('SELECT id, nombre FROM farmacias ORDER BY nombre');
layout_start('Pagos de suscripción', 'admin_pagos');
page_header('Pagos de suscripción', count($pagos) . ' pagos · Total ' . money($total, 'Q'), '<a href="' . url('admin_pagos', array_merge($_GET, ['csv' => 1])) . '" class="btn-light"><i data-lucide="download" class="w-4 h-4"></i> CSV</a>');
$sel = '<div class="sm:w-60"><label class="label">Farmacia</label><select name="farmacia" class="input"><option value="">Todas</option>';
foreach ($farmacias as $f) $sel .= '<option value="' . $f['id'] . '" ' . ($farm === (int) $f['id'] ? 'selected' : '') . '>' . e($f['nombre']) . '</option>';
$sel .= '</select></div>';
date_filter_form('admin_pagos', $sel, [], date('Y-01-01'));
?>
<div class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>Fecha</th><th>Farmacia</th><th>Periodo</th><th>Método</th><th>Referencia</th><th class="text-right">Monto</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($pagos as $r): ?>
        <tr>
          <td class="whitespace-nowrap"><?= fdate($r['fecha_pago']) ?></td>
          <td class="font-semibold"><a class="hover:text-brand-700" href="<?= url('admin_farmacias', ['a' => 'ver', 'id' => $r['farmacia_id']]) ?>"><?= e($r['farmacia']) ?></a></td>
          <td class="whitespace-nowrap text-slate-500"><?= fdate($r['periodo_desde']) ?> → <?= fdate($r['periodo_hasta']) ?></td>
          <td><?= e($r['metodo']) ?></td>
          <td class="text-slate-500"><?= e($r['referencia'] ?: '—') ?></td>
          <td class="text-right font-bold"><?= money($r['monto'], 'Q') ?></td>
          <td class="text-right"><form method="post" data-confirm="¿Eliminar este pago?"><?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50"><i data-lucide="trash-2" class="w-4 h-4"></i></button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <?php if ($pagos): ?><tfoot><tr class="bg-slate-50 font-bold"><td colspan="5" class="px-4 py-3">Total</td><td class="px-4 py-3 text-right"><?= money($total, 'Q') ?></td><td></td></tr></tfoot><?php endif; ?>
    </table>
    <?php if (!$pagos) echo empty_state('Sin pagos en el periodo', 'receipt'); ?>
  </div>
</div>
<?php layout_end();
