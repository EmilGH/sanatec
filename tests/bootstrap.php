<?php

declare(strict_types=1);

/**
 * Test harness.
 *
 * No PHPUnit, because the project has no package manager and adding one to run
 * a few dozen assertions would cost more than it returns. This is the smallest
 * thing that fails loudly.
 *
 * Tests run against a throwaway database built from db/migrations and
 * db/seed.sql, dropped and recreated on every run, so they never touch real
 * data and never depend on what the shop has edited.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

defined('SANATEC') || define('SANATEC', true);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Migrator.php';
require_once __DIR__ . '/../src/Auth.php';

// Before the runner prints anything: PHP refuses to start a session once output
// has begun, and the auth tests need a real one to exercise id rotation.
start_session();

// --- assertions --------------------------------------------------------------

final class TestFailure extends RuntimeException {}

$GLOBALS['sanatec_tests'] = [];
$GLOBALS['sanatec_assertions'] = 0;

function test(string $name, callable $body): void
{
    $GLOBALS['sanatec_tests'][] = ['name' => $name, 'body' => $body];
}

function bump(): void
{
    $GLOBALS['sanatec_assertions']++;
}

function fail(string $message): never
{
    throw new TestFailure($message);
}

function is_same(mixed $expected, mixed $actual, string $what = ''): void
{
    bump();
    if ($expected !== $actual) {
        fail(sprintf(
            "%s\n      expected: %s\n      actual:   %s",
            $what !== '' ? $what : 'values differ',
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function is_true(mixed $actual, string $what = 'expected true'): void
{
    bump();
    if ($actual !== true) {
        fail($what . "\n      got: " . var_export($actual, true));
    }
}

function is_false(mixed $actual, string $what = 'expected false'): void
{
    bump();
    if ($actual !== false) {
        fail($what . "\n      got: " . var_export($actual, true));
    }
}

function has(string $needle, string $haystack, string $what = ''): void
{
    bump();
    if (!str_contains($haystack, $needle)) {
        fail(($what !== '' ? $what : 'missing substring') . "\n      looked for: " . $needle);
    }
}

function has_not(string $needle, string $haystack, string $what = ''): void
{
    bump();
    if (str_contains($haystack, $needle)) {
        fail(($what !== '' ? $what : 'unexpected substring') . "\n      found: " . $needle);
    }
}

function throws(callable $body, string $what = 'expected an exception'): void
{
    bump();
    try {
        $body();
    } catch (Throwable) {
        return;
    }
    fail($what);
}

// --- the throwaway database ---------------------------------------------------

/**
 * Build a clean test database and point the shared PDO handle at it.
 *
 * Called once per run, before any test. The name is the configured database
 * with _test appended; the grant for it is created by bin/setup-test-db.sh.
 */
function test_db_reset(): string
{
    $db = cfg('db', []);
    $name = ($db['name'] ?? 'sanatec') . '_test';

    if (str_ends_with((string) ($db['name'] ?? ''), '_test')) {
        fwrite(STDERR, "Refusing to run: configured database is already a _test database.\n");
        exit(1);
    }

    // Connect without selecting a database so the test one can be recreated.
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'] ?? '127.0.0.1', (int) ($db['port'] ?? 3306));

    try {
        $root = new PDO($dsn, $db['user'] ?? '', $db['pass'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $root->exec("DROP DATABASE IF EXISTS `{$name}`");
        $root->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        fwrite(STDERR, "Cannot create the test database `{$name}`.\n");
        fwrite(STDERR, "  " . $e->getMessage() . "\n\n");
        fwrite(STDERR, "The database user needs rights on it. On the server:\n");
        fwrite(STDERR, "  sudo mysql -e \"GRANT ALL ON \\`{$name}\\`.* TO 'sanatec'@'localhost';\"\n");
        exit(1);
    }

    // Repoint the config the rest of the code reads, then drop the live handle
    // so the next db() call connects to the test database.
    $config = cfg_all();
    $config['db']['name'] = $name;
    $config['mail_transport'] = 'log';   // nothing leaves the box during a test run
    $config['uploads_dir'] = sys_get_temp_dir() . '/sanatec-test-uploads';   // never the real files
    cfg_all($config);
    @mkdir($config['uploads_dir'], 0700, true);
    ini_set('error_log', sys_get_temp_dir() . '/sanatec-tests.log');   // keep the log transport out of the report
    db(true);

    migrator_run();
    db()->exec((string) file_get_contents(__DIR__ . '/../db/seed.sql'));

    return $name;
}
