<?php

declare(strict_types=1);

define('ADMIN_PUBLIC', true);
require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';

if ($currentUser !== null) {
    redirect('/admin/');
}

/** Only ever bounce back to a path inside this admin — never to another host. */
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '/admin/');
if (!preg_match('#^/admin/[A-Za-z0-9._/?=&-]*$#', $next)) {
    $next = '/admin/';
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (login_is_locked_out()) {
        $error = 'Too many failed attempts. Wait fifteen minutes and try again.';
    } elseif (login_attempt(post('username'), (string) ($_POST['password'] ?? ''))) {
        audit('login', 'session');
        redirect($next);
    } else {
        $error = 'That username and password did not match.';
    }
}

admin_header('Sign in', false);
?>
<div style="max-width:420px;margin:6vh auto 0">
  <h1>Sign in</h1>
  <p class="lede">Manage courses, cenote prices and the text on the public site.</p>

  <?php if ($error !== null): ?>
    <div class="flash warn"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <div class="field">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" autocomplete="username" autocapitalize="none" autofocus required>
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required>
    </div>
    <button class="btn primary" type="submit" style="width:100%">Sign in</button>
  </form>
</div>
<?php admin_footer();
