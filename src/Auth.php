<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/People.php';
require_once __DIR__ . '/Messaging.php';

/**
 * Passwordless sign-in.
 *
 * Nobody has a password. A person proves they control a channel — a code
 * arrives by WhatsApp, SMS or email, or a one-time link is issued from the
 * server — and that is the whole of authentication. The same flow serves
 * staff now and divers later; what differs is only what they may open.
 *
 * Codes are six digits, salted and hashed, expire in minutes, and give up
 * after a few wrong guesses. Links are 256-bit random. Neither is stored.
 */

const LOGIN_CODE_DIGITS        = 6;
const LOGIN_CODE_MINUTES       = 10;
const LOGIN_LINK_MINUTES       = 15;
const LOGIN_MAX_CODE_ATTEMPTS  = 5;     // wrong guesses before a token is dead
const LOGIN_MAX_BEGINS         = 10;    // codes one IP may request per window
const LOGIN_MAX_FAILURES       = 8;     // wrong codes from one IP per window
const LOGIN_WINDOW_SECONDS     = 900;
const SESSION_IDLE_SECONDS     = 28800; // eight hours
const REMEMBER_DEVICE_DAYS     = 30;
const DEVICE_COOKIE            = 'sanatec_device';

// ---------------------------------------------------------------------------
// Throttling
// ---------------------------------------------------------------------------

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

function login_begins_recent(): int
{
    $ip = client_ip_binary();
    if ($ip === null) {
        return 0;
    }
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_tokens
         WHERE requested_ip = :ip AND created_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_SECONDS . ' SECOND)'
    );
    $stmt->execute([':ip' => $ip]);

    return (int) $stmt->fetchColumn();
}

function login_is_locked_out(): bool
{
    return login_failures_recent() >= LOGIN_MAX_FAILURES || login_begins_recent() >= LOGIN_MAX_BEGINS;
}

function login_record_attempt(string $identifier, bool $succeeded): void
{
    $ip = client_ip_binary();
    if ($ip === null) {
        return;
    }
    db()->prepare('INSERT INTO login_attempts (ip, identifier, succeeded) VALUES (:ip, :id, :ok)')
        ->execute([':ip' => $ip, ':id' => mb_substr($identifier, 0, 255), ':ok' => $succeeded ? 1 : 0]);
    db()->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 7 DAY)');
}

// ---------------------------------------------------------------------------
// Tokens
// ---------------------------------------------------------------------------

function login_token_hash(string $salt, string $secret): string
{
    return hash('sha256', $salt . ':' . $secret);
}

