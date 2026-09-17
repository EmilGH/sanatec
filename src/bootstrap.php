<?php

declare(strict_types=1);

/**
 * Shared bootstrap: configuration, database handle, session, small helpers.
 *
 * Every entry point defines SANATEC before requiring this file. Library files
 * refuse to run without it, so a stray request straight at src/ gets a 404 even
 * if the .htaccess rules are ever lost — the document root is a git checkout,
 * so defence in depth is cheap insurance here.
 */

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

date_default_timezone_set('America/Cancun');
mb_internal_encoding('UTF-8');

const SANATEC_ROOT = __DIR__ . '/..';

/**
 * Load configuration from outside the document root.
 *
 * The site is served directly from a git checkout, so credentials must not live
 * inside the repository. Candidates are tried in order; the first that exists
 * wins. config.local.php is for development only and is git-ignored.
 */
function cfg(?string $key = null, mixed $default = null): mixed
{
    static $config = null;

    if ($config === null) {
        $candidates = array_filter([
            getenv('SANATEC_CONFIG') ?: null,
            '/var/www/private/sanatecdiving/config.php',
            SANATEC_ROOT . '/../.sanatec-config.php',
            SANATEC_ROOT . '/config.local.php',
        ]);

        $config = [];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                $loaded = require $path;
                if (is_array($loaded)) {
                    $config = $loaded;
                    break;
                }
            }
        }

        if ($config === []) {
            http_response_code(500);
            error_log('SanaTec: no configuration file found. Looked in: ' . implode(', ', $candidates));
            exit('Configuration missing.');
        }
    }

    if ($key === null) {
        return $config;
    }

    return $config[$key] ?? $default;
}

/** Shared PDO handle. Exceptions on error, real prepared statements, utf8mb4. */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = cfg('db', []);
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $db['host'] ?? '127.0.0.1',
        (int) ($db['port'] ?? 3306),
        $db['name'] ?? 'sanatec'
    );

    try {
        $pdo = new PDO($dsn, $db['user'] ?? '', $db['pass'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $e) {
        // Never leak a DSN or credentials to the browser.
        error_log('SanaTec: database connection failed: ' . $e->getMessage());
        http_response_code(503);
        exit('The site is temporarily unavailable.');
    }

    // Put MySQL on the same clock as PHP. Without this, NOW() is written in the
    // server's timezone and then read back as Cancun time, which pushed
    // Last-Modified five hours into the future.
    $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");

    return $pdo;
}

/** Escape for HTML text and attribute context. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Format a MXN amount the way the printed price guides do: $10,000 with no
 * decimals unless the shop has actually priced something in centavos.
 */
function money(int|float|string|null $amount): ?string
{
    if ($amount === null || $amount === '') {
        return null;
    }

    $value = (float) $amount;
    $decimals = fmod($value, 1.0) === 0.0 ? 0 : 2;

    return '$' . number_format($value, $decimals, '.', ',');
}

/** The visitor's IP as packed bytes, for throttling and the audit log. */
function client_ip_binary(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $packed = $ip !== '' ? @inet_pton($ip) : false;

    return $packed === false ? null : $packed;
}

/** Start a session with cookie flags appropriate to the current scheme. */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

    session_name('sanatec_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/admin/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Catalog.php';
require_once __DIR__ . '/I18n.php';
