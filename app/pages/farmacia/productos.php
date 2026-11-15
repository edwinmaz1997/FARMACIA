<?php
require_farmacia();
$F = fid();
$a = g('a', 'lista');

if (is_post()) {
    csrf_check();
    if (!is_admin()) {
        flash('error', 'Sin permiso.');
        redirect('productos');
    }
    $acc = p('accion');
    if ($acc === 'guardar') {
        $id = (int) p('id');
        $cat = (int) p('categoria_id');
        $d = [
            'nombre' => p('nombre'),
            'codigo' => nulo(p('codigo')),
            'principio_activo' => nulo(p('principio_activo')),
            'presentacion' => nulo(p('presentacion')),
            'laboratorio' => nulo(p('laboratorio')),
            'categoria_id' => $cat && val('SELECT id FROM categorias WHERE id = ? AND farmacia_id = ?', [$cat, $F]) ? $cat : null,
            'unidad' => p('unidad') ?: 'Unidad',
            'precio_compra' => (float) p('precio_compra'),
            'precio_venta' => (float) p('precio_venta'),
            'stock_minimo' => max(0, (int) p('stock_minimo')),
            'requiere_receta' => p('requiere_receta') ? 1 : 0,
            'activo' => p('activo', '1') ? 1 : 0,
        ];
        if ($d['nombre'] === '') {
            flash('error', 'El nombre es obligatorio.');
            back();
        }
        if ($d['codigo'] && val('SELECT COUNT(*) FROM productos WHERE farmacia_id = ? AND codigo = ? AND id <> ?', [$F, $d['codigo'], $id])) {
            flash('error', 'Ya existe otro producto con el código ' . $d['codigo'] . '.');
            back();
        }
        if ($id) {
            update('productos', $d, 'id = ? AND farmacia_id = ?', [$id, $F]);
        } else {
            $d['farmacia_id'] = $F;
            $id = insert('productos', $d);
            // Stock inicial opcional
            $ini = (int) p('stock_inicial');
            if ($ini > 0) {
                $lid = insert('lotes', ['farmacia_id' => $F, 'producto_id' => $id, 'numero_lote' => nulo(p('lote_inicial')), 'fecha_vencimiento' => nulo(p('venc_inicial')), 'cantidad_inicial' => $ini, 'cantidad_actual' => $ini, 'costo_unitario' => $d['precio_compra']]);
                registrar_movimiento($id, $lid, 'ajuste_entrada', $ini, 'Inventario inicial');
            }
        }
        flash('success', 'Producto guardado.');
        if (p('otro')) redirect('productos', ['a' => 'nuevo']);
        redirect('productos', ['a' => 'ver', 'id' => $id]);
    }
    if ($acc === 'categoria') {
        if (p('nombre') !== '') insert('categorias', ['farmacia_id' => $F, 'nombre' => p('nombre')]);
        flash('success', 'Categoría agregada.');
    }
    if ($acc === 'borrar_categoria') {
        q('DELETE FROM categorias WHERE id = ? AND farmacia_id = ?', [(int) p('id'), $F]);
        flash('success', 'Categoría eliminada.');
    }
    if ($acc === 'eliminar') {
        $id = (int) p('id');
        $usado = val('SELECT COUNT(*) FROM venta_detalle d JOIN ventas v ON v.id=d.venta_id WHERE d.producto_id = ? AND v.farmacia_id = ?', [$id, $F])
            + val('SELECT COUNT(*) FROM compra_detalle d JOIN compras c ON c.id=d.compra_id WHERE d.producto_id = ? AND c.farmacia_id = ?', [$id, $F]);
        if ($usado) {
            update('productos', ['activo' => 0], 'id = ? AND farmacia_id = ?', [$id, $F]);
            flash('success', 'El producto tiene historial, así que se desactivó en lugar de eliminarse.');
        } else {
            q('DELETE FROM productos WHERE id = ? AND farmacia_id = ?', [$id, $F]);
            flash('success', 'Producto eliminado.');
        }
        redirect('productos');
    }
    back('productos');
}

$categorias = all('SELECT * FROM categorias WHERE farmacia_id = ? ORDER BY nombre', [$F]);

if ($a === 'importar') {
    if (!is_admin()) redirect('productos');
    require __DIR__ . '/productos_importar.php';
    return;
}

