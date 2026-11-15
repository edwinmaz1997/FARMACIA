<?php
require_admin();
$F = fid();
$a = g('a', 'lista');

if (is_post() && p('accion') === 'anular') {
    csrf_check();
    $c = one("SELECT * FROM compras WHERE id = ? AND farmacia_id = ? AND estado = 'registrada'", [(int) p('id'), $F]);
    if ($c) {
        $lotes = all('SELECT * FROM lotes WHERE compra_id = ? AND farmacia_id = ?', [$c['id'], $F]);
        $usados = array_filter($lotes, function ($l) { return (int) $l['cantidad_actual'] !== (int) $l['cantidad_inicial']; });
        if ($usados) {
            flash('error', 'No se puede anular: algunos lotes de esta compra ya tienen ventas o ajustes. Usa un ajuste de inventario si necesitas corregir.');
        } else {
            db()->beginTransaction();
            foreach ($lotes as $l) {
                q('UPDATE lotes SET cantidad_actual = 0 WHERE id = ?', [$l['id']]);
                registrar_movimiento($l['producto_id'], $l['id'], 'anulacion_compra', -$l['cantidad_actual'], 'Anulación compra #' . $c['id']);
            }
            update('compras', ['estado' => 'anulada', 'notas' => trim(($c['notas'] ? $c['notas'] . ' | ' : '') . 'Anulada: ' . p('motivo'))], 'id = ?', [$c['id']]);
            db()->commit();
            flash('success', 'Compra anulada y su inventario retirado.');
        }
    }
    redirect('compras', ['a' => 'ver', 'id' => (int) p('id')]);
}

