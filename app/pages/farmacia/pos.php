<?php
require_farmacia();
layout_start('Punto de venta', 'pos');
$puedePrecio = is_admin();
?>
<div class="grid xl:grid-cols-[1fr_380px] gap-6">
  <div class="space-y-4 min-w-0">
    <div class="card p-4 relative">
      <div class="relative">
        <i data-lucide="scan-barcode" class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-brand-600"></i>
        <input id="buscar" autocomplete="off" placeholder="Escanea o busca por nombre, principio activo o laboratorio (F2)" class="input pl-12 py-3.5 text-base">
      </div>
      <div id="resultados" class="hidden absolute left-4 right-4 top-full -mt-2 z-20 card max-h-[60vh] overflow-y-auto divide-y divide-slate-100"></div>
    </div>

    <div class="card overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-bold flex items-center gap-2"><i data-lucide="shopping-cart" class="w-4 h-4 text-brand-600"></i> Carrito <span id="nItems" class="chip bg-slate-100 text-slate-600">0</span></h3>
        <button onclick="if(cart.length && confirm('¿Vaciar el carrito?')){cart=[];render();}" class="text-xs font-semibold text-slate-500 hover:text-rose-600">Vaciar</button>
      </div>
      <div id="carrito" class="divide-y divide-slate-100"></div>
    </div>
  </div>

  <div class="space-y-4 xl:sticky xl:top-24 h-fit">
    <div class="card p-5 space-y-4">
      <div class="relative">
        <label class="label">Cliente</label>
        <div id="clienteSel" class="hidden items-center gap-2 rounded-xl bg-brand-50 px-3 py-2.5 text-sm">
          <i data-lucide="user-check" class="w-4 h-4 text-brand-700"></i><span id="clienteNom" class="flex-1 font-semibold text-brand-800 truncate"></span>
          <button onclick="setCliente(null)" class="text-brand-700"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <div id="clienteBox" class="flex gap-2">
          <input id="buscarCliente" autocomplete="off" placeholder="Consumidor final · buscar por nombre o NIT" class="input">
          <button onclick="nuevoCliente()" class="btn-light px-3" title="Nuevo cliente"><i data-lucide="user-plus" class="w-4 h-4"></i></button>
        </div>
        <div id="resClientes" class="hidden absolute left-0 right-0 top-full mt-1 z-20 card divide-y divide-slate-100 max-h-64 overflow-y-auto"></div>
      </div>

      <div>
        <label class="label">Método de pago</label>
        <div class="grid grid-cols-3 gap-2" id="metodos">
          <button data-m="efectivo" class="metodo rounded-xl border-2 py-2.5 text-xs font-bold flex flex-col items-center gap-1"><i data-lucide="banknote" class="w-4 h-4"></i>Efectivo</button>
          <button data-m="tarjeta" class="metodo rounded-xl border-2 py-2.5 text-xs font-bold flex flex-col items-center gap-1"><i data-lucide="credit-card" class="w-4 h-4"></i>Tarjeta</button>
          <button data-m="transferencia" class="metodo rounded-xl border-2 py-2.5 text-xs font-bold flex flex-col items-center gap-1"><i data-lucide="landmark" class="w-4 h-4"></i>Transfer.</button>
        </div>
      </div>

      <div class="space-y-2 text-sm border-t border-slate-100 pt-4">
        <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span id="tSub" class="font-semibold">—</span></div>
        <div class="flex justify-between items-center gap-3"><span class="text-slate-500">Descuento</span><input id="descuento" type="number" min="0" step="0.01" value="0" class="input w-28 py-1.5 text-right"></div>
        <div class="flex justify-between items-end pt-2"><span class="font-bold">Total</span><span id="tTotal" class="text-3xl font-extrabold tracking-tight text-slate-900">—</span></div>
      </div>

      <div id="efectivoBox" class="grid grid-cols-2 gap-3">
        <div><label class="label">Recibido</label><input id="recibido" type="number" min="0" step="0.01" class="input text-right font-semibold"></div>
        <div><label class="label">Cambio</label><div id="cambio" class="input bg-slate-50 text-right font-bold text-brand-700">—</div></div>
      </div>
      <input id="notas" class="input" placeholder="Nota (opcional)">
      <button id="btnCobrar" onclick="cobrar()" class="btn-primary w-full py-4 text-base"><i data-lucide="check-circle-2" class="w-5 h-5"></i> Cobrar <span class="opacity-70 text-xs">(F9)</span></button>
    </div>
  </div>
