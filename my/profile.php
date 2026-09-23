<?php

declare(strict_types=1);

/** The Diver Information Form, as a page the diver fills themselves. */

require __DIR__ . '/_init.php';
require_once __DIR__ . '/../src/Customers.php';

if (!privacy_consent_current((int) $currentUser['id'])) {
    redirect('/my/consent.php');
}

$personId = (int) $currentUser['id'];
$channels = person_channels($personId);
$primary = static function (string $kind) use ($channels): string {
    foreach ($channels as $c) { if ($c['kind'] === $kind) { return $c['value']; } }
    return '';
};
$ecs = emergency_contacts((int) $customer['id']);
$ec = $ecs[0] ?? [];
$certs = certifications((int) $customer['id']);
$cert = $certs[0] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        db()->beginTransaction();
        customer_save((int) $customer['id'], $_POST);

        foreach (['email', 'mobile'] as $kind) {
            $value = post($kind);
            if ($value !== '' && normalize_channel($kind, $value) !== $primary($kind)) {
                channel_upsert($personId, $kind, $value, ['primary' => true]);
            }
        }

        if (post('emergency_name') !== '' || post('emergency_phone') !== '') {
            emergency_contact_save((int) $customer['id'], isset($ec['id']) ? (int) $ec['id'] : null, [
                'name' => post('emergency_name'), 'relationship' => post('emergency_relationship'),
                'phone' => post('emergency_phone'), 'is_primary' => 1,
            ]);
        }

        if (post('cert_level_code') !== '' && post('cert_agency') !== '') {
            certification_save((int) $customer['id'], isset($cert['id']) ? (int) $cert['id'] : null, [
                'agency' => post('cert_agency'), 'level_code' => post('cert_level_code'),
                'level' => post('cert_level'), 'number' => post('cert_number'),
            ]);
        }

        // A minor names their guardian here; the guardian becomes a person we can reach.
        if (is_minor(post('date_of_birth') ?: null) && post('guardian_name') !== '' && post('guardian_contact') !== '') {
            $kind = str_contains(post('guardian_contact'), '@') ? 'email' : 'mobile';
            $g = person_find_by_channel($kind, post('guardian_contact'));
            $gid = $g ? (int) $g['id'] : person_create(post('guardian_name'));
            if (!$g) {
                channel_upsert($gid, $kind, post('guardian_contact'), ['primary' => true]);
            }
            customer_set_guardian((int) $customer['id'], post('guardian_contact'));
        }

        db()->commit();
        audit('update', 'customer', $customer['id'], 'diver information (self)');
        flash(tr('Saved.', 'Guardado.'));
        redirect('/my/');
    } catch (Throwable $e) {
        db()->rollBack();
        flash($e->getMessage(), 'warn');
        redirect('/my/profile.php');
    }
}

$row = customer_find((int) $customer['id']);
$minor = is_minor($row['date_of_birth']);

shell_start(tr('My information', 'Mi información'), $currentUser, 'diver');
?>
<h1 class="h3 mb-1"><?= e(tr('Diver information', 'Información del buceador')) ?></h1>
<p class="text-secondary mb-4"><?= e(tr('What the shop needs to plan your dives safely.', 'Lo que el centro necesita para planear tus buceos con seguridad.')) ?></p>

