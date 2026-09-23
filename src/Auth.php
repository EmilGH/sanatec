<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * Admin authentication.
 *
 * One shop, a handful of staff: a username and a hashed password in the
 * database is the right size of solution. Failed attempts are throttled per IP
 * so the login form is not a free brute-force oracle sitting on the public web.
 */

const LOGIN_MAX_FAILURES   = 8;      // failures allowed inside the window
const LOGIN_WINDOW_SECONDS = 900;    // 15 minutes
const SESSION_IDLE_SECONDS = 28800;  // 8 hours — a working day, then re-auth

/** Count recent failures from this IP, to decide whether to accept a try. */
function login_failures_recent(): int
{
    $ip = client_ip_binary();
    if ($ip === null) {
        return 0;
    }

    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip = :ip AND succeeded = 0 AND attempted_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_SECONDS . ' SECOND)'
    );
    $stmt->execute([':ip' => $ip]);

    return (int) $stmt->fetchColumn();
}

function login_is_locked_out(): bool
{
    return login_failures_recent() >= LOGIN_MAX_FAILURES;
}

function login_record_attempt(string $username, bool $succeeded): void
{
    $ip = client_ip_binary();
    if ($ip === null) {
        return;
    }

    db()->prepare('INSERT INTO login_attempts (ip, username, succeeded) VALUES (:ip, :u, :ok)')
        ->execute([':ip' => $ip, ':u' => mb_substr($username, 0, 64), ':ok' => $succeeded ? 1 : 0]);

    // Keep the table from growing forever without needing a cron job.
    db()->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 7 DAY)');
}

/**
 * Verify credentials and start an authenticated session.
 *
 * Always runs a hash verification, even for an unknown username, so response
 * time does not reveal which usernames exist.
 */
function login_attempt(string $username, string $password): bool
{
    if (login_is_locked_out()) {
        return false;
    }

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE username = :u');
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    $hash = $user['password_hash']
        ?? '$2y$12$3gE2dzwQ8bG54E9xqscWPe9kTQPrfChbn8h9dC7I5OhsZw8vitWr.';  // dummy, never matches

    $ok = password_verify($password, $hash) && $user !== false;

    login_record_attempt($username, $ok);

    if (!$ok) {
        return false;
    }

    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE admin_users SET password_hash = :h WHERE id = :id')
            ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $user['id']]);
    }

    // Rotate the session id so a fixed one cannot be reused. This needs an
    // active session; start_session() is idempotent, so calling it here makes
    // the protection unconditional rather than dependent on the caller.
    start_session();
    session_regenerate_id(true);

    $_SESSION['uid']       = (int) $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['last_seen'] = time();

    db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id')->execute([':id' => $user['id']]);

    return true;
}

function logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'],
        ]);
    }

    session_destroy();
}

/** The signed-in admin, or null. Expires an idle session on the way past. */
function current_user(): ?array
{
    if (empty($_SESSION['uid'])) {
        return null;
    }

    if (time() - (int) ($_SESSION['last_seen'] ?? 0) > SESSION_IDLE_SECONDS) {
        logout();

        return null;
    }

    $_SESSION['last_seen'] = time();

    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['uid']]);

    return $stmt->fetch() ?: null;
}

/** Send anyone who is not signed in to the login form, remembering where they were. */
function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        $target = $_SERVER['REQUEST_URI'] ?? '/admin/';
        header('Location: /admin/login.php?next=' . rawurlencode($target));
        exit;
    }

    return $user;
}

function set_admin_password(int $userId, string $password): void
{
    db()->prepare('UPDATE admin_users SET password_hash = :h, must_change_password = 0 WHERE id = :id')
        ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $userId]);
}

/**
 * Minimum bar for an admin password. Length does more work than character
 * classes, so that is what is required.
 */
function password_problem(string $password): ?string
{
    if (mb_strlen($password) < 12) {
        return 'Use at least 12 characters.';
    }

    if (preg_match('/^(password|sanatec|diving|12345)/i', $password)) {
        return 'That is too easy to guess. Try a phrase you will remember.';
    }

    return null;
}
