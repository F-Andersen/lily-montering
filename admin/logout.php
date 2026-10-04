<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/auth.php';
start_session();
require_post_csrf();
$_SESSION = [];
$p = session_get_cookie_params();
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
session_destroy();
redirect('admin/login.php');
