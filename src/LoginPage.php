<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/Onboarding.php';

/**
 * The sign-in page, shared by the admin (/admin/login.php) and the diver area
 * (/my/login.php). Same passwordless flow; the diver area additionally lets a
 * new diver create their account, which begins with consent to the privacy
 * notice — nothing personal is stored before that box is ticked.
 *
 * Expects: start_session() done, csrf helpers loaded, $area = 'admin'|'diver'.
 */
function login_page(string $area): void
{
    $isDiver = $area === 'diver';
    $home = $isDiver ? '/my/' : '/admin/';
    $selfUrl = $isDiver ? '/my/login.php' : '/admin/login.php';
    $user = current_user();

    if ($user !== null && ($isDiver || $user['team'] !== null)) {
        header('Location: ' . $home);
        exit;
    }

    $next = (string) ($_GET['next'] ?? $_POST['next'] ?? $home);
    if (!preg_match('#^/[A-Za-z0-9._/?=&%-]*$#', $next) || str_starts_with($next, '//')) {
        $next = $home;
    }
    $lang = normalize_lang($_GET['lang'] ?? $_POST['lang'] ?? ($_SESSION['lang'] ?? 'en'));
    $_SESSION['lang'] = $lang;
    $t = static fn (string $en, string $es): string => $lang === 'es' ? $es : $en;

    $error = null;
    $info = null;
    $pending = $_SESSION['login_pending'] ?? null;
    $registering = false;
    $reg = ['name' => '', 'identifier' => ''];

    $linkToken = null;
    $rawLink = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
    if ($rawLink !== '') {
        $linkToken = login_link_peek($rawLink);
        if ($linkToken === null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $error = $t('That link has expired or was already used. Ask for a new one.', 'Ese enlace caducó o ya se usó. Pide uno nuevo.');
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $step = post('step');

        if ($step === 'confirm_link') {
            $person = login_with_link($rawLink, isset($_POST['remember']));
            if ($person !== null) {
                unset($_SESSION['login_pending']);
                audit('login', 'session', $person['id'], 'via link');
                header('Location: ' . $next);
                exit;
            }
            $error = $t('That link has expired or was already used.', 'Ese enlace caducó o ya se usó.');
            $linkToken = null;
        } elseif ($step === 'reject_link') {
            login_link_reject($rawLink);
            $linkToken = null;
            $error = $t('That link has been cancelled.', 'Ese enlace fue cancelado.');
        } elseif ($step === 'begin' || $step === 'email_instead') {
            $identifier = $step === 'email_instead' ? (string) ($pending['identifier'] ?? '') : post('identifier');
            $result = login_begin($identifier, $step === 'email_instead' ? 'email' : null);
            if ($result['ok']) {
                $_SESSION['login_pending'] = $result + ['identifier' => $identifier];
                header('Location: ' . $selfUrl . '?next=' . rawurlencode($next));
                exit;
            }
            if ($result['reason'] === 'unknown' && $isDiver) {
                $registering = true;                   // new diver: offer to create the account
                $reg['identifier'] = $identifier;
            } elseif ($result['reason'] === 'unknown') {
                $_SESSION['login_pending'] = ['token_id' => 0, 'transport' => 'email', 'to' => $t('the address you gave', 'la dirección indicada'), 'can_email' => false, 'identifier' => $identifier];
                header('Location: ' . $selfUrl . '?next=' . rawurlencode($next));
                exit;
            } else {
                $error = match ($result['reason']) {
                    'locked' => $t('Too many attempts from this connection. Wait fifteen minutes.', 'Demasiados intentos desde esta conexión. Espera quince minutos.'),
                    default  => $t('We could not send a code right now. Ask the shop for a sign-in link.', 'No pudimos enviar un código ahora. Pide al centro un enlace de acceso.'),
                };
            }
        } elseif ($step === 'register' && $isDiver) {
            $reg = ['name' => post('name'), 'identifier' => post('identifier')];
            $registering = true;
            $kind = str_contains($reg['identifier'], '@') ? 'email' : 'mobile';
            if (!isset($_POST['consent'])) {
                $error = $t('Please read and accept the privacy notice to continue.', 'Lee y acepta el aviso de privacidad para continuar.');
            } elseif (trim($reg['name']) === '') {
                $error = $t('Please tell us your name.', 'Dinos tu nombre.');
            } elseif (normalize_channel($kind, $reg['identifier']) === null) {
                $error = $t('That does not look like an email address or a mobile number with country code.', 'Eso no parece un correo ni un número de móvil con código de país.');
            } elseif (login_identify($reg['identifier']) !== null) {
                $registering = false;
                $error = $t('That address is already registered — sign in instead.', 'Esa dirección ya está registrada: inicia sesión.');
            } else {
                $pid = person_create($reg['name'], ['preferred_language' => $lang]);
                $cid = channel_upsert($pid, $kind, $reg['identifier'], ['primary' => true]);
                privacy_consent_record($pid, $cid);
                customer_for_person($pid);
                audit('register', 'customer', $pid, $reg['name']);
                $result = login_begin($reg['identifier']);
                if ($result['ok']) {
                    $_SESSION['login_pending'] = $result + ['identifier' => $reg['identifier']];
                    header('Location: ' . $selfUrl . '?next=' . rawurlencode($next));
                    exit;
                }
                $registering = false;
                $info = $t('Your account is created, but we could not send a code right now. Ask the shop for a sign-in link.',
                           'Tu cuenta está creada, pero no pudimos enviar un código ahora. Pide al centro un enlace de acceso.');
            }
        } elseif ($step === 'verify' && $pending) {
            $person = login_verify((int) $pending['token_id'], post('code'), isset($_POST['remember']));
            if ($person !== null) {
                unset($_SESSION['login_pending']);
                audit('login', 'session', $person['id'], 'via ' . $pending['transport']);
                header('Location: ' . $next);
                exit;
            }
            $error = $t('That code is not right, or has expired.', 'Ese código no es correcto o ya caducó.');
        } elseif ($step === 'restart') {
            unset($_SESSION['login_pending']);
            header('Location: ' . $selfUrl . '?next=' . rawurlencode($next));
            exit;
        }
    }

    $transportLabel = ['email' => 'email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'log' => 'the server log'];
    $hidden = static fn () => csrf_field() . '<input type="hidden" name="next" value="' . e($next) . '"><input type="hidden" name="lang" value="' . e($lang) . '">';

    shell_start($t('Sign in', 'Entrar'), null, $isDiver ? 'diver' : 'admin');
    ?>
<div class="row justify-content-center">
  <div class="col-12 col-sm-8 col-md-6 col-lg-4 mt-4">
    <?php if ($isDiver): ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <span class="text-secondary small"><?= e(setting('business_name') ?: 'SanaTec Diving') ?></span>
      <span class="small"><a href="?lang=en" class="<?= $lang === 'en' ? 'fw-bold' : 'text-secondary' ?>">EN</a> · <a href="?lang=es" class="<?= $lang === 'es' ? 'fw-bold' : 'text-secondary' ?>">ES</a></span>
    </div>
    <?php endif; ?>
    <h1 class="h3 mb-1"><?= $registering ? e($t('Create your account', 'Crea tu cuenta')) : e($t('Sign in', 'Entrar')) ?></h1>
    <?php if ($info): ?><div class="alert alert-info"><?= e($info) ?></div><?php endif; ?>

    <?php if ($linkToken !== null): ?>
      <p class="text-secondary mb-4"><?= e($t("Confirm it's you before we sign you in.", 'Confirma que eres tú antes de entrar.')) ?></p>
      <?php if ($error): ?><div class="alert alert-warning"><?= e($error) ?></div><?php endif; ?>
      <div class="card"><div class="card-body">
        <p class="mb-1 text-secondary small"><?= e($t('Signing in as', 'Entrando como')) ?></p>
        <p class="fs-5 fw-semibold mb-3"><i class="fa-solid fa-user text-aqua me-2"></i><?= e($linkToken['person_name']) ?></p>
        <form method="post"><?= $hidden() ?><input type="hidden" name="step" value="confirm_link"><input type="hidden" name="t" value="<?= e($rawLink) ?>">
          <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" checked>
            <label class="form-check-label" for="remember"><?= e($t('Remember this device for ' . REMEMBER_DEVICE_DAYS . ' days', 'Recordar este dispositivo ' . REMEMBER_DEVICE_DAYS . ' días')) ?></label></div>
          <button class="btn btn-aqua btn-lg w-100" type="submit"><i class="fa-solid fa-right-to-bracket me-2"></i><?= e($t('Yes, sign me in', 'Sí, entrar')) ?></button></form>
        <form method="post" class="mt-3 text-center"><?= $hidden() ?><input type="hidden" name="step" value="reject_link"><input type="hidden" name="t" value="<?= e($rawLink) ?>">
          <button class="btn btn-link btn-sm text-secondary" type="submit"><?= e($t("That wasn't me — cancel this link", 'No fui yo: cancelar este enlace')) ?></button></form>
      </div></div>

    <?php elseif ($registering): ?>
      <p class="text-secondary mb-4"><?= e($t("We don't know that address yet. Tell us your name and we'll set you up — no password, just a code.", 'No conocemos esa dirección todavía. Dinos tu nombre y te damos de alta: sin contraseña, solo un código.')) ?></p>
      <?php if ($error): ?><div class="alert alert-warning"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="card"><div class="card-body"><?= $hidden() ?><input type="hidden" name="step" value="register">
        <label class="form-label" for="name"><?= e($t('Your name', 'Tu nombre')) ?></label>
        <input class="form-control form-control-lg mb-3" id="name" name="name" value="<?= e($reg['name']) ?>" autocomplete="name" required autofocus>
        <label class="form-label" for="identifier"><?= e($t('Email or mobile', 'Correo o móvil')) ?></label>
        <input class="form-control form-control-lg mb-3" id="identifier" name="identifier" value="<?= e($reg['identifier']) ?>" autocomplete="username" autocapitalize="none" required>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="consent" name="consent" value="1" required>
          <label class="form-check-label small" for="consent"><?= $t('I have read the <a href="/privacy" target="_blank">privacy notice</a> and consent to SanaTec Diving holding my details, including health information from the diver medical questionnaire.',
                                                                    'He leído el <a href="/es/privacy" target="_blank">aviso de privacidad</a> y doy mi consentimiento para que SanaTec Diving conserve mis datos, incluida la información de salud del cuestionario médico.') ?></label></div>
        <button class="btn btn-aqua btn-lg w-100" type="submit"><?= e($t('Create account and send me a code', 'Crear cuenta y enviarme un código')) ?></button>
      </div></form>
      <form method="post" class="mt-3 text-center"><?= $hidden() ?><input type="hidden" name="step" value="restart">
        <button class="btn btn-link btn-sm text-secondary" type="submit"><?= e($t('I already have an account', 'Ya tengo cuenta')) ?></button></form>

    <?php elseif ($pending === null): ?>
      <p class="text-secondary mb-4"><?= e($t("Enter your email or mobile number. We'll send you a code — no password.", 'Escribe tu correo o tu móvil. Te enviamos un código: sin contraseña.')) ?></p>
      <?php if ($error): ?><div class="alert alert-warning"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="card"><div class="card-body"><?= $hidden() ?><input type="hidden" name="step" value="begin">
        <label class="form-label" for="identifier"><?= e($t('Email or mobile', 'Correo o móvil')) ?></label>
        <input class="form-control form-control-lg mb-3" type="text" id="identifier" name="identifier" autocomplete="username" autocapitalize="none" inputmode="email" placeholder="you@example.com / +52…" autofocus required>
        <button class="btn btn-aqua btn-lg w-100" type="submit"><?= e($t('Send me a code', 'Enviarme un código')) ?></button>
      </div></form>
      <?php if ($isDiver): ?><p class="text-secondary small mt-3 text-center"><?= e($t("New here? Enter your email or mobile and we'll create your account.", '¿Eres nuevo? Escribe tu correo o móvil y creamos tu cuenta.')) ?></p><?php endif; ?>

    <?php else: ?>
      <p class="text-secondary mb-4"><?= e($t("We sent a code by " . ($transportLabel[$pending['transport']] ?? $pending['transport']) . " to", 'Enviamos un código por ' . ($transportLabel[$pending['transport']] ?? $pending['transport']) . ' a')) ?> <strong><?= e($pending['to']) ?></strong>.</p>
      <?php if ($error): ?><div class="alert alert-warning"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="card"><div class="card-body"><?= $hidden() ?><input type="hidden" name="step" value="verify">
        <label class="form-label" for="code"><?= e($t('Code', 'Código')) ?></label>
        <input class="form-control form-control-lg code-input mb-3" type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" maxlength="<?= LOGIN_CODE_DIGITS ?>" autofocus required>
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" checked>
          <label class="form-check-label" for="remember"><?= e($t('Remember this device for ' . REMEMBER_DEVICE_DAYS . ' days', 'Recordar este dispositivo ' . REMEMBER_DEVICE_DAYS . ' días')) ?></label></div>
        <button class="btn btn-aqua btn-lg w-100" type="submit"><?= e($t('Sign in', 'Entrar')) ?></button>
      </div></form>
      <div class="d-flex flex-wrap gap-3 mt-3 small">
        <?php if (!empty($pending['can_email'])): ?><form method="post" class="d-inline"><?= $hidden() ?><input type="hidden" name="step" value="email_instead">
          <button class="btn btn-link btn-sm p-0" type="submit"><i class="fa-solid fa-envelope me-1"></i><?= e($t('Send it by email instead', 'Enviarlo por correo')) ?></button></form><?php endif; ?>
        <form method="post" class="d-inline"><?= $hidden() ?><input type="hidden" name="step" value="restart">
          <button class="btn btn-link btn-sm p-0 text-secondary" type="submit"><i class="fa-solid fa-rotate-left me-1"></i><?= e($t('Start over', 'Empezar de nuevo')) ?></button></form>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
    shell_end(null, $isDiver ? 'diver' : 'admin');
}
