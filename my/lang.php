<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$new = normalize_lang($_GET['lang'] ?? 'en');
db()->prepare('UPDATE people SET preferred_language = :l WHERE id = :id')->execute([':l' => $new, ':id' => $currentUser['id']]);
$_SESSION['lang'] = $new;
header('Location: ' . (str_starts_with((string) ($_SERVER['HTTP_REFERER'] ?? ''), rtrim((string) cfg('base_url'), '/') . '/my/') ? $_SERVER['HTTP_REFERER'] : '/my/'));
