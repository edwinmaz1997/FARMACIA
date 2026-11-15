<?php
// Importación masiva de productos (Excel .xlsx/.xls o CSV). Se procesa el archivo en el navegador y se envía por lotes a la API.
layout_start('Importar productos', 'productos');
page_header('Importar productos', 'Sube un archivo de Excel o CSV con tus productos. Puedes incluir el inventario inicial con lote y vencimiento.',
    '<a href="' . url('productos') . '" class="btn-light"><i data-lucide="arrow-left" class="w-4 h-4"></i> Productos</a>');
?>
<script src="assets/vendor/xlsx.full.min.js"></script>

<div id="paso1" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 space-y-6">
    <label id="drop" class="card flex flex-col items-center justify-center text-center gap-3 p-10 border-2 border-dashed border-slate-300 hover:border-brand-400 hover:bg-brand-50/30 cursor-pointer transition">
      <div class="h-16 w-16 rounded-2xl bg-brand-50 text-brand-600 grid place-items-center"><i data-lucide="file-spreadsheet" class="w-8 h-8"></i></div>
      <div class="font-bold text-lg">Arrastra tu archivo aquí</div>
      <div class="text-sm text-slate-500">o toca para elegirlo · Excel (.xlsx, .xls) o CSV</div>
      <span class="btn-primary mt-2"><i data-lucide="upload" class="w-4 h-4"></i> Elegir archivo</span>
      <input id="archivo" type="file" accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv" class="hidden">
    </label>
    <div id="errArchivo" class="hidden rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm px-4 py-3"></div>
  </div>
  <div class="space-y-6">
    <div class="card p-6">
      <h3 class="font-bold mb-1">1. Descarga la plantilla</h3>
      <p class="text-sm text-slate-500 mb-4">Llénala en Excel y súbela. Solo el <b>nombre</b> y el <b>precio de venta</b> son obligatorios.</p>
      <button onclick="descargarPlantilla()" class="btn-dark w-full"><i data-lucide="download" class="w-4 h-4"></i> Descargar plantilla Excel</button>
    </div>
    <div class="card p-6 text-sm">
      <h3 class="font-bold mb-3">Columnas reconocidas</h3>
      <div class="flex flex-wrap gap-1.5">
        <?php foreach (['codigo', 'nombre *', 'principio_activo', 'presentacion', 'laboratorio', 'categoria', 'unidad', 'precio_compra', 'precio_venta *', 'stock_minimo', 'requiere_receta', 'stock_inicial', 'lote', 'vencimiento'] as $c): ?>
          <span class="chip bg-slate-100 text-slate-700"><?= e($c) ?></span>
        <?php endforeach; ?>
      </div>
      <ul class="mt-4 space-y-1.5 text-slate-500 text-xs list-disc pl-4">
        <li>Si el <b>código</b> ya existe, el producto se puede actualizar en lugar de duplicarse.</li>
        <li>Las categorías que no existan se crean solas.</li>
        <li><b>requiere_receta</b>: escribe "Sí" o "No".</li>
        <li><b>stock_inicial</b> crea un lote con el <b>lote</b> y <b>vencimiento</b> (dd/mm/aaaa) de esa fila.</li>
        <li>Si importas dos veces el mismo archivo con stock, el inventario se suma otra vez.</li>
      </ul>
    </div>
  </div>
</div>

<div id="paso2" class="hidden space-y-6">
  <div class="card p-5 flex flex-col lg:flex-row lg:items-center gap-4">
    <div class="flex items-center gap-3 min-w-0 flex-1">
      <div class="h-11 w-11 rounded-xl bg-brand-50 text-brand-600 grid place-items-center shrink-0"><i data-lucide="file-check-2" class="w-5 h-5"></i></div>
      <div class="min-w-0"><div id="nomArchivo" class="font-bold truncate"></div><div id="resumen" class="text-sm text-slate-500"></div></div>
    </div>
    <label class="flex items-center gap-2 text-sm font-medium"><input type="checkbox" id="actualizar" checked class="rounded text-brand-600 w-4 h-4"> Actualizar productos que ya existen</label>
    <div class="flex gap-2">
      <button onclick="reiniciar()" class="btn-light">Cambiar archivo</button>
      <button id="btnImportar" onclick="importar()" class="btn-primary"><i data-lucide="upload" class="w-4 h-4"></i> Importar</button>
    </div>
  </div>
  <div id="avisoCols" class="hidden rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-3"></div>
  <div id="progreso" class="hidden card p-5">
    <div class="flex justify-between text-sm mb-2"><span class="font-semibold">Importando…</span><span id="progTxt"></span></div>
    <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden"><div id="progBar" class="h-full bg-brand-500 transition-all" style="width:0%"></div></div>
  </div>
  <div id="final" class="hidden grid grid-cols-2 lg:grid-cols-4 gap-4"></div>
  <div class="card overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
      <h3 class="font-bold">Vista previa</h3>
      <select id="filtro" onchange="pintar()" class="input w-auto py-1.5"><option value="">Todas las filas</option><option value="error">Solo con errores</option></select>
    </div>
    <div class="overflow-x-auto max-h-[60vh]">
      <table class="tbl">
        <thead class="sticky top-0 z-10"><tr><th>Fila</th><th>Estado</th><th>Código</th><th>Nombre</th><th class="hidden md:table-cell">Presentación</th><th class="hidden lg:table-cell">Categoría</th><th class="text-right">Costo</th><th class="text-right">Precio</th><th class="text-right">Stock ini.</th><th class="hidden md:table-cell">Vence</th></tr></thead>
        <tbody id="tbody"></tbody>
      </table>
    </div>
    <div id="masFilas" class="hidden px-5 py-3 text-xs text-slate-500 border-t border-slate-100"></div>
  </div>
