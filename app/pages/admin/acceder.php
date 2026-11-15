<?php
require_super();
if (!is_post()) redirect('admin_farmacias');
csrf_check();
$f = one('SELECT id, nombre FROM farmacias WHERE id = ?', [(int) p('id')]);
if (!$f) redirect('admin_farmacias');
$_SESSION['farmacia_id'] = (int) $f['id'];
flash('success', 'Ahora estás dentro de ' . $f['nombre'] . '.');
redirect('dashboard');
