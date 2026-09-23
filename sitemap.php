<?php

declare(strict_types=1);

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/src/bootstrap.php';

$baseUrl  = rtrim((string) cfg('base_url', 'https://sanatecdiving.com'), '/');
$modified = gmdate('Y-m-d', catalog_last_modified());

send_header('Content-Type: application/xml; charset=utf-8');
send_header('Cache-Control: public, max-age=3600');

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
<?php foreach (LANGUAGES as $code => $meta): ?>
  <url>
    <loc><?= e(lang_url($code, $baseUrl)) ?></loc>
<?php foreach (LANGUAGES as $alt => $altMeta): ?>
    <xhtml:link rel="alternate" hreflang="<?= e($alt) ?>" href="<?= e(lang_url($alt, $baseUrl)) ?>"/>
<?php endforeach; ?>
    <lastmod><?= e($modified) ?></lastmod>
    <changefreq>monthly</changefreq>
  </url>
<?php endforeach; ?>
</urlset>
