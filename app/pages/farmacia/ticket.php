<?php
require_farmacia();
$v = one('SELECT v.*, c.nombre cliente, c.nit, u.nombre vendedor FROM ventas v LEFT JOIN clientes c ON c.id = v.cliente_id LEFT JOIN usuarios u ON u.id = v.usuario_id WHERE v.id = ? AND v.farmacia_id = ?', [(int) g('id'), fid()]);
if (!$v) exit('Venta no encontrada');
$det = all('SELECT d.*, p.nombre, p.presentacion FROM venta_detalle d JOIN productos p ON p.id = d.producto_id WHERE d.venta_id = ?', [$v['id']]);
$f = farmacia_actual();
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ticket #<?= $v['numero'] ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12px; color: #000; margin: 0; background: #f1f5f9; }
  .t { width: 80mm; max-width: 100%; margin: 16px auto; background: #fff; padding: 14px 12px; }
  .c { text-align: center; } .r { text-align: right; } .b { font-weight: 700; }
  hr { border: 0; border-top: 1px dashed #000; margin: 8px 0; }
  table { width: 100%; border-collapse: collapse; } td { vertical-align: top; padding: 1px 0; }
  .big { font-size: 16px; }
  .btns { text-align: center; margin: 12px; font-family: system-ui; }
  .btns button { padding: 10px 18px; border-radius: 10px; border: 0; background: #059669; color: #fff; font-weight: 600; cursor: pointer; margin: 0 4px; }
  .anulada { color: #b91c1c; border: 2px solid #b91c1c; padding: 4px; margin: 6px 0; }
  @media print { body { background: #fff; } .t { margin: 0; width: 100%; padding: 0; } .btns { display: none; } @page { margin: 4mm; size: 80mm auto; } }
</style></head>
<body>
<div class="t">
  <div class="c b big"><?= e($f['nombre']) ?></div>
  <?php if ($f['nit']): ?><div class="c">NIT: <?= e($f['nit']) ?></div><?php endif; ?>
  <?php if ($f['direccion']): ?><div class="c"><?= e($f['direccion']) ?></div><?php endif; ?>
  <?php if ($f['telefono']): ?><div class="c">Tel. <?= e($f['telefono']) ?></div><?php endif; ?>
  <hr>
  <div>Venta: <b>#<?= str_pad($v['numero'], 6, '0', STR_PAD_LEFT) ?></b></div>
  <div>Fecha: <?= fdatetime($v['fecha']) ?></div>
  <div>Cliente: <?= e($v['cliente'] ?: 'Consumidor final') ?></div>
  <div>NIT: <?= e($v['nit'] ?: 'CF') ?></div>
  <div>Atendió: <?= e($v['vendedor']) ?></div>
  <?php if ($v['estado'] === 'anulada'): ?><div class="c b anulada">VENTA ANULADA</div><?php endif; ?>
  <hr>
  <table>
    <?php foreach ($det as $d): ?>
      <tr><td colspan="2"><?= e($d['nombre']) ?><?= $d['presentacion'] ? ' ' . e($d['presentacion']) : '' ?></td></tr>
      <tr><td><?= num($d['cantidad']) ?> x <?= number_format($d['precio_unitario'], 2) ?></td><td class="r"><?= number_format($d['subtotal'], 2) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <hr>
  <table>
    <tr><td>Subtotal</td><td class="r"><?= money($v['subtotal']) ?></td></tr>
    <?php if ($v['descuento'] > 0): ?><tr><td>Descuento</td><td class="r">-<?= money($v['descuento']) ?></td></tr><?php endif; ?>
    <tr class="b big"><td>TOTAL</td><td class="r"><?= money($v['total']) ?></td></tr>
    <tr><td>Pago (<?= e($v['metodo_pago']) ?>)</td><td class="r"><?= money($v['monto_recibido']) ?></td></tr>
    <?php if ($v['cambio'] > 0): ?><tr><td>Cambio</td><td class="r"><?= money($v['cambio']) ?></td></tr><?php endif; ?>
  </table>
  <hr>
  <div class="c"><?= e($f['ticket_pie'] ?: '¡Gracias por su compra! Su salud es nuestra prioridad.') ?></div>
  <div class="c" style="margin-top:6px;font-size:10px">Documento no válido como factura</div>
</div>
<div class="btns"><button onclick="print()">Imprimir</button><button onclick="close()" style="background:#475569">Cerrar</button></div>
<script>window.addEventListener('load', () => setTimeout(() => print(), 300));</script>
</body></html>
