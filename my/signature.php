<?php

declare(strict_types=1);

/** A diver's own signature image, for the signed state on their page. */

require __DIR__ . '/_init.php';
require_once __DIR__ . '/../src/Uploads.php';

$stmt = db()->prepare('SELECT signature_image_path FROM form_submissions WHERE id = :id AND customer_id = :c');
$stmt->execute([':id' => (int) ($_GET['id'] ?? 0), ':c' => $customer['id']]);
$path = $stmt->fetchColumn();
if (!$path) {
    http_response_code(404);
    exit;
}
send_upload((string) $path);
