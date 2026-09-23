<?php

declare(strict_types=1);

/**
 * Set the System Administrator's identity and contact channels.
 *
 *   php bin/sysadmin.php --name "Full Name" --email you@example.com \
 *                        --mobile +12125551234 [--whatsapp]
 *
 * Run on the server, after migrations. Personal details are given here rather
 * than written into a migration, so the repository never contains them.
 * Channels set this way are marked verified: the person running this command
 * on the server is trusted to know their own phone number.
 *
 * Re-running updates the existing administrator; it never creates a second.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/People.php';
require_once __DIR__ . '/../src/Audit.php';

$opts = getopt('', ['name:', 'email:', 'mobile:', 'whatsapp']);
$name   = trim((string) ($opts['name'] ?? ''));
$email  = (string) ($opts['email'] ?? '');
$mobile = (string) ($opts['mobile'] ?? '');

if ($name === '' || $email === '' || $mobile === '') {
    fwrite(STDERR, "Usage: php bin/sysadmin.php --name \"Full Name\" --email a@b.c --mobile +1... [--whatsapp]\n");
    exit(1);
}

if (normalize_email($email) === null) {
    fwrite(STDERR, "Not a valid email address: {$email}\n");
    exit(1);
}
if (normalize_mobile($mobile) === null) {
    fwrite(STDERR, "Not a valid mobile number. Use +countrycode then digits, e.g. +12125551234\n");
    exit(1);
}

$pdo = db();
$pdo->beginTransaction();

try {
    $admin = $pdo->query(
        'SELECT t.id AS team_id, p.id AS person_id
         FROM team_members t JOIN people p ON p.id = t.person_id
         WHERE t.is_system_admin = 1 AND p.deleted_at IS NULL
         ORDER BY t.id LIMIT 1'
    )->fetch();

    if ($admin) {
        $personId = (int) $admin['person_id'];
        $pdo->prepare('UPDATE people SET name = :n WHERE id = :id')->execute([':n' => $name, ':id' => $personId]);
        $action = 'updated';
    } else {
        $personId = person_create($name);
        $pdo->prepare(
            'INSERT INTO team_members (person_id, is_system_admin, is_active,
                can_manage_customers, can_manage_excursions, can_manage_training, can_manage_catalog, can_manage_team,
                job_title)
             VALUES (:p, 1, 1, 1, 1, 1, 1, 1, :title)'
        )->execute([':p' => $personId, ':title' => 'System Administrator']);
        $action = 'created';
    }

    channel_upsert($personId, 'email', $email, ['verified' => true, 'primary' => true]);
    channel_upsert($personId, 'mobile', $mobile, [
        'verified' => true,
        'primary'  => true,
        'whatsapp' => isset($opts['whatsapp']),
    ]);

    $_SESSION['username'] = 'cli';
    audit('sysadmin', 'person', $personId, "System Administrator {$action}: {$name}");

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "System Administrator {$action}.\n\n";
printf("  person #%d  %s\n", $personId, $name);
foreach (person_channels($personId) as $c) {
    printf("  %-7s %-32s %s%s\n",
        $c['kind'], $c['value'],
        $c['verified_at'] ? 'verified' : 'unverified',
        $c['kind'] === 'mobile' ? ($c['whatsapp_capable'] ? ', WhatsApp' : ', no WhatsApp') : '');
}
