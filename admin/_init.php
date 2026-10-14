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

// A POST bigger than PHP's post_max_size arrives with $_POST and $_FILES empty, which would
// otherwise surface as a baffling CSRF failure. Say what happened and go back.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('That upload was too large for the server to accept (' . ini_get('post_max_size') . 'B in one go). Try a smaller photo.', 'warn');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/'));
    exit;
}

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
