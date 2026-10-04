<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/auth.php';
start_session();
require_post_csrf();
$locale = admin_locale();
session_regenerate_id(true);
$_SESSION = ['admin_locale' => $locale, 'csrf' => bin2hex(random_bytes(32))];
redirect('admin/login.php');
