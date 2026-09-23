<?php

declare(strict_types=1);

define('DIVER_PUBLIC', true);
require __DIR__ . '/_init.php';

if ($currentUser !== null) {
    audit('logout', 'session', $currentUser['id']);
}
logout();
header('Location: /my/login.php');
