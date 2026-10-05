<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../templates/ui/brand.php';

/**
 * The application shell — Tide Line.
 *
 * One function pair wraps every admin and diver page. The sidebar, app bar,
 * tab bar, theme switch and flash messages are defined here and nowhere
 * else, so a change to the navigation is one change.
 *
 *   shell_start('Customers', $user);                       admin
 *   shell_start('My documents', $user, 'diver');           diver area
 *   shell_start('Privacy notice', null);                   bare: no navigation
 *
 * $opts: 'back' => url (phone app bar), 'actions' => html (app bar right side).
 */

/** Admin sections in nav order: label, href, icon, permission (null = everyone), on the phone tab bar. */
function admin_sections(): array
{
    return [
        ['Overview',      '/admin/',                       'home',     null,                    true],
        ['Excursions',    '/admin/excursions/',            'wave',     'can_manage_excursions', true],
        ['Training',      '/admin/training/',              'cap',      'can_manage_training',   true],
        ['Customers',     '/admin/customers/',             'users',    'can_manage_customers',  true],
        ['Team',          '/admin/team/',                  'badge',    'can_manage_team',       false],
        ['Catalog',       '/admin/catalog-courses.php',    'tag',      'can_manage_catalog',    false],
        ['Business info', '/admin/settings.php',           'store',    'can_manage_catalog',    false],
    ];
}

function shell_is_current(string $href, string $current): bool
{
    if ($href === '/admin/') {
        return $current === '/admin/index.php' || $current === '/admin/';
    }
    if (str_ends_with($href, '.php')) {
        return $current === $href || ($href === '/admin/catalog-courses.php' && str_starts_with($current, '/admin/catalog-'));
    }

    return str_starts_with($current, $href);
}