if ($a === 'nueva') {
    $proveedores = all('SELECT id, nombre FROM proveedores WHERE farmacia_id = ? AND activo = 1 ORDER BY nombre', [$F]);
    layout_start('Nueva compra', 'compras');
    page_header('Registrar compra', 'Ingresa la factura del proveedor. Cada línea crea un lote con su fecha de vencimiento.', '<a href="' . url('compras') . '" class="btn-light"><i data-lucide="arrow-left" class="w-4 h-4"></i> Compras</a>');
    ?>
<div class="card p-5 mb-6 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
  <div class="lg:col-span-2"><label class="label">Proveedor</label>
    <div class="flex gap-2"><select id="proveedor" class="input"><option value="">Sin proveedor</option><?php foreach ($proveedores as $pv): ?><option value="<?= $pv['id'] ?>"><?= e($pv['nombre']) ?></option><?php endforeach; ?></select>
    <a href="<?= url('proveedores') ?>" target="_blank" class="btn-light px-3" title="Gestionar proveedores"><i data-lucide="plus" class="w-4 h-4"></i></a></div>
  </div>
  <div><label class="label">No. de factura</label><input id="factura" class="input"></div>
  <div><label class="label">Fecha</label><input type="date" id="fecha" value="<?= date('Y-m-d') ?>" class="input"></div>
</div>

<div class="card p-4 mb-4 relative">
  <div class="flex gap-2">
    <div class="relative flex-1">
      <i data-lucide="search" class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-brand-600"></i>
      <input id="buscar" autocomplete="off" placeholder="Busca o escanea el producto a ingresar…" class="input pl-12 py-3">
    </div>
    <button onclick="crearProducto()" class="btn-light"><i data-lucide="plus" class="w-4 h-4"></i> <span class="hidden sm:inline">Producto nuevo</span></button>
  </div>
  <div id="resultados" class="hidden absolute left-4 right-4 top-full -mt-2 z-20 card max-h-80 overflow-y-auto divide-y divide-slate-100"></div>
</div>

<div class="card overflow-hidden">
  <div class="hidden lg:grid grid-cols-[minmax(0,1fr)_110px_150px_90px_110px_110px_110px_36px] gap-3 px-5 py-3 bg-slate-50/80 text-[11px] font-bold uppercase tracking-wider text-slate-500">
    <div>Producto</div><div>No. lote</div><div>Vencimiento</div><div class="text-right">Cantidad</div><div class="text-right">Costo unit.</div><div class="text-right">Precio venta</div><div class="text-right">Subtotal</div><div></div>
  </div>
  <div id="lineas" class="divide-y divide-slate-100"></div>
  <div class="p-5 border-t border-slate-100 flex flex-col sm:flex-row gap-4 sm:items-center">
    <input id="notas" class="input sm:max-w-md" placeholder="Notas (opcional)">
    <div class="sm:ml-auto flex items-center gap-6">
      <div class="text-right"><div class="text-xs text-slate-500">Total de la compra</div><div id="total" class="text-2xl font-extrabold">Q 0.00</div></div>
      <button id="btnGuardar" onclick="guardar()" class="btn-primary py-3"><i data-lucide="check" class="w-4 h-4"></i> Guardar compra</button>
    </div>
  </div>
</div>

<div id="modalProd" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm grid place-items-center p-4" onclick="if(event.target===this)this.classList.add('hidden')">
  <form id="fProd" class="card w-full max-w-lg p-6 max-h-[92vh] overflow-y-auto" onsubmit="guardarProducto(event)">
    <div class="flex items-start gap-3 mb-5">
      <div class="h-11 w-11 rounded-xl bg-brand-50 text-brand-600 grid place-items-center shrink-0"><i data-lucide="pill" class="w-5 h-5"></i></div>
      <div class="flex-1"><h3 class="font-bold text-lg leading-tight">Nuevo producto</h3><p class="text-sm text-slate-500">Se agregará a esta compra al guardarlo.</p></div>
      <button type="button" onclick="modalProd.classList.add('hidden')" class="p-2 -m-2 rounded-lg text-slate-400 hover:bg-slate-100"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div class="sm:col-span-2"><label class="label">Nombre *</label><input name="nombre" class="input" required></div>
      <div><label class="label">Código de barras</label><input name="codigo" class="input"></div>
      <div><label class="label">Presentación</label><input name="presentacion" class="input" placeholder="Caja 10 tabletas"></div>
      <div><label class="label">Precio de venta</label><input type="number" step="0.01" min="0" name="precio_venta" class="input"></div>
    </div>
    <p class="text-xs text-slate-500 mt-3">Podrás completar principio activo, laboratorio y categoría después desde Productos.</p>
    <div id="errProd" class="hidden mt-4 rounded-xl bg-rose-50 text-rose-700 text-sm px-4 py-3"></div>
    <div class="flex gap-2 justify-end mt-6"><button type="button" class="btn-light" onclick="modalProd.classList.add('hidden')">Cancelar</button><button class="btn-primary"><i data-lucide="check" class="w-4 h-4"></i> Guardar y agregar</button></div>
  </form>
</div>

<script>
let lineas = [], res = [], tm = null;
const $ = id => document.getElementById(id);
async function api(action, opts = {}) {
  const r = await fetch('index.php?p=api&action=' + action + (opts.qs || ''), opts.body ? { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF }, body: JSON.stringify(opts.body) } : {});
  return r.json();
}
$('buscar').addEventListener('input', e => { clearTimeout(tm); const v = e.target.value.trim(); if (!v) return $('resultados').classList.add('hidden'); tm = setTimeout(() => buscar(v), 200); });
$('buscar').addEventListener('keydown', async e => {
  if (e.key !== 'Enter') return; e.preventDefault(); clearTimeout(tm);
  const v = e.target.value.trim(); if (!v) return;
  await buscar(v);
  const ex = res.find(p => p.codigo === v); if (ex) agregar(ex); else if (res.length === 1) agregar(res[0]);
});
async function buscar(v) {
  const r = await api('productos', { qs: '&q=' + encodeURIComponent(v) }); res = r.data || [];
  const box = $('resultados');
  box.innerHTML = res.map((p, i) => `<button data-i="${i}" class="w-full text-left flex justify-between gap-3 px-4 py-3 hover:bg-slate-50"><div class="min-w-0"><div class="font-semibold text-sm truncate">${escapeHtml(p.nombre)}</div><div class="text-xs text-slate-500">${escapeHtml([p.presentacion, p.laboratorio, p.codigo].filter(Boolean).join(' · '))}</div></div><div class="text-xs text-slate-500 text-right shrink-0">Stock ${p.stock}<br>Costo ${fmtMoney(p.precio_compra)}</div></button>`).join('')
    || `<div class="p-4 text-sm text-slate-500 text-center">Sin resultados · <button class="text-brand-700 font-semibold" onclick="crearProducto()">Crear producto</button></div>`;
  box.classList.remove('hidden');
  box.querySelectorAll('[data-i]').forEach(b => b.onclick = () => agregar(res[b.dataset.i]));
}
function agregar(p) {
  lineas.push({ producto_id: p.id, nombre: p.nombre, detalle: [p.presentacion, p.laboratorio].filter(Boolean).join(' · '), lote: '', vencimiento: '', cantidad: 1, costo: Number(p.precio_compra) || 0, precio_venta: Number(p.precio_venta) || 0 });
  $('buscar').value = ''; $('resultados').classList.add('hidden'); render();
  const ins = document.querySelectorAll('#lineas > div:last-child input'); if (ins[0] && innerWidth >= 1024) ins[0].focus();
}
function render() {
  const lbl = t => `<span class="lg:hidden block text-[11px] font-semibold text-slate-500 mb-1">${t}</span>`;
  $('lineas').innerHTML = lineas.length ? lineas.map((l, k) => `
    <div class="grid grid-cols-2 lg:grid-cols-[minmax(0,1fr)_110px_150px_90px_110px_110px_110px_36px] gap-3 px-5 py-4 lg:py-3 items-center">
      <div class="col-span-2 lg:col-span-1 flex items-start gap-2 min-w-0">
        <div class="flex-1 min-w-0"><div class="font-semibold text-sm">${escapeHtml(l.nombre)}</div><div class="text-xs text-slate-500 truncate">${escapeHtml(l.detalle)}</div></div>
        <button onclick="lineas.splice(${k},1);render()" class="lg:hidden p-2 -m-1 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50">✕</button>
      </div>
      <label class="block">${lbl('No. lote')}<input value="${escapeHtml(l.lote)}" oninput="lineas[${k}].lote=this.value" class="input py-1.5" placeholder="Lote"></label>
      <label class="block">${lbl('Vencimiento')}<input type="date" value="${l.vencimiento}" onchange="lineas[${k}].vencimiento=this.value" class="input py-1.5"></label>
      <label class="block">${lbl('Cantidad')}<input type="number" min="1" inputmode="numeric" value="${l.cantidad}" oninput="lineas[${k}].cantidad=parseInt(this.value)||0;tot()" class="input py-1.5 text-right"></label>
      <label class="block">${lbl('Costo unit.')}<input type="number" min="0" step="0.01" inputmode="decimal" value="${l.costo}" oninput="lineas[${k}].costo=Number(this.value)||0;tot()" class="input py-1.5 text-right"></label>
      <label class="block">${lbl('Precio venta')}<input type="number" min="0" step="0.01" inputmode="decimal" value="${l.precio_venta}" oninput="lineas[${k}].precio_venta=Number(this.value)||0" class="input py-1.5 text-right"></label>
      <div class="text-right">${lbl('Subtotal')}<span class="font-bold whitespace-nowrap" id="sub${k}"></span></div>
      <button onclick="lineas.splice(${k},1);render()" class="hidden lg:block p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50">✕</button>
    </div>`).join('') : '<div class="py-14 text-center text-slate-400 text-sm">Agrega productos con el buscador</div>';
  tot();
}
function tot() {
  let t = 0; lineas.forEach((l, k) => { const s = l.cantidad * l.costo; t += s; const el = $('sub' + k); if (el) el.textContent = fmtMoney(s); });
  $('total').textContent = fmtMoney(t); $('btnGuardar').disabled = !lineas.length;
}
function crearProducto() {
  const f = $('fProd'); f.reset(); $('errProd').classList.add('hidden');
  const t = $('buscar').value.trim();
  if (t) { if (/^\d{6,}$/.test(t)) f.codigo.value = t; else f.nombre.value = t; }
  $('resultados').classList.add('hidden'); $('modalProd').classList.remove('hidden');
  setTimeout(() => f.nombre.focus(), 50);
}
async function guardarProducto(ev) {
  ev.preventDefault();
  const r = await api('crear_producto', { body: Object.fromEntries(new FormData($('fProd')).entries()) });
  if (!r.ok) { $('errProd').textContent = r.error; $('errProd').classList.remove('hidden'); return; }
  $('modalProd').classList.add('hidden'); agregar(r.data);
}
async function guardar() {
  if (!lineas.length) return;
  const sinVenc = lineas.filter(l => !l.vencimiento).length;
  if (lineas.some(l => l.cantidad <= 0)) return alert('Revisa las cantidades.');
  if (sinVenc && !confirm(sinVenc + ' línea(s) no tienen fecha de vencimiento. ¿Guardar de todas formas?')) return;
  $('btnGuardar').disabled = true;
  const r = await api('guardar_compra', { body: { proveedor_id: $('proveedor').value, numero_factura: $('factura').value, fecha: $('fecha').value, notas: $('notas').value, items: lineas } });
  if (!r.ok) { alert(r.error); $('btnGuardar').disabled = false; return; }
  location.href = 'index.php?p=compras&a=ver&id=' + r.id;
}
window.addEventListener('beforeunload', e => { if (lineas.length && !$('btnGuardar').disabled) { e.preventDefault(); e.returnValue = ''; } });
document.addEventListener('click', e => { if (!e.target.closest('#resultados') && e.target !== $('buscar')) $('resultados').classList.add('hidden'); });
render(); $('buscar').focus();
</script>
<?php
    layout_end();
    return;
}

