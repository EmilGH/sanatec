<?php

declare(strict_types=1);

define('PARTNER_PUBLIC', true);
require __DIR__ . '/_init.php';
require_once __DIR__ . '/../src/LoginPage.php';

login_page('partner');
