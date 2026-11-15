<?php
require_admin();
$F = fid();
$catsBase = ['Alquiler', 'Energía eléctrica', 'Agua', 'Internet y teléfono', 'Salarios', 'Impuestos', 'Mantenimiento', 'Limpieza', 'Publicidad', 'Transporte', 'Papelería', 'Otros'];

if (is_post()) {
    csrf_check();
    if (p('accion') === 'guardar') {
        $id = (int) p('id');
        $d = ['categoria' => p('categoria') ?: 'Otros', 'descripcion' => nulo(p('descripcion')), 'monto' => round((float) p('monto'), 2), 'fecha' => p('fecha') ?: date('Y-m-d'), 'metodo_pago' => p('metodo_pago') ?: 'efectivo'];
        if ($d['monto'] <= 0) {
            flash('error', 'El monto debe ser mayor a cero.');
        } elseif ($id) {
            update('gastos', $d, 'id = ? AND farmacia_id = ?', [$id, $F]);
            flash('success', 'Gasto actualizado.');
        } else {
            $d['farmacia_id'] = $F;
            $d['usuario_id'] = uid();
            insert('gastos', $d);
            flash('success', 'Gasto registrado.');
        }
    }
    if (p('accion') === 'eliminar') {
        q('DELETE FROM gastos WHERE id = ? AND farmacia_id = ?', [(int) p('id'), $F]);
        flash('success', 'Gasto eliminado.');
    }
    back('gastos');
}

list($desde, $hasta) = rango_fechas();
$params = [$F, $desde, $hasta];
$w = 'g.farmacia_id = ? AND g.fecha BETWEEN ? AND ?';
if (g('cat')) { $w .= ' AND g.categoria = ?'; $params[] = g('cat'); }
$lista = all("SELECT g.*, u.nombre usuario FROM gastos g LEFT JOIN usuarios u ON u.id = g.usuario_id WHERE $w ORDER BY g.fecha DESC, g.id DESC", $params);
$porCat = all("SELECT categoria, SUM(monto) t FROM gastos g WHERE $w GROUP BY categoria ORDER BY t DESC", $params);
$total = array_sum(array_column($lista, 'monto'));
if (g('csv')) csv_download('gastos.csv', ['Fecha', 'Categoría', 'Descripción', 'Método', 'Monto', 'Registró'], array_map(function ($r) {
    return [$r['fecha'], $r['categoria'], $r['descripcion'], $r['metodo_pago'], $r['monto'], $r['usuario']];
}, $lista));
$cats = array_values(array_unique(array_merge($catsBase, array_column(all('SELECT DISTINCT categoria FROM gastos WHERE farmacia_id = ?', [$F]), 'categoria'))));