function shell_start(string $title, ?array $user = null, string $area = 'admin', array $opts = []): void
{
    $current = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $business = setting('business_name') ?: 'SANA TEC DIVING';
    $isDiver = $area === 'diver';
    $defaultTheme = $isDiver || $user === null ? 'dark' : 'light';       // Daylight is the admin default
    $lang = $user['preferred_language'] ?? 'en';
    $v = static fn (string $f): string => (string) @filemtime(SANATEC_ROOT . '/assets/' . $f);
    $flashes = function_exists('take_flashes') ? take_flashes() : [];
    ?><!doctype html>
<html lang="<?= $isDiver ? e($lang) : 'en' ?>" data-theme="<?= $defaultTheme ?>" data-bs-theme="<?= $defaultTheme ?>" data-default-theme="<?= $defaultTheme ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#04263a" media="(prefers-color-scheme: dark)">
<meta name="theme-color" content="#f5f8f9" media="(prefers-color-scheme: light)">
<title><?= e($title) ?> · <?= e($business) ?></title>
<?php if (!empty($opts['og'])): $og = $opts['og']; ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($business) ?>">
<meta property="og:title" content="<?= e($og['title']) ?>">
<meta property="og:description" content="<?= e($og['description'] ?? '') ?>">
<meta property="og:url" content="<?= e($og['url'] ?? '') ?>">
<meta property="og:image" content="<?= e($og['image'] ?? '') ?>">
<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<meta name="description" content="<?= e($og['description'] ?? '') ?>">
<?php endif; ?>
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/assets/brand/apple-touch-icon.png">
<script>
/* Theme before first paint: the phone's preference, or the area's default when it has none; a manual choice sticks per device. */
(function(){var r=document.documentElement,k='st-theme',d=r.getAttribute('data-default-theme'),s=null;try{s=localStorage.getItem(k)}catch(e){}
var mq=window.matchMedia(d==='light'?'(prefers-color-scheme: dark)':'(prefers-color-scheme: light)');
function ap(t){r.setAttribute('data-theme',t);r.setAttribute('data-bs-theme',t)}
ap(s||(mq.matches?(d==='light'?'dark':'light'):d));
mq.addEventListener&&mq.addEventListener('change',function(e){if(!s)ap(e.matches?(d==='light'?'dark':'light'):d)});
window.stToggleTheme=function(){var n=r.getAttribute('data-theme')==='light'?'dark':'light';try{localStorage.setItem(k,n)}catch(e){}s=n;ap(n)};})();
</script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/tokens.css?v=<?= $v('css/tokens.css') ?>">
<link rel="stylesheet" href="/assets/css/bootstrap-theme.css?v=<?= $v('css/bootstrap-theme.css') ?>">
<link rel="stylesheet" href="/assets/css/components.css?v=<?= $v('css/components.css') ?>">
<link rel="stylesheet" href="/assets/css/admin.css?v=<?= $v('css/admin.css') ?>">
</head>
<body class="st-root<?= $user !== null && !$isDiver ? ' has-tabbar' : '' ?>">
<?= str_replace('id="st-wm"', 'id="st-wm-dark"', ui_brand_defs('dark')) ?>
<?= str_replace('id="st-wm"', 'id="st-wm-light"', ui_brand_defs('light')) ?>
<?php
    $wordmark = static fn (string $cls = ''): string =>
        '<svg class="st-wm st-wm-dark ' . $cls . '" viewBox="0 0 486.5 126.8" role="img" aria-label="' . e($business) . '"><use href="#st-wm-dark"/></svg>'
        . '<svg class="st-wm st-wm-light ' . $cls . '" viewBox="0 0 486.5 126.8" role="img" aria-label="' . e($business) . '"><use href="#st-wm-light"/></svg>';
    $themeBtn = '<button type="button" class="st-iconbtn" onclick="stToggleTheme()" title="Light / dark">' . ui_icon('sun', 'st-icon') . '</button>';

    if ($user !== null && !$isDiver):
        $sections = array_values(array_filter(admin_sections(), static fn (array $s): bool => $s[3] === null || can($s[3], $user)));
        $tabs = array_slice(array_filter($sections, static fn (array $s): bool => $s[4]), 0, 4);
?>
<div class="st-shell">
  <aside class="st-side">
    <a class="st-side__brand" href="/admin/"><img class="st-roundel" src="/assets/brand/roundel-512.png" width="36" height="36" alt=""><?= $wordmark() ?></a>
    <?php foreach ($sections as [$label, $href, $icon, , ]): ?>
      <a href="<?= e($href) ?>" <?= shell_is_current($href, $current) ? 'aria-current="page"' : '' ?>><?= ui_icon($icon) ?><?= e($label) ?></a>
    <?php endforeach; ?>
    <div class="st-side__foot">
      <a href="#" onclick="stToggleTheme();return false"><?= ui_icon('sun') ?>Daylight</a>
      <a href="/admin/profile.php" <?= $current === '/admin/profile.php' ? 'aria-current="page"' : '' ?>><?= ui_icon('user') ?><?= e($user['name']) ?></a>
      <a href="/admin/logout.php"><?= ui_icon('logout') ?>Sign out</a>
    </div>
  </aside>
  <div class="st-main">
    <header class="st-appbar">
      <?php if (!empty($opts['back'])): ?><a class="st-appbar__back st-iconbtn" href="<?= e($opts['back']) ?>" aria-label="Back"><?= ui_icon('back') ?></a>
      <?php else: ?><img class="st-roundel" src="/assets/brand/roundel-512.png" width="32" height="32" alt=""><?php endif; ?>
      <div class="st-appbar__title"><?= e($title) ?></div>
      <div class="st-appbar__actions"><?= $opts['actions'] ?? '' ?><?= $themeBtn ?>
        <button type="button" class="st-iconbtn" onclick="document.getElementById('st-sheet').hidden=false" aria-label="Menu"><?= ui_icon('menu') ?></button></div>
    </header>
    <div id="st-sheet" class="st-sheet" hidden>
      <button type="button" onclick="document.getElementById('st-sheet').hidden=true"><?= ui_icon('x') ?>Close</button>
      <?php foreach ($sections as [$label, $href, $icon, , ]): ?><a href="<?= e($href) ?>"><?= ui_icon($icon) ?><?= e($label) ?></a><?php endforeach; ?>
      <a href="/admin/profile.php"><?= ui_icon('user') ?><?= e($user['name']) ?></a>
      <a href="/" target="_blank" rel="noopener"><?= ui_icon('external') ?>View the site</a>
      <a href="/admin/logout.php"><?= ui_icon('logout') ?>Sign out</a>
    </div>
<?php elseif ($user !== null):
    $nav = function_exists('diver_nav') ? diver_nav() : [];
    $navKey = (string) ($opts['nav'] ?? ''); ?>
<div class="st-shell st-shell--diver" style="display:block">
  <div class="st-main" style="max-width:720px;margin:0 auto">
    <header class="st-appbar">
      <?php if (!empty($opts['back'])): ?><a class="st-appbar__back st-iconbtn" href="<?= e($opts['back']) ?>" aria-label="Back"><?= ui_icon('back') ?></a>
      <?php else: ?><a href="/my/"><?= $wordmark('st-appbar__wm') ?></a><?php endif; ?>
      <div class="st-appbar__title"><?= e($title) ?></div>
      <div class="st-appbar__actions"><?= $themeBtn ?></div>
    </header>
    <?php if ($nav !== []): ?>
    <nav class="st-tabs st-tabs--diver mb-3" aria-label="<?= $lang === 'es' ? 'Secciones' : 'Sections' ?>">
      <?php foreach ($nav as [$href, $label, $key]): ?><a href="<?= e($href) ?>" <?= $key === $navKey ? 'aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
    </nav>
    <?php endif; ?>
<?php else: ?>
<div class="st-shell" style="display:block">
  <div class="st-main" style="max-width:720px;margin:0 auto">
<?php endif; ?>
    <?php if (!empty($GLOBALS['st_notice'])): ?><div class="st-flashes"><div class="st-alert st-alert--info" role="status"><?= ui_icon('badge') ?><div><?= $GLOBALS['st_notice'] ?></div></div></div><?php endif; ?>
    <?php if ($flashes !== []): ?><div class="st-flashes">
      <?php foreach ($flashes as $f): ?><div class="st-alert st-alert--<?= $f['kind'] === 'warn' ? 'warn' : 'ok' ?>" role="alert"><?= ui_icon($f['kind'] === 'warn' ? 'warn' : 'check') ?><div><?= e($f['message']) ?></div></div><?php endforeach; ?>
    </div><?php endif; ?>
<?php
}