if ($a === 'ver') {
    $c = one('SELECT c.*, pv.nombre proveedor, u.nombre usuario FROM compras c LEFT JOIN proveedores pv ON pv.id = c.proveedor_id LEFT JOIN usuarios u ON u.id = c.usuario_id WHERE c.id = ? AND c.farmacia_id = ?', [(int) g('id'), $F]);
    if (!$c) redirect('compras');
    $det = all('SELECT d.*, p.nombre, p.presentacion, l.numero_lote, l.fecha_vencimiento, l.cantidad_actual FROM compra_detalle d JOIN productos p ON p.id = d.producto_id LEFT JOIN lotes l ON l.id = d.lote_id WHERE d.compra_id = ?', [$c['id']]);
    layout_start('Compra #' . $c['id'], 'compras');
    page_header('Compra #' . $c['id'], e($c['proveedor'] ?: 'Sin proveedor') . ' · Factura ' . e($c['numero_factura'] ?: 's/n') . ' · ' . fdate($c['fecha']) . ' ' . ($c['estado'] === 'anulada' ? badge('Anulada', 'rose') : badge('Registrada', 'emerald')),
        '<a href="' . url('compras') . '" class="btn-light"><i data-lucide="arrow-left" class="w-4 h-4"></i> Compras</a><button onclick="print()" class="btn-light"><i data-lucide="printer" class="w-4 h-4"></i> Imprimir</button>');
    ?>
<div class="card overflow-hidden">
  <div class="overflow-x-auto"><table class="tbl">
    <thead><tr><th>Producto</th><th>Lote</th><th>Vence</th><th class="text-right">Cantidad</th><th class="text-right">Disponible</th><th class="text-right">Costo</th><th class="text-right">Subtotal</th></tr></thead>
    <tbody><?php foreach ($det as $d): ?>
      <tr><td><div class="font-semibold"><?= e($d['nombre']) ?></div><div class="text-xs text-slate-500"><?= e($d['presentacion']) ?></div></td><td><?= e($d['numero_lote'] ?: 's/n') ?></td><td><?= fdate($d['fecha_vencimiento']) ?></td><td class="text-right"><?= num($d['cantidad']) ?></td><td class="text-right text-slate-500"><?= num($d['cantidad_actual']) ?></td><td class="text-right"><?= money($d['costo_unitario']) ?></td><td class="text-right font-semibold"><?= money($d['subtotal']) ?></td></tr>
    <?php endforeach; ?></tbody>
    <tfoot><tr class="bg-slate-50"><td colspan="6" class="px-4 py-3 font-bold">Total</td><td class="px-4 py-3 text-right text-lg font-extrabold"><?= money($c['total']) ?></td></tr></tfoot>
  </table></div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
  <div class="card p-5 text-sm space-y-2"><div class="flex justify-between"><span class="text-slate-500">Registrada por</span><span><?= e($c['usuario']) ?></span></div><div class="flex justify-between"><span class="text-slate-500">Fecha de registro</span><span><?= fdatetime($c['created_at']) ?></span></div><?php if ($c['notas']): ?><div class="bg-slate-50 rounded-lg p-3"><?= e($c['notas']) ?></div><?php endif; ?></div>
  <?php if ($c['estado'] === 'registrada'): ?>
  <form method="post" class="card p-5 space-y-3 no-print" data-confirm="¿Anular esta compra? Se retirará su inventario.">
    <?= csrf_field() ?><input type="hidden" name="accion" value="anular"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
    <h3 class="font-bold text-rose-700">Anular compra</h3><p class="text-xs text-slate-500">Solo es posible si ningún lote de la compra ha tenido ventas.</p>
    <div class="flex gap-2"><input name="motivo" class="input" placeholder="Motivo" required><button class="btn-danger">Anular</button></div>
  </form>
  <?php endif; ?>
</div>
<?php
    layout_end();
    return;
}