</div>

<script>
const CAMPOS = ['codigo', 'nombre', 'principio_activo', 'presentacion', 'laboratorio', 'categoria', 'unidad', 'precio_compra', 'precio_venta', 'stock_minimo', 'requiere_receta', 'stock_inicial', 'lote', 'vencimiento'];
const ALIAS = {
  codigo: ['codigo', 'cod', 'codigo_barras', 'codigo_de_barras', 'barcode', 'sku', 'ean'],
  nombre: ['nombre', 'producto', 'descripcion', 'nombre_comercial', 'articulo'],
  principio_activo: ['principio_activo', 'principio', 'generico', 'componente', 'sustancia'],
  presentacion: ['presentacion', 'present', 'forma'],
  laboratorio: ['laboratorio', 'lab', 'marca', 'fabricante'],
  categoria: ['categoria', 'tipo', 'familia', 'grupo'],
  unidad: ['unidad', 'unidad_venta', 'um'],
  precio_compra: ['precio_compra', 'costo', 'costo_unitario', 'precio_costo', 'compra'],
  precio_venta: ['precio_venta', 'precio', 'pvp', 'venta', 'precio_publico'],
  stock_minimo: ['stock_minimo', 'minimo', 'min', 'existencia_minima'],
  requiere_receta: ['requiere_receta', 'receta', 'rx', 'controlado'],
  stock_inicial: ['stock_inicial', 'stock', 'cantidad', 'existencia', 'existencias', 'inventario', 'unidades'],
  lote: ['lote', 'no_lote', 'numero_lote', 'num_lote'],
  vencimiento: ['vencimiento', 'fecha_vencimiento', 'vence', 'caducidad', 'fecha_caducidad', 'expira'],
};
const norm = s => String(s ?? '').trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
const MAPA = {}; Object.entries(ALIAS).forEach(([c, l]) => l.forEach(a => MAPA[a] = c));
let filas = [], nombreArchivo = '';
const $ = id => document.getElementById(id);

const pad = n => String(n).padStart(2, '0');
function fecha(v) {
  if (v === null || v === undefined || v === '') return '';
  if (v instanceof Date && !isNaN(v)) return v.getFullYear() + '-' + pad(v.getMonth() + 1) + '-' + pad(v.getDate());
  if (typeof v === 'number' && v > 20000 && v < 80000) { const d = new Date(Math.round((v - 25569) * 864e5)); return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate()); }
  const s = String(v).trim(); let m;
  if ((m = s.match(/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/))) return m[1] + '-' + pad(m[2]) + '-' + pad(m[3]);
  if ((m = s.match(/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2,4})$/))) { const y = m[3].length === 2 ? '20' + m[3] : m[3]; return y + '-' + pad(m[2]) + '-' + pad(m[1]); }
  if ((m = s.match(/^(\d{1,2})[-\/.](\d{4})$/))) { const d = new Date(+m[2], +m[1], 0); return m[2] + '-' + pad(m[1]) + '-' + pad(d.getDate()); }
  return 'X';
}
const numero = v => { if (v === '' || v === null || v === undefined) return ''; if (typeof v === 'number') return v; const n = String(v).replace(/[Qq$,\s]/g, ''); return n === '' ? '' : (isNaN(n) ? 'X' : Number(n)); };

