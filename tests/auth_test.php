<?php

declare(strict_types=1);

test('passwords are stored hashed, never in the clear', function (): void {
    db()->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:u, :h)')
        ->execute([':u' => 'testuser', ':h' => password_hash('correct-horse-battery', PASSWORD_DEFAULT)]);

    $hash = db()->query("SELECT password_hash FROM admin_users WHERE username = 'testuser'")->fetchColumn();

    has_not('correct-horse-battery', (string) $hash, 'the password itself must never be in the row');
    is_true(str_starts_with((string) $hash, '$2y$'), 'expected a bcrypt hash');
    is_true(password_verify('correct-horse-battery', (string) $hash));
    is_false(password_verify('wrong', (string) $hash));
});

test('short passwords are refused', function (): void {
    is_true(is_string(password_problem('short')), 'under 12 characters must be refused');
    is_true(is_string(password_problem('password1234')), 'obvious guesses must be refused');
    is_true(is_string(password_problem('sanatec-diving-2026')), 'the business name is a bad start');
    is_same(null, password_problem('halocline-tuesday-lantern'), 'a long passphrase is fine');
});

test('failed logins are counted and eventually locked out', function (): void {
    db()->exec('DELETE FROM login_attempts');
    $_SERVER['REMOTE_ADDR'] = '203.0.113.7';

    is_same(0, login_failures_recent());
    is_false(login_is_locked_out());

    for ($i = 0; $i < LOGIN_MAX_FAILURES; $i++) {
        login_record_attempt('owner', false);
    }

    is_same(LOGIN_MAX_FAILURES, login_failures_recent());
    is_true(login_is_locked_out(), 'the login form must not be a free brute-force oracle');

    db()->exec('DELETE FROM login_attempts');
    is_false(login_is_locked_out());
});

test('a locked-out address cannot log in even with the right password', function (): void {
    db()->exec('DELETE FROM login_attempts');
    $_SERVER['REMOTE_ADDR'] = '203.0.113.8';
    $_SESSION = [];

    set_admin_password(
        (int) db()->query("SELECT id FROM admin_users WHERE username = 'testuser'")->fetchColumn(),
        'a-known-good-passphrase'
    );

    is_true(login_attempt('testuser', 'a-known-good-passphrase'), 'sanity: the password works');

    db()->exec('DELETE FROM login_attempts');
    for ($i = 0; $i < LOGIN_MAX_FAILURES; $i++) {
        login_record_attempt('testuser', false);
    }

    is_false(login_attempt('testuser', 'a-known-good-passphrase'), 'lockout must beat a correct password');

    db()->exec('DELETE FROM login_attempts');
});

test('an unknown username is rejected without a PHP error', function (): void {
    db()->exec('DELETE FROM login_attempts');
    $_SERVER['REMOTE_ADDR'] = '203.0.113.9';

    is_false(login_attempt('no-such-user', 'whatever'),
        'a missing row must return false, not warn about array access on bool');

    db()->exec('DELETE FROM login_attempts');
    db()->exec("DELETE FROM admin_users WHERE username = 'testuser'");
});