</div>

<div id="modalOk" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm grid place-items-center p-4">
  <div class="card w-full max-w-sm p-8 text-center">
    <div class="h-16 w-16 mx-auto rounded-full bg-brand-100 text-brand-600 grid place-items-center mb-4"><i data-lucide="check" class="w-8 h-8"></i></div>
    <h3 class="text-xl font-extrabold">¡Venta registrada!</h3>
    <p class="text-slate-500 text-sm mt-1">Venta <b id="okNum"></b></p>
    <div class="mt-5 rounded-2xl bg-slate-50 p-4 grid grid-cols-2 gap-2 text-left">
      <div><div class="text-xs text-slate-500">Total</div><div id="okTotal" class="text-lg font-extrabold"></div></div>
      <div><div class="text-xs text-slate-500">Cambio</div><div id="okCambio" class="text-lg font-extrabold text-brand-700"></div></div>
    </div>
    <div class="grid grid-cols-2 gap-3 mt-6">
      <a id="okTicket" target="_blank" class="btn-light"><i data-lucide="printer" class="w-4 h-4"></i> Ticket</a>
      <button onclick="nuevaVenta()" class="btn-primary">Nueva venta</button>
    </div>
  </div>
</div>

<script>
const PUEDE_PRECIO = <?= $puedePrecio ? 'true' : 'false' ?>;
let cart = [], cliente = null, metodo = 'efectivo', resultados = [], selIdx = -1, timer = null;
const $ = id => document.getElementById(id);

async function api(action, opts = {}) {
  const r = await fetch('index.php?p=api&action=' + action + (opts.qs || ''), opts.body ? { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF }, body: JSON.stringify(opts.body) } : {});
  return r.json();
}

