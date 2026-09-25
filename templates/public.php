<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/ui/public.php';

/**
 * The public page — Tide Line.
 *
 * @var string     $lang        Current language.
 * @var string     $baseUrl     Absolute site root, no trailing slash.
 * @var array      $courses     Published courses, display order.
 * @var array      $excursions  Published excursions, display order.
 * @var array|null $share       ['type' => 'course'|'excursion', 'row' => [...]] for /c/<slug>, else null.
 */

// A shared item leads its own section.
if ($share !== null) {
    $list = $share['type'] === 'course' ? 'courses' : 'excursions';
    usort($$list, static fn (array $a, array $b): int => ((int) $b['id'] === (int) $share['row']['id']) <=> ((int) $a['id'] === (int) $share['row']['id']));
}

$showIncluded = setting('included_publish') === '1'
    && (setting_lines('included_items', $lang) !== [] || setting_lines('excluded_items', $lang) !== []);
$hasSpecial = (bool) array_filter($excursions, static fn (array $r): bool => (bool) $r['is_special_price']);
?>
<?= ui_brand_defs('dark') ?>
<div class="st-root" data-theme="dark">
<a class="st-skip" href="#main"><?= e(t('skip_to_content', $lang)) ?></a>

<?= ui_public_header($lang) ?>

<main id="main">
  <section class="st-hero st-wrap" aria-labelledby="hero-title">
    <img class="st-hero__mark" src="/assets/brand/roundel-512.png" width="512" height="512" alt="" loading="eager" fetchpriority="high">
    <div class="st-hero__fade"></div>
    <div class="st-hero__text">
      <p class="st-eyebrow"><?= e(t('hero_kicker', $lang)) ?></p>
      <h1 class="st-display" id="hero-title"><?= e(setting('hero_title_a', $lang)) ?><em><?= e(setting('hero_title_b', $lang)) ?></em></h1>
      <p class="st-lede st-hero__lede"><?= e(setting('hero_intro', $lang)) ?></p>
      <div class="st-actions">
        <a class="st-btn st-btn--primary" href="<?= e(ui_wa_url(t('wa_prefill', $lang))) ?>"><?= ui_icon('whatsapp') ?><?= e(t('cta_whatsapp_long', $lang)) ?></a>
        <a class="st-btn st-btn--secondary" href="<?= e(ui_sms_url()) ?>"><?= ui_icon('sms') ?><?= e(t('cta_sms_long', $lang)) ?></a>
      </div>
    </div>
  </section>

  <?= ui_wave() ?>

  <?php if ($share !== null): ?>
  <p class="st-share st-wrap"><?= e(t('share_intro', $lang)) ?> <strong><?= e(ui_name($share['row'], $lang)) ?></strong></p>
  <?php endif; ?>

  <div class="st-cols st-wrap">
    <section class="st-section" id="training" aria-labelledby="training-title">
      <div class="st-section__head">
        <p class="st-eyebrow"><?= e(setting('training_eyebrow', $lang)) ?></p>
        <h2 class="st-h1" id="training-title"><?= e(setting('training_title', $lang)) ?></h2>
        <p class="st-lede"><?= e(t('courses_mxn', $lang)) ?></p>
      </div>
      <ul class="st-menu">
        <?php foreach ($courses as $c) { echo ui_menu_course($c, $lang); } ?>
      </ul>
      <p class="st-note"><?= e(setting('training_note', $lang)) ?></p>
    </section>

    <section class="st-section" id="adventures" aria-labelledby="adventure-title">
      <div class="st-section__head">
        <p class="st-eyebrow"><?= e(setting('adventures_eyebrow', $lang)) ?></p>
        <h2 class="st-h1" id="adventure-title"><?= e(setting('adventures_title', $lang)) ?></h2>
        <p class="st-lede"><?= e(t('per_diver_mxn', $lang)) ?></p>
      </div>
      <ul class="st-menu">
        <?php foreach ($excursions as $x) { echo ui_menu_excursion($x, $lang); } ?>
      </ul>
      <p class="st-note"><?= e(setting('adventures_legend', $lang)) ?><?php if ($hasSpecial): ?><br><?= e(setting('adventures_footnote', $lang)) ?><?php endif; ?></p>
    </section>
  </div>

  <?php if ($showIncluded): ?>
  <section class="st-section st-wrap" id="included" aria-label="<?= e(setting('included_title', $lang)) ?>">
    <div class="st-cols">
      <div><h3 class="st-h3 st-included__h"><?= e(setting('included_title', $lang)) ?></h3>
        <ul class="st-list st-list--in"><?php foreach (setting_lines('included_items', $lang) as $i) { echo '<li>', ui_icon('check'), e($i), '</li>'; } ?></ul></div>
      <div><h3 class="st-h3 st-included__h st-included__h--out"><?= e(setting('excluded_title', $lang)) ?></h3>
        <ul class="st-list st-list--out"><?php foreach (setting_lines('excluded_items', $lang) as $i) { echo '<li><span>×</span>', e($i), '</li>'; } ?></ul></div>
    </div>
    <?php if (has_setting('included_note', $lang)): ?><p class="st-note"><?= e(setting('included_note', $lang)) ?></p><?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="st-section st-wrap" id="contact" aria-labelledby="contact-title">
    <div class="st-section__head">
      <h2 class="st-h1" id="contact-title"><?= e(setting('contact_title', $lang)) ?></h2>
      <p class="st-lede"><?= e(setting('contact_text', $lang)) ?></p>
    </div>
    <?= ui_contact_list($lang) ?>
  </section>
</main>

<?= ui_public_footer($lang) ?>
<?= ui_ctabar($lang) ?>
</div>
