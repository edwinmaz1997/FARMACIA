<?php
require_farmacia();
$F = fid();
$pr = one('SELECT * FROM productos WHERE id = ? AND farmacia_id = ?', [(int) g('id'), $F]);
if (!$pr) redirect('productos');
list($desde, $hasta) = rango_fechas(date('Y-m-d', strtotime('-90 days')));

$saldoIni = (int) val('SELECT COALESCE(SUM(cantidad),0) FROM movimientos WHERE farmacia_id = ? AND producto_id = ? AND DATE(fecha) < ?', [$F, $pr['id'], $desde]);
$movs = all('SELECT m.*, l.numero_lote, u.nombre usuario FROM movimientos m LEFT JOIN lotes l ON l.id = m.lote_id LEFT JOIN usuarios u ON u.id = m.usuario_id
    WHERE m.farmacia_id = ? AND m.producto_id = ? AND DATE(m.fecha) BETWEEN ? AND ? ORDER BY m.fecha, m.id', [$F, $pr['id'], $desde, $hasta]);
$tipos = ['compra' => ['Compra', 'emerald'], 'venta' => ['Venta', 'indigo'], 'ajuste_entrada' => ['Ajuste +', 'sky'], 'ajuste_salida' => ['Ajuste −', 'amber'], 'anulacion_venta' => ['Anulación venta', 'slate'], 'anulacion_compra' => ['Anulación compra', 'rose']];

layout_start('Kardex', 'productos');
page_header('Kardex · ' . $pr['nombre'], 'Historial de entradas y salidas', '<a href="' . url('productos', ['a' => 'ver', 'id' => $pr['id']]) . '" class="btn-light"><i data-lucide="arrow-left" class="w-4 h-4"></i> Producto</a><button onclick="print()" class="btn-light"><i data-lucide="printer" class="w-4 h-4"></i> Imprimir</button>');
date_filter_form('kardex', '', ['id' => $pr['id']], $desde);
?>
<div class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>Fecha</th><th>Tipo</th><th>Referencia</th><th>Lote</th><th>Usuario</th><th class="text-right">Entrada</th><th class="text-right">Salida</th><th class="text-right">Saldo</th></tr></thead>
      <tbody>
        <tr class="bg-slate-50"><td colspan="7" class="font-semibold">Saldo inicial al <?= fdate($desde) ?></td><td class="text-right font-bold"><?= num($saldoIni) ?></td></tr>
        <?php $saldo = $saldoIni; foreach ($movs as $m): $saldo += $m['cantidad']; $tp = isset($tipos[$m['tipo']]) ? $tipos[$m['tipo']] : [$m['tipo'], 'slate']; ?>
          <tr>
            <td class="whitespace-nowrap"><?= fdatetime($m['fecha']) ?></td>
            <td><?= badge($tp[0], $tp[1]) ?></td>
            <td><?= e($m['referencia']) ?></td>
            <td class="text-slate-500"><?= e($m['numero_lote'] ?: 's/n') ?></td>
            <td class="text-slate-500"><?= e($m['usuario']) ?></td>
            <td class="text-right text-emerald-700 font-semibold"><?= $m['cantidad'] > 0 ? num($m['cantidad']) : '' ?></td>
            <td class="text-right text-rose-700 font-semibold"><?= $m['cantidad'] < 0 ? num(-$m['cantidad']) : '' ?></td>
            <td class="text-right font-bold"><?= num($saldo) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!$movs) echo empty_state('Sin movimientos en el periodo', 'history'); ?>
  </div>
</div>
<?php layout_end();