function shell_end(?array $user = null, string $area = 'admin'): void
{
    $current = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    ?>
  </div>
</div>
<?php if ($user !== null && ($area === 'diver' || $area === 'partner')):
    $lang = $user['preferred_language'] ?? 'en'; $base = $area === 'partner' ? '/partner' : '/my'; ?>
<footer class="st-diverfoot" style="max-width:720px;margin:0 auto">
  <?php if ($area === 'diver'): ?><a href="/my/lang.php?lang=<?= $lang === 'es' ? 'en' : 'es' ?>"><?= ui_icon('globe', 'st-icon') ?><?= $lang === 'es' ? 'English' : 'Español' ?></a><?php endif; ?>
  <a href="<?= $base ?>/logout.php"><?= ui_icon('logout', 'st-icon') ?><?= $lang === 'es' ? 'Salir' : 'Sign out' ?></a>
</footer>
<?php endif; ?>
<?php if ($user !== null && $area === 'admin'):
    $tabs = array_slice(array_values(array_filter(admin_sections(), static fn (array $s): bool => $s[4] && ($s[3] === null || can($s[3], $user)))), 0, 4); ?>
<nav class="st-tabbar" aria-label="Sections">
  <?php foreach ($tabs as [$label, $href, $icon, , ]): ?>
    <a href="<?= e($href) ?>" <?= shell_is_current($href, $current) ? 'aria-current="page"' : '' ?>><?= ui_icon($icon) ?><?= e($label) ?></a>
  <?php endforeach; ?>
  <a href="#" onclick="document.getElementById('st-sheet').hidden=false;return false"><?= ui_icon('more') ?>More</a>
</nav>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}

