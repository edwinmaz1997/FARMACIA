<?php
require_admin();
$F = fid();

if (is_post()) {
    csrf_check();
    $acc = p('accion');
    if ($acc === 'guardar') {
        $id = (int) p('id');
        $d = ['nombre' => p('nombre'), 'nit' => nulo(p('nit')), 'contacto' => nulo(p('contacto')), 'telefono' => nulo(p('telefono')), 'email' => nulo(p('email')), 'direccion' => nulo(p('direccion')), 'notas' => nulo(p('notas'))];
        if ($d['nombre'] === '') {
            flash('error', 'El nombre es obligatorio.');
        } elseif ($id) {
            update('proveedores', $d, 'id = ? AND farmacia_id = ?', [$id, $F]);
            flash('success', 'Proveedor actualizado.');
        } else {
            $d['farmacia_id'] = $F;
            insert('proveedores', $d);
            flash('success', 'Proveedor creado.');
        }
    }
    if ($acc === 'eliminar') {
        $id = (int) p('id');
        if (val('SELECT COUNT(*) FROM compras WHERE proveedor_id = ? AND farmacia_id = ?', [$id, $F])) {
            update('proveedores', ['activo' => 0], 'id = ? AND farmacia_id = ?', [$id, $F]);
            flash('success', 'El proveedor tiene compras, se desactivó.');
        } else {
            q('DELETE FROM proveedores WHERE id = ? AND farmacia_id = ?', [$id, $F]);
            flash('success', 'Proveedor eliminado.');
        }
    }
    redirect('proveedores');
}