if ($a === 'nuevo' || $a === 'editar') {
    if (!is_admin()) redirect('productos');
    $pr = $a === 'editar' ? one('SELECT * FROM productos WHERE id = ? AND farmacia_id = ?', [(int) g('id'), $F]) : null;
    $v = function ($k, $d = '') use ($pr) { return $pr ? $pr[$k] : $d; };
    layout_start($pr ? 'Editar producto' : 'Nuevo producto', 'productos');
    page_header($pr ? 'Editar producto' : 'Nuevo producto', '', '<a href="' . url('productos') . '" class="btn-light"><i data-lucide="arrow-left" class="w-4 h-4"></i> Productos</a>');
    ?>
<form method="post" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <?= csrf_field() ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id" value="<?= $pr ? (int) $pr['id'] : 0 ?>">
  <div class="card p-6 lg:col-span-2">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div class="sm:col-span-2"><label class="label">Nombre comercial *</label><input name="nombre" value="<?= e($v('nombre')) ?>" class="input" required autofocus placeholder="Ej. Acetaminofén 500 mg"></div>
      <div><label class="label">Código de barras</label><input name="codigo" value="<?= e($v('codigo')) ?>" class="input" placeholder="Escanea o escribe"></div>
      <div><label class="label">Categoría</label><select name="categoria_id" class="input"><option value="">Sin categoría</option><?php foreach ($categorias as $c): ?><option value="<?= $c['id'] ?>" <?= (int) $v('categoria_id') === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?></select></div>
      <div><label class="label">Principio activo</label><input name="principio_activo" value="<?= e($v('principio_activo')) ?>" class="input" placeholder="Ej. Paracetamol"></div>
      <div><label class="label">Presentación</label><input name="presentacion" value="<?= e($v('presentacion')) ?>" class="input" placeholder="Ej. Caja 10 tabletas"></div>
      <div><label class="label">Laboratorio</label><input name="laboratorio" value="<?= e($v('laboratorio')) ?>" class="input"></div>
      <div><label class="label">Unidad de venta</label><input name="unidad" value="<?= e($v('unidad', 'Unidad')) ?>" class="input" list="unidades"><datalist id="unidades"><option>Unidad</option><option>Caja</option><option>Blíster</option><option>Frasco</option><option>Tableta</option><option>Sobre</option><option>Tubo</option><option>Ampolla</option></datalist></div>
      <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 sm:col-span-2 cursor-pointer"><input type="checkbox" name="requiere_receta" value="1" <?= $v('requiere_receta') ? 'checked' : '' ?> class="rounded text-brand-600 w-4 h-4"><span class="text-sm"><b>Requiere receta médica</b><span class="block text-xs text-slate-500">Se pedirá confirmación al venderlo.</span></span></label>
    </div>
  </div>
  <div class="space-y-6">
    <div class="card p-6 space-y-4">
      <h3 class="font-bold">Precios e inventario</h3>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="label">Costo</label><input type="number" step="0.01" min="0" name="precio_compra" id="pc" value="<?= e($v('precio_compra', '0')) ?>" class="input"></div>
        <div><label class="label">Precio venta *</label><input type="number" step="0.01" min="0" name="precio_venta" id="pv" value="<?= e($v('precio_venta', '')) ?>" class="input" required></div>
      </div>
      <div class="text-xs text-slate-500">Margen: <b id="margen" class="text-emerald-600">—</b></div>
      <div><label class="label">Stock mínimo (alerta)</label><input type="number" min="0" name="stock_minimo" value="<?= e($v('stock_minimo', '5')) ?>" class="input"></div>
      <?php if ($pr): ?>
        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="activo" value="0"><input type="checkbox" name="activo" value="1" <?= $pr['activo'] ? 'checked' : '' ?> class="rounded text-brand-600"> Producto activo</label>
      <?php endif; ?>
    </div>
    <?php if (!$pr): ?>
    <div class="card p-6 space-y-3">
      <h3 class="font-bold">Inventario inicial <span class="text-xs font-normal text-slate-500">(opcional)</span></h3>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="label">Cantidad</label><input type="number" min="0" name="stock_inicial" class="input" value="0"></div>
        <div><label class="label">No. de lote</label><input name="lote_inicial" class="input"></div>
      </div>
      <div><label class="label">Fecha de vencimiento</label><input type="date" name="venc_inicial" class="input"></div>
    </div>
    <?php endif; ?>
    <div class="flex gap-2">
      <button class="btn-primary flex-1"><i data-lucide="save" class="w-4 h-4"></i> Guardar</button>
      <?php if (!$pr): ?><button name="otro" value="1" class="btn-light">Guardar y otro</button><?php endif; ?>
    </div>
  </div>
</form>
<script>
function margen() { const c = +pc.value || 0, v = +pv.value || 0; margen_el.textContent = v ? (((v - c) / v) * 100).toFixed(1) + '% · ganancia ' + fmtMoney(v - c) : '—'; }
const margen_el = document.getElementById('margen'); pc.oninput = pv.oninput = margen; margen();
</script>
<?php
    layout_end();
    return;
}