/** A page heading with optional eyebrow and action buttons. */
function shell_page(string $title, string $eyebrow = '', string $actions = ''): string
{
    return '<div class="st-page"><div>' . ($eyebrow !== '' ? '<p class="st-eyebrow">' . e($eyebrow) . '</p>' : '')
        . '<h1 class="st-h1">' . e($title) . '</h1></div>'
        . ($actions !== '' ? '<div class="st-page__actions">' . $actions . '</div>' : '') . '</div>';
}

/** A pair of EN/ES inputs for one field (Bootstrap markup). */
function field_pair(string $name, string $label, array $row, string $type = 'text', string $help = ''): void
{
    $en = (string) ($row[$name . '_en'] ?? '');
    $es = (string) ($row[$name . '_es'] ?? '');
    ?>
    <div class="mb-3">
      <label class="form-label" for="<?= e($name) ?>_en"><?= e($label) ?></label>
      <?php if ($help !== ''): ?><div class="form-text mb-1"><?= e($help) ?></div><?php endif; ?>
      <div class="row g-2">
        <div class="col-12 col-md-6"><span class="lang-tag">English</span>
          <?php if ($type === 'textarea'): ?><textarea class="form-control" id="<?= e($name) ?>_en" name="<?= e($name) ?>_en" rows="3"><?= e($en) ?></textarea>
          <?php else: ?><input class="form-control" type="text" id="<?= e($name) ?>_en" name="<?= e($name) ?>_en" value="<?= e($en) ?>"><?php endif; ?></div>
        <div class="col-12 col-md-6"><span class="lang-tag">Español</span>
          <?php if ($type === 'textarea'): ?><textarea class="form-control" id="<?= e($name) ?>_es" name="<?= e($name) ?>_es" rows="3"><?= e($es) ?></textarea>
          <?php else: ?><input class="form-control" type="text" id="<?= e($name) ?>_es" name="<?= e($name) ?>_es" value="<?= e($es) ?>"><?php endif; ?></div>
      </div>
    </div>
    <?php
}

/** A price input, shown blank when there is no price. */
function field_price(string $name, string $label, array $row, string $help = ''): void
{
    $value = $row[$name] ?? null;
    $shown = $value === null || $value === '' ? '' : (string) money($value);
    ?>
    <div>
      <label class="form-label" for="<?= e($name) ?>"><?= e($label) ?></label>
      <input class="form-control" type="text" id="<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($shown) ?>" inputmode="numeric" placeholder="—">
      <?php if ($help !== ''): ?><div class="form-text"><?= e($help) ?></div><?php endif; ?>
    </div>
    <?php
}

/**
 * A date the person can type (DD/MM/YYYY or YYYY-MM-DD) or pick from a
 * calendar. The text box is what is submitted; the calendar button opens a
 * native picker and copies the choice into it.
 */
