<?php

declare(strict_types=1);

require __DIR__ . '/../_init.php';
require __DIR__ . '/../_layout.php';

$currentUser = require_permission('can_manage_excursions');
$kind = 'excursion';
require __DIR__ . '/../events/_event.php';
