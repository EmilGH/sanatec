<?php

declare(strict_types=1);

define('ADMIN_PUBLIC', true);
require __DIR__ . '/_init.php';

if ($currentUser !== null) {
    audit('logout', 'session');
}

logout();
header('Location: /admin/login.php');