function ui_date_field(string $name, ?string $value, string $label, bool $required = false, string $help = ''): void
{
    $shown = $value ? date('d/m/Y', strtotime($value)) : '';
    ?>
    <label class="form-label" for="<?= e($name) ?>"><?= e($label) ?></label>
    <div class="input-group st-datefield">
      <input class="form-control" id="<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($shown) ?>" placeholder="DD/MM/YYYY" inputmode="numeric" autocomplete="off" maxlength="10"
        pattern="\d{1,2}[/.\-]\d{1,2}[/.\-](\d{4}|\d{2})|\d{4}-\d{1,2}-\d{1,2}|\d{6}|\d{8}" <?= $required ? 'required' : '' ?>
        oninput="var d=this.value.replace(/\D/g,'').slice(0,8);if(this.value.length>=this.selectionStart){this.value=d.length>4?d.slice(0,2)+'/'+d.slice(2,4)+'/'+d.slice(4):d.length>2?d.slice(0,2)+'/'+d.slice(2):d;}">
      <input type="date" class="visually-hidden" tabindex="-1" aria-hidden="true" value="<?= e((string) $value) ?>"
        onchange="if(this.value){var p=this.value.split('-');this.previousElementSibling.value=p[2]+'/'+p[1]+'/'+p[0];}">
      <button class="btn btn-outline-secondary" type="button" aria-label="<?= e($label) ?>" title="<?= e($label) ?>"
        onclick="var d=this.previousElementSibling;try{d.showPicker()}catch(e){d.click()}"><?= ui_icon('calendar', 'st-icon') ?></button>
    </div>
    <?php if ($help !== ''): ?><div class="form-text"><?= e($help) ?></div><?php endif; ?>
    <?php
}

/**
 * The three reference-list selects, rendered the same way everywhere.
 * $opts: 'empty' => label of the empty choice ('' for none), 'required', 'class', 'id'.
 */
function ui_agency_select(string $name, ?string $value, array $opts = []): void
{
    $id = $opts['id'] ?? $name;
    echo '<select class="form-select form-control ', e($opts['class'] ?? ''), '" id="', e($id), '" name="', e($name), '"', !empty($opts['required']) ? ' required' : '', '>';
    if (($opts['empty'] ?? '—') !== '') {
        echo '<option value="">', e($opts['empty'] ?? '—'), '</option>';
    }
    foreach (agencies() as $code => $full) {
        echo '<option value="', e($code), '" title="', e($full), '"', strtoupper((string) $value) === $code ? ' selected' : '', '>', e($code === 'OTHER' ? $full : $code), '</option>';
    }
    echo '</select>';
}

function ui_certification_select(string $name, ?string $value, array $opts = []): void
{
    $lang = $opts['lang'] ?? 'en';
    $id = $opts['id'] ?? $name;
    echo '<select class="form-select form-control ', e($opts['class'] ?? ''), '" id="', e($id), '" name="', e($name), '"', !empty($opts['required']) ? ' required' : '', '>';
    if (($opts['empty'] ?? '—') !== '') {
        echo '<option value="">', e($opts['empty'] ?? '—'), '</option>';
    }
    foreach (CERTIFICATION_KINDS as $kind => $kindNames) {
        $group = array_filter(certification_levels(), static fn (array $c): bool => $c[3] === $kind);
        if ($group === []) {
            continue;
        }
        echo '<optgroup label="', e($kindNames[$lang === 'es' ? 1 : 0]), '">';
        foreach ($group as $code => $c) {
            echo '<option value="', e($code), '"', (string) $value === $code ? ' selected' : '', '>', e($c[$lang === 'es' ? 1 : 0]), '</option>';
        }
        echo '</optgroup>';
    }
    echo '</select>';
}

function ui_nationality_select(string $name, ?string $value, array $opts = []): void
{
    $lang = $opts['lang'] ?? 'en';
    $id = $opts['id'] ?? $name;
    [$first, $rest] = nationalities_ordered($lang);
    echo '<select class="form-select form-control ', e($opts['class'] ?? ''), '" id="', e($id), '" name="', e($name), '"', !empty($opts['required']) ? ' required' : '', '>';
    echo '<option value="">', e($opts['empty'] ?? '—'), '</option>';
    foreach ($first as [$code, $label]) {
        echo '<option value="', e($code), '"', strtoupper((string) $value) === $code ? ' selected' : '', '>', e($label), '</option>';
    }
    echo '<option disabled>──────────</option>';
    foreach ($rest as [$code, $label]) {
        echo '<option value="', e($code), '"', strtoupper((string) $value) === $code ? ' selected' : '', '>', e($label), '</option>';
    }
    echo '</select>';
}