if ($a === 'ver') {
    $pr = one('SELECT p.*, c.nombre categoria FROM productos p LEFT JOIN categorias c ON c.id = p.categoria_id WHERE p.id = ? AND p.farmacia_id = ?', [(int) g('id'), $F]);
    if (!$pr) redirect('productos');
    $lotes = all('SELECT l.*, c.numero_factura, pv.nombre proveedor FROM lotes l LEFT JOIN compras c ON c.id = l.compra_id LEFT JOIN proveedores pv ON pv.id = c.proveedor_id WHERE l.producto_id = ? AND l.farmacia_id = ? ORDER BY l.cantidad_actual > 0 DESC, l.fecha_vencimiento IS NULL, l.fecha_vencimiento', [$pr['id'], $F]);
    $stock = array_sum(array_column($lotes, 'cantidad_actual'));
    $vend = one("SELECT COALESCE(SUM(d.cantidad),0) c, COALESCE(SUM(d.subtotal),0) t FROM venta_detalle d JOIN ventas v ON v.id=d.venta_id WHERE d.producto_id = ? AND v.estado='completada' AND v.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", [$pr['id']]);
    $dias = (int) farmacia_actual()['dias_alerta_vencimiento'];
    layout_start($pr['nombre'], 'productos');
    page_header($pr['nombre'], e(implode(' · ', array_filter([$pr['presentacion'], $pr['principio_activo'], $pr['laboratorio'], $pr['categoria']]))) . ($pr['requiere_receta'] ? ' ' . badge('Receta', 'amber') : '') . ($pr['activo'] ? '' : ' ' . badge('Inactivo', 'rose')),
        '<a href="' . url('productos') . '" class="btn-light"><i data-lucide="arrow-left" class="w-4 h-4"></i> Productos</a><a href="' . url('kardex', ['id' => $pr['id']]) . '" class="btn-light"><i data-lucide="history" class="w-4 h-4"></i> Kardex</a>'
        . (is_admin() ? '<a href="' . url('productos', ['a' => 'editar', 'id' => $pr['id']]) . '" class="btn-dark"><i data-lucide="pencil" class="w-4 h-4"></i> Editar</a>' : ''));
    ?>
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
  <?php
  stat_card('Stock actual', num($stock) . ' <span class="text-sm font-semibold text-slate-400">' . e($pr['unidad']) . '</span>', 'package', $stock <= $pr['stock_minimo'] ? 'rose' : 'brand', 'Mínimo ' . num($pr['stock_minimo']));
  stat_card('Precio de venta', money($pr['precio_venta']), 'tag', 'indigo', is_admin() ? 'Costo ' . money($pr['precio_compra']) : '');
  stat_card('Vendido (30 días)', num($vend['c']), 'trending-up', 'emerald', money($vend['t']));
  if (is_admin()) stat_card('Valor en inventario', money(array_sum(array_map(function ($l) { return $l['cantidad_actual'] * $l['costo_unitario']; }, $lotes))), 'coins', 'amber', 'A costo');
  ?>
</div>
<div class="card overflow-hidden">
  <div class="p-5 border-b border-slate-100 flex items-center justify-between"><h3 class="font-bold">Lotes</h3><span class="text-xs text-slate-500">Las ventas descuentan primero el lote que vence antes (FEFO)</span></div>
  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>Lote</th><th>Vence</th><th>Proveedor / factura</th><th class="text-right">Ingresó</th><th class="text-right">Disponible</th><th class="text-right">Costo</th><th>Estado</th></tr></thead>
      <tbody>
      <?php foreach ($lotes as $l): $d = dias_hasta($l['fecha_vencimiento']); ?>
        <tr class="<?= $l['cantidad_actual'] <= 0 ? 'opacity-50' : '' ?>">
          <td class="font-semibold"><?= e($l['numero_lote'] ?: 's/n') ?></td>
          <td class="whitespace-nowrap"><?= fdate($l['fecha_vencimiento']) ?></td>
          <td class="text-slate-500"><?= e($l['proveedor'] ?: ($l['compra_id'] ? 'Compra #' . $l['compra_id'] : 'Inventario inicial / ajuste')) ?><?= $l['numero_factura'] ? ' · ' . e($l['numero_factura']) : '' ?></td>
          <td class="text-right"><?= num($l['cantidad_inicial']) ?></td>
          <td class="text-right font-bold"><?= num($l['cantidad_actual']) ?></td>
          <td class="text-right"><?= money($l['costo_unitario']) ?></td>
          <td><?php
            if ($l['cantidad_actual'] <= 0) echo badge('Agotado', 'slate');
            elseif ($d === null) echo badge('Sin fecha', 'slate');
            elseif ($d < 0) echo badge('Vencido', 'rose');
            elseif ($d <= $dias) echo badge('Vence en ' . $d . ' d', $d <= 30 ? 'rose' : 'amber');
            else echo badge('Vigente', 'emerald');
          ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!$lotes) echo empty_state('Este producto aún no tiene lotes. Registra una compra para ingresar inventario.', 'package-open'); ?>
  </div>
