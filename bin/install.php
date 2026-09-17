<?php

declare(strict_types=1);

/**
 * One-off installer: create the tables, load the catalogue, make an admin user.
 *
 * Safe to re-run. The schema uses CREATE TABLE IF NOT EXISTS and the seed uses
 * INSERT IGNORE, so running it again will not overwrite prices the shop has
 * since edited.
 *
 *   php bin/install.php [username]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('SANATEC', true);
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/Auth.php';

$pdo = db();

echo "Database: ", cfg('db')['name'] ?? '?', " on ", cfg('db')['host'] ?? '?', "\n\n";

foreach (['schema' => 'schema.sql', 'seed' => 'seed.sql'] as $label => $file) {
    $sql = file_get_contents(__DIR__ . '/../db/' . $file);
    if ($sql === false) {
        fwrite(STDERR, "Cannot read db/{$file}\n");
        exit(1);
    }
    $pdo->exec($sql);
    echo "Applied db/{$file}\n";
}

$counts = [];
foreach (['courses', 'routes', 'settings'] as $table) {
    $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}
echo "Rows: courses={$counts['courses']} routes={$counts['routes']} settings={$counts['settings']}\n\n";

$username = $argv[1] ?? 'owner';
$exists = $pdo->prepare('SELECT id FROM admin_users WHERE username = :u');
$exists->execute([':u' => $username]);

if ($exists->fetchColumn() !== false) {
    echo "Admin user '{$username}' already exists — password left unchanged.\n";
    exit(0);
}

// A generated passphrase: long enough to be safe, short enough to retype once.
$words = ['cenote', 'halocline', 'sidemount', 'angelita', 'dreamgate', 'stalactite',
          'nitrox', 'chikinha', 'yaakun', 'ponderosa', 'carwash', 'manatee'];
$password = implode('-', [
    $words[random_int(0, count($words) - 1)],
    $words[random_int(0, count($words) - 1)],
    $words[random_int(0, count($words) - 1)],
    (string) random_int(100, 999),
]);

$pdo->prepare(
    'INSERT INTO admin_users (username, password_hash, display_name, must_change_password)
     VALUES (:u, :h, :d, 1)'
)->execute([
    ':u' => $username,
    ':h' => password_hash($password, PASSWORD_DEFAULT),
    ':d' => 'SanaTec Diving',
]);

echo "Created admin user.\n\n";
echo "  username: {$username}\n";
echo "  password: {$password}\n\n";
echo "This is the only time the password is shown. Sign in at /admin/ and change it.\n";