$('buscar').addEventListener('input', e => { clearTimeout(timer); const v = e.target.value.trim(); if (!v) return ocultar(); timer = setTimeout(() => buscar(v), 180); });
$('buscar').addEventListener('keydown', async e => {
  if (e.key === 'ArrowDown') { e.preventDefault(); mover(1); }
  else if (e.key === 'ArrowUp') { e.preventDefault(); mover(-1); }
  else if (e.key === 'Escape') ocultar();
  else if (e.key === 'Enter') {
    e.preventDefault(); clearTimeout(timer);
    const v = e.target.value.trim(); if (!v) return;
    if (selIdx >= 0 && resultados[selIdx]) return agregar(resultados[selIdx]);
    const res = await api('productos', { qs: '&q=' + encodeURIComponent(v) });
    resultados = res.data || [];
    const exacto = resultados.find(p => p.codigo && p.codigo === v);
    if (exacto) return agregar(exacto);
    if (resultados.length === 1) return agregar(resultados[0]);
    pintarResultados();
  }
});
async function buscar(v) { const res = await api('productos', { qs: '&q=' + encodeURIComponent(v) }); resultados = res.data || []; selIdx = resultados.length ? 0 : -1; pintarResultados(); }
function mover(d) { if (!resultados.length) return; selIdx = (selIdx + d + resultados.length) % resultados.length; pintarResultados(); }
function ocultar() { $('resultados').classList.add('hidden'); resultados = []; selIdx = -1; }
function pintarResultados() {
  const box = $('resultados');
  if (!resultados.length) { box.innerHTML = '<div class="p-6 text-center text-sm text-slate-400">Sin resultados</div>'; box.classList.remove('hidden'); return; }
  box.innerHTML = resultados.map((p, i) => {
    const sin = Number(p.stock) <= 0;
    return `<button data-i="${i}" class="w-full text-left flex items-center gap-3 px-4 py-3 ${i === selIdx ? 'bg-brand-50' : 'hover:bg-slate-50'} ${sin ? 'opacity-50' : ''}">
      <div class="h-10 w-10 rounded-xl bg-slate-100 grid place-items-center text-slate-500"><i data-lucide="pill" class="w-4 h-4"></i></div>
      <div class="flex-1 min-w-0"><div class="font-semibold text-sm truncate">${escapeHtml(p.nombre)} ${p.requiere_receta == 1 ? '<span class="chip bg-amber-50 text-amber-700">Receta</span>' : ''}</div>
      <div class="text-xs text-slate-500 truncate">${escapeHtml([p.presentacion, p.principio_activo, p.laboratorio, p.codigo].filter(Boolean).join(' · '))}</div></div>
      <div class="text-right shrink-0"><div class="font-bold text-sm">${fmtMoney(p.precio_venta)}</div><div class="text-xs ${sin ? 'text-rose-600' : 'text-slate-500'}">${sin ? 'Sin stock' : 'Stock ' + p.stock}</div></div>
    </button>`;
  }).join('');
  box.classList.remove('hidden');
  box.querySelectorAll('button').forEach(b => b.onclick = () => agregar(resultados[b.dataset.i]));
  lucide.createIcons();
}
function agregar(p) {
  if (Number(p.stock) <= 0) { alert('"' + p.nombre + '" no tiene stock disponible.'); return; }
  const ex = cart.find(i => i.producto_id == p.id);
  if (ex) { if (ex.cantidad < ex.stock) ex.cantidad++; else alert('No hay más stock disponible.'); }
  else cart.unshift({ producto_id: p.id, nombre: p.nombre, detalle: [p.presentacion, p.laboratorio].filter(Boolean).join(' · '), precio: Number(p.precio_venta), cantidad: 1, stock: Number(p.stock), receta: p.requiere_receta == 1, venc: p.prox_venc });
  $('buscar').value = ''; ocultar(); render(); $('buscar').focus();
}
function render() {
  const box = $('carrito');
  $('nItems').textContent = cart.reduce((a, i) => a + i.cantidad, 0);
  if (!cart.length) {
    box.innerHTML = '<div class="py-16 text-center text-slate-400"><svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13 5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm-8 2a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/></svg><div class="text-sm">Escanea o busca productos para comenzar</div></div>';
  } else {
    box.innerHTML = cart.map((i, k) => `
      <div class="flex flex-wrap sm:flex-nowrap items-center gap-3 px-5 py-3.5">
        <div class="flex-1 min-w-[180px]">
          <div class="font-semibold text-sm">${escapeHtml(i.nombre)} ${i.receta ? '<span class="chip bg-amber-50 text-amber-700">Requiere receta</span>' : ''}</div>
          <div class="text-xs text-slate-500">${escapeHtml(i.detalle)} ${i.venc ? '· vence ' + i.venc.split('-').reverse().join('/') : ''} · disp. ${i.stock}</div>
        </div>
        <div class="flex items-center rounded-xl border border-slate-200 overflow-hidden">
          <button class="px-3 py-2 hover:bg-slate-100" onclick="cant(${k},-1)">−</button>
          <input value="${i.cantidad}" onchange="setCant(${k},this.value)" class="w-12 text-center text-sm font-semibold outline-none" inputmode="numeric">
          <button class="px-3 py-2 hover:bg-slate-100" onclick="cant(${k},1)">+</button>
        </div>
        <div class="w-24">${PUEDE_PRECIO ? `<input type="number" step="0.01" value="${i.precio.toFixed(2)}" onchange="cart[${k}].precio=Math.max(0,Number(this.value)||0);render()" class="input py-1.5 text-right text-sm">` : `<div class="text-right text-sm text-slate-500">${fmtMoney(i.precio)}</div>`}</div>
        <div class="w-28 text-right font-bold">${fmtMoney(i.precio * i.cantidad)}</div>
        <button onclick="cart.splice(${k},1);render()" class="p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2m-9 0 1 14h8l1-14"/></svg></button>
      </div>`).join('');
  }
  totales();
}
function cant(k, d) { const i = cart[k]; i.cantidad = Math.min(i.stock, Math.max(1, i.cantidad + d)); render(); }
function setCant(k, v) { const i = cart[k]; v = parseInt(v) || 1; if (v > i.stock) { alert('Stock disponible: ' + i.stock); v = i.stock; } i.cantidad = Math.max(1, v); render(); }
function calc() {
  const sub = cart.reduce((a, i) => a + i.precio * i.cantidad, 0);
  const desc = Math.min(sub, Math.max(0, Number($('descuento').value) || 0));
  return { sub, desc, total: Math.round((sub - desc) * 100) / 100 };
}
function totales() {
  const t = calc();
  $('tSub').textContent = fmtMoney(t.sub); $('tTotal').textContent = fmtMoney(t.total);
  const rec = Number($('recibido').value) || 0;
  $('cambio').textContent = rec ? fmtMoney(Math.max(0, rec - t.total)) : '—';
  $('cambio').classList.toggle('text-rose-600', rec > 0 && rec < t.total);
  $('btnCobrar').disabled = !cart.length;
}
$('descuento').addEventListener('input', totales);
$('recibido').addEventListener('input', totales);

