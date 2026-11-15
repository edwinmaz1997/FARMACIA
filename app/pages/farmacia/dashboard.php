<?php
require_farmacia();
$F = fid();
$farm = farmacia_actual();
$dias = (int) $farm['dias_alerta_vencimiento'];

$hoy = one("SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM ventas WHERE farmacia_id = ? AND estado='completada' AND DATE(fecha) = CURDATE()", [$F]);
$ayer = (float) val("SELECT COALESCE(SUM(total),0) FROM ventas WHERE farmacia_id = ? AND estado='completada' AND DATE(fecha) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)", [$F]);
$mes = one("SELECT COUNT(*) n, COALESCE(SUM(total),0) t, COALESCE(SUM(total - costo_total),0) u FROM ventas WHERE farmacia_id = ? AND estado='completada' AND fecha >= DATE_FORMAT(CURDATE(),'%Y-%m-01')", [$F]);
$gastosMes = (float) val("SELECT COALESCE(SUM(monto),0) FROM gastos WHERE farmacia_id = ? AND fecha >= DATE_FORMAT(CURDATE(),'%Y-%m-01')", [$F]);

$stockBajo = all("SELECT p.id, p.nombre, p.presentacion, p.stock_minimo, COALESCE(SUM(l.cantidad_actual),0) stock
    FROM productos p LEFT JOIN lotes l ON l.producto_id = p.id AND l.cantidad_actual > 0
    WHERE p.farmacia_id = ? AND p.activo = 1 GROUP BY p.id HAVING stock <= p.stock_minimo ORDER BY stock, p.nombre", [$F]);
$porVencer = all("SELECT l.*, p.nombre, p.presentacion FROM lotes l JOIN productos p ON p.id = l.producto_id
    WHERE l.farmacia_id = ? AND l.cantidad_actual > 0 AND l.fecha_vencimiento IS NOT NULL AND l.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
    ORDER BY l.fecha_vencimiento", [$F, $dias]);
$vencidos = count(array_filter($porVencer, function ($l) { return $l['fecha_vencimiento'] < date('Y-m-d'); }));

// Serie 30 días
$serie = [];
for ($i = 29; $i >= 0; $i--) $serie[date('Y-m-d', strtotime("-$i day"))] = 0;
foreach (all("SELECT DATE(fecha) d, SUM(total) t FROM ventas WHERE farmacia_id = ? AND estado='completada' AND fecha >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY d", [$F]) as $r) {
    if (isset($serie[$r['d']])) $serie[$r['d']] = (float) $r['t'];
}
$top = all("SELECT p.nombre, SUM(d.cantidad) c, SUM(d.subtotal) t FROM venta_detalle d JOIN ventas v ON v.id = d.venta_id JOIN productos p ON p.id = d.producto_id
    WHERE v.farmacia_id = ? AND v.estado='completada' AND v.fecha >= DATE_FORMAT(CURDATE(),'%Y-%m-01') GROUP BY p.id, p.nombre ORDER BY c DESC LIMIT 5", [$F]);
$ultimas = all("SELECT v.*, c.nombre cliente FROM ventas v LEFT JOIN clientes c ON c.id = v.cliente_id WHERE v.farmacia_id = ? ORDER BY v.id DESC LIMIT 6", [$F]);

$var = $ayer > 0 ? (($hoy['t'] - $ayer) / $ayer) * 100 : null;

layout_start('Inicio', 'dashboard');
$saludo = (int) date('H') < 12 ? 'Buenos días' : ((int) date('H') < 19 ? 'Buenas tardes' : 'Buenas noches');
page_header($saludo . ', ' . explode(' ', $_SESSION['nombre'])[0], 'Así va ' . e($farm['nombre']) . ' hoy, ' . fdate(date('Y-m-d')) . '.',
    '<a href="' . url('pos') . '" class="btn-primary"><i data-lucide="scan-barcode" class="w-4 h-4"></i> Vender</a>' . (is_admin() ? '<a href="' . url('compras', ['a' => 'nueva']) . '" class="btn-light"><i data-lucide="package-plus" class="w-4 h-4"></i> Registrar compra</a>' : ''));
?>
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
  <?php
  stat_card('Ventas de hoy', money($hoy['t']), 'shopping-bag', 'brand', num($hoy['n']) . ' transacciones' . ($var !== null ? ' · <span class="' . ($var >= 0 ? 'text-emerald-600' : 'text-rose-600') . ' font-semibold">' . ($var >= 0 ? '▲' : '▼') . ' ' . num(abs($var), 1) . '%</span> vs ayer' : ''), url('ventas'));
  stat_card('Ventas del mes', money($mes['t']), 'calendar-days', 'indigo', num($mes['n']) . ' ventas · ticket ' . money($mes['n'] ? $mes['t'] / $mes['n'] : 0));
  if (is_admin()) {
      stat_card('Utilidad neta del mes', money($mes['u'] - $gastosMes), 'piggy-bank', 'emerald', 'Bruta ' . money($mes['u']) . ' − gastos ' . money($gastosMes), url('reportes', ['tab' => 'utilidad']));
  } else {
      stat_card('Productos vendidos hoy', num(val("SELECT COALESCE(SUM(d.cantidad),0) FROM venta_detalle d JOIN ventas v ON v.id=d.venta_id WHERE v.farmacia_id=? AND v.estado='completada' AND DATE(v.fecha)=CURDATE()", [$F])), 'package', 'emerald');
  }
  stat_card('Alertas de inventario', num(count($stockBajo) + count($porVencer)), 'bell-ring', (count($stockBajo) + count($porVencer)) ? 'rose' : 'slate', num(count($stockBajo)) . ' stock bajo · ' . num(count($porVencer) - $vencidos) . ' por vencer · ' . num($vencidos) . ' vencidos', url('inventario'));
  ?>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
  <div class="card p-5 xl:col-span-2">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-bold text-slate-900">Ventas de los últimos 30 días</h3>
      <span class="text-sm font-semibold text-slate-500"><?= money(array_sum($serie)) ?></span>
    </div>
    <div class="h-72"><canvas id="chVentas"></canvas></div>
  </div>
  <div class="card overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
      <h3 class="font-bold text-slate-900">Más vendidos del mes</h3>
      <i data-lucide="trophy" class="w-4 h-4 text-amber-500"></i>
    </div>
    <div class="p-3">
      <?php if (!$top) echo empty_state('Aún no hay ventas este mes', 'trophy'); ?>
      <?php $max = $top ? max(array_column($top, 'c')) : 1; foreach ($top as $i => $t): ?>
        <div class="px-2 py-2.5">
          <div class="flex items-center justify-between text-sm mb-1.5">
            <span class="font-semibold truncate pr-2"><span class="text-slate-400 mr-1"><?= $i + 1 ?>.</span><?= e($t['nombre']) ?></span>
            <span class="text-slate-500 shrink-0"><?= num($t['c']) ?> u</span>
          </div>
          <div class="h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full bg-gradient-to-r from-brand-400 to-brand-600" style="width: <?= round($t['c'] / $max * 100) ?>%"></div></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-6">
  <div class="card overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
      <h3 class="font-bold text-slate-900 flex items-center gap-2"><i data-lucide="calendar-x" class="w-4 h-4 text-amber-500"></i> Lotes por vencer</h3>
      <a href="<?= url('inventario', ['tab' => 'vencimientos']) ?>" class="text-xs font-semibold text-brand-700">Ver todos</a>
    </div>
    <div class="divide-y divide-slate-100">
      <?php if (!$porVencer) echo empty_state('Sin lotes próximos a vencer', 'calendar-check'); ?>
      <?php foreach (array_slice($porVencer, 0, 6) as $l): $d = dias_hasta($l['fecha_vencimiento']); ?>
        <div class="flex items-center gap-3 px-5 py-3">
          <div class="flex-1 min-w-0"><div class="text-sm font-semibold truncate"><?= e($l['nombre']) ?></div><div class="text-xs text-slate-500">Lote <?= e($l['numero_lote'] ?: 's/n') ?> · <?= num($l['cantidad_actual']) ?> u</div></div>
          <?= $d < 0 ? badge('Vencido', 'rose') : badge($d . ' días', $d <= 30 ? 'rose' : 'amber') ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
      <h3 class="font-bold text-slate-900 flex items-center gap-2"><i data-lucide="package-x" class="w-4 h-4 text-rose-500"></i> Stock bajo</h3>
      <a href="<?= url('inventario') ?>" class="text-xs font-semibold text-brand-700">Ver todos</a>
    </div>
    <div class="divide-y divide-slate-100">
      <?php if (!$stockBajo) echo empty_state('Todo el inventario está en orden', 'package-check'); ?>
      <?php foreach (array_slice($stockBajo, 0, 6) as $s): ?>
        <div class="flex items-center gap-3 px-5 py-3">
          <div class="flex-1 min-w-0"><div class="text-sm font-semibold truncate"><?= e($s['nombre']) ?></div><div class="text-xs text-slate-500">Mínimo <?= num($s['stock_minimo']) ?></div></div>
          <?= badge(num($s['stock']) . ' u', $s['stock'] <= 0 ? 'rose' : 'amber') ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card overflow-hidden">
    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
      <h3 class="font-bold text-slate-900">Últimas ventas</h3>
      <a href="<?= url('ventas') ?>" class="text-xs font-semibold text-brand-700">Ver todas</a>
    </div>
    <div class="divide-y divide-slate-100">
      <?php if (!$ultimas) echo empty_state('Aún no hay ventas', 'receipt'); ?>
      <?php foreach ($ultimas as $v): ?>
        <a href="<?= url('ventas', ['a' => 'ver', 'id' => $v['id']]) ?>" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
          <div class="h-9 w-9 rounded-lg bg-slate-100 grid place-items-center text-xs font-bold text-slate-600">#<?= $v['numero'] ?></div>
          <div class="flex-1 min-w-0"><div class="text-sm font-semibold truncate"><?= e($v['cliente'] ?: 'Consumidor final') ?></div><div class="text-xs text-slate-500"><?= date('d/m H:i', strtotime($v['fecha'])) ?> · <?= e($v['metodo_pago']) ?></div></div>
          <div class="text-sm font-bold <?= $v['estado'] === 'anulada' ? 'line-through text-slate-400' : '' ?>"><?= money($v['total']) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
(() => {
  const ctx = document.getElementById('chVentas').getContext('2d');
  const g = ctx.createLinearGradient(0, 0, 0, 280); g.addColorStop(0, 'rgba(16,185,129,.25)'); g.addColorStop(1, 'rgba(16,185,129,0)');
  new Chart(ctx, {
    type: 'line',
    data: { labels: <?= json_encode(array_map(function ($d) { return date('d/m', strtotime($d)); }, array_keys($serie))) ?>,
      datasets: [{ data: <?= json_encode(array_values($serie)) ?>, borderColor: '#10b981', backgroundColor: g, fill: true, tension: .35, pointRadius: 0, pointHoverRadius: 5, borderWidth: 2.5 }] },
    options: { maintainAspectRatio: false, interaction: { intersect: false, mode: 'index' }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => fmtMoney(c.parsed.y) } } },
      scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { callback: v => v.toLocaleString() } }, x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } } } }
  });
})();
</script>
<?php layout_end();
