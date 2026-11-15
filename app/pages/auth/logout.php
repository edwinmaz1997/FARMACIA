<?php
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $prm = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $prm['path'], $prm['domain'], $prm['secure'], $prm['httponly']);
}
session_destroy();
header('Location: index.php?p=login');
exit;