/** Create a token row. Returns [id, secret]. The secret is never stored. */
function login_token_create(int $personId, int $channelId, string $purpose, string $secret, int $minutes): int
{
    $salt = bin2hex(random_bytes(16));
    db()->prepare(
        'INSERT INTO login_tokens (person_id, channel_id, purpose, token_hash, salt, expires_at, requested_ip, requested_ua)
         VALUES (:p, :c, :purpose, :h, :s, DATE_ADD(NOW(), INTERVAL :m MINUTE), :ip, :ua)'
    )->execute([
        ':p' => $personId, ':c' => $channelId, ':purpose' => $purpose,
        ':h' => login_token_hash($salt, $secret), ':s' => $salt, ':m' => $minutes,
        ':ip' => client_ip_binary(), ':ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    return (int) db()->lastInsertId();
}

function login_token_find(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT t.*, (t.expires_at < NOW()) AS expired FROM login_tokens t WHERE t.id = :id'
    );
    $stmt->execute([':id' => $id]);

    return $stmt->fetch() ?: null;
}

/** Hide most of a channel value for display: e***@rensing.com, +1•••••6800. */
function mask_channel(string $kind, string $value): string
{
    if ($kind === 'email') {
        [$local, $domain] = explode('@', $value, 2) + ['', ''];
        return mb_substr($local, 0, 1) . '•••@' . $domain;
    }

    return mb_substr($value, 0, 3) . '•••••' . mb_substr($value, -4);
}

// ---------------------------------------------------------------------------
// Starting a sign-in
// ---------------------------------------------------------------------------

/** The person and channel behind whatever was typed, or null. */
function login_identify(string $identifier): ?array
{
    $kind = str_contains($identifier, '@') ? 'email' : 'mobile';

    return person_find_by_channel($kind, $identifier);
}

/**
 * Send a sign-in code.
 *
 * Returns a result the form can show. It looks the same whether or not the
 * identifier is known, so the form cannot be used to discover who has an
 * account; the only difference is that nothing is sent.
 *
 *   ['ok' => true,  'token_id' => 12, 'transport' => 'whatsapp', 'to' => '+1•••••6800', 'can_email' => true]
 *   ['ok' => false, 'reason' => 'locked' | 'unknown' | 'undeliverable']
 */
function login_begin(string $identifier, ?string $preferTransport = null): array
{
    if (login_is_locked_out()) {
        return ['ok' => false, 'reason' => 'locked'];
    }

    $found = login_identify($identifier);
    if ($found === null) {
        // Cost the same time as a real send would, roughly, and say nothing.
        usleep(random_int(120_000, 300_000));
        return ['ok' => false, 'reason' => 'unknown'];
    }

    $personId = (int) $found['id'];
    $channels = person_channels($personId);
    $byId = array_column($channels, null, 'id');
    $channel = $byId[(int) $found['channel_id']];

    // "Send it by email instead": swap to the person's primary email.
    if ($preferTransport === 'email' && $channel['kind'] !== 'email') {
        foreach ($channels as $c) {
            if ($c['kind'] === 'email' && ($c['is_primary'] || $channel['kind'] !== 'email')) {
                $channel = $c;
                break;
            }
        }
    }

    $transport = transport_for_channel($channel);
    $code = str_pad((string) random_int(0, 10 ** LOGIN_CODE_DIGITS - 1), LOGIN_CODE_DIGITS, '0', STR_PAD_LEFT);
    $tokenId = login_token_create($personId, (int) $channel['id'], 'login', $code, LOGIN_CODE_MINUTES);

    $locale = (string) ($found['preferred_language'] ?? 'en');
    $rendered = render_message('login_code', $locale, ['code' => $code, 'minutes' => LOGIN_CODE_MINUTES]);
    $messageId = message_queue($transport, $channel['value'], 'login_code', $rendered, [
        'person_id' => $personId, 'channel_id' => (int) $channel['id'], 'locale' => $locale,
    ]);

    $hasEmail = (bool) array_filter($channels, static fn (array $c): bool => $c['kind'] === 'email');

    if (!message_deliver($messageId)) {
        // A dead WhatsApp/SMS provider must not strand someone who has email.
        if ($transport !== 'email' && $hasEmail) {
            return login_begin($identifier, 'email');
        }
        return ['ok' => false, 'reason' => 'undeliverable'];
    }

    return [
        'ok'        => true,
        'token_id'  => $tokenId,
        'transport' => $transport,
        'to'        => mask_channel($channel['kind'], $channel['value']),
        'can_email' => $hasEmail && $channel['kind'] !== 'email',
    ];
}

/**
 * Check a code against a token. Returns the person on success, null otherwise.
 * Each wrong guess is counted on the token and against the IP.
 */
function login_verify(int $tokenId, string $code, bool $remember = false): ?array
{
    $token = login_token_find($tokenId);
    $code = preg_replace('/\D/', '', $code) ?? '';

    if ($token === null || $token['purpose'] !== 'login' || $token['consumed_at'] !== null
        || (int) $token['expired'] === 1 || (int) $token['attempts'] >= LOGIN_MAX_CODE_ATTEMPTS
        || login_is_locked_out()) {
        return null;
    }

    if (!hash_equals($token['token_hash'], login_token_hash($token['salt'], $code))) {
        db()->prepare('UPDATE login_tokens SET attempts = attempts + 1 WHERE id = :id')->execute([':id' => $tokenId]);
        login_record_attempt('token:' . $tokenId, false);
        return null;
    }

    return login_complete($token, $remember);
}

/** A one-time link: "<id>.<secret>". Returns the person on success. */
function login_with_link(string $raw, bool $remember = false): ?array
{
    if (preg_match('/^(\d+)\.([a-f0-9]{64})$/', $raw, $m) !== 1) {
        return null;
    }

    $token = login_token_find((int) $m[1]);
    if ($token === null || $token['purpose'] !== 'login' || $token['consumed_at'] !== null || (int) $token['expired'] === 1) {
        return null;
    }
    if (!hash_equals($token['token_hash'], login_token_hash($token['salt'], $m[2]))) {
        login_record_attempt('link:' . $m[1], false);
        return null;
    }

    return login_complete($token, $remember);
}

/** Issue a one-time sign-in link for a channel. Returns the absolute URL. */
function login_issue_link(int $personId, int $channelId, int $minutes = LOGIN_LINK_MINUTES): string
{
    $secret = bin2hex(random_bytes(32));
    $id = login_token_create($personId, $channelId, 'login', $secret, $minutes);

    return rtrim((string) cfg('base_url', 'https://sanatecdiving.com'), '/') . '/admin/login.php?t=' . $id . '.' . $secret;
}

/** Consume a token, mark its channel verified, and open a session. */
function login_complete(array $token, bool $remember): ?array
{
    $person = person_find((int) $token['person_id']);
    if ($person === null) {
        return null;
    }

    db()->prepare('UPDATE login_tokens SET consumed_at = NOW() WHERE id = :id')->execute([':id' => $token['id']]);
    db()->prepare('UPDATE contact_channels SET verified_at = COALESCE(verified_at, NOW()) WHERE id = :id')
        ->execute([':id' => $token['channel_id']]);
    login_record_attempt('person:' . $person['id'], true);

    session_establish($person);

    if ($remember) {
        device_remember((int) $person['id']);
    }

    return $person;
}

// ---------------------------------------------------------------------------
// Sessions
// ---------------------------------------------------------------------------

function session_establish(array $person): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['person_id'] = (int) $person['id'];
    $_SESSION['username']  = $person['name'];      // audit_log reads this
    $_SESSION['last_seen'] = time();
    current_user(true);                            // drop whatever this request cached before sign-in
}

