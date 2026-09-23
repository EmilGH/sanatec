<?php

declare(strict_types=1);

/**
 * Shared admin bootstrap.
 *
 * Every admin page requires this first. Pages reachable while signed out (the
 * login form) define ADMIN_PUBLIC before requiring it.
 */

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Csrf.php';
require_once __DIR__ . '/../src/Audit.php';

start_session();

$currentUser = defined('ADMIN_PUBLIC') ? current_user() : require_login();

/** Queue a one-off message to show after a redirect. */
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

/** Redirect within the admin and stop. */
function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

/** Read a POSTed field as a trimmed string. */
function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}
