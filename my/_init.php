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

// Provenance: the first external referrer and any UTM tags a diver arrived
// with are kept for the session and written onto every signature they make.
$utm = array_filter($_GET, static fn ($v, $k): bool => is_string($v) && str_starts_with($k, 'utm_'), ARRAY_FILTER_USE_BOTH);
if ($utm !== [] && empty($_SESSION['provenance']['utm'])) {
    $_SESSION['provenance']['utm'] = array_map(static fn (string $v): string => mb_substr($v, 0, 200), $utm);
}
$ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
if ($ref !== '' && empty($_SESSION['provenance']['landing_referrer'])
    && parse_url($ref, PHP_URL_HOST) !== parse_url((string) cfg('base_url'), PHP_URL_HOST)) {
    $_SESSION['provenance']['landing_referrer'] = mb_substr($ref, 0, 500);
    $_SESSION['provenance']['landing_at'] = date('c');
}

$currentUser = current_user();
if ($currentUser === null && !defined('DIVER_PUBLIC')) {
    header('Location: /my/login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/my/'));
    exit;
}

$lang = $currentUser ? normalize_lang($currentUser['preferred_language'] ?? 'en') : normalize_lang($_SESSION['lang'] ?? 'en');
// A diver gets a profile the first time they arrive. Staff do not: they see
// the area as a diver would, and nothing they do here is written anywhere.
// A team member who really dives gets a profile from the shop, in Customers.
$customer = null;
$staffPreview = false;
if ($currentUser !== null) {
    $customer = customer_find_by_person((int) $currentUser['id']);
    if ($customer === null && $currentUser['team'] !== null) {
        $staffPreview = true;
        $customer = customer_preview_for($currentUser);
    } elseif ($customer === null) {
        $customer = customer_for_person((int) $currentUser['id']);
    }
}

/** Translate inline. */
function tr(string $en, string $es): string
{
    return ($GLOBALS['lang'] ?? 'en') === 'es' ? $es : $en;
}

if ($staffPreview) {
    $GLOBALS['st_notice'] = ($lang === 'es'
        ? '<strong>Vista previa para el equipo.</strong> Estás viendo el área del buceador como la vería un cliente. Nada de lo que hagas aquí se guarda. Si también buceas como cliente, el centro te crea un perfil en Clientes.'
        : '<strong>Staff preview.</strong> You are seeing the diver area as a customer would. Nothing you do here is saved. If you also dive as a customer, the shop creates a profile for you under Customers.');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['flash'][] = ['message' => $lang === 'es' ? 'Vista previa: no se guardó nada.' : 'Preview only: nothing was saved.', 'kind' => 'warn'];
        header('Location: ' . ($_SERVER['REQUEST_URI'] ?? '/my/'));
        exit;
    }
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
