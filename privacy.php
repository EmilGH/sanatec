<?php

declare(strict_types=1);

/** The public privacy notice, in both languages. */

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/admin/_layout.php';

$lang = normalize_lang($_GET['lang'] ?? 'en');
$version = setting('privacy_notice_version');
$business = setting('business_name') ?: 'SanaTec Diving';

function take_flashes(): array { return []; }

send_header('Content-Type: text/html; charset=utf-8');
send_header('X-Robots-Tag: noindex');
shell_start($lang === 'es' ? 'Aviso de privacidad' : 'Privacy notice', null);
?>
<div class="row justify-content-center"><div class="col-12 col-lg-8">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <a class="text-secondary small text-decoration-none" href="<?= $lang === 'es' ? '/es/' : '/' ?>"><i class="fa-solid fa-arrow-left me-1"></i><?= e($business) ?></a>
    <span class="small"><a href="/privacy" class="<?= $lang === 'en' ? 'fw-bold' : 'text-secondary' ?>">EN</a> · <a href="/es/privacy" class="<?= $lang === 'es' ? 'fw-bold' : 'text-secondary' ?>">ES</a></span>
  </div>
  <h1 class="h3 mb-1"><?= $lang === 'es' ? 'Aviso de privacidad' : 'Privacy notice' ?></h1>
  <p class="text-secondary small"><?= $lang === 'es' ? 'Versión' : 'Version' ?> <?= e($version) ?></p>
  <?php if (stripos($version, 'draft') !== false): ?><div class="alert alert-warning small"><?= $lang === 'es' ? 'Este aviso es un borrador pendiente de revisión legal.' : 'This notice is a draft pending legal review.' ?></div><?php endif; ?>
  <?php require __DIR__ . '/templates/notice_text.php'; ?>
</div></div>
<?php shell_end(null);
