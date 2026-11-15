<?php
require_admin();
$F = fid();
$tabs = [
    'resumen' => ['Resumen', 'layout-grid'],
    'diario' => ['Ventas diarias', 'calendar-days'],
    'mensual' => ['Ventas mensuales', 'calendar-range'],
    'productos' => ['Mejores productos', 'trophy'],
    'sin_movimiento' => ['Sin movimiento', 'snail'],
    'categorias' => ['Por categoría', 'tags'],
    'clientes' => ['Mejores clientes', 'users'],
    'vendedores' => ['Por vendedor', 'user-check'],
    'horas' => ['Horas pico', 'clock'],
    'utilidad' => ['Estado de resultados', 'scale'],
    'inventario' => ['Inventario valorizado', 'coins'],
    'compras' => ['Compras por proveedor', 'truck'],
];
$tab = g('tab', 'resumen');
if (!isset($tabs[$tab])) $tab = 'resumen';
list($desde, $hasta) = rango_fechas();
$anio = (int) g('anio', date('Y'));
$vw = "v.farmacia_id = ? AND v.estado = 'completada' AND DATE(v.fecha) BETWEEN ? AND ?";
$vp = [$F, $desde, $hasta];

$headers = [];
$fmt = [];
$rows = [];
$chart = null;
$cards = [];
$usaRango = !in_array($tab, ['mensual', 'inventario']);

