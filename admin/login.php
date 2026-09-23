<?php

declare(strict_types=1);

define('ADMIN_PUBLIC', true);
require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';

if ($currentUser !== null && $currentUser['team'] !== null) {
    redirect('/admin/');
}

/** Only ever bounce back to a path on this site. */
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '/admin/');
if (!preg_match('#^/[A-Za-z0-9._/?=&%-]*$#', $next) || str_starts_with($next, '//')) {
    $next = '/admin/';
}

$error = null;
$pending = $_SESSION['login_pending'] ?? null;   // ['token_id', 'transport', 'to', 'can_email', 'identifier']

// A one-time link from bin/login-link.php or an email.
if (isset($_GET['t'])) {
    $person = login_with_link((string) $_GET['t'], true);
    if ($person !== null) {
        unset($_SESSION['login_pending']);
        audit('login', 'session', $person['id'], 'via link');
        redirect($next);
    }
    $error = 'That link has expired or was already used. Ask for a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $step = post('step');

    if ($step === 'begin' || $step === 'email_instead') {
        $identifier = $step === 'email_instead' ? (string) ($pending['identifier'] ?? '') : post('identifier');
        $result = login_begin($identifier, $step === 'email_instead' ? 'email' : null);

        if ($result['ok']) {
            $_SESSION['login_pending'] = $result + ['identifier' => $identifier];
            redirect('/admin/login.php?next=' . rawurlencode($next));
        }

        $error = match ($result['reason']) {
            'locked'        => 'Too many attempts from this connection. Wait fifteen minutes.',
            'undeliverable' => 'We could not send a code right now. Try email, or ask for a sign-in link.',
            default         => 'If that email or number is known, a code is on its way.',
        };
        // 'unknown' is shown as if it succeeded, so the form reveals nothing.
        if ($result['reason'] === 'unknown') {
            $_SESSION['login_pending'] = ['token_id' => 0, 'transport' => 'email', 'to' => 'the address you gave', 'can_email' => false, 'identifier' => $identifier];
            redirect('/admin/login.php?next=' . rawurlencode($next));
        }
    }

    if ($step === 'verify' && $pending) {
        $person = login_verify((int) $pending['token_id'], post('code'), isset($_POST['remember']));
        if ($person !== null) {
            unset($_SESSION['login_pending']);
            audit('login', 'session', $person['id'], 'via ' . $pending['transport']);
            redirect($next);
        }
        $error = 'That code is not right, or has expired. Check it and try again.';
    }

    if ($step === 'restart') {
        unset($_SESSION['login_pending']);
        redirect('/admin/login.php?next=' . rawurlencode($next));
    }
}

$transportLabel = ['email' => 'email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'log' => 'the server log'];

shell_start('Sign in');
?>
<div class="row justify-content-center">
  <div class="col-12 col-sm-8 col-md-6 col-lg-4 mt-4">
    <h1 class="h3 mb-1">Sign in</h1>

    <?php if ($pending === null): ?>
      <p class="text-secondary mb-4">Enter your email or mobile number. We'll send you a code — no password.</p>
      <?php if ($error): ?><div class="alert alert-warning"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="card"><div class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="begin">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label class="form-label" for="identifier">Email or mobile</label>
        <input class="form-control form-control-lg mb-3" type="text" id="identifier" name="identifier"
               autocomplete="username" autocapitalize="none" inputmode="email" placeholder="you@example.com or +52…" autofocus required>
        <button class="btn btn-aqua btn-lg w-100" type="submit">Send me a code</button>
      </div></form>

    <?php else: ?>
      <p class="text-secondary mb-4">
        We sent a <?= LOGIN_CODE_DIGITS ?>-digit code by <?= e($transportLabel[$pending['transport']] ?? $pending['transport']) ?>
        to <strong><?= e($pending['to']) ?></strong>. It expires in <?= LOGIN_CODE_MINUTES ?> minutes.
      </p>
      <?php if ($error): ?><div class="alert alert-warning"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="card"><div class="card-body">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="verify">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label class="form-label" for="code">Code</label>
        <input class="form-control form-control-lg code-input mb-3" type="text" id="code" name="code"
               inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" maxlength="<?= LOGIN_CODE_DIGITS ?>" autofocus required>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" checked>
          <label class="form-check-label" for="remember">Remember this device for <?= REMEMBER_DEVICE_DAYS ?> days</label>
        </div>
        <button class="btn btn-aqua btn-lg w-100" type="submit">Sign in</button>
      </div></form>

      <div class="d-flex flex-wrap gap-3 mt-3 small">
        <?php if (!empty($pending['can_email'])): ?>
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="step" value="email_instead"><input type="hidden" name="next" value="<?= e($next) ?>">
          <button class="btn btn-link btn-sm p-0" type="submit"><i class="fa-solid fa-envelope me-1"></i>Send it by email instead</button></form>
        <?php endif; ?>
        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="step" value="restart"><input type="hidden" name="next" value="<?= e($next) ?>">
          <button class="btn btn-link btn-sm p-0 text-secondary" type="submit"><i class="fa-solid fa-rotate-left me-1"></i>Start over</button></form>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php shell_end();
