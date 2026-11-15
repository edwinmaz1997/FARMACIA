<?php
class UserError extends Exception
{
}

// ---------- Base de datos ----------
function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $c = $GLOBALS['config'];
        $pdo = new PDO(
            'mysql:host=' . $c['db_host'] . ';dbname=' . $c['db_name'] . ';charset=utf8mb4',
            $c['db_user'],
            $c['db_pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $pdo->exec("SET time_zone = '" . date('P') . "'");
        $pdo->exec("SET SESSION sql_mode = REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', '')");
        migrar($pdo);
    }
    return $pdo;
}

function q($sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function one($sql, array $params = [])
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function all($sql, array $params = [])
{
    return q($sql, $params)->fetchAll();
}

function val($sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

function insert($table, array $data)
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    q($sql, array_values($data));
    return (int) db()->lastInsertId();
}

function update($table, array $data, $where, array $whereParams)
{
    $sets = [];
    foreach (array_keys($data) as $c) {
        $sets[] = $c . ' = ?';
    }
    q('UPDATE ' . $table . ' SET ' . implode(',', $sets) . ' WHERE ' . $where, array_merge(array_values($data), $whereParams));
}

// ---------- Salida / formato ----------
function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function moneda()
{
    $f = farmacia_actual();
    return $f ? $f['moneda'] : 'Q';
}

function money($n, $sym = null)
{
    $sym = $sym === null ? moneda() : $sym;
    return $sym . ' ' . number_format((float) $n, 2, '.', ',');
}

function num($n, $dec = 0)
{
    return number_format((float) $n, $dec, '.', ',');
}

function fdate($d)
{
    if (!$d) return '—';
    return date('d/m/Y', strtotime($d));
}

function fdatetime($d)
{
    if (!$d) return '—';
    return date('d/m/Y H:i', strtotime($d));
}

function dias_hasta($fecha)
{
    if (!$fecha) return null;
    $hoy = new DateTime(date('Y-m-d'));
    $f = new DateTime($fecha);
    $diff = (int) $hoy->diff($f)->format('%r%a');
    return $diff;
}

function mes_nombre($m)
{
    $n = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    return $n[(int) $m];
}

// ---------- Request ----------
function g($k, $d = '')
{
    return isset($_GET[$k]) ? trim((string) $_GET[$k]) : $d;
}

function p($k, $d = '')
{
    return isset($_POST[$k]) ? (is_array($_POST[$k]) ? $_POST[$k] : trim((string) $_POST[$k])) : $d;
}

function nulo($v)
{
    $v = is_string($v) ? trim($v) : $v;
    return ($v === '' || $v === null) ? null : $v;
}

function is_post()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function url($page, array $params = [])
{
    $params = array_merge(['p' => $page], $params);
    return 'index.php?' . http_build_query($params);
}

function redirect($page, array $params = [])
{
    header('Location: ' . url($page, $params));
    exit;
}

function back($fallback = 'dashboard')
{
    $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    if ($ref && strpos($ref, $_SERVER['HTTP_HOST']) !== false) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

// ---------- CSRF ----------
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check()
{
    $t = isset($_POST['_csrf']) ? $_POST['_csrf'] : (isset($_SERVER['HTTP_X_CSRF']) ? $_SERVER['HTTP_X_CSRF'] : '');
    if (!$t || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Sesión expirada. Recarga la página e intenta de nuevo.');
    }
}

// ---------- Flash ----------
function flash($type, $msg)
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes()
{
    $f = isset($_SESSION['flash']) ? $_SESSION['flash'] : [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------- Autenticación / contexto ----------
function user()
{
    return isset($_SESSION['uid']) ? $_SESSION : null;
}

function uid()
{
    return isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
}

function is_super()
{
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'superadmin';
}

function is_admin()
{
    return is_super() || (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin');
}

function fid()
{
    return isset($_SESSION['farmacia_id']) ? (int) $_SESSION['farmacia_id'] : 0;
}

function farmacia_actual()
{
    static $cache = [];
    $id = fid();
    if (!$id) return null;
    if (!isset($cache[$id])) {
        $cache[$id] = one('SELECT * FROM farmacias WHERE id = ?', [$id]);
    }
    return $cache[$id];
}

function require_login()
{
    if (!uid()) redirect('login');
}

function require_farmacia()
{
    require_login();
    if (!fid()) {
        if (is_super()) redirect('admin');
        redirect('logout');
    }
    if (!is_super()) {
        $f = farmacia_actual();
        if (!$f || $f['estado'] === 'suspendida') {
            if (g('p') === 'api') json_out(['ok' => false, 'error' => 'Farmacia suspendida'], 403);
            redirect('logout');
        }
    }
}

function require_admin()
{
    require_farmacia();
    if (!is_admin()) {
        flash('error', 'No tienes permiso para acceder a esa sección.');
        redirect('dashboard');
    }
}

function require_super()
{
    require_login();
    if (!is_super()) redirect('dashboard');
}

// ---------- Utilidades de negocio ----------
function stock_producto($productoId)
{
    return (int) val('SELECT COALESCE(SUM(cantidad_actual),0) FROM lotes WHERE producto_id = ? AND farmacia_id = ?', [$productoId, fid()]);
}

function registrar_movimiento($productoId, $loteId, $tipo, $cantidad, $ref)
{
    insert('movimientos', [
        'farmacia_id' => fid(),
        'producto_id' => $productoId,
        'lote_id' => $loteId,
        'tipo' => $tipo,
        'cantidad' => $cantidad,
        'referencia' => $ref,
        'usuario_id' => uid() ?: null,
        'fecha' => date('Y-m-d H:i:s'),
    ]);
}

function sumar_periodo($fecha, $ciclo)
{
    $map = ['mensual' => '+1 month', 'trimestral' => '+3 months', 'semestral' => '+6 months', 'anual' => '+1 year'];
    $m = isset($map[$ciclo]) ? $map[$ciclo] : '+1 month';
    return date('Y-m-d', strtotime($m, strtotime($fecha)));
}

function estado_suscripcion($f)
{
    if ($f['estado'] === 'suspendida') return ['Suspendida', 'red'];
    $d = dias_hasta($f['fecha_vencimiento']);
    if ($d < 0) return ['Vencida', 'red'];
    if ($d <= 7) return ['Por vencer', 'amber'];
    return ['Al día', 'emerald'];
}

function rango_fechas($defDesde = null, $defHasta = null)
{
    $desde = g('desde', $defDesde ?: date('Y-m-01'));
    $hasta = g('hasta', $defHasta ?: date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = date('Y-m-d');
    if ($desde > $hasta) {
        $t = $desde;
        $desde = $hasta;
        $hasta = $t;
    }
    return [$desde, $hasta];
}

function csv_download($filename, array $headers, array $rows)
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);
    foreach ($rows as $r) {
        fputcsv($out, array_values($r));
    }
    fclose($out);
    exit;
}

function json_out($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function paginar($total, $porPagina = 25)
{
    $pag = max(1, (int) g('pag', 1));
    $paginas = max(1, (int) ceil($total / $porPagina));
    $pag = min($pag, $paginas);
    return [$pag, $paginas, ($pag - 1) * $porPagina, $porPagina];
}

// ---------- Migraciones automáticas (el hosting no tiene terminal) ----------
const SCHEMA_VERSION = 2;

function migrar(PDO $pdo)
{
    if (isset($_SESSION['schema_v']) && $_SESSION['schema_v'] >= SCHEMA_VERSION) return;
    $col = function ($tabla, $columna) use ($pdo) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $st->execute([$tabla, $columna]);
        return (int) $st->fetchColumn() > 0;
    };
    // v2: logo de la farmacia
    if (!$col('farmacias', 'logo')) $pdo->exec('ALTER TABLE farmacias ADD COLUMN logo VARCHAR(255) NULL AFTER ticket_pie');
    if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['schema_v'] = SCHEMA_VERSION;
}

// ---------- Logo de farmacia ----------
function logo_url($f)
{
    if (!$f || empty($f['logo'])) return null;
    $ruta = __DIR__ . '/../' . $f['logo'];
    return file_exists($ruta) ? $f['logo'] . '?v=' . filemtime($ruta) : null;
}

/** Procesa el campo de archivo "logo" y la casilla "quitar_logo". Devuelve mensaje de error o null. */
function procesar_logo($farmaciaId)
{
    $f = one('SELECT id, logo FROM farmacias WHERE id = ?', [$farmaciaId]);
    if (!$f) return null;
    $borrarActual = function () use ($f) {
        if ($f['logo'] && strpos($f['logo'], 'uploads/logos/') === 0 && file_exists(__DIR__ . '/../' . $f['logo'])) @unlink(__DIR__ . '/../' . $f['logo']);
    };
    if (p('quitar_logo') === '1') {
        $borrarActual();
        update('farmacias', ['logo' => null], 'id = ?', [$farmaciaId]);
    }
    if (empty($_FILES['logo']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) return null;
    $up = $_FILES['logo'];
    if ($up['error'] !== UPLOAD_ERR_OK) return 'No se pudo subir el logo (código ' . $up['error'] . ').';
    if ($up['size'] > 2 * 1024 * 1024) return 'El logo no debe pesar más de 2 MB.';
    $info = @getimagesize($up['tmp_name']);
    $tipos = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($tipos[$info[2]])) return 'El logo debe ser una imagen PNG, JPG, WEBP o GIF.';
    $dir = __DIR__ . '/../uploads/logos';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return 'No se pudo crear la carpeta de logos en el servidor.';
    $nombre = 'f' . (int) $farmaciaId . '_' . bin2hex(random_bytes(6)) . '.' . $tipos[$info[2]];
    if (!@move_uploaded_file($up['tmp_name'], $dir . '/' . $nombre)) return 'No se pudo guardar el logo en el servidor.';
    $borrarActual();
    update('farmacias', ['logo' => 'uploads/logos/' . $nombre], 'id = ?', [$farmaciaId]);
    return null;
}
