<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * The public page, in whichever language was requested.
 *
 * @var string $lang     Current language code.
 * @var string $baseUrl  Absolute site root, no trailing slash.
 * @var array  $courses  Published courses, in display order.
 * @var array  $routes   Published cenote routes, in display order.
 */

$wa       = setting('whatsapp_number');
$tel      = setting('phone_e164');
$waHref   = 'https://wa.me/' . rawurlencode($wa) . '?text=' . rawurlencode(t('wa_prefill', $lang));
$smsHref  = 'sms:' . $tel;
$hasSpecial = (bool) array_filter($routes, static fn (array $r): bool => (bool) $r['is_special_price']);
$showIncluded = setting('included_publish') === '1'
    && (setting_lines('included_items', $lang) !== [] || setting_lines('excluded_items', $lang) !== []);

/** Route/course name in the current language, falling back to English. */
$name = static fn (array $row): string => trim((string) ($row['name_' . $lang] ?? '')) ?: (string) $row['name_en'];

/** A price cell: the amount, or an em dash when that option is not offered. */
$priceCell = static function (?string $amount, bool $marker = false): string {
    $formatted = money($amount);

    return $formatted === null
        ? '<td aria-label="—">—</td>'
        : '<td class="price">' . e($formatted) . ($marker ? '*' : '') . '</td>';
};
?>
<a class="skip" href="#main"><?= e(t('skip_to_content', $lang)) ?></a>
<div class="wrap">

<header>
  <a class="brand" href="<?= e(LANGUAGES[$lang]['path']) ?>">SanaTec<span>Diving</span></a>
  <div class="headnav">
    <nav aria-label="<?= e(t('nav_label', $lang)) ?>">
      <a href="#training"><?= e(t('nav_training', $lang)) ?></a>
      <a href="#adventures"><?= e(t('nav_adventures', $lang)) ?></a>
    </nav>
    <div class="langs" role="group" aria-label="<?= e(t('lang_label', $lang)) ?>">
      <?php foreach (LANGUAGES as $code => $meta): ?>
      <a href="<?= e($meta['path']) ?>" hreflang="<?= e($code) ?>" lang="<?= e($code) ?>"
         <?= $code === $lang ? 'aria-current="true"' : '' ?>><?= e(strtoupper($code)) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</header>

