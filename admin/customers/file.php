<?php

declare(strict_types=1);

/** Stream a customer's stored file — scan, signature or physician letter — to staff. */

require __DIR__ . '/../_init.php';
require_once __DIR__ . '/../../src/Uploads.php';

$currentUser = require_permission('can_manage_customers');

$kind = (string) ($_GET['kind'] ?? '');
$id = (int) ($_GET['id'] ?? 0);

$relative = match ($kind) {
    'scan'      => db()->query("SELECT scan_path FROM form_submissions WHERE id = {$id}")->fetchColumn(),
    'signature' => db()->query("SELECT signature_image_path FROM form_submissions WHERE id = {$id}")->fetchColumn(),
    'physician' => db()->query("SELECT physician_document_path FROM medical_evaluations WHERE submission_id = {$id}")->fetchColumn(),
    default     => false,
};

if (!$relative) {
    http_response_code(404);
    exit('No file.');
}
send_upload((string) $relative);