<form method="post"><?= csrf_field() ?>
  <div class="card mb-3"><div class="card-body">
    <h2 class="h6 text-aqua text-uppercase mb-3"><?= e(tr('Contact', 'Contacto')) ?></h2>
    <div class="row g-3">
      <div class="col-12 col-md-6"><label class="form-label" for="name"><?= e(tr('Full name', 'Nombre completo')) ?></label><input class="form-control" id="name" name="name" value="<?= e($row['name']) ?>" required autocomplete="name"></div>
      <div class="col-6 col-md-3"><label class="form-label" for="date_of_birth"><?= e(tr('Date of birth', 'Fecha de nacimiento')) ?></label><input class="form-control" type="date" id="date_of_birth" name="date_of_birth" value="<?= e((string) $row['date_of_birth']) ?>" required></div>
      <div class="col-6 col-md-3"><label class="form-label" for="nationality"><?= e(tr('Nationality', 'Nacionalidad')) ?></label><input class="form-control" id="nationality" name="nationality" value="<?= e((string) $row['nationality']) ?>" maxlength="2" pattern="[A-Za-z]{2}" placeholder="MX, US, DE" style="text-transform:uppercase"></div>
      <div class="col-12 col-md-6"><label class="form-label" for="mobile"><?= e(tr('Mobile (with country code)', 'Móvil (con código de país)')) ?></label><input class="form-control" id="mobile" name="mobile" value="<?= e($primary('mobile')) ?>" placeholder="+52 984 …" autocomplete="tel"></div>
      <div class="col-12 col-md-6"><label class="form-label" for="email"><?= e(tr('Email', 'Correo')) ?></label><input class="form-control" id="email" name="email" value="<?= e($primary('email')) ?>" autocomplete="email"></div>
      <div class="col-12"><label class="form-label" for="local_address"><?= e(tr('Hotel / address in Mexico', 'Hotel / dirección en México')) ?></label><input class="form-control" id="local_address" name="local_address" value="<?= e((string) $row['local_address']) ?>"></div>
      <input type="hidden" name="preferred_language" value="<?= e($lang) ?>">
      <input type="hidden" name="timezone" value="<?= e((string) $row['timezone']) ?>">
    </div>
  </div></div>

  <?php if ($minor): ?>
  <div class="card mb-3 border-warning"><div class="card-body">
    <h2 class="h6 text-warning text-uppercase mb-3"><?= e(tr('Parent or guardian', 'Padre, madre o tutor')) ?></h2>
    <p class="small text-secondary"><?= e(tr('Under 18: a parent or guardian signs your forms. We will send them a link.', 'Menor de 18: un padre, madre o tutor firma tus formularios. Le enviaremos un enlace.')) ?></p>
    <?php if ($row['guardian_name']): ?><p><i class="fa-solid fa-user-shield text-aqua me-1"></i><?= e($row['guardian_name']) ?></p><?php endif; ?>
    <div class="row g-3">
      <div class="col-12 col-md-6"><label class="form-label" for="guardian_name"><?= e(tr('Guardian name', 'Nombre del tutor')) ?></label><input class="form-control" id="guardian_name" name="guardian_name"></div>
      <div class="col-12 col-md-6"><label class="form-label" for="guardian_contact"><?= e(tr('Guardian email or mobile', 'Correo o móvil del tutor')) ?></label><input class="form-control" id="guardian_contact" name="guardian_contact"></div>
    </div>
  </div></div>
  <?php endif; ?>

  <div class="card mb-3"><div class="card-body">
    <h2 class="h6 text-aqua text-uppercase mb-3"><?= e(tr('Experience', 'Experiencia')) ?></h2>
    <div class="row g-3">
      <div class="col-6 col-md-3"><label class="form-label" for="cert_agency"><?= e(tr('Agency', 'Agencia')) ?></label>
        <select class="form-select form-control" id="cert_agency" name="cert_agency"><option value=""><?= e(tr('— not certified yet', '— aún sin certificar')) ?></option><?php foreach (CERT_AGENCIES as $a): ?><option <?= ($cert['agency'] ?? '') === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
      <div class="col-6 col-md-3"><label class="form-label" for="cert_level_code"><?= e(tr('Highest level', 'Nivel más alto')) ?></label>
        <select class="form-select form-control" id="cert_level_code" name="cert_level_code"><option value="">—</option><?php foreach (CERT_LEVELS as $k => [$l]): ?><option value="<?= $k ?>" <?= ($cert['level_code'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="col-12 col-md-3"><label class="form-label" for="cert_level"><?= e(tr('As the card says', 'Como dice la tarjeta')) ?></label><input class="form-control" id="cert_level" name="cert_level" value="<?= e((string) ($cert['level'] ?? '')) ?>"></div>
      <div class="col-12 col-md-3"><label class="form-label" for="cert_number"><?= e(tr('Certification number', 'Número de certificación')) ?></label><input class="form-control" id="cert_number" name="cert_number" value="<?= e((string) ($cert['number'] ?? '')) ?>"></div>
      <div class="col-4 col-md-2"><label class="form-label" for="total_dives"><?= e(tr('Total dives', 'Buceos en total')) ?></label><input class="form-control" type="number" min="0" id="total_dives" name="total_dives" value="<?= e((string) $row['total_dives']) ?>"></div>
      <div class="col-4 col-md-2"><label class="form-label" for="dives_last_year"><?= e(tr('Last year', 'Último año')) ?></label><input class="form-control" type="number" min="0" id="dives_last_year" name="dives_last_year" value="<?= e((string) $row['dives_last_year']) ?>"></div>
      <div class="col-4 col-md-3"><label class="form-label" for="last_dive_on"><?= e(tr('Last dive', 'Último buceo')) ?></label><input class="form-control" type="date" id="last_dive_on" name="last_dive_on" value="<?= e((string) $row['last_dive_on']) ?>"></div>
      <div class="col-6 col-md-3"><label class="form-label" for="dan_number"><?= e(tr('DAN number', 'Número DAN')) ?></label><input class="form-control" id="dan_number" name="dan_number" value="<?= e((string) $row['dan_number']) ?>"></div>
      <div class="col-6 col-md-2"><label class="form-label" for="dan_expires_on"><?= e(tr('DAN expires', 'DAN vence')) ?></label><input class="form-control" type="date" id="dan_expires_on" name="dan_expires_on" value="<?= e((string) $row['dan_expires_on']) ?>"></div>
    </div>
  </div></div>

  <div class="card mb-3"><div class="card-body">
    <h2 class="h6 text-aqua text-uppercase mb-3"><?= e(tr('Emergency contact', 'Contacto de emergencia')) ?></h2>
    <div class="row g-3">
      <div class="col-12 col-md-5"><label class="form-label" for="emergency_name"><?= e(tr('Name', 'Nombre')) ?></label><input class="form-control" id="emergency_name" name="emergency_name" value="<?= e((string) ($ec['name'] ?? '')) ?>" required></div>
      <div class="col-6 col-md-3"><label class="form-label" for="emergency_relationship"><?= e(tr('Relationship', 'Parentesco')) ?></label><input class="form-control" id="emergency_relationship" name="emergency_relationship" value="<?= e((string) ($ec['relationship'] ?? '')) ?>"></div>
      <div class="col-6 col-md-4"><label class="form-label" for="emergency_phone"><?= e(tr('Phone (with country code)', 'Teléfono (con código de país)')) ?></label><input class="form-control" id="emergency_phone" name="emergency_phone" value="<?= e((string) ($ec['phone'] ?? '')) ?>" placeholder="+1 …" required></div>
    </div>
  </div></div>

  <div class="card mb-3"><div class="card-body">
    <h2 class="h6 text-aqua text-uppercase mb-3"><?= e(tr('Gear sizes', 'Tallas de equipo')) ?></h2>
    <div class="row g-3">
      <?php foreach (['wetsuit_size' => tr('Wetsuit', 'Traje'), 'bcd_size' => 'BCD', 'fin_size' => tr('Fins', 'Aletas'), 'boot_size' => tr('Boots', 'Botines')] as $k => $l): ?>
        <div class="col-6 col-md-2"><label class="form-label" for="<?= $k ?>"><?= e($l) ?></label><input class="form-control" id="<?= $k ?>" name="<?= $k ?>" value="<?= e((string) $row[$k]) ?>"></div>
      <?php endforeach; ?>
      <div class="col-6 col-md-2"><label class="form-label" for="height_cm"><?= e(tr('Height cm', 'Estatura cm')) ?></label><input class="form-control" type="number" min="0" id="height_cm" name="height_cm" value="<?= e((string) $row['height_cm']) ?>"></div>
      <div class="col-6 col-md-2"><label class="form-label" for="weight_kg"><?= e(tr('Weight kg', 'Peso kg')) ?></label><input class="form-control" type="number" min="0" id="weight_kg" name="weight_kg" value="<?= e((string) $row['weight_kg']) ?>"></div>
    </div>
  </div></div>

  <button class="btn btn-aqua btn-lg" type="submit"><?= e(tr('Save', 'Guardar')) ?></button>
</form>
<?php shell_end();