$('archivo').addEventListener('change', e => e.target.files[0] && leer(e.target.files[0]));
['dragover', 'dragenter'].forEach(ev => $('drop').addEventListener(ev, e => { e.preventDefault(); $('drop').classList.add('border-brand-500', 'bg-brand-50/50'); }));
['dragleave', 'drop'].forEach(ev => $('drop').addEventListener(ev, e => { e.preventDefault(); $('drop').classList.remove('border-brand-500', 'bg-brand-50/50'); }));
$('drop').addEventListener('drop', e => e.dataTransfer.files[0] && leer(e.dataTransfer.files[0]));

function errorArchivo(msg) { $('errArchivo').textContent = msg; $('errArchivo').classList.remove('hidden'); }

async function leer(file) {
  $('errArchivo').classList.add('hidden');
  if (file.size > 10 * 1024 * 1024) return errorArchivo('El archivo pesa más de 10 MB.');
  nombreArchivo = file.name;
  try {
    const buf = await file.arrayBuffer();
    const esCsv = /\.csv$/i.test(file.name);
    const wb = esCsv ? XLSX.read(new TextDecoder('utf-8').decode(buf).replace(/^\uFEFF/, ''), { type: 'string', raw: true }) : XLSX.read(buf, { type: 'array', cellDates: true });
    const hoja = wb.Sheets[wb.SheetNames[0]];
    const matriz = XLSX.utils.sheet_to_json(hoja, { header: 1, defval: '', raw: true, blankrows: false });
    if (matriz.length < 2) return errorArchivo('El archivo no tiene filas de productos.');
    // Buscar la fila de encabezados (primeras 10 filas)
    let hIdx = matriz.slice(0, 10).findIndex(r => r.some(c => ['nombre', 'producto', 'descripcion'].includes(norm(c))));
    if (hIdx < 0) return errorArchivo('No encontré la columna "nombre". Usa la plantilla o revisa los encabezados.');
    const encab = matriz[hIdx].map(c => MAPA[norm(c)] || null);
    const ignoradas = matriz[hIdx].filter((c, i) => String(c).trim() && !encab[i]);
    filas = matriz.slice(hIdx + 1).map((r, i) => {
      const o = { _fila: hIdx + i + 2 };
      encab.forEach((c, j) => { if (c && o[c] === undefined) o[c] = r[j]; });
      return o;
    }).filter(o => CAMPOS.some(c => String(o[c] ?? '').trim() !== ''));
    filas.forEach(validar);
    $('avisoCols').classList.toggle('hidden', !ignoradas.length);
    $('avisoCols').textContent = ignoradas.length ? 'Estas columnas se ignorarán porque no las reconozco: ' + ignoradas.join(', ') : '';
    $('paso1').classList.add('hidden'); $('paso2').classList.remove('hidden');
    $('final').classList.add('hidden'); $('progreso').classList.add('hidden'); $('btnImportar').disabled = false;
    $('nomArchivo').textContent = nombreArchivo;
    resumen(); pintar();
  } catch (e) { console.error(e); errorArchivo('No pude leer el archivo. Verifica que sea Excel o CSV válido.'); }
}

function validar(o) {
  const errs = [];
  ['codigo', 'nombre', 'principio_activo', 'presentacion', 'laboratorio', 'categoria', 'unidad', 'lote', 'requiere_receta'].forEach(c => { if (o[c] !== undefined) o[c] = String(o[c] instanceof Date ? fecha(o[c]) : o[c]).trim(); });
  ['precio_compra', 'precio_venta'].forEach(c => { o[c] = numero(o[c]); if (o[c] === 'X') errs.push((c === 'precio_venta' ? 'Precio' : 'Costo') + ' no es un número'); else if (o[c] !== '' && o[c] < 0) errs.push('Precio negativo'); });
  ['stock_minimo', 'stock_inicial'].forEach(c => { o[c] = numero(o[c]); if (o[c] === 'X' || (o[c] !== '' && (o[c] < 0 || !Number.isInteger(o[c])))) errs.push((c === 'stock_inicial' ? 'Stock inicial' : 'Stock mínimo') + ' debe ser entero ≥ 0'); });
  o.vencimiento = fecha(o.vencimiento);
  if (o.vencimiento === 'X') errs.push('Vencimiento con formato no válido (usa dd/mm/aaaa)');
  if (!o.nombre && !o.codigo) errs.push('Falta el nombre');
  if (!o.codigo && o.precio_venta === '') errs.push('Falta el precio de venta');
  o._errores = errs; o._estado = errs.length ? 'error' : 'ok';
}

