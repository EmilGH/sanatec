<?php

declare(strict_types=1);

/** Stream a form's PDF to a signed-in diver. The files live outside the document root. */

require __DIR__ . '/_init.php';

$template = form_template_by_code((string) ($_GET['code'] ?? ''));
$path = $template ? form_document_path($template, isset($_GET['physician'])) : null;

if ($path === null) {
    http_response_code(404);
    exit('Document not available.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($path) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($path);
