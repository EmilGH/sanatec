<?php

declare(strict_types=1);

/**
 * Apply pending database migrations.
 *
 *   php bin/migrate.php            apply everything pending
 *   php bin/migrate.php --status   show what is applied and what is pending
 *   php bin/migrate.php --new "add inquiries table"
 *                                  scaffold the next migration file
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Migrator.php';

$args = array_slice($argv, 1);
$command = $args[0] ?? '--run';

// --- scaffold a new migration ------------------------------------------------
if ($command === '--new') {
    $description = trim((string) ($args[1] ?? ''));
    if ($description === '') {
        fwrite(STDERR, "Usage: php bin/migrate.php --new \"short description\"\n");
        exit(1);
    }

    $existing = array_keys(migrator_available());
    $last = $existing === [] ? 0 : (int) substr((string) end($existing), 0, 4);
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $description) ?? '', '_'));
    $version = sprintf('%04d_%s', $last + 1, $slug);
    $path = MIGRATIONS_DIR . '/' . $version . '.sql';

    file_put_contents($path, <<<SQL
        -- {$description}
        --
        -- One logical change per migration. MySQL does not roll DDL back, so a file
        -- that does three things can fail having done one of them.

        SQL);

    echo "Created db/migrations/{$version}.sql\n";
    exit(0);
}

// --- report ------------------------------------------------------------------
$applied = migrator_applied();
$pending = migrator_pending();
$drifted = migrator_drifted();

if ($command === '--status') {
    echo "Database: ", cfg('db')['name'] ?? '?', "\n\n";

    foreach (migrator_available() as $version => $path) {
        if (isset($applied[$version])) {
            $mark = in_array($version, $drifted, true) ? 'CHANGED' : 'applied';
            printf("  %-9s %-44s %s\n", $mark, $version, $applied[$version]['applied_at']);
        } else {
            printf("  %-9s %s\n", 'pending', $version);
        }
    }

    echo "\n", count($applied), " applied, ", count($pending), " pending";
    echo $drifted === [] ? "\n" : ", " . count($drifted) . " changed since being applied\n";

    if ($drifted !== []) {
        echo "\nThese files were edited after they ran. The database does not match the\n";
        echo "file any more. Write a new migration rather than editing an old one.\n";
        exit(1);
    }

    exit(0);
}

// --- apply -------------------------------------------------------------------
if ($drifted !== []) {
    fwrite(STDERR, "Refusing to run: these applied migrations have been edited since:\n  "
        . implode("\n  ", $drifted) . "\nWrite a new migration instead.\n");
    exit(1);
}

if ($pending === []) {
    echo "Nothing to do — ", count($applied), " migration(s) already applied.\n";
    exit(0);
}

echo "Applying ", count($pending), " migration(s) to '", cfg('db')['name'] ?? '?', "':\n";

try {
    migrator_run(static fn (string $line) => print($line . "\n"));
} catch (RuntimeException $e) {
    fwrite(STDERR, "\n" . $e->getMessage() . "\n");
    exit(1);
}

echo "Done.\n";