</div>
<?php if (is_admin()): ?>
<form method="post" class="mt-6 text-right" data-confirm="¿Eliminar este producto? Si tiene historial se desactivará.">
  <?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int) $pr['id'] ?>">
  <button class="btn-danger btn-sm"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Eliminar producto</button>
</form>
<?php endif; ?>
<?php
    layout_end();
    return;
}

// Lista
$w = 'p.farmacia_id = ?';
$params = [$F];
$t = g('q');
if ($t !== '') {
    $w .= ' AND (p.nombre LIKE ? OR p.codigo = ? OR p.principio_activo LIKE ? OR p.laboratorio LIKE ?)';
    array_push($params, "%$t%", $t, "%$t%", "%$t%");
}
if (g('cat')) { $w .= ' AND p.categoria_id = ?'; $params[] = (int) g('cat'); }
$estado = g('estado', 'activos');
if ($estado === 'activos') $w .= ' AND p.activo = 1';
if ($estado === 'inactivos') $w .= ' AND p.activo = 0';
$having = '';
if (g('stock') === 'bajo') $having = 'HAVING stock <= p.stock_minimo';
if (g('stock') === 'sin') $having = 'HAVING stock <= 0';

$sql = "SELECT p.*, c.nombre categoria, COALESCE(SUM(l.cantidad_actual),0) stock, MIN(CASE WHEN l.cantidad_actual > 0 THEN l.fecha_vencimiento END) prox_venc
    FROM productos p LEFT JOIN categorias c ON c.id = p.categoria_id LEFT JOIN lotes l ON l.producto_id = p.id AND l.cantidad_actual > 0
    WHERE $w GROUP BY p.id $having ORDER BY p.nombre";
$total = (int) val("SELECT COUNT(*) FROM ($sql) x", $params);

if (g('csv')) {
    csv_download('productos.csv', ['Código', 'Nombre', 'Principio activo', 'Presentación', 'Laboratorio', 'Categoría', 'Costo', 'Precio', 'Stock', 'Stock mínimo', 'Próximo vencimiento'], array_map(function ($r) {
        return [$r['codigo'], $r['nombre'], $r['principio_activo'], $r['presentacion'], $r['laboratorio'], $r['categoria'], $r['precio_compra'], $r['precio_venta'], $r['stock'], $r['stock_minimo'], $r['prox_venc']];
    }, all($sql, $params)));
}
list($pag, $paginas, $off, $lim) = paginar($total, 30);
$lista = all($sql . " LIMIT $lim OFFSET $off", $params);
$dias = (int) farmacia_actual()['dias_alerta_vencimiento'];

layout_start('Productos', 'productos');
page_header('Productos', num($total) . ' productos', '<a href="' . url('productos', array_merge($_GET, ['csv' => 1])) . '" class="btn-light"><i data-lucide="download" class="w-4 h-4"></i> CSV</a>' . (is_admin() ? '<a href="' . url('productos', ['a' => 'importar']) . '" class="btn-light"><i data-lucide="file-up" class="w-4 h-4"></i> Importar</a><button onclick="document.getElementById(\'cats\').classList.toggle(\'hidden\')" class="btn-light"><i data-lucide="tags" class="w-4 h-4"></i> Categorías</button><a href="' . url('productos', ['a' => 'nuevo']) . '" class="btn-primary"><i data-lucide="plus" class="w-4 h-4"></i> Nuevo producto</a>' : ''));
?>
<?php if (is_admin()): ?>
<div id="cats" class="hidden card p-5 mb-6">
  <div class="flex flex-wrap gap-2 mb-4">
    <?php foreach ($categorias as $c): ?>
      <form method="post" class="chip bg-slate-100 text-slate-700 py-1.5 pl-3 pr-1.5 text-xs" data-confirm="¿Eliminar la categoría <?= e($c['nombre']) ?>?">
        <?= csrf_field() ?><input type="hidden" name="accion" value="borrar_categoria"><input type="hidden" name="id" value="<?= $c['id'] ?>">
        <?= e($c['nombre']) ?><button class="ml-1 rounded-full hover:bg-slate-200 p-0.5"><i data-lucide="x" class="w-3 h-3"></i></button>
      </form>
    <?php endforeach; ?>
  </div>
  <form method="post" class="flex gap-2 max-w-md"><?= csrf_field() ?><input type="hidden" name="accion" value="categoria"><input name="nombre" class="input" placeholder="Nueva categoría" required><button class="btn-dark">Agregar</button></form>
