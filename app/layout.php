<?php
function contar_alertas()
{
    if (!fid()) return 0;
    $f = farmacia_actual();
    $dias = (int) $f['dias_alerta_vencimiento'];
    $bajo = (int) val('SELECT COUNT(*) FROM (SELECT p.id FROM productos p LEFT JOIN lotes l ON l.producto_id = p.id AND l.cantidad_actual > 0
        WHERE p.farmacia_id = ? AND p.activo = 1 GROUP BY p.id, p.stock_minimo HAVING COALESCE(SUM(l.cantidad_actual),0) <= p.stock_minimo) t', [fid()]);
    $venc = (int) val('SELECT COUNT(*) FROM lotes WHERE farmacia_id = ? AND cantidad_actual > 0 AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)', [fid(), $dias]);
    return $bajo + $venc;
}

function menu_items()
{
    if (fid()) {
        $items = [
            ['dashboard', 'Inicio', 'layout-dashboard', false],
            ['pos', 'Punto de venta', 'scan-barcode', false],
            ['ventas', 'Ventas', 'receipt', false],
            ['productos', 'Productos', 'pill', false],
            ['inventario', 'Inventario y alertas', 'bell-ring', false],
            ['clientes', 'Clientes', 'users', false],
            ['__', 'Administración', '', true],
            ['compras', 'Compras', 'package-plus', true],
            ['proveedores', 'Proveedores', 'truck', true],
            ['gastos', 'Gastos', 'wallet', true],
            ['reportes', 'Reportes', 'bar-chart-3', true],
            ['usuarios', 'Usuarios', 'user-cog', true],
            ['configuracion', 'Configuración', 'settings', true],
        ];
        return array_values(array_filter($items, function ($i) {
            return !$i[3] || is_admin();
        }));
    }
    return [
        ['admin', 'Panel general', 'layout-dashboard', false],
        ['admin_farmacias', 'Farmacias', 'building-2', false],
        ['admin_pagos', 'Pagos de suscripción', 'credit-card', false],
        ['admin_reportes', 'Reportes', 'bar-chart-3', false],
        ['admin_usuarios', 'Superadministradores', 'shield-check', false],
    ];
}

