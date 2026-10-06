<?php

declare(strict_types=1);

/** The public privacy notice and the terms and conditions, in both languages. */

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/admin/_layout.php';

$lang = normalize_lang($_GET['lang'] ?? 'en');
$version = setting('privacy_notice_version');
$termsVersion = setting('terms_version');
$es = $lang === 'es';
$business = setting('business_name') ?: 'SanaTec Diving';

function take_flashes(): array { return []; }

send_header('Content-Type: text/html; charset=utf-8');
send_header('X-Robots-Tag: noindex');
shell_start($es ? 'Privacidad, términos y políticas de cancelación' : 'Privacy, Terms & Cancellation Policies', null);
?>
<div class="row justify-content-center"><div class="col-12">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <a class="text-secondary small text-decoration-none" href="<?= $lang === 'es' ? '/es/' : '/' ?>"><i class="fa-solid fa-arrow-left me-1"></i><?= e($business) ?></a>
    <span class="small"><a href="/privacy" class="<?= $lang === 'en' ? 'fw-bold' : 'text-secondary' ?>">EN</a> · <a href="/es/privacy" class="<?= $lang === 'es' ? 'fw-bold' : 'text-secondary' ?>">ES</a></span>
  </div>
  <h1 class="h3 mb-1"><?= $es ? 'Privacidad, términos y políticas de cancelación' : 'Privacy, Terms & Cancellation Policies' ?></h1>
  <p class="text-secondary small mb-4"><a class="st-link" href="#privacy"><?= $es ? 'Aviso de privacidad' : 'Privacy notice' ?></a> · <a class="st-link" href="#terms"><?= $es ? 'Términos, condiciones y cancelaciones' : 'Terms, conditions and cancellations' ?></a></p>

  <section id="privacy" class="mb-5">
    <h2 class="h4 mb-1"><?= $es ? 'Aviso de privacidad' : 'Privacy notice' ?></h2>
    <p class="text-secondary small"><?= $es ? 'Versión' : 'Version' ?> <?= e($version) ?></p>
    <?php if (stripos($version, 'draft') !== false): ?><div class="alert alert-warning small"><?= $es ? 'Este aviso es un borrador pendiente de revisión legal.' : 'This notice is a draft pending legal review.' ?></div><?php endif; ?>
    <?php $noticeKey = 'privacy_notice'; require __DIR__ . '/templates/notice_text.php'; ?>
  </section>

  <section id="terms" class="mb-4">
    <h2 class="h4 mb-1"><?= $es ? 'Términos y condiciones' : 'Terms and conditions' ?></h2>
    <p class="text-secondary small"><?= $es ? 'Incluye la política de cambios y cancelaciones.' : 'Including the changes and cancellation policy.' ?><?= $termsVersion !== '' ? ' · ' . ($es ? 'Versión' : 'Version') . ' ' . e($termsVersion) : '' ?></p>
    <?php $noticeKey = 'terms_conditions'; require __DIR__ . '/templates/notice_text.php'; ?>
  </section>
</div></div>
<?php shell_end(null);