</div>
<?php endif; ?>

<form class="card p-4 mb-6 grid sm:grid-cols-2 lg:grid-cols-[1fr_200px_170px_170px_auto] gap-3">
  <input type="hidden" name="p" value="productos">
  <div class="relative"><i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i><input name="q" value="<?= e($t) ?>" placeholder="Nombre, código, principio activo, laboratorio" class="input pl-10"></div>
  <select name="cat" class="input"><option value="">Todas las categorías</option><?php foreach ($categorias as $c): ?><option value="<?= $c['id'] ?>" <?= g('cat') == $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?></select>
  <select name="stock" class="input"><option value="">Todo el stock</option><option value="bajo" <?= g('stock') === 'bajo' ? 'selected' : '' ?>>Stock bajo</option><option value="sin" <?= g('stock') === 'sin' ? 'selected' : '' ?>>Sin stock</option></select>
  <select name="estado" class="input"><option value="activos">Activos</option><option value="inactivos" <?= $estado === 'inactivos' ? 'selected' : '' ?>>Inactivos</option><option value="todos" <?= $estado === 'todos' ? 'selected' : '' ?>>Todos</option></select>
  <button class="btn-dark">Filtrar</button>
</form>

<div class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="tbl">
      <thead><tr><th>Producto</th><th class="hidden md:table-cell">Categoría</th><th class="text-right">Precio</th><th class="text-right">Stock</th><th class="hidden md:table-cell">Próx. vencimiento</th><th class="hidden sm:table-cell"></th></tr></thead>
      <tbody>
      <?php foreach ($lista as $r): $d = dias_hasta($r['prox_venc']); ?>
        <tr>
          <td>
            <a href="<?= url('productos', ['a' => 'ver', 'id' => $r['id']]) ?>" class="font-semibold text-slate-900 hover:text-brand-700"><?= e($r['nombre']) ?></a>
            <?= $r['requiere_receta'] ? badge('Rx', 'amber') : '' ?>
            <div class="text-xs text-slate-500"><?= e(implode(' · ', array_filter([$r['presentacion'], $r['principio_activo'], $r['laboratorio'], $r['codigo']]))) ?></div>
          </td>
          <td class="hidden md:table-cell text-slate-500"><?= e($r['categoria'] ?: '—') ?></td>
          <td class="text-right font-semibold whitespace-nowrap"><?= money($r['precio_venta']) ?></td>
          <td class="text-right"><?= badge(num($r['stock']), $r['stock'] <= 0 ? 'rose' : ($r['stock'] <= $r['stock_minimo'] ? 'amber' : 'emerald')) ?></td>
          <td class="hidden md:table-cell whitespace-nowrap"><?php if ($r['prox_venc']): ?><?= fdate($r['prox_venc']) ?> <?= $d < 0 ? badge('Vencido', 'rose') : ($d <= $dias ? badge($d . ' d', $d <= 30 ? 'rose' : 'amber') : '') ?><?php else: ?><span class="text-slate-400">—</span><?php endif; ?></td>
          <td class="hidden sm:table-cell text-right whitespace-nowrap">
            <a href="<?= url('productos', ['a' => 'ver', 'id' => $r['id']]) ?>" class="btn-light btn-sm">Ver</a>
            <?php if (is_admin()): ?><a href="<?= url('productos', ['a' => 'editar', 'id' => $r['id']]) ?>" class="btn-light btn-sm"><i data-lucide="pencil" class="w-3.5 h-3.5"></i></a><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!$lista) echo empty_state('No hay productos. ' . (is_admin() ? 'Crea el primero con "Nuevo producto" o súbelos todos con "Importar".' : ''), 'pill'); ?>
  </div>
  <?php pagination($pag, $paginas); ?>
</div>
<?php layout_end();
