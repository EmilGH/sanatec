<?php

declare(strict_types=1);

define('PARTNER_PUBLIC', true);
require __DIR__ . '/_init.php';

logout();
header('Location: /partner/login.php');
