<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * Render the privacy notice setting: a blank line starts a new paragraph and
 * a line beginning "# " is a heading, whether or not a blank line follows it.
 */
$lang = $lang ?? 'en';
foreach (preg_split('/\R\s*\R/', setting('privacy_notice', $lang)) ?: [] as $block) {
    $lines = preg_split('/\R/', trim($block)) ?: [];
    $para = [];
    $flush = static function () use (&$para): void {
        if ($para !== []) {
            echo '<p>', nl2br(e(implode("\n", $para))), '</p>';
            $para = [];
        }
    };
    foreach ($lines as $line) {
        if (str_starts_with(ltrim($line), '# ')) {
            $flush();
            echo '<h2 class="h5 mt-4 mb-2">', e(substr(ltrim($line), 2)), '</h2>';
        } elseif (trim($line) !== '') {
            $para[] = $line;
        }
    }
    $flush();
}