$t = g('q');
$w = 'pv.farmacia_id = ? AND pv.activo = 1';
$params = [$F];
if ($t !== '') { $w .= ' AND (pv.nombre LIKE ? OR pv.nit LIKE ? OR pv.contacto LIKE ?)'; array_push($params, "%$t%", "%$t%", "%$t%"); }
$lista = all("SELECT pv.*, (SELECT COUNT(*) FROM compras c WHERE c.proveedor_id=pv.id AND c.estado='registrada') compras,
    (SELECT COALESCE(SUM(total),0) FROM compras c WHERE c.proveedor_id=pv.id AND c.estado='registrada') total,
    (SELECT MAX(fecha) FROM compras c WHERE c.proveedor_id=pv.id AND c.estado='registrada') ultima
    FROM proveedores pv WHERE $w ORDER BY pv.nombre", $params);
if (g('csv')) csv_download('proveedores.csv', ['Nombre', 'NIT', 'Contacto', 'Teléfono', 'Correo', 'Dirección', 'Compras', 'Total comprado', 'Última compra'], array_map(function ($r) {
    return [$r['nombre'], $r['nit'], $r['contacto'], $r['telefono'], $r['email'], $r['direccion'], $r['compras'], $r['total'], $r['ultima']];
}, $lista));

layout_start('Proveedores', 'proveedores');
page_header('Proveedores', count($lista) . ' proveedores', '<a href="' . url('proveedores', array_merge($_GET, ['csv' => 1])) . '" class="btn-light"><i data-lucide="download" class="w-4 h-4"></i> CSV</a><button onclick="editar(null)" class="btn-primary"><i data-lucide="plus" class="w-4 h-4"></i> Nuevo proveedor</button>');
?>
<form class="card p-4 mb-6 flex gap-3"><input type="hidden" name="p" value="proveedores">
  <div class="relative flex-1"><i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i><input name="q" value="<?= e($t) ?>" placeholder="Nombre, NIT o contacto" class="input pl-10"></div>
  <button class="btn-dark">Buscar</button>
</form>
<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
  <?php foreach ($lista as $r): ?>
    <div class="card p-5">
      <div class="flex items-start gap-3">
        <div class="h-11 w-11 rounded-xl bg-indigo-50 text-indigo-600 grid place-items-center"><i data-lucide="truck" class="w-5 h-5"></i></div>
        <div class="flex-1 min-w-0"><div class="font-bold truncate"><?= e($r['nombre']) ?></div><div class="text-xs text-slate-500">NIT <?= e($r['nit'] ?: '—') ?><?= $r['contacto'] ? ' · ' . e($r['contacto']) : '' ?></div></div>
        <button onclick='editar(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="p-2 rounded-lg hover:bg-slate-100 text-slate-500"><i data-lucide="pencil" class="w-4 h-4"></i></button>
      </div>
      <div class="mt-4 space-y-1 text-sm text-slate-600">
        <?php if ($r['telefono']): ?><div class="flex items-center gap-2"><i data-lucide="phone" class="w-3.5 h-3.5 text-slate-400"></i><?= e($r['telefono']) ?></div><?php endif; ?>
        <?php if ($r['email']): ?><div class="flex items-center gap-2 truncate"><i data-lucide="mail" class="w-3.5 h-3.5 text-slate-400"></i><?= e($r['email']) ?></div><?php endif; ?>
      </div>
      <div class="grid grid-cols-3 gap-2 mt-4 pt-4 border-t border-slate-100 text-center">
        <div><div class="text-[10px] uppercase font-bold text-slate-400">Compras</div><div class="font-bold text-sm"><?= num($r['compras']) ?></div></div>
        <div><div class="text-[10px] uppercase font-bold text-slate-400">Total</div><div class="font-bold text-sm"><?= money($r['total']) ?></div></div>
        <div><div class="text-[10px] uppercase font-bold text-slate-400">Última</div><div class="font-bold text-sm"><?= $r['ultima'] ? date('d/m/y', strtotime($r['ultima'])) : '—' ?></div></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php if (!$lista) echo '<div class="card">' . empty_state('Aún no hay proveedores', 'truck') . '</div>'; ?>

<div id="modal" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm grid place-items-center p-4" onclick="if(event.target===this)this.classList.add('hidden')">
  <form method="post" id="fp" class="card w-full max-w-lg p-6 space-y-4 max-h-[90vh] overflow-y-auto">
    <?= csrf_field() ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id">
    <h3 class="font-bold text-lg" id="mTitulo">Proveedor</h3>
    <div class="grid sm:grid-cols-2 gap-3">
      <div class="sm:col-span-2"><label class="label">Nombre / razón social *</label><input name="nombre" class="input" required></div>
      <div><label class="label">NIT</label><input name="nit" class="input"></div>
      <div><label class="label">Contacto / visitador</label><input name="contacto" class="input"></div>
      <div><label class="label">Teléfono</label><input name="telefono" class="input"></div>
      <div><label class="label">Correo</label><input type="email" name="email" class="input"></div>
      <div class="sm:col-span-2"><label class="label">Dirección</label><input name="direccion" class="input"></div>
      <div class="sm:col-span-2"><label class="label">Notas (días de visita, crédito, etc.)</label><input name="notas" class="input"></div>
    </div>
    <div class="flex gap-2 justify-between">
      <button type="button" id="btnDel" class="btn-danger" onclick="if(confirm('¿Eliminar este proveedor?')){fp.accion.value='eliminar';fp.submit();}"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
      <div class="flex gap-2"><button type="button" class="btn-light" onclick="modal.classList.add('hidden')">Cancelar</button><button class="btn-primary">Guardar</button></div>
    </div>
  </form>
</div>
<script>
function editar(r) {
  fp.reset(); fp.accion.value = 'guardar';
  ['id', 'nombre', 'nit', 'contacto', 'telefono', 'email', 'direccion', 'notas'].forEach(k => fp.elements[k].value = r ? (r[k] || '') : '');
  mTitulo.textContent = r ? 'Editar proveedor' : 'Nuevo proveedor';
  btnDel.classList.toggle('invisible', !r);
  modal.classList.remove('hidden'); fp.nombre.focus();
}
</script>
<?php layout_end();