switch ($tab) {
    case 'resumen':
    case 'utilidad':
        $v = one("SELECT COUNT(*) n, COALESCE(SUM(subtotal),0) sub, COALESCE(SUM(descuento),0) descu, COALESCE(SUM(total),0) t, COALESCE(SUM(costo_total),0) c FROM ventas v WHERE $vw", $vp);
        $gastos = (float) val('SELECT COALESCE(SUM(monto),0) FROM gastos WHERE farmacia_id = ? AND fecha BETWEEN ? AND ?', $vp);
        $compras = (float) val("SELECT COALESCE(SUM(total),0) FROM compras WHERE farmacia_id = ? AND estado='registrada' AND fecha BETWEEN ? AND ?", $vp);
        $mermas = (float) val("SELECT COALESCE(SUM(-m.cantidad * l.costo_unitario),0) FROM movimientos m JOIN lotes l ON l.id = m.lote_id WHERE m.farmacia_id = ? AND m.tipo = 'ajuste_salida' AND DATE(m.fecha) BETWEEN ? AND ?", $vp);
        $bruta = $v['t'] - $v['c'];
        $neta = $bruta - $gastos - $mermas;
        if ($tab === 'resumen') {
            $cards = [
                ['Ventas netas', money($v['t']), 'banknote', 'brand', num($v['n']) . ' ventas'],
                ['Ticket promedio', money($v['n'] ? $v['t'] / $v['n'] : 0), 'shopping-basket', 'indigo', 'Descuentos ' . money($v['descu'])],
                ['Utilidad bruta', money($bruta), 'trending-up', 'emerald', 'Margen ' . ($v['t'] > 0 ? num($bruta / $v['t'] * 100, 1) : 0) . '%'],
                ['Utilidad neta', money($neta), 'piggy-bank', $neta >= 0 ? 'emerald' : 'rose', 'Gastos ' . money($gastos) . ' · mermas ' . money($mermas)],
            ];
            $headers = ['Método de pago', 'Ventas', 'Total', '% del total'];
            $fmt = ['text', 'num', 'money', 'pct'];
            foreach (all("SELECT metodo_pago, COUNT(*) n, SUM(total) t FROM ventas v WHERE $vw GROUP BY metodo_pago ORDER BY t DESC", $vp) as $r) {
                $rows[] = [ucfirst($r['metodo_pago']), $r['n'], $r['t'], $v['t'] > 0 ? $r['t'] / $v['t'] * 100 : 0];
            }
            $chart = ['type' => 'doughnut', 'labels' => array_column($rows, 0), 'data' => array_map('floatval', array_column($rows, 2)), 'title' => 'Ventas por método de pago'];
            $extraCompras = $compras;
        } else {
            $headers = ['Concepto', 'Monto', '% de ventas'];
            $fmt = ['text', 'money', 'pct'];
            $pct = function ($x) use ($v) { return $v['t'] > 0 ? $x / $v['t'] * 100 : 0; };
            $rows = [
                ['Ventas brutas', $v['sub'], $pct($v['sub'])],
                ['(−) Descuentos', -$v['descu'], $pct(-$v['descu'])],
                ['= Ventas netas', $v['t'], 100],
                ['(−) Costo de lo vendido', -$v['c'], $pct(-$v['c'])],
                ['= Utilidad bruta', $bruta, $pct($bruta)],
                ['(−) Gastos operativos', -$gastos, $pct(-$gastos)],
                ['(−) Mermas y ajustes de inventario', -$mermas, $pct(-$mermas)],
                ['= Utilidad neta', $neta, $pct($neta)],
            ];
            foreach (all('SELECT categoria, SUM(monto) t FROM gastos WHERE farmacia_id = ? AND fecha BETWEEN ? AND ? GROUP BY categoria ORDER BY t DESC', $vp) as $g) {
                $detGastos[] = $g;
            }
            $cards = [
                ['Ventas netas', money($v['t']), 'banknote', 'brand', ''],
                ['Utilidad bruta', money($bruta), 'trending-up', 'emerald', 'Margen ' . num($pct($bruta), 1) . '%'],
                ['Gastos + mermas', money($gastos + $mermas), 'wallet', 'amber', ''],
                ['Utilidad neta', money($neta), 'piggy-bank', $neta >= 0 ? 'emerald' : 'rose', 'Margen ' . num($pct($neta), 1) . '%'],
            ];
        }
        break;

    case 'diario':
        $headers = ['Fecha', 'Ventas', 'Total', 'Costo', 'Utilidad', 'Ticket prom.'];
        $fmt = ['date', 'num', 'money', 'money', 'money', 'money'];
        $rows = array_map(function ($r) { return [$r['d'], $r['n'], $r['t'], $r['c'], $r['t'] - $r['c'], $r['n'] ? $r['t'] / $r['n'] : 0]; },
            all("SELECT DATE(fecha) d, COUNT(*) n, SUM(total) t, SUM(costo_total) c FROM ventas v WHERE $vw GROUP BY d ORDER BY d", $vp));
        $chart = ['type' => 'bar', 'labels' => array_map(function ($r) { return date('d/m', strtotime($r[0])); }, $rows), 'data' => array_map('floatval', array_column($rows, 2)), 'data2' => array_map('floatval', array_column($rows, 4)), 'title' => 'Ventas y utilidad por día'];
        $tot = array_sum(array_column($rows, 2));
        $cards = [
            ['Total del periodo', money($tot), 'banknote', 'brand', num(array_sum(array_column($rows, 1))) . ' ventas'],
            ['Promedio diario', money(count($rows) ? $tot / count($rows) : 0), 'activity', 'indigo', count($rows) . ' días con ventas'],
            ['Mejor día', $rows ? money(max(array_column($rows, 2))) : '—', 'flame', 'amber', $rows ? fdate($rows[array_search(max(array_column($rows, 2)), array_column($rows, 2))][0]) : ''],
            ['Utilidad', money(array_sum(array_column($rows, 4))), 'trending-up', 'emerald', ''],
        ];
        break;

    case 'mensual':
        $headers = ['Mes', 'Ventas', 'Total vendido', 'Utilidad bruta', 'Gastos', 'Utilidad neta', 'Compras'];
        $fmt = ['text', 'num', 'money', 'money', 'money', 'money', 'money'];
        $m = array_fill(1, 12, ['n' => 0, 't' => 0, 'c' => 0, 'g' => 0, 'co' => 0]);
        foreach (all("SELECT MONTH(fecha) m, COUNT(*) n, SUM(total) t, SUM(costo_total) c FROM ventas WHERE farmacia_id = ? AND estado='completada' AND YEAR(fecha) = ? GROUP BY m", [$F, $anio]) as $r) { $m[$r['m']]['n'] = $r['n']; $m[$r['m']]['t'] = $r['t']; $m[$r['m']]['c'] = $r['c']; }
        foreach (all('SELECT MONTH(fecha) m, SUM(monto) t FROM gastos WHERE farmacia_id = ? AND YEAR(fecha) = ? GROUP BY m', [$F, $anio]) as $r) $m[$r['m']]['g'] = $r['t'];
        foreach (all("SELECT MONTH(fecha) m, SUM(total) t FROM compras WHERE farmacia_id = ? AND estado='registrada' AND YEAR(fecha) = ? GROUP BY m", [$F, $anio]) as $r) $m[$r['m']]['co'] = $r['t'];
        foreach ($m as $k => $r) $rows[] = [mes_nombre($k), $r['n'], $r['t'], $r['t'] - $r['c'], $r['g'], $r['t'] - $r['c'] - $r['g'], $r['co']];
        $chart = ['type' => 'bar', 'labels' => array_map(function ($k) { return substr(mes_nombre($k), 0, 3); }, range(1, 12)), 'data' => array_map('floatval', array_column($rows, 2)), 'data2' => array_map('floatval', array_column($rows, 5)), 'title' => 'Ventas y utilidad neta ' . $anio];
        $cards = [
            ['Ventas ' . $anio, money(array_sum(array_column($rows, 2))), 'banknote', 'brand', num(array_sum(array_column($rows, 1))) . ' ventas'],
            ['Utilidad bruta', money(array_sum(array_column($rows, 3))), 'trending-up', 'emerald', ''],
            ['Gastos', money(array_sum(array_column($rows, 4))), 'wallet', 'amber', ''],
            ['Utilidad neta', money(array_sum(array_column($rows, 5))), 'piggy-bank', 'indigo', ''],
        ];
        break;

    case 'productos':
        $orden = in_array(g('orden'), ['cantidad', 'total', 'utilidad']) ? g('orden') : 'cantidad';
        $headers = ['Producto', 'Presentación', 'Cantidad', 'Total vendido', 'Utilidad', 'Margen', 'Stock actual'];
        $fmt = ['text', 'text', 'num', 'money', 'money', 'pct', 'num'];
        $rows = array_map(function ($r) { return [$r['nombre'], $r['presentacion'], $r['cantidad'], $r['total'], $r['utilidad'], $r['total'] > 0 ? $r['utilidad'] / $r['total'] * 100 : 0, $r['stock']]; },
            all("SELECT p.nombre, p.presentacion, SUM(d.cantidad) cantidad, SUM(d.subtotal) total, SUM(d.subtotal - d.costo_unitario * d.cantidad) utilidad,
                (SELECT COALESCE(SUM(cantidad_actual),0) FROM lotes l WHERE l.producto_id = p.id) stock
                FROM venta_detalle d JOIN ventas v ON v.id = d.venta_id JOIN productos p ON p.id = d.producto_id
                WHERE $vw GROUP BY p.id ORDER BY $orden DESC LIMIT 100", $vp));
        $top = array_slice($rows, 0, 10);
        $idx = ['cantidad' => 2, 'total' => 3, 'utilidad' => 4][$orden];
        $chart = ['type' => 'hbar', 'labels' => array_column($top, 0), 'data' => array_map('floatval', array_column($top, $idx)), 'title' => 'Top 10 por ' . $orden, 'count' => $orden === 'cantidad'];
        break;

    case 'sin_movimiento':
        $headers = ['Producto', 'Presentación', 'Stock', 'Valor a costo', 'Última venta'];
        $fmt = ['text', 'text', 'num', 'money', 'date'];
        $rows = array_map(function ($r) { return [$r['nombre'], $r['presentacion'], $r['stock'], $r['valor'], $r['ultima']]; },
            all("SELECT p.nombre, p.presentacion, SUM(l.cantidad_actual) stock, SUM(l.cantidad_actual * l.costo_unitario) valor,
                (SELECT MAX(v2.fecha) FROM venta_detalle d2 JOIN ventas v2 ON v2.id = d2.venta_id WHERE d2.producto_id = p.id AND v2.estado='completada') ultima
                FROM productos p JOIN lotes l ON l.producto_id = p.id AND l.cantidad_actual > 0
                WHERE p.farmacia_id = ? AND p.activo = 1 AND NOT EXISTS (SELECT 1 FROM venta_detalle d JOIN ventas v ON v.id = d.venta_id WHERE d.producto_id = p.id AND $vw)
                GROUP BY p.id ORDER BY valor DESC", array_merge([$F], $vp)));
        $cards = [['Productos sin ventas', num(count($rows)), 'snail', 'amber', 'Con stock en el periodo'], ['Capital inmovilizado', money(array_sum(array_column($rows, 3))), 'coins', 'rose', 'A costo de compra']];
        break;

    case 'categorias':
        $headers = ['Categoría', 'Unidades', 'Total vendido', 'Utilidad', '% de ventas'];
        $fmt = ['text', 'num', 'money', 'money', 'pct'];
        $data = all("SELECT COALESCE(c.nombre,'Sin categoría') cat, SUM(d.cantidad) u, SUM(d.subtotal) t, SUM(d.subtotal - d.costo_unitario * d.cantidad) ut
            FROM venta_detalle d JOIN ventas v ON v.id = d.venta_id JOIN productos p ON p.id = d.producto_id LEFT JOIN categorias c ON c.id = p.categoria_id
            WHERE $vw GROUP BY cat ORDER BY t DESC", $vp);
        $tt = array_sum(array_column($data, 't'));
        foreach ($data as $r) $rows[] = [$r['cat'], $r['u'], $r['t'], $r['ut'], $tt ? $r['t'] / $tt * 100 : 0];
        $chart = ['type' => 'doughnut', 'labels' => array_column($rows, 0), 'data' => array_map('floatval', array_column($rows, 2)), 'title' => 'Participación por categoría'];
        break;

    case 'clientes':
        $headers = ['Cliente', 'NIT', 'Teléfono', 'Compras', 'Total', 'Ticket prom.', 'Última compra'];
        $fmt = ['text', 'text', 'text', 'num', 'money', 'money', 'date'];
        $rows = array_map(function ($r) { return [$r['nombre'], $r['nit'] ?: 'CF', $r['telefono'], $r['n'], $r['t'], $r['n'] ? $r['t'] / $r['n'] : 0, $r['ult']]; },
            all("SELECT c.nombre, c.nit, c.telefono, COUNT(v.id) n, SUM(v.total) t, MAX(v.fecha) ult FROM ventas v JOIN clientes c ON c.id = v.cliente_id WHERE $vw GROUP BY c.id ORDER BY t DESC LIMIT 100", $vp));
        $cf = one("SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM ventas v WHERE $vw AND v.cliente_id IS NULL", $vp);
        $cards = [['Clientes que compraron', num(count($rows)), 'users', 'brand', ''], ['Ventas a clientes registrados', money(array_sum(array_column($rows, 4))), 'user-check', 'indigo', ''], ['Consumidor final', money($cf['t']), 'user', 'slate', num($cf['n']) . ' ventas']];
        $top = array_slice($rows, 0, 10);
        $chart = ['type' => 'hbar', 'labels' => array_column($top, 0), 'data' => array_map('floatval', array_column($top, 4)), 'title' => 'Top 10 clientes'];
        break;

    case 'vendedores':
        $headers = ['Vendedor', 'Ventas', 'Total', 'Ticket prom.', 'Utilidad', 'Anuladas'];
        $fmt = ['text', 'num', 'money', 'money', 'money', 'num'];
        $rows = array_map(function ($r) { return [$r['nombre'], $r['n'], $r['t'], $r['n'] ? $r['t'] / $r['n'] : 0, $r['u'], $r['anul']]; },
            all("SELECT COALESCE(u.nombre,'—') nombre, SUM(v.estado='completada') n, SUM(CASE WHEN v.estado='completada' THEN v.total ELSE 0 END) t,
                SUM(CASE WHEN v.estado='completada' THEN v.total - v.costo_total ELSE 0 END) u, SUM(v.estado='anulada') anul
                FROM ventas v LEFT JOIN usuarios u ON u.id = v.usuario_id WHERE v.farmacia_id = ? AND DATE(v.fecha) BETWEEN ? AND ? GROUP BY v.usuario_id, u.nombre ORDER BY t DESC", $vp));
        $chart = ['type' => 'bar', 'labels' => array_column($rows, 0), 'data' => array_map('floatval', array_column($rows, 2)), 'title' => 'Ventas por vendedor'];
        break;

    case 'horas':
        $headers = ['Hora', 'Ventas', 'Total', 'Ticket prom.'];
        $fmt = ['text', 'num', 'money', 'money'];
        $h = array_fill(0, 24, ['n' => 0, 't' => 0]);
        foreach (all("SELECT HOUR(fecha) h, COUNT(*) n, SUM(total) t FROM ventas v WHERE $vw GROUP BY h", $vp) as $r) $h[(int) $r['h']] = ['n' => $r['n'], 't' => $r['t']];
        foreach ($h as $k => $r) if ($r['n'] > 0 || ($k >= 7 && $k <= 21)) $rows[] = [str_pad($k, 2, '0', STR_PAD_LEFT) . ':00 – ' . str_pad($k, 2, '0', STR_PAD_LEFT) . ':59', $r['n'], $r['t'], $r['n'] ? $r['t'] / $r['n'] : 0];
        $chart = ['type' => 'bar', 'labels' => array_map(function ($r) { return substr($r[0], 0, 5); }, $rows), 'data' => array_map('floatval', array_column($rows, 1)), 'title' => 'Número de ventas por hora', 'count' => true];
        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $porDia = all("SELECT DAYOFWEEK(fecha) d, COUNT(*) n, SUM(total) t FROM ventas v WHERE $vw GROUP BY d ORDER BY d", $vp);
        break;

    case 'inventario':
        $headers = ['Producto', 'Categoría', 'Stock', 'Costo prom.', 'Valor a costo', 'Precio venta', 'Valor a venta', 'Utilidad potencial'];
        $fmt = ['text', 'text', 'num', 'money', 'money', 'money', 'money', 'money'];
        $rows = array_map(function ($r) { return [$r['nombre'], $r['cat'], $r['stock'], $r['stock'] ? $r['costo'] / $r['stock'] : 0, $r['costo'], $r['precio_venta'], $r['stock'] * $r['precio_venta'], $r['stock'] * $r['precio_venta'] - $r['costo']]; },
            all("SELECT p.nombre, COALESCE(c.nombre,'Sin categoría') cat, p.precio_venta, SUM(l.cantidad_actual) stock, SUM(l.cantidad_actual * l.costo_unitario) costo
                FROM productos p JOIN lotes l ON l.producto_id = p.id AND l.cantidad_actual > 0 AND (l.fecha_vencimiento IS NULL OR l.fecha_vencimiento >= CURDATE())
                LEFT JOIN categorias c ON c.id = p.categoria_id WHERE p.farmacia_id = ? GROUP BY p.id ORDER BY costo DESC", [$F]));
        $cards = [
            ['Unidades vigentes', num(array_sum(array_column($rows, 2))), 'package', 'brand', count($rows) . ' productos'],
            ['Valor a costo', money(array_sum(array_column($rows, 4))), 'coins', 'indigo', 'Sin incluir vencidos'],
            ['Valor a precio de venta', money(array_sum(array_column($rows, 6))), 'tag', 'emerald', ''],
            ['Utilidad potencial', money(array_sum(array_column($rows, 7))), 'trending-up', 'amber', ''],
        ];
        break;

    case 'compras':
        $headers = ['Proveedor', 'Compras', 'Unidades', 'Total comprado', '% del total'];
        $fmt = ['text', 'num', 'num', 'money', 'pct'];
        $data = all("SELECT COALESCE(pv.nombre,'Sin proveedor') nombre, COUNT(DISTINCT c.id) n, SUM(d.cantidad) u, SUM(d.subtotal) t FROM compras c
            JOIN compra_detalle d ON d.compra_id = c.id LEFT JOIN proveedores pv ON pv.id = c.proveedor_id
            WHERE c.farmacia_id = ? AND c.estado = 'registrada' AND c.fecha BETWEEN ? AND ? GROUP BY c.proveedor_id, pv.nombre ORDER BY t DESC", $vp);
        $tt = array_sum(array_column($data, 't'));
        foreach ($data as $r) $rows[] = [$r['nombre'], $r['n'], $r['u'], $r['t'], $tt ? $r['t'] / $tt * 100 : 0];
        $chart = ['type' => 'doughnut', 'labels' => array_column($rows, 0), 'data' => array_map('floatval', array_column($rows, 3)), 'title' => 'Compras por proveedor'];
        $cards = [['Total comprado', money($tt), 'package-plus', 'brand', count($rows) . ' proveedores']];
        break;
}

if (g('csv')) csv_download('reporte_' . $tab . ($usaRango ? '_' . $desde . '_' . $hasta : '') . '.csv', $headers, $rows);

function celda($v, $f)
{
    switch ($f) {
        case 'money': return money($v);
        case 'num': return num($v);
        case 'pct': return num($v, 1) . '%';
        case 'date': return fdate($v);
        default: return e($v);
    }
}

$farm = farmacia_actual();
layout_start('Reportes', 'reportes');
?>
<div class="flex flex-col lg:flex-row gap-6">
  <aside class="lg:w-60 shrink-0 no-print">
    <div class="card p-2 lg:sticky lg:top-24 flex lg:flex-col gap-1 overflow-x-auto">
      <?php foreach ($tabs as $k => $t): ?>
        <a href="<?= url('reportes', ['tab' => $k, 'desde' => $desde, 'hasta' => $hasta]) ?>" class="nav-link shrink-0 <?= $tab === $k ? 'active' : '' ?>"><i data-lucide="<?= $t[1] ?>" class="w-4 h-4"></i><?= e($t[0]) ?></a>
      <?php endforeach; ?>
    </div>
  </aside>
  <div class="flex-1 min-w-0">
    <div class="hidden print:block mb-4"><div class="text-xl font-bold"><?= e($farm['nombre']) ?></div></div>
    <?php
    page_header($tabs[$tab][0], $usaRango ? 'Del ' . fdate($desde) . ' al ' . fdate($hasta) : ($tab === 'mensual' ? 'Año ' . $anio : 'Al ' . fdate(date('Y-m-d'))),
        '<a href="' . url('reportes', array_merge($_GET, ['csv' => 1])) . '" class="btn-light"><i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Excel (CSV)</a><button onclick="print()" class="btn-light"><i data-lucide="printer" class="w-4 h-4"></i> Imprimir / PDF</button>');

    if ($usaRango) {
        $extra = $tab === 'productos' ? '<div class="sm:w-44"><label class="label">Ordenar por</label><select name="orden" class="input"><option value="cantidad">Cantidad</option><option value="total" ' . (g('orden') === 'total' ? 'selected' : '') . '>Total vendido</option><option value="utilidad" ' . (g('orden') === 'utilidad' ? 'selected' : '') . '>Utilidad</option></select></div>' : '';
        date_filter_form('reportes', $extra, ['tab' => $tab]);
    } elseif ($tab === 'mensual') { ?>
      <form class="card p-4 mb-6 flex gap-3 items-end no-print"><input type="hidden" name="p" value="reportes"><input type="hidden" name="tab" value="mensual">
        <div><label class="label">Año</label><select name="anio" class="input" onchange="this.form.submit()"><?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?><option <?= $y == $anio ? 'selected' : '' ?>><?= $y ?></option><?php endfor; ?></select></div>
      </form>
    <?php } ?>

    <?php if ($cards): ?>
      <div class="grid grid-cols-2 xl:grid-cols-<?= min(4, count($cards)) ?> gap-4 mb-6">
        <?php foreach ($cards as $c) stat_card($c[0], $c[1], $c[2], $c[3], $c[4]); ?>
      </div>
    <?php endif; ?>
    <?php if ($tab === 'resumen'): ?>
      <div class="grid grid-cols-2 gap-4 mb-6">
        <?php stat_card('Compras del periodo', money($extraCompras), 'package-plus', 'sky', '', url('reportes', ['tab' => 'compras', 'desde' => $desde, 'hasta' => $hasta]));
        stat_card('Gastos del periodo', money($gastos), 'wallet', 'amber', '', url('gastos', ['desde' => $desde, 'hasta' => $hasta])); ?>
      </div>
    <?php endif; ?>

    <?php if ($chart && $chart['data']): ?>
      <div class="card p-5 mb-6">
        <h3 class="font-bold mb-4"><?= e($chart['title']) ?></h3>
        <div class="<?= $chart['type'] === 'hbar' ? 'h-96' : 'h-72' ?>"><canvas id="ch"></canvas></div>
      </div>
    <?php endif; ?>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="tbl">
          <thead><tr><?php foreach ($headers as $i => $h): ?><th class="<?= $fmt[$i] !== 'text' ? 'text-right' : '' ?>"><?= e($h) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
          <?php foreach ($rows as $ri => $r): $destacar = $tab === 'utilidad' && strpos($r[0], '=') === 0; ?>
            <tr class="<?= $destacar ? 'bg-slate-50 font-bold' : '' ?>">
              <?php foreach ($r as $i => $c): ?>
                <td class="<?= $fmt[$i] !== 'text' ? 'text-right whitespace-nowrap' : ($i === 0 ? 'font-semibold' : 'text-slate-500') ?> <?= $fmt[$i] === 'money' && $c < 0 ? 'text-rose-600' : '' ?>"><?= celda($c, $fmt[$i]) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <?php if ($rows && !in_array($tab, ['utilidad', 'horas'])): ?>
            <tfoot><tr class="bg-slate-50 font-bold">
              <?php foreach ($headers as $i => $h): ?>
                <td class="px-4 py-3 <?= $fmt[$i] !== 'text' ? 'text-right' : '' ?>"><?php
                  if ($i === 0) echo 'Total';
                  elseif (in_array($fmt[$i], ['money', 'num']) && !preg_match('/prom|Precio|Costo prom|Stock actual/i', $h)) echo celda(array_sum(array_column($rows, $i)), $fmt[$i]);
                ?></td>
              <?php endforeach; ?>
            </tr></tfoot>
          <?php endif; ?>
        </table>
        <?php if (!$rows) echo empty_state('No hay datos para este periodo', 'bar-chart-3'); ?>
      </div>
    </div>

    <?php if ($tab === 'utilidad' && !empty($detGastos)): ?>
      <div class="card overflow-hidden mt-6">
        <div class="p-5 border-b border-slate-100"><h3 class="font-bold">Detalle de gastos</h3></div>
        <table class="tbl"><tbody><?php foreach ($detGastos as $g): ?><tr><td><?= e($g['categoria']) ?></td><td class="text-right font-semibold"><?= money($g['t']) ?></td></tr><?php endforeach; ?></tbody></table>
      </div>
    <?php endif; ?>
    <?php if ($tab === 'horas' && $porDia): ?>
      <div class="card p-5 mt-6">
        <h3 class="font-bold mb-4">Ventas por día de la semana</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
          <?php $mx = max(array_column($porDia, 't')); foreach ($porDia as $d): ?>
            <div class="rounded-xl bg-slate-50 p-3 text-center">
              <div class="text-xs font-bold text-slate-500"><?= $dias[$d['d'] - 1] ?></div>
              <div class="font-extrabold mt-1"><?= money($d['t']) ?></div>
              <div class="text-xs text-slate-500"><?= num($d['n']) ?> ventas</div>
              <div class="h-1.5 rounded-full bg-slate-200 mt-2"><div class="h-full rounded-full bg-brand-500" style="width:<?= $mx ? round($d['t'] / $mx * 100) : 0 ?>%"></div></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($chart && $chart['data']): ?>
<script>
(() => {
  const c = <?= json_encode($chart) ?>;
  const colors = ['#10b981', '#6366f1', '#f59e0b', '#ef4444', '#0ea5e9', '#8b5cf6', '#14b8a6', '#f97316', '#64748b', '#ec4899', '#84cc16', '#06b6d4'];
  const money = v => c.count ? v : fmtMoney(v);
  let cfg;
  if (c.type === 'doughnut') {
    cfg = { type: 'doughnut', data: { labels: c.labels, datasets: [{ data: c.data, backgroundColor: colors, borderWidth: 0 }] },
      options: { maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'right' }, tooltip: { callbacks: { label: x => x.label + ': ' + money(x.parsed) } } } } };
  } else {
    const ds = [{ label: c.count ? 'Ventas' : 'Ventas', data: c.data, backgroundColor: '#10b981', borderRadius: 6, maxBarThickness: 38 }];
    if (c.data2) ds.push({ label: 'Utilidad', data: c.data2, backgroundColor: '#6366f1', borderRadius: 6, maxBarThickness: 38 });
    cfg = { type: 'bar', data: { labels: c.labels, datasets: ds },
      options: { indexAxis: c.type === 'hbar' ? 'y' : 'x', maintainAspectRatio: false, plugins: { legend: { display: !!c.data2 }, tooltip: { callbacks: { label: x => x.dataset.label + ': ' + money(c.type === 'hbar' ? x.parsed.x : x.parsed.y) } } },
        scales: { x: { grid: { display: c.type === 'hbar' } }, y: { beginAtZero: true, grid: { color: '#f1f5f9' } } } } };
  }
  new Chart(document.getElementById('ch'), cfg);
})();
</script>
<?php endif; ?>
<?php layout_end();
