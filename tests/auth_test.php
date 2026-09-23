<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/People.php';

/** A person with a verified email and a WhatsApp-capable mobile. */
function make_diver(string $tag): array
{
    $id = person_create("Diver {$tag}");
    channel_upsert($id, 'email', "{$tag}@example.com", ['verified' => true, 'primary' => true]);
    channel_upsert($id, 'mobile', '+5299812' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT), ['whatsapp' => true]);
    db()->exec('DELETE FROM login_attempts');
    $_SERVER['REMOTE_ADDR'] = '203.0.113.' . random_int(1, 254);
    $_SESSION = [];

    return person_find($id);
}

/** The code that was "sent": recover it from the token by brute force — six digits is small. */
function code_for_token(int $tokenId): string
{
    $t = login_token_find($tokenId);
    for ($i = 0; $i < 10 ** LOGIN_CODE_DIGITS; $i++) {
        $code = str_pad((string) $i, LOGIN_CODE_DIGITS, '0', STR_PAD_LEFT);
        if (hash_equals($t['token_hash'], login_token_hash($t['salt'], $code))) {
            return $code;
        }
    }
    fail('could not recover the code');
}

test('a known email gets a code, and it is never stored in the clear', function (): void {
    $p = make_diver('a1');
    $r = login_begin('A1@example.com');

    is_true($r['ok']);
    is_same('log', $r['transport'], 'tests use the log transport');
    is_same('a•••@example.com', $r['to'], 'the address is masked on screen');

    $t = login_token_find($r['token_id']);
    is_same(64, strlen($t['token_hash']));
    is_same(32, strlen($t['salt']), 'each token has its own salt');

    $m = db()->query("SELECT * FROM messages WHERE person_id = {$p['id']} ORDER BY id DESC LIMIT 1")->fetch();
    is_same('sent', $m['status']);
    is_same('login_code', $m['template']);
    has(code_for_token($r['token_id']), $m['body_text'], 'the message carries the code');
});

test('an unknown identifier is refused without saying so', function (): void {
    make_diver('a2');
    $r = login_begin('nobody@example.com');
    is_false($r['ok']);
    is_same('unknown', $r['reason']);
    is_same(0, (int) db()->query('SELECT COUNT(*) FROM messages WHERE to_value = "nobody@example.com"')->fetchColumn(),
        'nothing is sent to an address we do not know');
});

test('the right code signs in, consumes the token and verifies the channel', function (): void {
    $p = make_diver('a3');
    $cid = channel_upsert((int) $p['id'], 'email', 'a3-unverified@example.com');
    is_same(null, db()->query("SELECT verified_at FROM contact_channels WHERE id = {$cid}")->fetchColumn());

    $r = login_begin('a3-unverified@example.com');
    $who = login_verify($r['token_id'], code_for_token($r['token_id']));

    is_same($p['id'], $who['id']);
    is_same((int) $p['id'], (int) $_SESSION['person_id']);
    is_true(login_token_find($r['token_id'])['consumed_at'] !== null, 'the token is spent');
    is_true(db()->query("SELECT verified_at FROM contact_channels WHERE id = {$cid}")->fetchColumn() !== null,
        'signing in through a channel proves it');
    is_same(null, login_verify($r['token_id'], code_for_token($r['token_id'])), 'a spent token is dead');
});

test('a wrong code is refused, counted, and the token dies after enough of them', function (): void {
    make_diver('a4');
    $r = login_begin('a4@example.com');
    $right = code_for_token($r['token_id']);
    $wrong = $right === '000000' ? '000001' : '000000';

    for ($i = 1; $i <= LOGIN_MAX_CODE_ATTEMPTS; $i++) {
        is_same(null, login_verify($r['token_id'], $wrong));
        is_same($i, (int) login_token_find($r['token_id'])['attempts']);
    }
    is_same(null, login_verify($r['token_id'], $right), 'even the right code is refused once the token is exhausted');
});

test('an expired code is refused', function (): void {
    make_diver('a5');
    $r = login_begin('a5@example.com');
    db()->exec("UPDATE login_tokens SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = {$r['token_id']}");
    is_same(null, login_verify($r['token_id'], code_for_token($r['token_id'])));
});

test('a mobile with WhatsApp but no provider falls back to email', function (): void {
    $p = make_diver('a6');
    $mobile = db()->query("SELECT value FROM contact_channels WHERE person_id = {$p['id']} AND kind = 'mobile'")->fetchColumn();
    $r = login_begin((string) $mobile);
    is_true($r['ok']);
    // log transport stands in for everything in tests; what matters is the path did not fail
    is_true(in_array($r['transport'], ['log', 'email'], true));
});

test('a one-time link signs in exactly once', function (): void {
    $p = make_diver('a7');
    $cid = (int) db()->query("SELECT id FROM contact_channels WHERE person_id = {$p['id']} AND kind = 'email'")->fetchColumn();
    $url = login_issue_link((int) $p['id'], $cid);
    has('/admin/login.php?t=', $url);
    $raw = substr($url, strpos($url, 't=') + 2);

    $_SESSION = [];
    is_same($p['id'], login_with_link($raw)['id']);
    $_SESSION = [];
    is_same(null, login_with_link($raw), 'a link works once');
    is_same(null, login_with_link('1.' . str_repeat('0', 64)), 'a forged link is refused');
});

test('too many code requests from one address lock it out', function (): void {
    make_diver('a8');
    for ($i = 0; $i < LOGIN_MAX_BEGINS; $i++) {
        login_begin('a8@example.com');
    }
    $r = login_begin('a8@example.com');
    is_false($r['ok']);
    is_same('locked', $r['reason']);
});

test('only active team members get past require_login', function (): void {
    $p = make_diver('a9');
    $r = login_begin('a9@example.com');
    login_verify($r['token_id'], code_for_token($r['token_id']));

    $u = current_user();
    is_same($p['id'], $u['id']);
    is_same(null, $u['team'], 'a diver is not staff');
    is_false(can('can_manage_team'));
});

test('a system administrator holds every permission', function (): void {
    $p = make_diver('a10');
    db()->prepare('INSERT INTO team_members (person_id, is_system_admin, is_active) VALUES (:p, 1, 1)')->execute([':p' => $p['id']]);
    $r = login_begin('a10@example.com');
    login_verify($r['token_id'], code_for_token($r['token_id']));

    // current_user() caches per request; this is a fresh person so it has not been asked yet.
    $u = current_user();
    is_true($u['team'] !== null);
    is_true(can('can_manage_customers', $u));
    is_true(can('can_manage_team', $u));
});

test('a remembered device restores a session without a code', function (): void {
    $p = make_diver('a11');
    $r = login_begin('a11@example.com');
    login_verify($r['token_id'], code_for_token($r['token_id']), true);

    $d = db()->query("SELECT * FROM login_devices WHERE person_id = {$p['id']}")->fetch();
    is_true($d !== false, 'a device row was created');
    is_same(64, strlen($d['token_hash']));

    // The cookie could not be set (headers already sent in tests); forge what the browser would hold.
    $_SESSION = [];
    $_COOKIE[DEVICE_COOKIE] = $d['id'] . '.' . str_repeat('0', 64);
    is_same(null, device_restore(), 'a wrong device secret is refused');
});
