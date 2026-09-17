<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * Admin chrome. Deliberately plain: this is a tool for changing prices on a
 * phone between dives, not a dashboard to be admired.
 */
function admin_header(string $title, bool $signedIn = true): void
{
    $current = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · SanaTec admin</title>
<style>
:root{color-scheme:dark;--bg:#061e27;--panel:#0b2a35;--panel2:#103440;--ink:#f1f8f7;--muted:#a9c2c8;--aqua:#55dce0;--line:#274650;--warn:#e2a49a}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
a{color:var(--aqua)}
.bar{background:var(--panel);border-bottom:1px solid var(--line);padding:14px 22px;display:flex;flex-wrap:wrap;gap:14px 22px;align-items:center}
.bar .logo{font-weight:700;letter-spacing:-.5px;text-decoration:none;color:var(--ink)}
.bar .logo span{color:var(--aqua)}
.bar nav{display:flex;flex-wrap:wrap;gap:16px;font-size:14px}
.bar nav a{text-decoration:none;color:var(--muted);padding:4px 2px;border-bottom:2px solid transparent}
.bar nav a.on{color:var(--aqua);border-bottom-color:var(--aqua)}
.bar .right{margin-left:auto;display:flex;gap:16px;font-size:14px;align-items:center}
main{max-width:1060px;margin:0 auto;padding:26px 22px 80px}
h1{font-size:25px;font-weight:600;letter-spacing:-.5px;margin:0 0 4px}
h2{font-size:17px;font-weight:600;margin:32px 0 12px}
.lede{color:var(--muted);margin:0 0 24px;max-width:70ch}
.flash{padding:13px 16px;border-radius:7px;margin-bottom:18px;border:1px solid}
.flash.ok{background:#0d3b34;border-color:#1f6b5c;color:#bff3e4}
.flash.warn{background:#3b2a22;border-color:#7a5240;color:#f2d4c6}
.card{background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:22px;margin-bottom:22px}
.card.note{border-color:#7a5240;background:#2a1f1b}
.card.note strong{color:var(--warn)}
table{width:100%;border-collapse:collapse}
th,td{text-align:left;padding:11px 12px;border-bottom:1px solid var(--line);vertical-align:middle}
thead th{font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);font-weight:600}
tbody tr:hover{background:#0a2934}
td.num{font-variant-numeric:tabular-nums;white-space:nowrap}
.muted{color:var(--muted)}
.pill{display:inline-block;font-size:11px;padding:3px 9px;border-radius:99px;border:1px solid var(--line);color:var(--muted)}
.pill.live{border-color:#1f6b5c;background:#0d3b34;color:#7fe0c4}
.pill.off{border-color:#7a5240;background:#2a1f1b;color:var(--warn)}
label{display:block;font-size:13px;font-weight:600;margin:0 0 5px}
.help{font-size:12px;color:var(--muted);font-weight:400;margin:4px 0 0}
input[type=text],input[type=password],input[type=number],textarea,select{
  width:100%;background:#061e27;border:1px solid var(--line);color:var(--ink);
  border-radius:6px;padding:10px 12px;font:inherit}
input:focus,textarea:focus{outline:2px solid var(--aqua);outline-offset:1px;border-color:var(--aqua)}
textarea{min-height:92px;resize:vertical;line-height:1.5}
.field{margin-bottom:18px}
.pair{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px}
.check{display:flex;align-items:center;gap:9px;font-size:14px}
.check input{width:17px;height:17px;accent-color:var(--aqua)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:40px;padding:9px 17px;
  border-radius:6px;border:1px solid var(--line);background:#143c49;color:var(--ink);
  font:inherit;font-weight:600;cursor:pointer;text-decoration:none}
.btn:hover{background:#1b4c5c}
.btn.primary{background:var(--aqua);border-color:var(--aqua);color:#06232b}
.btn.primary:hover{background:#8af1ee}
.btn.danger{border-color:#7a5240;color:var(--warn);background:transparent}
.btn.danger:hover{background:#3b2a22}
.btn.tiny{min-height:30px;padding:3px 9px;font-size:13px;font-weight:500}
.actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:22px}
.inline{display:inline}
.order-cell{white-space:nowrap}
.order-cell form{display:inline-flex;gap:4px}
.lang-tag{font-size:10px;font-weight:700;letter-spacing:.7px;color:var(--aqua);text-transform:uppercase}
@media(max-width:700px){
  .pair{grid-template-columns:1fr}
  main{padding:20px 14px 70px}
  th,td{padding:9px 7px}
  .bar{padding:12px 14px}
}
</style>
</head>
<body>
<div class="bar">
  <a class="logo" href="/admin/">SanaTec<span>Admin</span></a>
  <?php if ($signedIn): ?>
  <nav>
    <a href="/admin/" class="<?= $current === 'index.php' ? 'on' : '' ?>">Overview</a>
    <a href="/admin/courses.php" class="<?= $current === 'courses.php' ? 'on' : '' ?>">Courses</a>
    <a href="/admin/routes.php" class="<?= $current === 'routes.php' ? 'on' : '' ?>">Cenotes</a>
    <a href="/admin/settings.php" class="<?= $current === 'settings.php' ? 'on' : '' ?>">Site content</a>
  </nav>
  <div class="right">
    <a href="/" target="_blank" rel="noopener">View site ↗</a>
    <a href="/admin/password.php">Password</a>
    <a href="/admin/logout.php">Log out</a>
  </div>
  <?php endif; ?>
</div>
<main>
<?php foreach (take_flashes() as $flash): ?>
  <div class="flash <?= e($flash['kind']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
<?php
}

function admin_footer(): void
{
    ?>
</main>
</body>
</html>
<?php
}

/** A pair of EN/ES inputs for one field. */
function field_pair(string $name, string $label, array $row, string $type = 'text', string $help = ''): void
{
    $en = (string) ($row[$name . '_en'] ?? '');
    $es = (string) ($row[$name . '_es'] ?? '');
    ?>
    <div class="field">
      <label for="<?= e($name) ?>_en"><?= e($label) ?></label>
      <?php if ($help !== ''): ?><p class="help"><?= e($help) ?></p><?php endif; ?>
      <div class="pair" style="margin-top:6px">
        <div>
          <span class="lang-tag">English</span>
          <?php if ($type === 'textarea'): ?>
            <textarea id="<?= e($name) ?>_en" name="<?= e($name) ?>_en"><?= e($en) ?></textarea>
          <?php else: ?>
            <input type="text" id="<?= e($name) ?>_en" name="<?= e($name) ?>_en" value="<?= e($en) ?>">
          <?php endif; ?>
        </div>
        <div>
          <span class="lang-tag">Español</span>
          <?php if ($type === 'textarea'): ?>
            <textarea id="<?= e($name) ?>_es" name="<?= e($name) ?>_es"><?= e($es) ?></textarea>
          <?php else: ?>
            <input type="text" id="<?= e($name) ?>_es" name="<?= e($name) ?>_es" value="<?= e($es) ?>">
          <?php endif; ?>
        </div>
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
      <label for="<?= e($name) ?>"><?= e($label) ?></label>
      <input type="text" id="<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($shown) ?>" inputmode="numeric" placeholder="—">
      <?php if ($help !== ''): ?><p class="help"><?= e($help) ?></p><?php endif; ?>
    </div>
    <?php
}
