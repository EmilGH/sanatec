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
$answers = [];
$error = null;

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
    echo '<div class="d-flex flex-column flex-md-row gap-2 py-2 border-top">';
    echo '<div class="flex-grow-1">', e($q), $star ? ' <span class="text-warning">*</span>' : '', '</div>';
    echo '<div class="btn-group btn-group-sm flex-shrink-0" role="group">';
    foreach (['yes' => $lang === 'es' ? 'Sí' : 'Yes', 'no' => 'No'] as $val => $label) {
        $rid = $id . '_' . $val;
        echo '<input type="radio" class="btn-check" name="', e($id), '" id="', e($rid), '" value="', $val, '" ', $v === $val ? 'checked' : '', ' required>';
        echo '<label class="btn btn-outline-', $val === 'yes' ? 'warning' : 'secondary', '" for="', e($rid), '">', $label, '</label>';
    }
    echo '</div></div>';
};

shell_start($template['title'], $currentUser, 'diver');
?>
<a class="small text-secondary text-decoration-none" href="/my/"><i class="fa-solid fa-arrow-left me-1"></i><?= e(tr('My documents', 'Mis documentos')) ?></a>
<h1 class="h3 mb-1"><?= e($template['title']) ?></h1>
<p class="text-secondary small mb-3"><?= e($template['publisher']) ?> · v<?= e($template['version']) ?></p>

<?php if ($status && $status['status'] === 'signed'): $s = $status['submission']; ?>
  <div class="alert alert-<?= $status['ok'] ? 'success' : 'warning' ?>">
    <i class="fa-solid fa-circle-check me-1"></i><?= e(tr('Signed on', 'Firmado el')) ?> <?= e(substr($s['signed_at'], 0, 10)) ?><?= $s['expires_on'] ? ' · ' . e(tr('valid until', 'válido hasta')) . ' ' . e($s['expires_on']) : '' ?>
    <?php if ($isMedical && $status['outcome'] === 'physician_required'): ?>
      <div class="mt-2"><strong><?= e(tr('Your dive cannot go ahead until a physician has signed the evaluation form.', 'Tu buceo no puede realizarse hasta que un médico firme el formulario de evaluación.')) ?></strong>
        <?= e(tr('Please contact the shop to cancel or move your booking. Download the form, print it, have your physician complete and sign it, and bring it with you.', 'Contacta al centro para cancelar o mover tu reserva. Descarga el formulario, imprímelo, pide a tu médico que lo complete y firme, y tráelo contigo.')) ?>
        <div class="mt-2"><a class="btn btn-sm btn-warning" href="/my/document.php?code=medical&physician=1" target="_blank"><i class="fa-solid fa-file-pdf me-1"></i><?= e(tr("Physician's evaluation form", 'Formulario de evaluación médica')) ?></a></div></div>
    <?php elseif ($isMedical && $status['outcome'] === 'physician_cleared'): ?>
      <div class="mt-1"><?= e(tr('Cleared by physician on', 'Autorizado por un médico el')) ?> <?= e((string) $s['physician_cleared_on']) ?>.</div>
    <?php endif; ?>
  </div>
  <p class="text-secondary small"><?= e(tr('You can sign again if anything has changed; the new copy replaces the old one.', 'Puedes firmar de nuevo si algo cambió; la nueva copia sustituye a la anterior.')) ?></p>
<?php endif; ?>

<?php if ($error): ?><div class="alert alert-warning"><?= e($error) ?></div><?php endif; ?>

