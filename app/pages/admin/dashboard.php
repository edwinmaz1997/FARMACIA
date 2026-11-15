<?php
require_super();
$_SESSION['farmacia_id'] = 0;

$mrrSql = "SUM(CASE ciclo WHEN 'mensual' THEN precio_suscripcion WHEN 'trimestral' THEN precio_suscripcion/3 WHEN 'semestral' THEN precio_suscripcion/6 ELSE precio_suscripcion/12 END)";
$tot = one("SELECT COUNT(*) total,
    SUM(estado='activa' AND fecha_vencimiento >= CURDATE()) al_dia,
    SUM(estado='activa' AND fecha_vencimiento < CURDATE()) vencidas,
    SUM(estado='suspendida') suspendidas,
    COALESCE($mrrSql, 0) mrr_bruto
    FROM farmacias");
$mrr = (float) val("SELECT COALESCE($mrrSql,0) FROM farmacias WHERE estado = 'activa'");
$ingMes = (float) val("SELECT COALESCE(SUM(monto),0) FROM pagos_suscripcion WHERE fecha_pago >= DATE_FORMAT(CURDATE(),'%Y-%m-01')");
$ingAnio = (float) val("SELECT COALESCE(SUM(monto),0) FROM pagos_suscripcion WHERE YEAR(fecha_pago) = YEAR(CURDATE())");

// Ingresos últimos 12 meses
$serie = [];
for ($i = 11; $i >= 0; $i--) {
    $k = date('Y-m', strtotime("first day of -$i month"));
    $serie[$k] = 0;
}
foreach (all("SELECT DATE_FORMAT(fecha_pago,'%Y-%m') m, SUM(monto) t FROM pagos_suscripcion WHERE fecha_pago >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL 11 MONTH) GROUP BY m") as $r) {
    if (isset($serie[$r['m']])) $serie[$r['m']] = (float) $r['t'];
}
$labels = array_map(function ($k) { return substr(mes_nombre((int) substr($k, 5, 2)), 0, 3) . ' ' . substr($k, 2, 2); }, array_keys($serie));

$porVencer = all("SELECT * FROM farmacias WHERE estado='activa' AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 15 DAY) ORDER BY fecha_vencimiento LIMIT 10");
$top = all("SELECT f.id, f.nombre, COUNT(v.id) n, COALESCE(SUM(v.total),0) t FROM farmacias f
    LEFT JOIN ventas v ON v.farmacia_id = f.id AND v.estado='completada' AND v.fecha >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
    GROUP BY f.id, f.nombre ORDER BY t DESC LIMIT 6");

layout_start('Panel general', 'admin');
page_header('Hola, ' . explode(' ', $_SESSION['nombre'])[0] . ' 👋', 'Resumen de la plataforma al ' . fdate(date('Y-m-d')),
    '<a href="' . url('admin_farmacias', ['a' => 'nueva']) . '" class="btn-primary"><i data-lucide="plus" class="w-4 h-4"></i> Nueva farmacia</a>');
?>
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
  <?php
  stat_card('Farmacias', num($tot['total']), 'building-2', 'brand', num($tot['al_dia']) . ' al día', url('admin_farmacias'));
  stat_card('Ingreso mensual recurrente', money($mrr, 'Q'), 'repeat', 'indigo', 'Suscripciones activas');
  stat_card('Cobrado este mes', money($ingMes, 'Q'), 'banknote', 'emerald', 'Año: ' . money($ingAnio, 'Q'), url('admin_pagos'));
  stat_card('Vencidas / suspendidas', num($tot['vencidas']) . ' / ' . num($tot['suspendidas']), 'alert-octagon', 'rose', 'Requieren seguimiento', url('admin_farmacias', ['estado' => 'vencida']));
  ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="card p-5 lg:col-span-2">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-bold text-slate-900">Ingresos por suscripciones</h3>
      <span class="text-xs text-slate-500">Últimos 12 meses</span>
    </div>
    <div class="h-72"><canvas id="chIng"></canvas></div>
  </div>
  <div class="card overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
      <h3 class="font-bold text-slate-900">Por vencer (15 días)</h3>
      <?= badge(count($porVencer), count($porVencer) ? 'amber' : 'slate') ?>
    </div>
    <div class="divide-y divide-slate-100">
      <?php if (!$porVencer) echo empty_state('Ninguna farmacia por vencer', 'calendar-check'); ?>
      <?php foreach ($porVencer as $f): $d = dias_hasta($f['fecha_vencimiento']); ?>
        <a href="<?= url('admin_farmacias', ['a' => 'ver', 'id' => $f['id']]) ?>" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
          <div class="flex-1 min-w-0">
            <div class="font-semibold text-sm truncate"><?= e($f['nombre']) ?></div>
            <div class="text-xs text-slate-500"><?= money($f['precio_suscripcion'], 'Q') ?> · <?= e($f['ciclo']) ?></div>
          </div>
          <?= $d < 0 ? badge('Vencida hace ' . abs($d) . 'd', 'rose') : badge($d === 0 ? 'Hoy' : 'En ' . $d . 'd', 'amber') ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card mt-6 overflow-hidden">
  <div class="p-5 border-b border-slate-100"><h3 class="font-bold text-slate-900">Actividad de ventas este mes</h3></div>
  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>Farmacia</th><th class="text-right">Ventas</th><th class="text-right">Total vendido</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($top as $r): ?>
        <tr>
          <td class="font-semibold"><?= e($r['nombre']) ?></td>
          <td class="text-right"><?= num($r['n']) ?></td>
          <td class="text-right font-semibold"><?= money($r['t'], 'Q') ?></td>
          <td class="text-right"><a class="btn-light btn-sm" href="<?= url('admin_farmacias', ['a' => 'ver', 'id' => $r['id']]) ?>">Ver</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!$top) echo empty_state('Aún no hay farmacias registradas', 'building-2'); ?>
  </div>
</div>

<script>
new Chart(document.getElementById('chIng'), {
  type: 'bar',
  data: { labels: <?= json_encode($labels) ?>, datasets: [{ data: <?= json_encode(array_values($serie)) ?>, backgroundColor: '#10b981', borderRadius: 8, maxBarThickness: 36 }] },
  options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } } }
});
</script>
<?php layout_end();