function head_html($title)
{
    $app = e($GLOBALS['config']['app_name']);
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#059669">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="<?= $app ?>">
<meta name="format-detection" content="telephone=no">
<link rel="manifest" href="manifest.webmanifest">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<title><?= e($title) ?> · <?= $app ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
<link rel="icon" type="image/svg+xml" href="assets/icons/icon.svg">
<link rel="stylesheet" href="assets/app.css?v=<?= @filemtime(__DIR__ . '/../assets/app.css') ?>">
<script src="assets/vendor/lucide.min.js"></script>
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
function fmtMoney(n) { return <?= json_encode(moneda() . ' ') ?> + Number(n || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
// Bloquear zoom (pinch y doble toque) en móviles
document.addEventListener('gesturestart', e => e.preventDefault(), { passive: false });
document.addEventListener('touchmove', e => { if (e.touches && e.touches.length > 1) e.preventDefault(); }, { passive: false });
// App instalable (PWA)
if ('serviceWorker' in navigator) window.addEventListener('load', () => navigator.serviceWorker.register('sw.js').catch(() => {}));
let _promptInstalar = null;
const _esIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const _instalada = () => matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
function _mostrarInstalar(v) { document.querySelectorAll('[data-instalar]').forEach(el => el.classList.toggle('hidden', !v)); }
window.addEventListener('beforeinstallprompt', e => { e.preventDefault(); _promptInstalar = e; _mostrarInstalar(true); });
window.addEventListener('appinstalled', () => { _promptInstalar = null; _mostrarInstalar(false); });
document.addEventListener('DOMContentLoaded', () => { if (_esIOS && !_instalada()) _mostrarInstalar(true); });
async function instalarApp() {
  if (_promptInstalar) { _promptInstalar.prompt(); await _promptInstalar.userChoice; _promptInstalar = null; _mostrarInstalar(false); return; }
  const m = document.getElementById('modalInstalar'); if (m) m.classList.remove('hidden');
}
function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>
<script src="assets/vendor/chart.umd.min.js"></script>
</head>
<?php
}

function layout_start($title, $active = '')
{
    head_html($title);
    $f = farmacia_actual();
    $alertas = fid() ? contar_alertas() : 0;
    $app = $GLOBALS['config']['app_name'];
    $iniciales = strtoupper(mb_substr(isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'U', 0, 1));
    ?>
<body class="bg-slate-50 text-slate-800 antialiased">
<div class="min-h-screen lg:flex">
  <div id="sb-backdrop" class="fixed inset-0 z-30 bg-slate-900/40 backdrop-blur-sm hidden lg:hidden no-print" onclick="toggleSidebar(false)"></div>
  <aside id="sidebar" class="no-print fixed inset-y-0 left-0 z-40 w-72 -translate-x-full lg:translate-x-0 lg:sticky lg:top-0 lg:h-screen transition-transform duration-200 bg-white border-r border-slate-200/70 flex flex-col">
    <div class="h-16 flex items-center gap-3 px-5 border-b border-slate-100">
      <?php if ($f && ($lu = logo_url($f))): ?>
        <div class="h-10 w-10 shrink-0 rounded-xl border border-slate-200 bg-white p-0.5 overflow-hidden"><img src="<?= e($lu) ?>" alt="" class="w-full h-full object-contain"></div>
      <?php else: ?>
      <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-brand-400 to-brand-600 grid place-items-center text-white shadow-lg shadow-brand-500/30">
        <i data-lucide="cross" class="w-5 h-5"></i>
      </div>
      <?php endif; ?>
      <div class="min-w-0">
        <div class="font-extrabold tracking-tight text-slate-900 leading-tight"><?= e($app) ?></div>
        <div class="text-[11px] text-slate-500 truncate"><?= $f ? e($f['nombre']) : 'Panel de plataforma' ?></div>
      </div>
    </div>
    <nav class="flex-1 overflow-y-auto p-3 space-y-1">
      <?php foreach (menu_items() as $it): ?>
        <?php if ($it[0] === '__'): ?>
          <div class="pt-4 pb-1 px-3 text-[10px] font-bold uppercase tracking-widest text-slate-400"><?= e($it[1]) ?></div>
        <?php else: ?>
          <a href="<?= url($it[0]) ?>" class="nav-link <?= $active === $it[0] ? 'active' : '' ?>">
            <i data-lucide="<?= $it[2] ?>" class="w-[18px] h-[18px]"></i>
            <span class="flex-1"><?= e($it[1]) ?></span>
            <?php if ($it[0] === 'inventario' && $alertas > 0): ?>
              <span class="chip bg-rose-500 text-white"><?= $alertas ?></span>
            <?php endif; ?>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <div class="p-3 border-t border-slate-100">
      <button data-instalar onclick="instalarApp()" class="hidden w-full mb-2 flex items-center gap-3 rounded-xl bg-gradient-to-r from-brand-50 to-teal-50 px-3 py-2.5 text-sm font-semibold text-brand-800 hover:from-brand-100 hover:to-teal-100">
        <i data-lucide="smartphone" class="w-[18px] h-[18px]"></i> Instalar app <i data-lucide="download" class="w-4 h-4 ml-auto"></i>
      </button>
      <div class="flex items-center gap-3 rounded-xl p-2">
        <div class="h-9 w-9 rounded-full bg-slate-900 text-white grid place-items-center text-sm font-bold"><?= e($iniciales) ?></div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-semibold truncate"><?= e($_SESSION['nombre']) ?></div>
          <div class="text-[11px] text-slate-500 capitalize"><?= e($_SESSION['rol']) ?></div>
        </div>
        <a href="<?= url('perfil') ?>" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500" title="Mi perfil"><i data-lucide="user-round-cog" class="w-4 h-4"></i></a>
        <a href="<?= url('logout') ?>" class="p-2 rounded-lg hover:bg-rose-50 text-slate-500 hover:text-rose-600" title="Salir"><i data-lucide="log-out" class="w-4 h-4"></i></a>
      </div>
    </div>
  </aside>

  <div class="flex-1 min-w-0 flex flex-col">
    <header class="no-print sticky top-0 z-20 h-16 bg-white/80 backdrop-blur border-b border-slate-200/70 flex items-center gap-3 px-4 lg:px-8">
      <button class="lg:hidden p-2 -ml-2 rounded-lg hover:bg-slate-100" onclick="toggleSidebar(true)"><i data-lucide="menu" class="w-5 h-5"></i></button>
      <h1 class="font-bold text-slate-900 truncate"><?= e($title) ?></h1>
      <div class="ml-auto flex items-center gap-2">
        <button data-instalar onclick="instalarApp()" class="hidden lg:!hidden p-2 rounded-lg text-brand-700 hover:bg-brand-50" title="Instalar app"><i data-lucide="download" class="w-5 h-5"></i></button>
        <?php if (fid()): ?>
          <a href="<?= url('pos') ?>" class="btn-primary btn-sm hidden sm:inline-flex"><i data-lucide="plus" class="w-4 h-4"></i> Nueva venta</a>
        <?php endif; ?>
        <span class="hidden md:inline text-xs text-slate-500"><?= date('d/m/Y') ?></span>
      </div>
    </header>

    <?php if (is_super() && fid()): ?>
      <div class="no-print bg-slate-900 text-white text-sm px-4 lg:px-8 py-2 flex items-center gap-3">
        <i data-lucide="eye" class="w-4 h-4 text-brand-300"></i>
        <span class="truncate">Estás administrando <b><?= e($f['nombre']) ?></b> como superadmin</span>
        <a href="<?= url('admin_salir') ?>" class="ml-auto shrink-0 rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1 text-xs font-semibold">Volver al panel</a>
      </div>
    <?php endif; ?>

    <?php if ($f && !is_super()):
        $d = dias_hasta($f['fecha_vencimiento']);
        if ($d !== null && $d <= 5): ?>
      <div class="no-print <?= $d < 0 ? 'bg-rose-600' : 'bg-amber-500' ?> text-white text-sm px-4 lg:px-8 py-2 flex items-center gap-2">
        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
        <?= $d < 0 ? 'Tu suscripción venció el ' . fdate($f['fecha_vencimiento']) . '. Contacta a soporte para renovarla.' : 'Tu suscripción vence ' . ($d === 0 ? 'hoy' : 'en ' . $d . ' día(s)') . ' (' . fdate($f['fecha_vencimiento']) . ').' ?>
      </div>
    <?php endif; endif; ?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-[1500px] w-full mx-auto">
      <?php foreach (flashes() as $fl):
          $c = $fl['type'] === 'error' ? 'bg-rose-50 text-rose-800 border-rose-200' : ($fl['type'] === 'warning' ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-brand-50 text-brand-800 border-brand-200');
          $ic = $fl['type'] === 'error' ? 'circle-x' : ($fl['type'] === 'warning' ? 'alert-triangle' : 'circle-check');
      ?>
        <div class="flash mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm <?= $c ?>">
          <i data-lucide="<?= $ic ?>" class="w-5 h-5 shrink-0"></i>
          <div class="flex-1"><?= e($fl['msg']) ?></div>
          <button onclick="this.parentElement.remove()" class="opacity-60 hover:opacity-100"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
      <?php endforeach; ?>
<?php
}

function layout_end()
{
    ?>
    </main>
  </div>
</div>
<?php install_ui(); ?>
<script>
function toggleSidebar(open) {
  document.getElementById('sidebar').classList.toggle('-translate-x-full', !open);
  document.getElementById('sb-backdrop').classList.toggle('hidden', !open);
}
document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', ev => { if (!confirm(f.dataset.confirm)) ev.preventDefault(); }));
lucide.createIcons();
setTimeout(() => document.querySelectorAll('.flash').forEach(el => el.style.display = 'none'), 6000);
</script>
</body>
</html>
<?php
}

function install_ui()
{
    $app = e($GLOBALS['config']['app_name']);
    ?>
<div id="modalInstalar" class="hidden fixed inset-0 z-[60] bg-slate-900/50 backdrop-blur-sm grid place-items-end sm:place-items-center p-4 no-print" onclick="if(event.target===this)this.classList.add('hidden')">
  <div class="card w-full max-w-sm p-6">
    <div class="flex items-center gap-3 mb-4">
      <img src="assets/icons/icon-192.png" alt="" class="w-12 h-12 rounded-xl">
      <div><div class="font-bold">Instalar <?= $app ?></div><div class="text-xs text-slate-500">Úsala como una app desde tu pantalla de inicio</div></div>
    </div>
    <ol class="space-y-3 text-sm text-slate-700">
      <li class="flex gap-3"><span class="h-6 w-6 shrink-0 rounded-full bg-brand-50 text-brand-700 grid place-items-center text-xs font-bold">1</span><span>Toca el botón <b>Compartir</b> <i data-lucide="share" class="w-4 h-4 inline -mt-1"></i> en la barra de Safari.</span></li>
      <li class="flex gap-3"><span class="h-6 w-6 shrink-0 rounded-full bg-brand-50 text-brand-700 grid place-items-center text-xs font-bold">2</span><span>Elige <b>Agregar a inicio</b> <i data-lucide="square-plus" class="w-4 h-4 inline -mt-1"></i>.</span></li>
      <li class="flex gap-3"><span class="h-6 w-6 shrink-0 rounded-full bg-brand-50 text-brand-700 grid place-items-center text-xs font-bold">3</span><span>Toca <b>Agregar</b>. En Android usa el menú ⋮ → <b>Instalar app</b>.</span></li>
    </ol>
    <button onclick="document.getElementById('modalInstalar').classList.add('hidden')" class="btn-primary w-full mt-6">Entendido</button>
  </div>
</div>
<?php
}

// ---------- Componentes ----------
function logo_field($f)
{
    $url = logo_url($f);
    ?>
<div class="flex items-center gap-4">
  <div id="logoPrev" class="h-20 w-32 shrink-0 rounded-2xl border border-slate-200 bg-white p-2 flex items-center justify-center overflow-hidden">
    <?php if ($url): ?><img src="<?= e($url) ?>" alt="Logo" class="w-full h-full object-contain"><?php else: ?><i data-lucide="image" class="w-7 h-7 text-slate-300"></i><?php endif; ?>
  </div>
  <div class="flex-1 min-w-0 space-y-2">
    <label class="btn-light btn-sm cursor-pointer"><i data-lucide="upload" class="w-3.5 h-3.5"></i> <?= $url ? 'Cambiar logo' : 'Subir logo' ?>
      <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden" onchange="previewLogo(this)">
    </label>
    <?php if ($url): ?><label class="flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" name="quitar_logo" value="1" class="rounded text-rose-600"> Quitar logo</label><?php endif; ?>
    <div class="text-xs text-slate-500">PNG, JPG o WEBP · máx. 2 MB. Sale en el ticket y en el menú. Para impresoras térmicas usa un logo en blanco y negro.</div>
  </div>
</div>
<script>
function previewLogo(inp) {
  const f = inp.files[0]; if (!f) return;
  if (f.size > 2 * 1024 * 1024) { alert('El logo no debe pesar más de 2 MB.'); inp.value = ''; return; }
  const r = new FileReader(); r.onload = e => { document.getElementById('logoPrev').innerHTML = '<img src="' + e.target.result + '" class="w-full h-full object-contain">'; }; r.readAsDataURL(f);
}
</script>
<?php
}

function page_header($title, $subtitle = '', $actions = '')
{
    echo '<div class="flex flex-col sm:flex-row sm:items-end gap-3 mb-6">';
    echo '<div class="flex-1 min-w-0"><h2 class="text-2xl font-extrabold tracking-tight text-slate-900">' . e($title) . '</h2>';
    if ($subtitle) echo '<p class="text-sm text-slate-500 mt-1">' . $subtitle . '</p>';
    echo '</div>';
    if ($actions) echo '<div class="flex flex-wrap gap-2 no-print">' . $actions . '</div>';
    echo '</div>';
}

function stat_card($label, $value, $icon, $color = 'brand', $sub = '', $href = '')
{
    $tag = $href ? 'a href="' . e($href) . '"' : 'div';
    $close = $href ? 'a' : 'div';
    echo '<' . $tag . ' class="card p-4 sm:p-5 block min-w-0 ' . ($href ? 'hover:border-' . $color . '-300 transition' : '') . '">';
    echo '<div class="flex items-center gap-3"><div class="h-9 w-9 shrink-0 rounded-xl bg-' . $color . '-50 text-' . $color . '-600 grid place-items-center"><i data-lucide="' . $icon . '" class="w-[18px] h-[18px]"></i></div>';
    echo '<div class="text-xs font-semibold text-slate-500 leading-tight">' . e($label) . '</div></div>';
    echo '<div class="text-xl sm:text-2xl font-extrabold tracking-tight text-slate-900 mt-3 break-words leading-tight">' . $value . '</div>';
    if ($sub) echo '<div class="text-xs text-slate-500 mt-1">' . $sub . '</div>';
    echo '</' . $close . '>';
}

function badge($text, $color = 'slate')
{
    return '<span class="chip bg-' . $color . '-50 text-' . $color . '-700 ring-1 ring-inset ring-' . $color . '-600/15">' . e($text) . '</span>';
}

function empty_state($msg, $icon = 'inbox')
{
    return '<div class="py-14 text-center text-slate-400"><i data-lucide="' . $icon . '" class="w-10 h-10 mx-auto mb-3 opacity-60"></i><div class="text-sm">' . e($msg) . '</div></div>';
}

function pagination($pag, $paginas)
{
    if ($paginas <= 1) return;
    $params = $_GET;
    echo '<div class="flex items-center justify-between gap-2 px-4 py-3 border-t border-slate-100 text-sm no-print">';
    echo '<span class="text-slate-500">Página ' . $pag . ' de ' . $paginas . '</span><div class="flex gap-2">';
    if ($pag > 1) {
        $params['pag'] = $pag - 1;
        echo '<a class="btn-light btn-sm" href="index.php?' . e(http_build_query($params)) . '">Anterior</a>';
    }
    if ($pag < $paginas) {
        $params['pag'] = $pag + 1;
        echo '<a class="btn-light btn-sm" href="index.php?' . e(http_build_query($params)) . '">Siguiente</a>';
    }
    echo '</div></div>';
}

function date_filter_form($page, $extra = '', array $hidden = [], $defDesde = null, $defHasta = null)
{
    list($desde, $hasta) = rango_fechas($defDesde, $defHasta);
    echo '<form method="get" class="card p-4 mb-6 flex flex-col sm:flex-row sm:items-end gap-3 no-print">';
    echo '<input type="hidden" name="p" value="' . e($page) . '">';
    foreach ($hidden as $k => $v) echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    echo '<div class="flex-1 grid grid-cols-2 gap-3"><div><label class="label">Desde</label><input type="date" name="desde" value="' . e($desde) . '" class="input"></div>';
    echo '<div><label class="label">Hasta</label><input type="date" name="hasta" value="' . e($hasta) . '" class="input"></div></div>';
    echo $extra;
    echo '<div class="flex gap-2"><button class="btn-dark"><i data-lucide="filter" class="w-4 h-4"></i> Filtrar</button>';
    echo '<div class="relative"><select onchange="aplicarRango(this)" class="input pr-8"><option value="">Rápido…</option><option value="hoy">Hoy</option><option value="ayer">Ayer</option><option value="7">Últimos 7 días</option><option value="30">Últimos 30 días</option><option value="mes">Este mes</option><option value="mesant">Mes anterior</option><option value="anio">Este año</option></select></div></div>';
    echo '</form>';
    ?>
<script>
function aplicarRango(sel) {
  const f = sel.form, d = new Date(), pad = n => String(n).padStart(2, '0'), iso = x => x.getFullYear() + '-' + pad(x.getMonth() + 1) + '-' + pad(x.getDate());
  let a = new Date(d), b = new Date(d);
  switch (sel.value) {
    case 'ayer': a.setDate(d.getDate() - 1); b = new Date(a); break;
    case '7': a.setDate(d.getDate() - 6); break;
    case '30': a.setDate(d.getDate() - 29); break;
    case 'mes': a = new Date(d.getFullYear(), d.getMonth(), 1); break;
    case 'mesant': a = new Date(d.getFullYear(), d.getMonth() - 1, 1); b = new Date(d.getFullYear(), d.getMonth(), 0); break;
    case 'anio': a = new Date(d.getFullYear(), 0, 1); break;
    case 'hoy': break;
    default: return;
  }
  f.desde.value = iso(a); f.hasta.value = iso(b); f.submit();
}
</script>
<?php
}
