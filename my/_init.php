<?php

declare(strict_types=1);

/**
 * The diver area. Anyone signed in may enter; a customer profile is created
 * the first time they do. Pages that must work while signed out (login)
 * define DIVER_PUBLIC before requiring this.
 */

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Csrf.php';
require_once __DIR__ . '/../src/Audit.php';
require_once __DIR__ . '/../src/Onboarding.php';
require_once __DIR__ . '/../admin/_layout.php';

start_session();

$currentUser = current_user();
if ($currentUser === null && !defined('DIVER_PUBLIC')) {
    header('Location: /my/login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/my/'));
    exit;
}

$lang = $currentUser ? normalize_lang($currentUser['preferred_language'] ?? 'en') : normalize_lang($_SESSION['lang'] ?? 'en');
$customer = $currentUser ? customer_for_person((int) $currentUser['id']) : null;

/** Translate inline. */
function tr(string $en, string $es): string
{
    return ($GLOBALS['lang'] ?? 'en') === 'es' ? $es : $en;
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

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}
