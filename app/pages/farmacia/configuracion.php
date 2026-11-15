<?php
require_admin();
$F = fid();

if (is_post()) {
    csrf_check();
    if (p('nombre') === '') {
        flash('error', 'El nombre es obligatorio.');
        redirect('configuracion');
    }
    update('farmacias', [
        'nombre' => p('nombre'),
        'nit' => nulo(p('nit')),
        'telefono' => nulo(p('telefono')),
        'email' => nulo(p('email')),
        'direccion' => nulo(p('direccion')),
        'responsable' => nulo(p('responsable')),
        'dias_alerta_vencimiento' => max(1, min(730, (int) p('dias_alerta_vencimiento'))),
        'moneda' => mb_substr(p('moneda') ?: 'Q', 0, 5),
        'ticket_pie' => nulo(p('ticket_pie')),
    ], 'id = ?', [$F]);
    if ($err = procesar_logo($F)) {
        flash('error', $err);
        redirect('configuracion');
    }
    flash('success', 'Configuración guardada.');
    redirect('configuracion');
}

$f = one('SELECT * FROM farmacias WHERE id = ?', [$F]);
list($estTxt, $estCol) = estado_suscripcion($f);
$pagos = all('SELECT * FROM pagos_suscripcion WHERE farmacia_id = ? ORDER BY fecha_pago DESC LIMIT 6', [$F]);

layout_start('Configuración', 'configuracion');
page_header('Configuración', 'Datos de la farmacia, alertas y ticket.');
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <form method="post" enctype="multipart/form-data" class="lg:col-span-2 space-y-6">
    <?= csrf_field() ?>
    <div class="card p-6">
      <h3 class="font-bold mb-4">Logo</h3>
      <?php logo_field($f); ?>
    </div>
    <div class="card p-6">
      <h3 class="font-bold mb-4">Datos del negocio</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="sm:col-span-2"><label class="label">Nombre comercial</label><input name="nombre" value="<?= e($f['nombre']) ?>" class="input" required></div>
        <div><label class="label">NIT</label><input name="nit" value="<?= e($f['nit']) ?>" class="input"></div>
        <div><label class="label">Responsable / Químico biólogo</label><input name="responsable" value="<?= e($f['responsable']) ?>" class="input"></div>
        <div><label class="label">Teléfono</label><input name="telefono" value="<?= e($f['telefono']) ?>" class="input"></div>
        <div><label class="label">Correo</label><input type="email" name="email" value="<?= e($f['email']) ?>" class="input"></div>
        <div class="sm:col-span-2"><label class="label">Dirección</label><input name="direccion" value="<?= e($f['direccion']) ?>" class="input"></div>
      </div>
    </div>
    <div class="card p-6">
      <h3 class="font-bold mb-4">Preferencias</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div><label class="label">Alertar vencimientos con anticipación de (días)</label><input type="number" min="1" max="730" name="dias_alerta_vencimiento" value="<?= (int) $f['dias_alerta_vencimiento'] ?>" class="input"></div>
        <div><label class="label">Símbolo de moneda</label><input name="moneda" value="<?= e($f['moneda']) ?>" class="input" maxlength="5"></div>
        <div class="sm:col-span-2"><label class="label">Mensaje al pie del ticket</label><input name="ticket_pie" value="<?= e($f['ticket_pie']) ?>" class="input" placeholder="¡Gracias por su compra!"></div>
      </div>
    </div>
    <button class="btn-primary"><i data-lucide="save" class="w-4 h-4"></i> Guardar cambios</button>
  </form>
  <div class="card p-6 h-fit">
    <h3 class="font-bold mb-4 flex items-center justify-between">Mi suscripción <?= badge($estTxt, $estCol) ?></h3>
    <div class="space-y-2 text-sm">
      <div class="flex justify-between"><span class="text-slate-500">Plan</span><span class="font-semibold"><?= e($f['plan']) ?></span></div>
      <div class="flex justify-between"><span class="text-slate-500">Valor</span><span class="font-semibold"><?= money($f['precio_suscripcion'], 'Q') ?> / <?= e($f['ciclo']) ?></span></div>
      <div class="flex justify-between"><span class="text-slate-500">Vence</span><span class="font-semibold"><?= fdate($f['fecha_vencimiento']) ?></span></div>
    </div>
    <?php if ($pagos): ?>
      <div class="mt-5 pt-4 border-t border-slate-100">
        <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Últimos pagos</div>
        <?php foreach ($pagos as $pg): ?><div class="flex justify-between text-sm py-1"><span class="text-slate-500"><?= fdate($pg['fecha_pago']) ?></span><span><?= money($pg['monto'], 'Q') ?></span></div><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php layout_end();
