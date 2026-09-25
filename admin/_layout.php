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
<?php elseif ($user !== null): ?>
<div class="st-shell" style="display:block">
  <div class="st-main" style="max-width:720px;margin:0 auto">
    <header class="st-appbar">
      <?php if (!empty($opts['back'])): ?><a class="st-appbar__back st-iconbtn" href="<?= e($opts['back']) ?>" aria-label="Back"><?= ui_icon('back') ?></a>
      <?php else: ?><a href="/my/"><?= $wordmark('st-appbar__wm') ?></a><?php endif; ?>
      <div class="st-appbar__title"><?= e($title) ?></div>
      <div class="st-appbar__actions"><?= $themeBtn ?>
        <button type="button" class="st-iconbtn" onclick="document.getElementById('st-sheet').hidden=false" aria-label="Menu"><?= ui_icon('menu') ?></button></div>
    </header>
    <div id="st-sheet" class="st-sheet" hidden>
      <button type="button" onclick="document.getElementById('st-sheet').hidden=true"><?= ui_icon('x') ?><?= $lang === 'es' ? 'Cerrar' : 'Close' ?></button>
      <a href="/my/"><?= ui_icon('list') ?><?= $lang === 'es' ? 'Mis documentos' : 'My documents' ?></a>
      <a href="/my/passport.php"><?= ui_icon('wave') ?><?= $lang === 'es' ? 'Mi pasaporte' : 'My passport' ?></a>
      <a href="/my/profile.php"><?= ui_icon('user') ?><?= $lang === 'es' ? 'Mi información' : 'My information' ?></a>
      <a href="/my/lang.php?lang=<?= $lang === 'es' ? 'en' : 'es' ?>"><?= ui_icon('globe') ?><?= $lang === 'es' ? 'English' : 'Español' ?></a>
      <a href="/my/logout.php"><?= ui_icon('logout') ?><?= $lang === 'es' ? 'Salir' : 'Sign out' ?></a>
    </div>
<?php else: ?>
<div class="st-shell" style="display:block">
  <div class="st-main" style="max-width:720px;margin:0 auto">
<?php endif; ?>
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
