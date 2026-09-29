<?php

declare(strict_types=1);

/** An active affiliate's logo, for the welcome strip on the public pages. */

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Affiliates.php';

$a = affiliate_find_by_code((string) ($_GET['code'] ?? ''));
if ($a === null || !$a['logo_path'] || upload_path((string) $a['logo_path']) === null) {
    http_response_code(404);
    exit;
}
send_upload((string) $a['logo_path'], '', true);
