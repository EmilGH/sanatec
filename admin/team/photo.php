<?php

declare(strict_types=1);

/** A team member's photo, for signed-in staff. */

require __DIR__ . '/../_init.php';
require_once __DIR__ . '/../../src/Uploads.php';

$stmt = db()->prepare('SELECT photo_path FROM team_members WHERE id = :id');
$stmt->execute([':id' => (int) ($_GET['id'] ?? 0)]);
$path = $stmt->fetchColumn();
if (!$path) {
    http_response_code(404);
    exit;
}
send_upload((string) $path);