function resumen() {
  const ok = filas.filter(f => f._estado === 'ok').length, err = filas.length - ok;
  const conStock = filas.filter(f => f._estado === 'ok' && Number(f.stock_inicial) > 0).length;
  $('resumen').innerHTML = `${filas.length} filas · <b class="text-emerald-700">${ok} listas</b>${err ? ` · <b class="text-rose-600">${err} con errores (no se importarán)</b>` : ''}${conStock ? ` · ${conStock} con inventario inicial` : ''}`;
  $('btnImportar').disabled = !ok;
}

const ESTADOS = {
  ok: '<span class="chip bg-slate-100 text-slate-600">Lista</span>',
  creado: '<span class="chip bg-emerald-50 text-emerald-700">Creado</span>',
  actualizado: '<span class="chip bg-indigo-50 text-indigo-700">Actualizado</span>',
  omitido: '<span class="chip bg-amber-50 text-amber-700">Ya existía</span>',
};
function pintar() {
  const f = $('filtro').value;
  const lista = filas.filter(r => !f || r._estado === 'error');
  const max = 500;
  $('tbody').innerHTML = lista.slice(0, max).map(r => `
    <tr class="${r._estado === 'error' ? 'bg-rose-50/40' : ''}">
      <td class="text-slate-400">${r._fila}</td>
      <td>${r._estado === 'error' ? `<span class="chip bg-rose-50 text-rose-700">Error</span><div class="text-[11px] text-rose-600 mt-1 max-w-[220px] whitespace-normal">${escapeHtml(r._errores.join(' · '))}</div>` : (ESTADOS[r._estado] || '')}</td>
      <td class="text-slate-500">${escapeHtml(r.codigo || '')}</td>
      <td class="font-semibold min-w-[160px]">${escapeHtml(r.nombre || '')}${String(r.requiere_receta || '').match(/^(s|si|sí|1|x)$/i) ? ' <span class="chip bg-amber-50 text-amber-700">Rx</span>' : ''}</td>
      <td class="hidden md:table-cell text-slate-500">${escapeHtml(r.presentacion || '')}</td>
      <td class="hidden lg:table-cell text-slate-500">${escapeHtml(r.categoria || '')}</td>
      <td class="text-right whitespace-nowrap">${r.precio_compra !== '' && r.precio_compra !== 'X' ? fmtMoney(r.precio_compra) : '—'}</td>
      <td class="text-right whitespace-nowrap font-semibold">${r.precio_venta !== '' && r.precio_venta !== 'X' ? fmtMoney(r.precio_venta) : '—'}</td>
      <td class="text-right">${r.stock_inicial !== '' && r.stock_inicial !== 'X' ? r.stock_inicial : '—'}</td>
      <td class="hidden md:table-cell whitespace-nowrap text-slate-500">${r.vencimiento && r.vencimiento !== 'X' ? r.vencimiento.split('-').reverse().join('/') : '—'}</td>
    </tr>`).join('') || '<tr><td colspan="10" class="py-10 text-center text-slate-400">Sin filas</td></tr>';
  $('masFilas').classList.toggle('hidden', lista.length <= max);
  $('masFilas').textContent = `Mostrando ${max} de ${lista.length} filas. Todas se importarán.`;
}

async function importar() {
  const validas = filas.filter(f => f._estado === 'ok');
  if (!validas.length) return;
  $('btnImportar').disabled = true; $('progreso').classList.remove('hidden');
  const tam = 150, cont = { creado: 0, actualizado: 0, omitido: 0, error: 0 };
  for (let i = 0; i < validas.length; i += tam) {
    const lote = validas.slice(i, i + tam).map(f => { const o = { _fila: f._fila }; CAMPOS.forEach(c => { if (f[c] !== undefined) o[c] = f[c]; }); return o; });
    try {
      const r = await fetch('index.php?p=api&action=importar_productos', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF }, body: JSON.stringify({ actualizar: $('actualizar').checked, filas: lote }) }).then(x => x.json());
      if (!r.ok) throw new Error(r.error || 'Error');
      r.resultados.forEach(x => {
        const f = filas.find(y => y._fila === x.fila); if (!f) return;
        if (x.ok) { f._estado = x.accion; cont[x.accion]++; } else { f._estado = 'error'; f._errores = [x.msg]; cont.error++; }
      });
    } catch (e) {
      lote.forEach(l => { const f = filas.find(y => y._fila === l._fila); f._estado = 'error'; f._errores = ['No se pudo enviar: ' + e.message]; cont.error++; });
    }
    const hecho = Math.min(i + tam, validas.length);
    $('progBar').style.width = (hecho / validas.length * 100) + '%'; $('progTxt').textContent = hecho + ' / ' + validas.length;
  }
  cont.error += filas.filter(f => f._estado === 'error').length - cont.error;
  $('progreso').classList.add('hidden');
  const card = (t, v, c, ic) => `<div class="card p-4 sm:p-5"><div class="flex items-center gap-3"><div class="h-9 w-9 rounded-xl bg-${c}-50 text-${c}-600 grid place-items-center"><i data-lucide="${ic}" class="w-[18px] h-[18px]"></i></div><div class="text-xs font-semibold text-slate-500">${t}</div></div><div class="text-2xl font-extrabold mt-3">${v}</div></div>`;
  $('final').innerHTML = card('Creados', cont.creado, 'emerald', 'circle-plus') + card('Actualizados', cont.actualizado, 'indigo', 'refresh-cw') + card('Ya existían (omitidos)', cont.omitido, 'amber', 'skip-forward') + card('Con errores', cont.error, 'rose', 'circle-x');
  $('final').classList.remove('hidden');
  $('resumen').innerHTML = `Importación terminada · <a href="index.php?p=productos" class="text-brand-700 font-semibold">Ver productos</a>${cont.error ? ' · <button onclick="descargarErrores()" class="text-rose-600 font-semibold">Descargar errores</button>' : ''}`;
  if (cont.error) $('filtro').value = 'error';
  pintar(); lucide.createIcons();
}

