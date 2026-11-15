<?php
require_farmacia();
$action = g('action');
$F = fid();

function api_input()
{
    $raw = file_get_contents('php://input');
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

if ($action === 'productos') {
    $t = g('q');
    $soloStock = g('stock') === '1';
    $params = [$F];
    $w = 'p.farmacia_id = ? AND p.activo = 1';
    if ($t !== '') {
        $w .= ' AND (p.codigo = ? OR p.nombre LIKE ? OR p.principio_activo LIKE ? OR p.laboratorio LIKE ?)';
        array_push($params, $t, '%' . $t . '%', '%' . $t . '%', '%' . $t . '%');
    }
    $rows = all("SELECT p.id, p.codigo, p.nombre, p.presentacion, p.principio_activo, p.laboratorio, p.precio_venta, p.precio_compra, p.requiere_receta, p.stock_minimo,
        COALESCE(SUM(CASE WHEN l.fecha_vencimiento IS NULL OR l.fecha_vencimiento >= CURDATE() THEN l.cantidad_actual ELSE 0 END),0) stock,
        MIN(CASE WHEN l.cantidad_actual > 0 AND (l.fecha_vencimiento IS NULL OR l.fecha_vencimiento >= CURDATE()) THEN l.fecha_vencimiento END) prox_venc
        FROM productos p LEFT JOIN lotes l ON l.producto_id = p.id AND l.cantidad_actual > 0
        WHERE $w GROUP BY p.id ORDER BY (p.codigo = " . db()->quote($t) . ") DESC, p.nombre LIMIT 20", $params);
    if ($soloStock) $rows = array_values(array_filter($rows, function ($r) { return $r['stock'] > 0; }));
    json_out(['ok' => true, 'data' => $rows]);
}

if ($action === 'top_productos') {
    $sel = "SELECT p.id, p.codigo, p.nombre, p.presentacion, p.principio_activo, p.laboratorio, p.precio_venta, p.precio_compra, p.requiere_receta, p.stock_minimo,
        (SELECT COALESCE(SUM(l.cantidad_actual),0) FROM lotes l WHERE l.producto_id = p.id AND l.cantidad_actual > 0 AND (l.fecha_vencimiento IS NULL OR l.fecha_vencimiento >= CURDATE())) stock,
        (SELECT MIN(l.fecha_vencimiento) FROM lotes l WHERE l.producto_id = p.id AND l.cantidad_actual > 0 AND (l.fecha_vencimiento IS NULL OR l.fecha_vencimiento >= CURDATE())) prox_venc";
    $rows = all("$sel, t.vendido FROM productos p JOIN (
            SELECT d.producto_id, SUM(d.cantidad) vendido FROM venta_detalle d JOIN ventas v ON v.id = d.venta_id
            WHERE v.farmacia_id = ? AND v.estado = 'completada' AND v.fecha >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY d.producto_id
        ) t ON t.producto_id = p.id
        WHERE p.farmacia_id = ? AND p.activo = 1 HAVING stock > 0 ORDER BY t.vendido DESC LIMIT 12", [$F, $F]);
    if (count($rows) < 12) {
        $ids = array_column($rows, 'id') ?: [0];
        $extra = all("$sel, 0 vendido FROM productos p WHERE p.farmacia_id = ? AND p.activo = 1 AND p.id NOT IN (" . implode(',', array_map('intval', $ids)) . ")
            HAVING stock > 0 ORDER BY p.id DESC LIMIT " . (12 - count($rows)), [$F]);
        $rows = array_merge($rows, $extra);
    }
    json_out(['ok' => true, 'data' => $rows]);
}

if ($action === 'clientes') {
    $t = '%' . g('q') . '%';
    json_out(['ok' => true, 'data' => all('SELECT id, nombre, nit, telefono FROM clientes WHERE farmacia_id = ? AND activo = 1 AND (nombre LIKE ? OR nit LIKE ? OR telefono LIKE ?) ORDER BY nombre LIMIT 10', [$F, $t, $t, $t])]);
}

if (!is_post()) json_out(['ok' => false, 'error' => 'Método no permitido'], 405);
csrf_check();
$in = api_input();

if ($action === 'crear_cliente') {
    $nombre = trim(isset($in['nombre']) ? $in['nombre'] : '');
    if ($nombre === '') json_out(['ok' => false, 'error' => 'El nombre es obligatorio']);
    $campo = function ($k) use ($in) { return nulo(isset($in[$k]) ? trim((string) $in[$k]) : ''); };
    $nit = $campo('nit');
    $id = insert('clientes', ['farmacia_id' => $F, 'nombre' => $nombre, 'nit' => $nit ? strtoupper($nit) : null, 'dpi' => $campo('dpi'), 'telefono' => $campo('telefono'), 'email' => $campo('email'), 'direccion' => $campo('direccion')]);
    json_out(['ok' => true, 'data' => one('SELECT id, nombre, nit, telefono FROM clientes WHERE id = ?', [$id])]);
}

if ($action === 'crear_producto') {
    if (!is_admin()) json_out(['ok' => false, 'error' => 'Sin permiso'], 403);
    $nombre = trim(isset($in['nombre']) ? $in['nombre'] : '');
    if ($nombre === '') json_out(['ok' => false, 'error' => 'El nombre es obligatorio']);
    $codigo = nulo(isset($in['codigo']) ? $in['codigo'] : '');
    if ($codigo && val('SELECT COUNT(*) FROM productos WHERE farmacia_id = ? AND codigo = ?', [$F, $codigo])) json_out(['ok' => false, 'error' => 'Ya existe un producto con ese código']);
    $id = insert('productos', ['farmacia_id' => $F, 'nombre' => $nombre, 'codigo' => $codigo, 'presentacion' => nulo(isset($in['presentacion']) ? $in['presentacion'] : ''), 'precio_venta' => (float) (isset($in['precio_venta']) ? $in['precio_venta'] : 0)]);
    json_out(['ok' => true, 'data' => one('SELECT id, codigo, nombre, presentacion, precio_venta, precio_compra, 0 stock FROM productos WHERE id = ?', [$id])]);
}

if ($action === 'guardar_venta') {
    $items = isset($in['items']) && is_array($in['items']) ? $in['items'] : [];
    if (!$items) json_out(['ok' => false, 'error' => 'Agrega al menos un producto']);
    $metodo = in_array(isset($in['metodo_pago']) ? $in['metodo_pago'] : '', ['efectivo', 'tarjeta', 'transferencia']) ? $in['metodo_pago'] : 'efectivo';
    $clienteId = !empty($in['cliente_id']) ? (int) $in['cliente_id'] : null;
    if ($clienteId && !val('SELECT id FROM clientes WHERE id = ? AND farmacia_id = ?', [$clienteId, $F])) $clienteId = null;
    $descuento = max(0, round((float) (isset($in['descuento']) ? $in['descuento'] : 0), 2));

    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('SELECT id FROM farmacias WHERE id = ? FOR UPDATE', [$F]);
        $numero = (int) val('SELECT COALESCE(MAX(numero),0) + 1 FROM ventas WHERE farmacia_id = ?', [$F]);
        $ventaId = insert('ventas', ['farmacia_id' => $F, 'numero' => $numero, 'cliente_id' => $clienteId, 'usuario_id' => uid(), 'fecha' => date('Y-m-d H:i:s'), 'metodo_pago' => $metodo, 'notas' => nulo(isset($in['notas']) ? $in['notas'] : '')]);
        $subtotal = 0;
        $costoTotal = 0;
        foreach ($items as $it) {
            $pid = (int) $it['producto_id'];
            $cant = (int) $it['cantidad'];
            if ($cant <= 0) continue;
            $prod = one('SELECT * FROM productos WHERE id = ? AND farmacia_id = ? AND activo = 1', [$pid, $F]);
            if (!$prod) throw new UserError('Producto no encontrado');
            $precio = is_admin() && isset($it['precio']) ? round((float) $it['precio'], 2) : (float) $prod['precio_venta'];
            if ($precio < 0) $precio = 0;
            $lotes = all('SELECT id, cantidad_actual, costo_unitario FROM lotes WHERE producto_id = ? AND farmacia_id = ? AND cantidad_actual > 0
                AND (fecha_vencimiento IS NULL OR fecha_vencimiento >= CURDATE())
                ORDER BY fecha_vencimiento IS NULL, fecha_vencimiento, id FOR UPDATE', [$pid, $F]);
            $disp = array_sum(array_column($lotes, 'cantidad_actual'));
            if ($disp < $cant) throw new UserError('Stock insuficiente de "' . $prod['nombre'] . '" (disponible: ' . $disp . ')');
            $sub = round($precio * $cant, 2);
            $detId = insert('venta_detalle', ['venta_id' => $ventaId, 'producto_id' => $pid, 'cantidad' => $cant, 'precio_unitario' => $precio, 'costo_unitario' => 0, 'subtotal' => $sub]);
            $pend = $cant;
            $costo = 0;
            foreach ($lotes as $l) {
                if ($pend <= 0) break;
                $toma = min($pend, (int) $l['cantidad_actual']);
                q('UPDATE lotes SET cantidad_actual = cantidad_actual - ? WHERE id = ?', [$toma, $l['id']]);
                insert('venta_detalle_lotes', ['venta_detalle_id' => $detId, 'lote_id' => $l['id'], 'cantidad' => $toma]);
                registrar_movimiento($pid, $l['id'], 'venta', -$toma, 'Venta #' . $numero);
                $costo += $toma * (float) $l['costo_unitario'];
                $pend -= $toma;
            }
            $costoUnit = $cant ? round($costo / $cant, 2) : 0;
            q('UPDATE venta_detalle SET costo_unitario = ? WHERE id = ?', [$costoUnit, $detId]);
            $subtotal += $sub;
            $costoTotal += $costo;
        }
        if ($subtotal <= 0) throw new UserError('La venta no tiene productos válidos');
        $descuento = min($descuento, $subtotal);
        $total = round($subtotal - $descuento, 2);
        $recibido = $metodo === 'efectivo' ? max($total, round((float) (isset($in['monto_recibido']) ? $in['monto_recibido'] : 0), 2)) : $total;
        update('ventas', ['subtotal' => $subtotal, 'descuento' => $descuento, 'total' => $total, 'costo_total' => round($costoTotal, 2), 'monto_recibido' => $recibido, 'cambio' => round($recibido - $total, 2)], 'id = ?', [$ventaId]);
        $pdo->commit();
        json_out(['ok' => true, 'id' => $ventaId, 'numero' => $numero, 'total' => $total, 'cambio' => round($recibido - $total, 2)]);
    } catch (UserError $ex) {
        $pdo->rollBack();
        json_out(['ok' => false, 'error' => $ex->getMessage()]);
    }
}

if ($action === 'importar_productos') {
    if (!is_admin()) json_out(['ok' => false, 'error' => 'Sin permiso'], 403);
    $filas = isset($in['filas']) && is_array($in['filas']) ? array_slice($in['filas'], 0, 300) : [];
    $actualizar = !empty($in['actualizar']);
    $cats = [];
    foreach (all('SELECT id, nombre FROM categorias WHERE farmacia_id = ?', [$F]) as $c) $cats[mb_strtolower(trim($c['nombre']))] = (int) $c['id'];
    $txt = function ($r, $k, $max = 200) { return isset($r[$k]) && trim((string) $r[$k]) !== '' ? mb_substr(trim((string) $r[$k]), 0, $max) : null; };
    $dec = function ($r, $k) { if (!isset($r[$k]) || $r[$k] === '') return null; $v = str_replace([',', 'Q', 'q', ' '], ['', '', '', ''], (string) $r[$k]); return is_numeric($v) ? round((float) $v, 2) : false; };
    $res = [];
    $pdo = db();
    $pdo->beginTransaction();
    foreach ($filas as $r) {
        $n = isset($r['_fila']) ? (int) $r['_fila'] : 0;
        try {
            $nombre = $txt($r, 'nombre');
            $codigo = $txt($r, 'codigo', 60);
            $pv = $dec($r, 'precio_venta');
            $pc = $dec($r, 'precio_compra');
            if (!$nombre && !$codigo) throw new UserError('Falta el nombre');
            if ($pv === false || $pc === false) throw new UserError('Precio inválido');
            $stockIni = isset($r['stock_inicial']) && $r['stock_inicial'] !== '' ? (int) $r['stock_inicial'] : 0;
            if ($stockIni < 0) throw new UserError('Stock inicial negativo');
            $venc = $txt($r, 'vencimiento', 10);
            if ($venc && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $venc)) throw new UserError('Fecha de vencimiento inválida');
            $catId = null;
            if ($cn = $txt($r, 'categoria', 100)) {
                $k = mb_strtolower($cn);
                if (!isset($cats[$k])) $cats[$k] = insert('categorias', ['farmacia_id' => $F, 'nombre' => $cn]);
                $catId = $cats[$k];
            }
            $d = array_filter([
                'nombre' => $nombre,
                'codigo' => $codigo,
                'principio_activo' => $txt($r, 'principio_activo'),
                'presentacion' => $txt($r, 'presentacion', 120),
                'laboratorio' => $txt($r, 'laboratorio', 120),
                'unidad' => $txt($r, 'unidad', 30),
                'categoria_id' => $catId,
                'precio_compra' => $pc,
                'precio_venta' => $pv,
                'stock_minimo' => isset($r['stock_minimo']) && $r['stock_minimo'] !== '' ? max(0, (int) $r['stock_minimo']) : null,
                'requiere_receta' => isset($r['requiere_receta']) && $r['requiere_receta'] !== '' ? (preg_match('/^(s|si|sí|1|x|true|yes)$/iu', trim((string) $r['requiere_receta'])) ? 1 : 0) : null,
            ], function ($v) { return $v !== null; });

            $existente = $codigo ? one('SELECT id FROM productos WHERE farmacia_id = ? AND codigo = ? LIMIT 1', [$F, $codigo]) : null;
            if (!$existente && !$codigo && $nombre) {
                $existente = one('SELECT id FROM productos WHERE farmacia_id = ? AND nombre = ? AND (presentacion <=> ?) LIMIT 1', [$F, $nombre, isset($d['presentacion']) ? $d['presentacion'] : null]);
            }
            if ($existente) {
                if (!$actualizar) {
                    $res[] = ['fila' => $n, 'ok' => true, 'accion' => 'omitido', 'msg' => 'Ya existe'];
                    continue;
                }
                $pid = (int) $existente['id'];
                $d['activo'] = 1;
                update('productos', $d, 'id = ? AND farmacia_id = ?', [$pid, $F]);
                $accion = 'actualizado';
            } else {
                if (!$nombre) throw new UserError('Falta el nombre');
                if (!isset($d['precio_venta'])) throw new UserError('Falta el precio de venta');
                $d['farmacia_id'] = $F;
                $pid = insert('productos', $d);
                $accion = 'creado';
            }
            if ($stockIni > 0) {
                $costo = isset($d['precio_compra']) ? $d['precio_compra'] : (float) val('SELECT precio_compra FROM productos WHERE id = ?', [$pid]);
                $lid = insert('lotes', ['farmacia_id' => $F, 'producto_id' => $pid, 'numero_lote' => $txt($r, 'lote', 60), 'fecha_vencimiento' => $venc, 'cantidad_inicial' => $stockIni, 'cantidad_actual' => $stockIni, 'costo_unitario' => $costo]);
                registrar_movimiento($pid, $lid, 'ajuste_entrada', $stockIni, 'Importación masiva');
            }
            $res[] = ['fila' => $n, 'ok' => true, 'accion' => $accion];
        } catch (UserError $ex) {
            $res[] = ['fila' => $n, 'ok' => false, 'msg' => $ex->getMessage()];
        }
    }
    $pdo->commit();
    json_out(['ok' => true, 'resultados' => $res]);
}

if ($action === 'guardar_compra') {
    if (!is_admin()) json_out(['ok' => false, 'error' => 'Sin permiso'], 403);
    $items = isset($in['items']) && is_array($in['items']) ? $in['items'] : [];
    if (!$items) json_out(['ok' => false, 'error' => 'Agrega al menos un producto']);
    $provId = !empty($in['proveedor_id']) ? (int) $in['proveedor_id'] : null;
    if ($provId && !val('SELECT id FROM proveedores WHERE id = ? AND farmacia_id = ?', [$provId, $F])) $provId = null;
    $fecha = isset($in['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['fecha']) ? $in['fecha'] : date('Y-m-d');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $compraId = insert('compras', ['farmacia_id' => $F, 'proveedor_id' => $provId, 'usuario_id' => uid(), 'numero_factura' => nulo(isset($in['numero_factura']) ? $in['numero_factura'] : ''), 'fecha' => $fecha, 'notas' => nulo(isset($in['notas']) ? $in['notas'] : '')]);
        $total = 0;
        foreach ($items as $it) {
            $pid = (int) $it['producto_id'];
            $cant = (int) $it['cantidad'];
            $costo = round((float) $it['costo'], 2);
            if ($cant <= 0) throw new UserError('Cantidad inválida');
            $prod = one('SELECT id, nombre FROM productos WHERE id = ? AND farmacia_id = ?', [$pid, $F]);
            if (!$prod) throw new UserError('Producto no encontrado');
            $venc = !empty($it['vencimiento']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $it['vencimiento']) ? $it['vencimiento'] : null;
            $loteId = insert('lotes', ['farmacia_id' => $F, 'producto_id' => $pid, 'compra_id' => $compraId, 'numero_lote' => nulo(isset($it['lote']) ? $it['lote'] : ''), 'fecha_vencimiento' => $venc, 'cantidad_inicial' => $cant, 'cantidad_actual' => $cant, 'costo_unitario' => $costo]);
            $sub = round($cant * $costo, 2);
            insert('compra_detalle', ['compra_id' => $compraId, 'producto_id' => $pid, 'lote_id' => $loteId, 'cantidad' => $cant, 'costo_unitario' => $costo, 'subtotal' => $sub]);
            registrar_movimiento($pid, $loteId, 'compra', $cant, 'Compra #' . $compraId);
            $upd = ['precio_compra' => $costo];
            if (!empty($it['precio_venta']) && (float) $it['precio_venta'] > 0) $upd['precio_venta'] = round((float) $it['precio_venta'], 2);
            update('productos', $upd, 'id = ?', [$pid]);
            $total += $sub;
        }
        update('compras', ['total' => $total], 'id = ?', [$compraId]);
        $pdo->commit();
        json_out(['ok' => true, 'id' => $compraId]);
    } catch (UserError $ex) {
        $pdo->rollBack();
        json_out(['ok' => false, 'error' => $ex->getMessage()]);
    }
}

json_out(['ok' => false, 'error' => 'Acción no válida'], 404);
