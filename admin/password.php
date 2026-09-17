<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (!password_verify((string) ($_POST['current_password'] ?? ''), $currentUser['password_hash'])) {
        $error = 'Your current password is not right.';
    } elseif ($new !== $confirm) {
        $error = 'The two new passwords do not match.';
    } elseif (($problem = password_problem($new)) !== null) {
        $error = $problem;
    } else {
        set_admin_password((int) $currentUser['id'], $new);
        audit('password_change', 'admin_user', $currentUser['id']);
        flash('Password changed.');
        redirect('/admin/');
    }
}

admin_header('Password');
?>
<h1>Change password</h1>
<p class="lede">Signed in as <strong><?= e($currentUser['username']) ?></strong>.</p>

<?php if ($error !== null): ?>
  <div class="flash warn"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" class="card" style="max-width:460px">
  <?= csrf_field() ?>
  <div class="field">
    <label for="current_password">Current password</label>
    <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
  </div>
  <div class="field">
    <label for="new_password">New password</label>
    <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>
    <p class="help">At least 12 characters. A short phrase you will remember beats a short password you will not.</p>
  </div>
  <div class="field">
    <label for="confirm_password">New password again</label>
    <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
  </div>
  <button class="btn primary" type="submit">Change password</button>
</form>
<?php admin_footer();
