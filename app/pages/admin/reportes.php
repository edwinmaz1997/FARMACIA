<?php
require_super();
$_SESSION['farmacia_id'] = 0;
$tab = g('tab', 'ingresos');
$tabs = ['ingresos' => 'Ingresos por mes', 'farmacias' => 'Por farmacia', 'cartera' => 'Cartera / morosidad', 'actividad' => 'Actividad de uso'];
if (!isset($tabs[$tab])) $tab = 'ingresos';

$headers = [];
$rows = [];
$chart = null;

if ($tab === 'ingresos') {
    $anio = (int) g('anio', date('Y'));
    $data = array_fill(1, 12, ['n' => 0, 't' => 0]);
    foreach (all('SELECT MONTH(fecha_pago) m, COUNT(*) n, SUM(monto) t FROM pagos_suscripcion WHERE YEAR(fecha_pago) = ? GROUP BY m', [$anio]) as $r) {
        $data[(int) $r['m']] = ['n' => (int) $r['n'], 't' => (float) $r['t']];
    }
    $headers = ['Mes', 'Pagos', 'Ingreso'];
    foreach ($data as $m => $r) $rows[] = [mes_nombre($m), $r['n'], $r['t']];
    $chart = ['type' => 'bar', 'labels' => array_map(function ($m) { return substr(mes_nombre($m), 0, 3); }, range(1, 12)), 'data' => array_column($rows, 2)];
} elseif ($tab === 'farmacias') {
    list($desde, $hasta) = rango_fechas(date('Y-01-01'));
    $rows = all("SELECT f.nombre, f.plan, f.precio_suscripcion, f.ciclo,
        (SELECT COUNT(*) FROM pagos_suscripcion p WHERE p.farmacia_id=f.id AND p.fecha_pago BETWEEN ? AND ?) pagos,
        (SELECT COALESCE(SUM(monto),0) FROM pagos_suscripcion p WHERE p.farmacia_id=f.id AND p.fecha_pago BETWEEN ? AND ?) cobrado,
        (SELECT COALESCE(SUM(total),0) FROM ventas v WHERE v.farmacia_id=f.id AND v.estado='completada' AND DATE(v.fecha) BETWEEN ? AND ?) vendido
        FROM farmacias f ORDER BY cobrado DESC", [$desde, $hasta, $desde, $hasta, $desde, $hasta]);
    $headers = ['Farmacia', 'Plan', 'Valor', 'Ciclo', 'Pagos', 'Cobrado', 'Ventas de la farmacia'];
} elseif ($tab === 'cartera') {
    $rows = all("SELECT nombre, telefono, plan, precio_suscripcion, ciclo, fecha_vencimiento, DATEDIFF(CURDATE(), fecha_vencimiento) dias_vencida, estado
        FROM farmacias WHERE fecha_vencimiento < DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY fecha_vencimiento");
    $headers = ['Farmacia', 'Teléfono', 'Plan', 'Valor', 'Ciclo', 'Vence', 'Días vencida', 'Estado'];
} else {
    list($desde, $hasta) = rango_fechas(date('Y-m-01'));
    $rows = all("SELECT f.nombre,
        (SELECT COUNT(*) FROM usuarios u WHERE u.farmacia_id=f.id AND u.activo=1) usuarios,
        (SELECT MAX(ultimo_acceso) FROM usuarios u WHERE u.farmacia_id=f.id) ultimo_acceso,
        (SELECT COUNT(*) FROM productos pr WHERE pr.farmacia_id=f.id AND pr.activo=1) productos,
        (SELECT COUNT(*) FROM ventas v WHERE v.farmacia_id=f.id AND v.estado='completada' AND DATE(v.fecha) BETWEEN ? AND ?) ventas,
        (SELECT COALESCE(SUM(total),0) FROM ventas v WHERE v.farmacia_id=f.id AND v.estado='completada' AND DATE(v.fecha) BETWEEN ? AND ?) total
        FROM farmacias f ORDER BY ventas DESC", [$desde, $hasta, $desde, $hasta]);
    $headers = ['Farmacia', 'Usuarios activos', 'Último acceso', 'Productos', 'Ventas', 'Total vendido'];
}

if (g('csv')) csv_download('reporte_' . $tab . '.csv', $headers, $rows);

layout_start('Reportes de plataforma', 'admin_reportes');
page_header('Reportes', 'Ingresos, cartera y uso de la plataforma.', '<a href="' . url('admin_reportes', array_merge($_GET, ['csv' => 1])) . '" class="btn-light"><i data-lucide="download" class="w-4 h-4"></i> CSV</a><button onclick="print()" class="btn-light"><i data-lucide="printer" class="w-4 h-4"></i> Imprimir</button>');
?>
<div class="no-print inline-flex gap-1 p-1 rounded-xl bg-slate-100 mb-6 overflow-x-auto max-w-full">
  <?php foreach ($tabs as $k => $l): ?><a href="<?= url('admin_reportes', ['tab' => $k]) ?>" class="tab <?= $tab === $k ? 'active' : '' ?>"><?= e($l) ?></a><?php endforeach; ?>
</div>

<?php if ($tab === 'ingresos'): ?>
  <form class="card p-4 mb-6 flex gap-3 items-end no-print"><input type="hidden" name="p" value="admin_reportes"><input type="hidden" name="tab" value="ingresos">
    <div><label class="label">Año</label><select name="anio" class="input" onchange="this.form.submit()"><?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?><option <?= $y == $anio ? 'selected' : '' ?>><?= $y ?></option><?php endfor; ?></select></div>
    <div class="ml-auto text-right"><div class="text-xs text-slate-500">Total del año</div><div class="text-2xl font-extrabold"><?= money(array_sum(array_column($rows, 2)), 'Q') ?></div></div>
  </form>
  <div class="card p-5 mb-6"><div class="h-72"><canvas id="ch"></canvas></div></div>
<?php elseif ($tab !== 'cartera'): date_filter_form('admin_reportes', '', ['tab' => $tab], $tab === 'farmacias' ? date('Y-01-01') : null); endif; ?>

<div class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><?php foreach ($headers as $i => $h): ?><th class="<?= $i ? 'text-right' : '' ?>"><?= e($h) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $r = array_values($r); ?>
        <tr>
          <?php foreach ($r as $i => $c):
              $h = $headers[$i];
              if (in_array($h, ['Ingreso', 'Valor', 'Cobrado', 'Ventas de la farmacia', 'Total vendido'])) $c = money($c, 'Q');
              elseif (in_array($h, ['Vence'])) $c = fdate($c);
              elseif ($h === 'Último acceso') $c = fdatetime($c);
              elseif ($h === 'Días vencida') $c = $c > 0 ? badge($c . ' días', 'rose') : badge('Por vencer', 'amber');
              elseif ($h === 'Estado') $c = badge($c, $c === 'activa' ? 'emerald' : 'rose');
              else $c = e($c);
          ?>
            <td class="<?= $i ? 'text-right' : 'font-semibold' ?>"><?= $c ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!$rows) echo empty_state('Sin datos', 'bar-chart-3'); ?>
  </div>
</div>
<?php if ($chart): ?>
<script>
new Chart(document.getElementById('ch'), { type: 'bar', data: { labels: <?= json_encode($chart['labels']) ?>, datasets: [{ data: <?= json_encode($chart['data']) ?>, backgroundColor: '#10b981', borderRadius: 8, maxBarThickness: 40 }] },
  options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } } } });
</script>
<?php endif; ?>
<?php layout_end();