document.querySelectorAll('.metodo').forEach(b => b.onclick = () => setMetodo(b.dataset.m));
function setMetodo(m) {
  metodo = m;
  document.querySelectorAll('.metodo').forEach(b => { const on = b.dataset.m === m; b.className = 'metodo rounded-xl border-2 py-2.5 text-xs font-bold flex flex-col items-center gap-1 ' + (on ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-500 hover:border-slate-300'); });
  $('efectivoBox').classList.toggle('hidden', m !== 'efectivo');
}

// Clientes
let tCli = null;
$('buscarCliente').addEventListener('input', e => {
  clearTimeout(tCli); const v = e.target.value.trim(); const box = $('resClientes');
  if (!v) return box.classList.add('hidden');
  tCli = setTimeout(async () => {
    const r = await api('clientes', { qs: '&q=' + encodeURIComponent(v) });
    box.innerHTML = (r.data || []).map(c => `<button class="w-full text-left px-4 py-2.5 hover:bg-slate-50 text-sm" data-c='${escapeHtml(JSON.stringify(c))}'><div class="font-semibold">${escapeHtml(c.nombre)}</div><div class="text-xs text-slate-500">NIT ${escapeHtml(c.nit || 'CF')} ${c.telefono ? '· ' + escapeHtml(c.telefono) : ''}</div></button>`).join('')
      || `<button class="w-full text-left px-4 py-3 text-sm text-brand-700 font-semibold" onclick="nuevoCliente()">+ Crear "${escapeHtml(v)}"</button>`;
    box.classList.remove('hidden');
    box.querySelectorAll('[data-c]').forEach(b => b.onclick = () => setCliente(JSON.parse(b.dataset.c)));
  }, 200);
});
function setCliente(c) {
  cliente = c; $('resClientes').classList.add('hidden'); $('buscarCliente').value = '';
  $('clienteSel').classList.toggle('hidden', !c); $('clienteSel').classList.toggle('flex', !!c);
  $('clienteBox').classList.toggle('hidden', !!c);
  if (c) $('clienteNom').textContent = c.nombre + (c.nit ? ' · ' + c.nit : '');
}
async function nuevoCliente() {
  const nombre = prompt('Nombre del cliente:', $('buscarCliente').value); if (!nombre) return;
  const nit = prompt('NIT (deja vacío para CF):', '') || '';
  const r = await api('crear_cliente', { body: { nombre, nit } });
  if (!r.ok) return alert(r.error); setCliente(r.data);
}

async function cobrar() {
  if (!cart.length) return;
  const t = calc(); const rec = Number($('recibido').value) || 0;
  if (metodo === 'efectivo' && rec > 0 && rec < t.total) return alert('El monto recibido es menor al total.');
  if (cart.some(i => i.receta) && !confirm('Hay productos que requieren receta médica. ¿Confirmas que el cliente presentó la receta?')) return;
  $('btnCobrar').disabled = true;
  try {
    const r = await api('guardar_venta', { body: { items: cart.map(i => ({ producto_id: i.producto_id, cantidad: i.cantidad, precio: i.precio })), cliente_id: cliente ? cliente.id : null, descuento: t.desc, metodo_pago: metodo, monto_recibido: rec || t.total, notas: $('notas').value } });
    if (!r.ok) { alert(r.error); $('btnCobrar').disabled = false; return; }
    $('okNum').textContent = '#' + r.numero; $('okTotal').textContent = fmtMoney(r.total); $('okCambio').textContent = fmtMoney(r.cambio);
    $('okTicket').href = 'index.php?p=ticket&id=' + r.id;
    $('modalOk').classList.remove('hidden');
  } catch (e) { alert('No se pudo registrar la venta. Revisa tu conexión.'); $('btnCobrar').disabled = false; }
}
function nuevaVenta() { cart = []; setCliente(null); $('descuento').value = 0; $('recibido').value = ''; $('notas').value = ''; $('modalOk').classList.add('hidden'); setMetodo('efectivo'); render(); $('buscar').focus(); }

document.addEventListener('keydown', e => {
  if (e.key === 'F2') { e.preventDefault(); $('buscar').focus(); }
  if (e.key === 'F9') { e.preventDefault(); cobrar(); }
});
document.addEventListener('click', e => {
  if (!e.target.closest('#resultados') && e.target !== $('buscar')) $('resultados').classList.add('hidden');
  if (!e.target.closest('#resClientes') && e.target !== $('buscarCliente')) $('resClientes').classList.add('hidden');
});
setMetodo('efectivo'); render(); $('buscar').focus();
</script>
<?php layout_end();
