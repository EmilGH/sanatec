<?php

declare(strict_types=1);

/** Sign one document: the medical questionnaire, or an acknowledgement of a PDF. */

require __DIR__ . '/_init.php';

$template = form_template_by_code((string) ($_GET['code'] ?? ''));
if ($template === null || $template['code'] === 'diver_info') {
    redirect('/my/');
}
if (!privacy_consent_current((int) $currentUser['id'])) {
    redirect('/my/consent.php');
}
$customer = customer_find((int) $customer['id']);
if (!profile_complete($customer)) {
    flash(tr('Please complete your information first.', 'Completa primero tu información.'), 'warn');
    redirect('/my/profile.php');
}
$signer = onboarding_signer($customer);
if ($signer === null) {
    flash(tr('A parent or guardian must be added before signing.', 'Hay que añadir un padre, madre o tutor antes de firmar.'), 'warn');
    redirect('/my/profile.php');
}
$signerPerson = person_find($signer['person_id']);
$isMedical = $template['code'] === 'medical';
$status = array_values(array_filter(customer_document_status((int) $customer['id']), static fn (array $d): bool => $d['template']['code'] === $template['code']))[0] ?? null;
require_once __DIR__ . '/../src/Uploads.php';
require_once __DIR__ . '/../templates/forms/documents.php';
$answers = [];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'physician_upload') {
    csrf_check();
    try {
        $sub = $status['submission'] ?? null;
        if ($sub === null || ($status['outcome'] ?? '') !== 'physician_required') {
            throw new RuntimeException(tr('Nothing to upload for.', 'No hay nada que subir.'));
        }
        $path = store_upload($_FILES['physician_form'] ?? [], 'medical', 'evaluation-' . $sub['id'] . '-' . time());
        db()->prepare('UPDATE medical_evaluations SET physician_document_path = :p, notes = CONCAT(COALESCE(notes, ""), :n) WHERE submission_id = :s')
            ->execute([':p' => $path, ':n' => 'Diver uploaded the signed form ' . date('Y-m-d H:i') . ". ", ':s' => $sub['id']]);
        audit('physician_form_uploaded', 'form_submission', $sub['id'], 'by diver');
        flash(tr("Thank you — we'll review the physician's form and confirm with you.", 'Gracias: revisaremos el formulario del médico y te confirmaremos.'));
    } catch (Throwable $e) {
        flash($e->getMessage(), 'warn');
    }
    redirect('/my/form.php?code=medical');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $answers = array_filter($_POST, static fn ($v, $k): bool => is_string($v) && preg_match('/^(q\d+|[A-G]\d+|ack_\w+)$/', $k) === 1, ARRAY_FILTER_USE_BOTH);
    try {
        if (!$isMedical && !isset($_POST['ack_read'])) {
            throw new InvalidArgumentException(tr('Please confirm you have read the document.', 'Confirma que has leído el documento.'));
        }
        $sid = form_sign($customer, $template, $answers, (string) ($_POST['signature'] ?? ''), $signer, [
            'referrer' => (string) ($_SERVER['HTTP_REFERER'] ?? ''),
            'utm'      => $_SESSION['provenance'] ?? [],
        ]);
        audit('sign', 'form_submission', $sid, $template['code'] . ' by ' . $signer['role']);
        if ($isMedical) {
            $outcome = medical_outcome($answers)['outcome'];
            flash($outcome === 'cleared'
                ? tr('Medical questionnaire signed — no physician evaluation needed.', 'Cuestionario médico firmado: no se requiere evaluación médica.')
                : tr('Signed. One or more answers mean a physician must evaluate you before you dive — see below.', 'Firmado. Una o más respuestas requieren que un médico te evalúe antes de bucear; ver abajo.'),
                $outcome === 'cleared' ? 'ok' : 'warn');
            redirect('/my/form.php?code=medical');
        }
        flash(tr('Signed. Thank you.', 'Firmado. Gracias.'));
        redirect('/my/');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$yesNo = static function (string $id, string $q, bool $star = false) use ($answers, $lang): void {
    $v = $answers[$id] ?? '';
    $n = preg_match('/^q(\d+)$/', $id, $m) ? $m[1] : $id;
    echo '<div class="st-q"><div class="st-q__t"><b>', e($n), '</b><span>', e($q), $star ? ' <span style="color:var(--warn)">*</span>' : '', '</span></div>';
    echo '<div class="st-yn">';
    foreach (['yes' => $lang === 'es' ? 'Sí' : 'Yes', 'no' => 'No'] as $val => $label) {
        $rid = $id . '_' . $val;
        echo '<label><input type="radio" name="', e($id), '" id="', e($rid), '" value="', $val, '" ', $v === $val ? 'checked' : '', ' required>', $label, '</label>';
    }
    echo '</div></div>';
};

shell_start($template['title'], $currentUser, 'diver', ['back' => '/my/']);
?>
<p class="st-eyebrow"><?= e($template['publisher']) ?> · v<?= e($template['version']) ?></p>
<h1 class="st-h1 mb-3"><?= e($template['title']) ?></h1>

<?php if ($status && $status['status'] === 'signed'): $s = $status['submission']; ?>
  <div class="st-signed mb-3"><?= ui_icon('check') ?><div><strong><?= e(tr('Signed', 'Firmado')) ?></strong> <?= e(substr($s['signed_at'], 0, 10)) ?><?= $s['expires_on'] ? ' · ' . e(tr('valid until', 'válido hasta')) . ' ' . e($s['expires_on']) : '' ?></div>
    <?php if ($s['signature_image_path']): ?><img src="/my/signature.php?id=<?= (int) $s['id'] ?>" alt="" class="st-sig-thumb"><?php endif; ?></div>
  <?php if ($isMedical && $status['outcome'] === 'physician_required'): ?>
    <div class="st-alert st-alert--warn mb-3"><?= ui_icon('warn') ?><div>
      <strong><?= e(tr('Your dive cannot go ahead until a physician has signed the evaluation form.', 'Tu buceo no puede realizarse hasta que un médico firme el formulario de evaluación.')) ?></strong>
      <?= e(tr('Please contact the shop to cancel or move your booking. Download the form, print it, have your physician complete and sign it, then upload the signed copy here or bring it with you.', 'Contacta al centro para cancelar o mover tu reserva. Descarga el formulario, imprímelo, pide a tu médico que lo complete y firme, y súbelo aquí o tráelo contigo.')) ?>
      <div class="mt-2 d-flex flex-wrap gap-2"><a class="st-btn st-btn--secondary st-btn--sm" href="/my/document.php?code=medical&physician=1" target="_blank"><?= ui_icon('file') ?><?= e(tr("Physician's evaluation form (PDF)", 'Formulario de evaluación médica (PDF)')) ?></a></div>
      <?php $ev = db()->query("SELECT physician_document_path FROM medical_evaluations WHERE submission_id = " . (int) $s['id'])->fetch(); ?>
      <?php if (!empty($ev['physician_document_path'])): ?>
        <p class="mt-2 mb-0"><?= ui_icon('check', 'st-icon') ?> <?= e(tr('Your signed form is uploaded and waiting for the shop to review it.', 'Tu formulario firmado está subido y en espera de revisión por el centro.')) ?></p>
      <?php else: ?>
        <form method="post" enctype="multipart/form-data" class="mt-3"><?= csrf_field() ?><input type="hidden" name="action" value="physician_upload">
          <label class="st-upload" for="physician_form"><?= ui_icon('upload') ?><span><b><?= e(tr('Upload the signed form', 'Subir el formulario firmado')) ?></b><br><?= e(tr('PDF or a clear photo, up to 10 MB', 'PDF o una foto clara, hasta 10 MB')) ?></span></label>
          <input class="visually-hidden" type="file" id="physician_form" name="physician_form" accept=".pdf,image/*" onchange="this.form.submit()" required>
        </form>
      <?php endif; ?>
    </div></div>
  <?php elseif ($isMedical && $status['outcome'] === 'physician_cleared'): ?>
    <div class="st-alert st-alert--ok mb-3"><?= ui_icon('check') ?><div><?= e(tr('Cleared by physician on', 'Autorizado por un médico el')) ?> <?= e((string) $s['physician_cleared_on']) ?>.</div></div>
  <?php endif; ?>
  <p class="st-muted small"><?= e(tr('You can sign again if anything has changed; the new copy replaces the old one.', 'Puedes firmar de nuevo si algo cambió; la nueva copia sustituye a la anterior.')) ?></p>
<?php endif; ?>

<?php if ($error): ?><div class="alert alert-warning"><?= e($error) ?></div><?php endif; ?>

<form method="post"><?= csrf_field() ?>
<?php if ($isMedical): ?>
  <?php if ($lang === 'es'): ?><div class="st-alert st-alert--info small mb-3"><?= ui_icon('warn') ?><div><?= e('Traducción no oficial del cuestionario DAN/WRSTC. En caso de duda prevalece la versión en inglés.') ?></div></div><?php endif; ?>
  <div class="st-card mb-3">
    <p class="st-muted small"><?= e(tr('Answer every question honestly. Questions marked * and any "yes" in a box require a physician evaluation before diving. If you are pregnant, or attempting to become pregnant, do not dive.', 'Responde con honestidad. Las preguntas marcadas con * y cualquier «sí» en un recuadro requieren evaluación médica antes de bucear. Si estás embarazada o intentando estarlo, no bucees.')) ?></p>
    <?php foreach (medical_questions() as $q): $yesNo($q['id'], $q[$lang] ?? $q['en'], $q['physician']); ?>
      <?php if ($q['box'] !== null): $box = medical_boxes()[$q['box']]; ?>
        <div class="st-followups mb-2" data-box="<?= $q['box'] ?>" data-for="<?= $q['id'] ?>">
          <div class="st-field__hint"><b><?= e(tr('Box', 'Recuadro')) ?> <?= $q['box'] ?></b> — <?= e($box[$lang] ?? $box['en']) ?></div>
          <?php foreach ($box['items'] as $i => $item): $yesNo($q['box'] . ($i + 1), $item[$lang] ?? $item['en'], true); endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div></div>
  <script>
  // A box only applies when its question is "yes". Hide it otherwise, and clear it so it is not submitted.
  document.querySelectorAll('[data-box]').forEach(function (box) {
    var q = box.getAttribute('data-for');
    function sync() {
      var yes = document.getElementById(q + '_yes').checked;
      box.style.display = yes ? '' : 'none';
      box.querySelectorAll('input[type=radio]').forEach(function (r) { r.required = yes; if (!yes) r.checked = false; });
    }
    document.querySelectorAll('input[name="' + q + '"]').forEach(function (r) { r.addEventListener('change', sync); });
    sync();
  });
  </script>
<?php else: ?>
  <?php $fills = liability_fills($customer, $template); ?>
  <div class="st-card mb-3">
    <?php if (str_starts_with($template['code'], 'liability')): ?>
      <p class="small text-secondary mb-2"><?= e(tr('Where the form says store/resort:', 'Donde el formulario dice store/resort:')) ?> <strong><?= e($fills['store_name']) ?></strong>
        <?php if ($template['code'] === 'liability'): ?><br><?= e(tr('Instructor(s):', 'Instructor(es):')) ?> <strong><?= e($fills['instructor_names'] ?: tr('assigned when your course is scheduled', 'se asignan al programar tu curso')) ?></strong><?php endif; ?>
        <?php if (!empty($fills['event_title'])): ?><br><?= e(tr('For:', 'Para:')) ?> <strong><?= e($fills['event_title']) ?></strong><?php endif; ?>
        <?php if ($template['code'] === 'liability_excursion'): ?><br><?= e(tr('Diver accident insurance:', 'Seguro de accidentes de buceo:')) ?> <strong><?= $customer['dan_number'] ? 'DAN ' . e($customer['dan_number']) : e(tr('none on file', 'ninguno registrado')) ?></strong><?php endif; ?></p>
    <?php endif; ?>
    <div class="st-doc-view" tabindex="0">
      <?= form_document_html($template['code'], [
          'participant' => $customer['name'], 'store' => $fills['store_name'], 'instructors' => $fills['instructor_names'],
          'dan' => $customer['dan_number'] ? 'YES · DAN ' . $customer['dan_number'] : '',
      ], $lang) ?>
    </div>
    <?php if (form_document_path($template)): ?><p class="small mt-2 mb-0"><a class="st-link" href="/my/document.php?code=<?= e($template['code']) ?>" target="_blank"><?= e(tr('Open the original PDF', 'Abrir el PDF original')) ?></a></p><?php endif; ?>
    <label class="st-check mt-3"><input type="checkbox" name="ack_read" value="yes" required><span class="st-box"><?= ui_icon('check') ?></span><span><?= e(tr('I have read this document in full, and I understand and agree to its terms.', 'He leído este documento en su totalidad, y entiendo y acepto sus términos.')) ?></span></label>
  </div></div>
<?php endif; ?>

  <div class="st-card mb-3">
    <h2 class="st-card__title mb-2"><?= e(tr('Your signature', 'Tu firma')) ?></h2>
    <?php if ($signer['role'] === 'guardian'): ?><p class="small text-warning"><?= e(tr('To be signed by the parent or guardian:', 'A firmar por el padre, madre o tutor:')) ?> <strong><?= e($signerPerson['name']) ?></strong></p><?php endif; ?>
    <p class="st-muted small"><?= e(tr('The date, time, network address and the page you came from are recorded with the signature.', 'Se registran la fecha, la hora, la dirección de red y la página de origen junto con la firma.')) ?></p>
    <div class="st-sign mb-2">
      <canvas id="sig"></canvas>
      <span class="st-sign__x">×</span><span class="st-sign__line"></span>
      <span class="st-sign__label"><?= e(tr('Sign with your finger', 'Firma con el dedo')) ?> · <?= e($signerPerson['name']) ?></span>
      <button type="button" class="st-sign__clear" id="sig-clear"><?= e(tr('Clear', 'Borrar')) ?></button>
    </div>
    <input type="hidden" name="signature" id="sig-data">
    <p class="st-muted small mb-3"><?= e($signerPerson['name']) ?> · <?= e(date('j M Y')) ?></p>
    <button class="st-btn st-btn--primary st-btn--block" type="submit" id="sig-submit"><?= ui_icon('pen') ?><?= e(tr('Sign document', 'Firmar documento')) ?></button>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.2.0/dist/signature_pad.umd.min.js"></script>
  <script>
  (function () {
    var canvas = document.getElementById('sig'), pad = new SignaturePad(canvas, { minWidth: 1, maxWidth: 2.5, penColor: '#0b2a35' });
    function resize() { var r = Math.max(window.devicePixelRatio || 1, 1), d = pad.toData(); canvas.width = canvas.offsetWidth * r; canvas.height = canvas.offsetHeight * r; canvas.getContext('2d').scale(r, r); pad.clear(); pad.fromData(d); }
    window.addEventListener('resize', resize); resize();
    document.getElementById('sig-clear').addEventListener('click', function () { pad.clear(); });
    canvas.closest('form').addEventListener('submit', function (ev) {
      if (pad.isEmpty()) { ev.preventDefault(); alert(<?= json_encode(tr('Please sign in the box before continuing.', 'Firma en el recuadro antes de continuar.')) ?>); return; }
      document.getElementById('sig-data').value = pad.toDataURL('image/png');
    });
  })();
  </script>

  </div></div>
</form>
<?php shell_end($currentUser, 'diver');