<form method="post"><?= csrf_field() ?>
<?php if ($isMedical): ?>
  <?php if ($lang === 'es'): ?><div class="alert alert-secondary small"><?= e('Traducción no oficial del cuestionario DAN/WRSTC. En caso de duda prevalece la versión en inglés.') ?></div><?php endif; ?>
  <div class="card mb-3"><div class="card-body">
    <p class="small text-secondary"><?= e(tr('Answer every question honestly. Questions marked * and any "yes" in a box require a physician evaluation before diving. If you are pregnant, or attempting to become pregnant, do not dive.', 'Responde con honestidad. Las preguntas marcadas con * y cualquier «sí» en un recuadro requieren evaluación médica antes de bucear. Si estás embarazada o intentando estarlo, no bucees.')) ?></p>
    <?php foreach (medical_questions() as $q): $yesNo($q['id'], $q[$lang] ?? $q['en'], $q['physician']); ?>
      <?php if ($q['box'] !== null): $box = medical_boxes()[$q['box']]; ?>
        <div class="ms-md-4 mb-2 p-3 rounded" style="background:rgba(85,220,224,.06)" data-box="<?= $q['box'] ?>" data-for="<?= $q['id'] ?>">
          <div class="small fw-semibold mb-1"><?= e(tr('Box', 'Recuadro')) ?> <?= $q['box'] ?> — <?= e($box[$lang] ?? $box['en']) ?></div>
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
  <div class="card mb-3"><div class="card-body">
    <?php if (str_starts_with($template['code'], 'liability')): ?>
      <p class="small text-secondary mb-2"><?= e(tr('Where the form says store/resort:', 'Donde el formulario dice store/resort:')) ?> <strong><?= e($fills['store_name']) ?></strong>
        <?php if ($template['code'] === 'liability'): ?><br><?= e(tr('Instructor(s):', 'Instructor(es):')) ?> <strong><?= e($fills['instructor_names'] ?: tr('assigned when your course is scheduled', 'se asignan al programar tu curso')) ?></strong><?php endif; ?>
        <?php if ($template['code'] === 'liability_excursion'): ?><br><?= e(tr('Diver accident insurance:', 'Seguro de accidentes de buceo:')) ?> <strong><?= $customer['dan_number'] ? 'DAN ' . e($customer['dan_number']) : e(tr('none on file', 'ninguno registrado')) ?></strong><?php endif; ?></p>
    <?php endif; ?>
    <?php if (form_document_path($template)): ?>
      <iframe src="/my/document.php?code=<?= e($template['code']) ?>" style="width:100%;height:70vh;border:1px solid var(--st-line);border-radius:6px;background:#fff" title="<?= e($template['title']) ?>"></iframe>
      <p class="small mt-2"><a href="/my/document.php?code=<?= e($template['code']) ?>" target="_blank"><i class="fa-solid fa-up-right-from-square me-1"></i><?= e(tr('Open the document in a new tab', 'Abrir el documento en otra pestaña')) ?></a></p>
    <?php else: ?>
      <div class="alert alert-warning small"><?= e(tr('The document file is not available right now. Ask the shop.', 'El documento no está disponible ahora. Pregunta al centro.')) ?></div>
    <?php endif; ?>
    <div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="ack_read" name="ack_read" value="yes" required>
      <label class="form-check-label" for="ack_read"><?= e(tr('I have read and understood this document in full and agree to its terms.', 'He leído y entendido este documento en su totalidad y acepto sus términos.')) ?></label></div>
  </div></div>
<?php endif; ?>

  <div class="card mb-3"><div class="card-body">
    <h2 class="h6 text-aqua text-uppercase mb-2"><?= e(tr('Signature', 'Firma')) ?></h2>
    <?php if ($signer['role'] === 'guardian'): ?><p class="small text-warning"><?= e(tr('To be signed by the parent or guardian:', 'A firmar por el padre, madre o tutor:')) ?> <strong><?= e($signerPerson['name']) ?></strong></p><?php endif; ?>
    <p class="small text-secondary"><?= e(tr('Sign with your finger or mouse. The date, time, network address and the page you came from are recorded with the signature.', 'Firma con el dedo o el ratón. Se registran la fecha, la hora, la dirección de red y la página de origen junto con la firma.')) ?></p>
    <div class="position-relative mb-2">
      <canvas id="sig" style="width:100%;height:180px;background:#fff;border:1px solid var(--st-line);border-radius:6px;touch-action:none"></canvas>
      <button type="button" class="btn btn-sm btn-outline-secondary position-absolute" style="top:8px;right:8px" id="sig-clear"><i class="fa-solid fa-eraser me-1"></i><?= e(tr('Clear', 'Borrar')) ?></button>
    </div>
    <input type="hidden" name="signature" id="sig-data">
    <div class="small text-secondary mb-3"><?= e($signerPerson['name']) ?></div>
    <button class="btn btn-aqua btn-lg" type="submit" id="sig-submit"><i class="fa-solid fa-pen-nib me-2"></i><?= e(tr('Sign', 'Firmar')) ?></button>
  </div></div>
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
  <div class="d-none">
  </div></div>
</form>
<?php shell_end();
