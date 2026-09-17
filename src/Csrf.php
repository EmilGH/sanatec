<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * CSRF protection for the admin forms.
 *
 * One token per session, compared in constant time. The session cookie is
 * already SameSite=Strict; this is the belt to that pair of braces.
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

/** Hidden input for a form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Verify a POST, or stop the request. */
function csrf_check(): void
{
    $sent = (string) ($_POST['csrf'] ?? '');

    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Your session expired. Go back, reload the page and try again.');
    }
}
