<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/** Render the privacy notice setting: blank line = paragraph, "# " = heading. */
$lang = $lang ?? 'en';
foreach (preg_split('/\R\s*\R/', setting('privacy_notice', $lang)) ?: [] as $block) {
    $block = trim($block);
    if ($block === '') {
        continue;
    }
    if (str_starts_with($block, '# ')) {
        echo '<h2 class="h6 text-aqua text-uppercase mt-3">', e(substr($block, 2)), '</h2>';
    } else {
        echo '<p>', nl2br(e($block)), '</p>';
    }
}