function device_remember(int $personId): void
{
    $secret = bin2hex(random_bytes(32));
    db()->prepare(
        'INSERT INTO login_devices (person_id, token_hash, user_agent, created_ip, expires_at)
         VALUES (:p, :h, :ua, :ip, DATE_ADD(NOW(), INTERVAL :d DAY))'
    )->execute([
        ':p' => $personId, ':h' => hash('sha256', $secret),
        ':ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ':ip' => client_ip_binary(), ':d' => REMEMBER_DEVICE_DAYS,
    ]);
    $id = (int) db()->lastInsertId();

    if (!headers_sent()) {
        setcookie(DEVICE_COOKIE, $id . '.' . $secret, [
            'expires' => time() + REMEMBER_DEVICE_DAYS * 86400, 'path' => '/',
            'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
        ]);
    }
}

/** Restore a session from a remembered-device cookie, if there is a valid one. */
function device_restore(): ?array
{
    $raw = (string) ($_COOKIE[DEVICE_COOKIE] ?? '');
    if (preg_match('/^(\d+)\.([a-f0-9]{64})$/', $raw, $m) !== 1) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT * FROM login_devices WHERE id = :id AND revoked_at IS NULL AND expires_at > NOW()'
    );
    $stmt->execute([':id' => (int) $m[1]]);
    $device = $stmt->fetch();

    if (!$device || !hash_equals($device['token_hash'], hash('sha256', $m[2]))) {
        return null;
    }

    $person = person_find((int) $device['person_id']);
    if ($person === null) {
        return null;
    }

    db()->prepare('UPDATE login_devices SET last_used_at = NOW() WHERE id = :id')->execute([':id' => $device['id']]);
    session_establish($person);

    return $person;
}

function device_forget(): void
{
    $raw = (string) ($_COOKIE[DEVICE_COOKIE] ?? '');
    if (preg_match('/^(\d+)\./', $raw, $m) === 1) {
        db()->prepare('UPDATE login_devices SET revoked_at = NOW() WHERE id = :id')->execute([':id' => (int) $m[1]]);
    }
    if (!headers_sent()) {
        setcookie(DEVICE_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    }
}

function is_https(): bool
{
    return (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function logout(): void
{
    device_forget();
    $_SESSION = [];
    if (ini_get('session.use_cookies') && !headers_sent()) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'],
            'httponly' => $p['httponly'], 'samesite' => $p['samesite'],
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    current_user(true);
}

/**
 * The signed-in person, with their team record under 'team' (null if they
 * are not staff). Expires an idle session; falls back to a remembered device.
 */
function current_user(bool $reload = false): ?array
{
    static $cached = null;
    if ($reload) {
        $cached = null;
    }
    if ($cached !== null) {
        return $cached ?: null;
    }

    $person = null;

    if (!empty($_SESSION['person_id'])) {
        if (time() - (int) ($_SESSION['last_seen'] ?? 0) > SESSION_IDLE_SECONDS) {
            $_SESSION = [];
        } else {
            $_SESSION['last_seen'] = time();
            $person = person_find((int) $_SESSION['person_id']);
        }
    }

    $person ??= device_restore();

    if ($person === null) {
        $cached = false;
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM team_members WHERE person_id = :p');
    $stmt->execute([':p' => $person['id']]);
    $team = $stmt->fetch() ?: null;

    $person['team'] = $team && (int) $team['is_active'] === 1 ? $team : null;

    return $cached = $person;
}

/** Only active team members past this point. */
function require_login(): array
{
    $user = current_user();
    if ($user === null || $user['team'] === null) {
        $target = $_SERVER['REQUEST_URI'] ?? '/admin/';
        header('Location: /admin/login.php?next=' . rawurlencode($target));
        exit;
    }

    return $user;
}

/** Does the signed-in person hold a permission? System admins hold them all. */
function can(string $permission, ?array $user = null): bool
{
    $user ??= current_user();
    $team = $user['team'] ?? null;
    if ($team === null) {
        return false;
    }

    return (int) $team['is_system_admin'] === 1 || (int) ($team[$permission] ?? 0) === 1;
}

function require_permission(string $permission): array
{
    $user = require_login();
    if (!can($permission, $user)) {
        http_response_code(403);
        exit('You do not have access to this section.');
    }

    return $user;
}
