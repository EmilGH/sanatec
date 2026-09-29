<?php

declare(strict_types=1);

/** Stream a customer's stored file — scan, signature or physician letter — to staff. */

require __DIR__ . '/../_init.php';
require_once __DIR__ . '/../../src/Uploads.php';

$currentUser = require_permission((string) ($_GET['kind'] ?? '') === 'affiliate_logo' ? 'can_manage_affiliates' : 'can_manage_customers');

$kind = (string) ($_GET['kind'] ?? '');
$id = (int) ($_GET['id'] ?? 0);

$relative = match ($kind) {
    'scan'      => db()->query("SELECT scan_path FROM form_submissions WHERE id = {$id}")->fetchColumn(),
    'signature' => db()->query("SELECT signature_image_path FROM form_submissions WHERE id = {$id}")->fetchColumn(),
    'physician' => db()->query("SELECT physician_document_path FROM medical_evaluations WHERE submission_id = {$id}")->fetchColumn(),
    'pdf'       => db()->query("SELECT rendered_pdf_path FROM form_submissions WHERE id = {$id}")->fetchColumn(),
    'affiliate_logo' => db()->query("SELECT logo_path FROM affiliates WHERE id = {$id}")->fetchColumn(),
    default     => false,
};

// Everything on one diver: the information form filled from the record, then every signed form.
if ($kind === 'info') {
    require_once __DIR__ . '/../../src/FormPdf.php';
    $bytes = customer_forms_pdf($id);
    $name = customer_find($id)['name'] ?? 'diver';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9]+/', '-', ascii_fold($name)) . '-forms.pdf"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: private, no-store');
    echo $bytes;
    exit;
}

if (!$relative) {
    http_response_code(404);
    exit('No file.');
}
send_upload((string) $relative);