list($desde, $hasta) = rango_fechas();
$params = [$F, $desde, $hasta];
$w = 'c.farmacia_id = ? AND c.fecha BETWEEN ? AND ?';
if (g('proveedor')) { $w .= ' AND c.proveedor_id = ?'; $params[] = (int) g('proveedor'); }
$lista = all("SELECT c.*, pv.nombre proveedor, (SELECT COUNT(*) FROM compra_detalle d WHERE d.compra_id=c.id) lineas FROM compras c LEFT JOIN proveedores pv ON pv.id = c.proveedor_id WHERE $w ORDER BY c.fecha DESC, c.id DESC", $params);
$total = array_sum(array_map(function ($c) { return $c['estado'] === 'registrada' ? $c['total'] : 0; }, $lista));
if (g('csv')) csv_download('compras.csv', ['No.', 'Fecha', 'Proveedor', 'Factura', 'Líneas', 'Total', 'Estado'], array_map(function ($c) {
    return [$c['id'], $c['fecha'], $c['proveedor'], $c['numero_factura'], $c['lineas'], $c['total'], $c['estado']];
}, $lista));
$proveedores = all('SELECT id, nombre FROM proveedores WHERE farmacia_id = ? ORDER BY nombre', [$F]);

layout_start('Compras', 'compras');
page_header('Compras', count($lista) . ' compras · ' . money($total), '<a href="' . url('compras', array_merge($_GET, ['csv' => 1])) . '" class="btn-light"><i data-lucide="download" class="w-4 h-4"></i> CSV</a><a href="' . url('compras', ['a' => 'nueva']) . '" class="btn-primary"><i data-lucide="package-plus" class="w-4 h-4"></i> Registrar compra</a>');
$sel = '<div class="sm:w-56"><label class="label">Proveedor</label><select name="proveedor" class="input"><option value="">Todos</option>';
foreach ($proveedores as $pv) $sel .= '<option value="' . $pv['id'] . '" ' . (g('proveedor') == $pv['id'] ? 'selected' : '') . '>' . e($pv['nombre']) . '</option>';
date_filter_form('compras', $sel . '</select></div>');
?>
<div class="card overflow-hidden">
  <div class="overflow-x-auto"><table class="tbl">
    <thead><tr><th>No.</th><th>Fecha</th><th>Proveedor</th><th>Factura</th><th class="text-right">Productos</th><th class="text-right">Total</th><th>Estado</th><th></th></tr></thead>
    <tbody><?php foreach ($lista as $c): ?>
      <tr class="<?= $c['estado'] === 'anulada' ? 'opacity-50' : '' ?>">
        <td class="font-bold">#<?= $c['id'] ?></td><td><?= fdate($c['fecha']) ?></td><td><?= e($c['proveedor'] ?: '—') ?></td><td><?= e($c['numero_factura'] ?: '—') ?></td>
        <td class="text-right"><?= num($c['lineas']) ?></td><td class="text-right font-semibold"><?= money($c['total']) ?></td>
        <td><?= $c['estado'] === 'anulada' ? badge('Anulada', 'rose') : badge('Registrada', 'emerald') ?></td>
        <td class="text-right"><a href="<?= url('compras', ['a' => 'ver', 'id' => $c['id']]) ?>" class="btn-light btn-sm">Ver</a></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table><?php if (!$lista) echo empty_state('No hay compras en el periodo', 'package-plus'); ?></div>
</div>
<?php layout_end();