<main id="main">

  <section class="hero" aria-labelledby="hero-title">
    <div>
      <p class="eyebrow"><?= e(setting('hero_eyebrow', $lang)) ?></p>
      <h1 id="hero-title"><?= e(setting('hero_title_a', $lang)) ?><br><em><?= e(setting('hero_title_b', $lang)) ?></em></h1>
      <p class="intro"><?= e(setting('hero_intro', $lang)) ?></p>
      <div class="actions">
        <a class="button primary" href="<?= e($waHref) ?>"><?= e(t('cta_whatsapp_long', $lang)) ?> ↗</a>
        <a class="button" href="<?= e($smsHref) ?>"><?= e(t('cta_sms_long', $lang)) ?></a>
      </div>
    </div>
    <div class="visual">
      <div class="brand-image">
        <img src="/assets/training.jpeg" width="738" height="1600" alt="<?= e(t('hero_image_alt', $lang)) ?>" fetchpriority="high">
      </div>
      <div class="visual-note">
        <span><?= e(t('visual_note_a', $lang)) ?></span>
        <strong><?= e(t('visual_note_b', $lang)) ?></strong>
      </div>
    </div>
  </section>

  <section id="training" class="section" aria-labelledby="training-title">
    <div class="section-top">
      <div>
        <p class="eyebrow"><?= e(setting('training_eyebrow', $lang)) ?></p>
        <h2 id="training-title"><?= e(setting('training_title', $lang)) ?></h2>
      </div>
      <a class="source" href="/assets/training.jpeg" target="_blank" rel="noopener"><?= e(t('source_training', $lang)) ?> ↗</a>
    </div>
    <div class="table-shell">
      <table class="training">
        <caption><?= e(setting('training_caption', $lang)) ?></caption>
        <thead>
          <tr>
            <th scope="col"><?= e(t('th_course', $lang)) ?></th>
            <th scope="col"><?= e(t('th_price_mxn', $lang)) ?></th>
            <th scope="col"><?= e(t('th_duration', $lang)) ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($courses as $course): ?>
          <tr>
            <th scope="row"><?= e($name($course)) ?></th>
            <td><?= e(money($course['price_mxn']) ?? t('ask_for_pricing', $lang)) ?></td>
            <td><?= e(trim((string) ($course['duration_' . $lang] ?? '')) ?: (string) $course['duration_en']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="note"><?= e(setting('training_note', $lang)) ?></p>
  </section>

  <section id="adventures" class="section" aria-labelledby="adventure-title">
    <div class="section-top">
      <div>
        <p class="eyebrow"><?= e(setting('adventures_eyebrow', $lang)) ?></p>
        <h2 id="adventure-title"><?= e(setting('adventures_title', $lang)) ?></h2>
      </div>
      <a class="source" href="/assets/adventures.jpeg" target="_blank" rel="noopener"><?= e(t('source_adventure', $lang)) ?> ↗</a>
    </div>
    <p class="note scroll-hint" style="margin:0 0 12px"><?= e(t('scroll_hint', $lang)) ?></p>
    <div class="table-shell adventure-scroll" tabindex="0" role="region" aria-label="<?= e(t('table_region', $lang)) ?>">
      <table class="adventures">
        <caption><?= e(setting('adventures_caption', $lang)) ?></caption>
        <thead>
          <tr>
            <th scope="col"><?= e(t('th_route', $lang)) ?></th>
            <th scope="col"><?= e(t('th_1_dive', $lang)) ?></th>
            <th scope="col"><?= e(t('th_2_dives', $lang)) ?></th>
            <th scope="col"><?= e(t('th_3_dives', $lang)) ?></th>
            <th scope="col"><?= e(t('th_certification', $lang)) ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($routes as $route):
            $special = (bool) $route['is_special_price'];
            $markerUsed = false;
        ?>
          <tr>
            <th scope="row"><?= e($name($route)) ?></th>
            <?php foreach (['price_1_dive', 'price_2_dives', 'price_3_dives'] as $col):
                $useMarker = $special && !$markerUsed && $route[$col] !== null;
                $markerUsed = $markerUsed || $useMarker;
                echo $priceCell($route[$col], $useMarker);
            endforeach; ?>
            <td><?= e(trim((string) ($route['cert_' . $lang] ?? '')) ?: (string) $route['cert_en']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="note">
      <?= e(setting('adventures_legend', $lang)) ?>
      <?php if ($hasSpecial): ?><br><?= e(setting('adventures_footnote', $lang)) ?><?php endif; ?>
    </p>
  </section>

  <?php if ($showIncluded): ?>
  <section id="included" class="section" aria-label="<?= e(setting('included_title', $lang)) ?>">
    <div class="includes">
      <div class="in">
        <h3><?= e(setting('included_title', $lang)) ?></h3>
        <ul>
          <?php foreach (setting_lines('included_items', $lang) as $item): ?>
          <li><?= e($item) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="out">
        <h3 class="excluded"><?= e(setting('excluded_title', $lang)) ?></h3>
        <ul>
          <?php foreach (setting_lines('excluded_items', $lang) as $item): ?>
          <li><?= e($item) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php if (has_setting('included_note', $lang)): ?>
    <p class="note"><?= e(setting('included_note', $lang)) ?></p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="contact" aria-labelledby="contact-title">
    <div>
      <p class="eyebrow"><?= e(setting('contact_eyebrow', $lang)) ?></p>
      <h2 id="contact-title"><?= e(setting('contact_title', $lang)) ?></h2>
      <p>
        <?= e(setting('contact_text', $lang)) ?><br>
        <a href="tel:<?= e($tel) ?>"><?= e(setting('phone_display')) ?></a>
        <?php if (has_setting('contact_email')): ?>
        · <a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a>
        <?php endif; ?>
      </p>
    </div>
    <div class="actions">
      <a class="button primary" href="<?= e($waHref) ?>"><?= e(t('cta_whatsapp', $lang)) ?> ↗</a>
      <a class="button" href="<?= e($smsHref) ?>"><?= e(t('cta_sms', $lang)) ?></a>
    </div>
  </section>

  <?php if (has_setting('addr_locality') || has_setting('opening_hours')): ?>
  <section class="location" aria-label="<?= e(t('find_us', $lang)) ?>">
    <?php if (has_setting('addr_locality')): ?>
    <div>
      <h3><?= e(t('find_us', $lang)) ?></h3>
      <address>
        <?php if (has_setting('addr_street')): ?><?= e(setting('addr_street')) ?><br><?php endif; ?>
        <?= e(trim(setting('addr_postal') . ' ' . setting('addr_locality'))) ?><br>
        <?= e(setting('addr_region')) ?>
      </address>
      <?php if (has_setting('maps_url')): ?>
      <p class="note"><a href="<?= e(setting('maps_url')) ?>" target="_blank" rel="noopener"><?= e(t('directions', $lang)) ?> ↗</a></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (has_setting('opening_hours')): ?>
    <div>
      <h3><?= e(t('opening_hours', $lang)) ?></h3>
      <p><?= e(setting('opening_hours')) ?></p>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

</main>

<footer>
  <span><?= e(setting('business_name')) ?></span>
  <span><?= e(setting('footer_note', $lang)) ?></span>
</footer>
</div>

<div class="mobile-cta" aria-label="<?= e(t('cta_label', $lang)) ?>">
  <a class="button primary" href="<?= e($waHref) ?>"><?= e(t('cta_whatsapp', $lang)) ?> ↗</a>
  <a class="button" href="<?= e($smsHref) ?>"><?= e(t('cta_sms', $lang)) ?></a>
</div>