function descargarErrores() {
  const datos = filas.filter(f => f._estado === 'error').map(f => { const o = { fila: f._fila, error: f._errores.join(' · ') }; CAMPOS.forEach(c => o[c] = f[c] ?? ''); return o; });
  const ws = XLSX.utils.json_to_sheet(datos), wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Errores'); XLSX.writeFile(wb, 'errores_importacion.xlsx');
}

function reiniciar() { filas = []; $('archivo').value = ''; $('paso2').classList.add('hidden'); $('paso1').classList.remove('hidden'); }

function descargarPlantilla() {
  const enc = ['codigo', 'nombre', 'principio_activo', 'presentacion', 'laboratorio', 'categoria', 'unidad', 'precio_compra', 'precio_venta', 'stock_minimo', 'requiere_receta', 'stock_inicial', 'lote', 'vencimiento'];
  const ej = [
    ['7401234567890', 'Acetaminofén 500 mg', 'Paracetamol', 'Caja 10 tabletas', 'Laboratorios Ejemplo', 'Medicamentos', 'Caja', 8.5, 15, 5, 'No', 40, 'L2401', '31/12/2027'],
    ['7401234567891', 'Amoxicilina 500 mg', 'Amoxicilina', 'Caja 12 cápsulas', 'Genéricos GT', 'Genéricos', 'Caja', 22, 38, 3, 'Sí', 12, 'AX99', '30/06/2027'],
    ['', 'Alcohol en gel 250 ml', '', 'Frasco', '', 'Cuidado personal', 'Unidad', 9, 18, 10, 'No', '', '', ''],
  ];
  const ws = XLSX.utils.aoa_to_sheet([enc, ...ej]);
  ws['!cols'] = enc.map(h => ({ wch: Math.max(12, h.length + 4) })); ws['!cols'][1] = { wch: 28 };
  const ayuda = XLSX.utils.aoa_to_sheet([
    ['Columna', 'Obligatoria', 'Descripción'],
    ['codigo', 'No', 'Código de barras. Si ya existe en el sistema, el producto se actualiza.'],
    ['nombre', 'Sí', 'Nombre comercial del producto.'],
    ['principio_activo', 'No', 'Sustancia o genérico.'],
    ['presentacion', 'No', 'Ej. Caja 10 tabletas, Frasco 120 ml.'],
    ['laboratorio', 'No', 'Fabricante o marca.'],
    ['categoria', 'No', 'Si no existe se crea automáticamente.'],
    ['unidad', 'No', 'Unidad de venta (Unidad, Caja, Blíster…).'],
    ['precio_compra', 'No', 'Costo por unidad.'],
    ['precio_venta', 'Sí', 'Precio al público.'],
    ['stock_minimo', 'No', 'Cantidad para la alerta de stock bajo (5 si se deja vacío).'],
    ['requiere_receta', 'No', 'Sí / No.'],
    ['stock_inicial', 'No', 'Existencia actual. Crea un lote con el lote y vencimiento de la fila.'],
    ['lote', 'No', 'Número de lote del inventario inicial.'],
    ['vencimiento', 'No', 'Fecha de vencimiento dd/mm/aaaa.'],
  ]);
  ayuda['!cols'] = [{ wch: 18 }, { wch: 12 }, { wch: 70 }];
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Productos'); XLSX.utils.book_append_sheet(wb, ayuda, 'Instrucciones');
  XLSX.writeFile(wb, 'plantilla_productos.xlsx');
}
</script>
<?php layout_end();
