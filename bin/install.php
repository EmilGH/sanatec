<?php

declare(strict_types=1);

/**
 * One-off installer: create the tables, load the catalogue, make an admin user.
 *
 * Safe to re-run. The schema uses CREATE TABLE IF NOT EXISTS and the seed uses
 * INSERT IGNORE, so running it again will not overwrite prices the shop has
 * since edited.
 *
 *   php bin/install.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Migrator.php';

$pdo = db();

echo "Database: ", cfg('db')['name'] ?? '?', " on ", cfg('db')['host'] ?? '?', "\n\n";

// Schema comes from db/migrations via the same runner bin/migrate.php uses, so
// a fresh install and an upgrade take exactly the same path through the code.
$applied = migrator_run(static fn (string $line) => print($line . "\n"));
echo $applied === [] ? "Schema already up to date\n" : count($applied) . " migration(s) applied\n";

$seed = file_get_contents(__DIR__ . '/../db/seed.sql');
if ($seed === false) {
    fwrite(STDERR, "Cannot read db/seed.sql\n");
    exit(1);
}
$pdo->exec($seed);
echo "Applied db/seed.sql\n";

$counts = [];
foreach (['courses', 'excursions', 'settings'] as $table) {
    $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}
echo "Rows: courses={$counts['courses']} excursions={$counts['excursions']} settings={$counts['settings']}\n\n";

echo "Next: set the System Administrator's identity and contact channels:\n";
echo "  php bin/sysadmin.php --name \"Full Name\" --email you@example.com --mobile +1... [--whatsapp]\n";
echo "Then sign in at /admin/ with a code, or issue a link: php bin/login-link.php you@example.com\n";