layout_start('Gastos', 'gastos');
page_header('Gastos', fdate($desde) . ' al ' . fdate($hasta) . ' · Total ' . money($total), '<a href="' . url('gastos', array_merge($_GET, ['csv' => 1])) . '" class="btn-light"><i data-lucide="download" class="w-4 h-4"></i> CSV</a><button onclick="editar(null)" class="btn-primary"><i data-lucide="plus" class="w-4 h-4"></i> Registrar gasto</button>');
$sel = '<div class="sm:w-52"><label class="label">Categoría</label><select name="cat" class="input"><option value="">Todas</option>';
foreach ($cats as $c) $sel .= '<option ' . (g('cat') === $c ? 'selected' : '') . '>' . e($c) . '</option>';
date_filter_form('gastos', $sel . '</select></div>');
?>
<div class="grid lg:grid-cols-3 gap-6">
  <div class="card overflow-hidden lg:col-span-2">
    <div class="overflow-x-auto"><table class="tbl">
      <thead><tr><th>Fecha</th><th>Categoría</th><th>Descripción</th><th>Método</th><th class="text-right">Monto</th><th></th></tr></thead>
      <tbody><?php foreach ($lista as $r): ?>
        <tr>
          <td class="whitespace-nowrap"><?= fdate($r['fecha']) ?></td>
          <td><?= badge($r['categoria'], 'indigo') ?></td>
          <td class="text-slate-600"><?= e($r['descripcion'] ?: '—') ?><div class="text-xs text-slate-400"><?= e($r['usuario']) ?></div></td>
          <td class="capitalize"><?= e($r['metodo_pago']) ?></td>
          <td class="text-right font-bold"><?= money($r['monto']) ?></td>
          <td class="text-right whitespace-nowrap">
            <button onclick='editar(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="p-2 rounded-lg hover:bg-slate-100 text-slate-500"><i data-lucide="pencil" class="w-4 h-4"></i></button>
            <form method="post" class="inline" data-confirm="¿Eliminar este gasto?"><?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50"><i data-lucide="trash-2" class="w-4 h-4"></i></button></form>
          </td>
        </tr>
      <?php endforeach; ?></tbody>
    </table><?php if (!$lista) echo empty_state('Sin gastos en el periodo', 'wallet'); ?></div>
  </div>
  <div class="card p-5 h-fit">
    <h3 class="font-bold mb-4">Por categoría</h3>
    <?php if ($porCat): ?><div class="h-56 mb-4"><canvas id="chCat"></canvas></div><?php endif; ?>
    <div class="space-y-2 text-sm">
      <?php foreach ($porCat as $c): ?><div class="flex justify-between"><span class="text-slate-600"><?= e($c['categoria']) ?></span><span class="font-semibold"><?= money($c['t']) ?> <span class="text-xs text-slate-400"><?= $total ? num($c['t'] / $total * 100) : 0 ?>%</span></span></div><?php endforeach; ?>
      <?php if (!$porCat) echo empty_state('—', 'pie-chart'); ?>
    </div>
  </div>
</div>

<div id="modal" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm grid place-items-center p-4" onclick="if(event.target===this)this.classList.add('hidden')">
  <form method="post" id="fg" class="card w-full max-w-md p-6 space-y-4">
    <?= csrf_field() ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id">
    <h3 class="font-bold text-lg" id="mTitulo">Registrar gasto</h3>
    <div class="grid grid-cols-2 gap-3">
      <div class="col-span-2"><label class="label">Categoría</label><input name="categoria" list="lcats" class="input" required><datalist id="lcats"><?php foreach ($cats as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></datalist></div>
      <div><label class="label">Monto</label><input type="number" step="0.01" min="0.01" name="monto" class="input" required></div>
      <div><label class="label">Fecha</label><input type="date" name="fecha" class="input" required></div>
      <div class="col-span-2"><label class="label">Método de pago</label><select name="metodo_pago" class="input"><option value="efectivo">Efectivo</option><option value="tarjeta">Tarjeta</option><option value="transferencia">Transferencia</option><option value="cheque">Cheque</option></select></div>
      <div class="col-span-2"><label class="label">Descripción</label><input name="descripcion" class="input"></div>
    </div>
    <div class="flex gap-2 justify-end"><button type="button" class="btn-light" onclick="modal.classList.add('hidden')">Cancelar</button><button class="btn-primary">Guardar</button></div>
  </form>
</div>
<script>
function editar(r) {
  fg.reset();
  fg.elements['id'].value = r ? r.id : ''; fg.categoria.value = r ? r.categoria : ''; fg.monto.value = r ? r.monto : '';
  fg.fecha.value = r ? r.fecha : new Date().toLocaleDateString('en-CA'); fg.metodo_pago.value = r ? r.metodo_pago : 'efectivo'; fg.descripcion.value = r ? (r.descripcion || '') : '';
  mTitulo.textContent = r ? 'Editar gasto' : 'Registrar gasto'; modal.classList.remove('hidden'); fg.categoria.focus();
}
<?php if ($porCat): ?>
new Chart(document.getElementById('chCat'), { type: 'doughnut', data: { labels: <?= json_encode(array_column($porCat, 'categoria')) ?>, datasets: [{ data: <?= json_encode(array_map('floatval', array_column($porCat, 't'))) ?>, backgroundColor: ['#10b981','#6366f1','#f59e0b','#ef4444','#0ea5e9','#8b5cf6','#14b8a6','#f97316','#64748b','#ec4899','#84cc16','#06b6d4'], borderWidth: 0 }] },
  options: { maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => c.label + ': ' + fmtMoney(c.parsed) } } } } });
<?php endif; ?>
</script>
<?php layout_end();
