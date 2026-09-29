<?php

declare(strict_types=1);

/** The affiliate's own view. Only the contact person of an active affiliate gets past this. */

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Csrf.php';
require_once __DIR__ . '/../src/Audit.php';
require_once __DIR__ . '/../src/Affiliates.php';
require_once __DIR__ . '/../admin/_layout.php';

start_session();

$currentUser = current_user();
if ($currentUser === null && !defined('PARTNER_PUBLIC')) {
    header('Location: /partner/login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/partner/'));
    exit;
}
$affiliate = $currentUser ? affiliate_for_person((int) $currentUser['id']) : null;
if ($currentUser !== null && $affiliate === null && !defined('PARTNER_PUBLIC')) {
    http_response_code(403);
    shell_start('Partners', null, 'partner');
    echo '<h1 class="st-h1">Not a partner account</h1><p class="st-lede">This sign-in is not linked to an affiliate. Ask the shop to add you as the contact for your business.</p><p><a class="st-link" href="/partner/logout.php">Sign out</a></p>';
    shell_end(null, 'partner');
    exit;
}

function flash(string $message, string $kind = 'ok'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'kind' => $kind];
}

function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $flashes;
}
