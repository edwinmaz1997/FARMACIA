<?php
require_super();
$id = fid();
$_SESSION['farmacia_id'] = 0;
if ($id) redirect('admin_farmacias', ['a' => 'ver', 'id' => $id]);
redirect('admin');
