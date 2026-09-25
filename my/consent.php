<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['consent'])) {
        privacy_consent_record((int) $currentUser['id']);
        audit('consent', 'person', $currentUser['id'], 'privacy notice v' . setting('privacy_notice_version'));
        flash(tr('Thank you.', 'Gracias.'));
        redirect('/my/');
    }
}

$version = setting('privacy_notice_version');
shell_start(tr('Privacy notice', 'Aviso de privacidad'), $currentUser, 'diver', ['back' => '/my/']);
?>
<h1 class="st-h1 mb-3"><?= e(tr('Privacy notice', 'Aviso de privacidad')) ?> <span class="text-secondary fs-6">v<?= e($version) ?></span></h1>
<?php if (stripos($version, 'draft') !== false): ?><div class="alert alert-warning small"><?= e(tr('This notice is a draft pending legal review.', 'Este aviso es un borrador pendiente de revisión legal.')) ?></div><?php endif; ?>
<div class="card mb-3"><div class="card-body" style="max-height:50vh;overflow:auto">
  <?php require __DIR__ . '/../templates/notice_text.php'; ?>
</div></div>
<?php if (privacy_consent_current((int) $currentUser['id'])): ?>
  <p class="text-success"><i class="fa-solid fa-circle-check me-1"></i><?= e(tr('You have accepted this version.', 'Has aceptado esta versión.')) ?></p>
<?php else: ?>
  <form method="post"><?= csrf_field() ?>
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="consent" name="consent" value="1" required>
      <label class="form-check-label" for="consent"><?= e(tr('I have read the notice and consent to SanaTec Diving holding my details, including health information from the diver medical questionnaire.', 'He leído el aviso y doy mi consentimiento para que SanaTec Diving conserve mis datos, incluida la información de salud del cuestionario médico.')) ?></label></div>
    <button class="st-btn st-btn--primary" type="submit"><?= e(tr('Accept and continue', 'Aceptar y continuar')) ?></button>
  </form>
<?php endif; ?>
<?php shell_end($currentUser, 'diver');
