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
        $in = $_POST;
        // Height and weight are stored in cm and kg whatever the diver typed in.
        if (($in['units'] ?? 'metric') === 'imperial') {
            $ft = (float) ($in['height_ft'] ?? 0);
            $inch = (float) ($in['height_in'] ?? 0);
            $in['height_cm'] = ($ft > 0 || $inch > 0) ? (string) (int) round(($ft * 12 + $inch) * 2.54) : '';
            $in['weight_kg'] = ($in['weight_lb'] ?? '') !== '' ? (string) (int) round((float) $in['weight_lb'] / 2.20462) : '';
        }
        customer_save((int) $customer['id'], $in);

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
        redirect($showTabs ? '/my/profile.php' : '/my/forms.php');
    } catch (Throwable $e) {
        db()->rollBack();
        flash($e->getMessage(), 'warn');
        redirect('/my/profile.php');
    }
}

$row = customer_find((int) $customer['id']) ?? $customer;
$minor = is_minor($row['date_of_birth']);

shell_start(tr('Diver Info', 'Mis datos'), $currentUser, 'diver', $showTabs ? ['nav' => 'profile'] : ['back' => '/my/forms.php']);
?>
<h1 class="st-h1 mb-1"><?= e(tr('Diver information', 'Información del buceador')) ?></h1>
<p class="st-lede mb-4"><?= e(tr('What the shop needs to plan your dives safely.', 'Lo que el centro necesita para planear tus buceos con seguridad.')) ?></p>

<form method="post"><?= csrf_field() ?>
  <div class="st-card mb-3">
    <h2 class="st-card__title mb-3"><?= e(tr('Contact', 'Contacto')) ?></h2>
    <div class="row g-3">
      <div class="col-12 col-md-6"><label class="form-label" for="name"><?= e(tr('Full name', 'Nombre completo')) ?></label><input class="form-control" id="name" name="name" value="<?= e($row['name']) ?>" required autocomplete="name"></div>
      <div class="col-7 col-md-3"><?php ui_date_field('date_of_birth', $row['date_of_birth'], tr('Date of birth', 'Fecha de nacimiento'), true); ?></div>
      <div class="col-5 col-md-3"><label class="form-label" for="nationality"><?= e(tr('Nationality', 'Nacionalidad')) ?></label>
        <?php ui_nationality_select('nationality', $row['nationality'], ['lang' => $lang]); ?></div>
      <div class="col-12 col-md-6"><label class="form-label" for="mobile"><?= e(tr('Mobile (with country code)', 'Móvil (con código de país)')) ?></label><input class="form-control" id="mobile" name="mobile" value="<?= e($primary('mobile')) ?>" placeholder="+52 984 …" autocomplete="tel"></div>
      <div class="col-12 col-md-6"><label class="form-label" for="email"><?= e(tr('Email', 'Correo')) ?></label><input class="form-control" id="email" name="email" value="<?= e($primary('email')) ?>" autocomplete="email"></div>
      <div class="col-12"><label class="form-label" for="local_address"><?= e(tr('Hotel / address in Mexico', 'Hotel / dirección en México')) ?></label><input class="form-control" id="local_address" name="local_address" value="<?= e((string) $row['local_address']) ?>"></div>
      <input type="hidden" name="preferred_language" value="<?= e($lang) ?>">
      <input type="hidden" name="timezone" value="<?= e((string) $row['timezone']) ?>">
    </div>
  </div>

  <?php if ($minor): ?>
  <div class="st-card mb-3" style="outline:1px solid var(--warn)">
    <h2 class="st-card__title mb-3" style="color:var(--warn)"><?= e(tr('Parent or guardian', 'Padre, madre o tutor')) ?></h2>
    <p class="st-muted small"><?= e(tr('Under 18: a parent or guardian signs your forms. We will send them a link.', 'Menor de 18: un padre, madre o tutor firma tus formularios. Le enviaremos un enlace.')) ?></p>
    <?php if ($row['guardian_name']): ?><p><i class="fa-solid fa-user-shield text-aqua me-1"></i><?= e($row['guardian_name']) ?></p><?php endif; ?>
    <div class="row g-3">
      <div class="col-12 col-md-6"><label class="form-label" for="guardian_name"><?= e(tr('Guardian name', 'Nombre del tutor')) ?></label><input class="form-control" id="guardian_name" name="guardian_name"></div>
      <div class="col-12 col-md-6"><label class="form-label" for="guardian_contact"><?= e(tr('Guardian email or mobile', 'Correo o móvil del tutor')) ?></label><input class="form-control" id="guardian_contact" name="guardian_contact"></div>
    </div>
  </div>
  <?php endif; ?>

  <div class="st-card mb-3">
    <h2 class="st-card__title mb-3"><?= e(tr('Highest Certification', 'Certificación más alta')) ?></h2>
    <div class="row g-3">
      <div class="col-6 col-md-3"><label class="form-label" for="cert_agency"><?= e(tr('Agency', 'Agencia')) ?></label>
        <?php ui_agency_select('cert_agency', $cert['agency'] ?? null, ['empty' => tr('— not certified yet', '— aún sin certificar')]); ?></div>
      <div class="col-6 col-md-3"><label class="form-label" for="cert_level_code"><?= e(tr('Highest level', 'Nivel más alto')) ?></label>
        <?php ui_certification_select('cert_level_code', $cert['level_code'] ?? null, ['lang' => $lang]); ?></div>
      <div class="col-12 col-md-6"><label class="form-label" for="cert_number"><?= e(tr('Certification number', 'Número de certificación')) ?></label><input class="form-control" id="cert_number" name="cert_number" value="<?= e((string) ($cert['number'] ?? '')) ?>"></div>
      <div class="col-4 col-md-3"><label class="form-label" for="total_dives"><?= e(tr('Total dives', 'Buceos en total')) ?></label><input class="form-control" type="number" min="0" id="total_dives" name="total_dives" value="<?= e((string) $row['total_dives']) ?>"></div>
      <div class="col-4 col-md-3"><label class="form-label" for="dives_last_year"><?= e(tr('Last year', 'Último año')) ?></label><input class="form-control" type="number" min="0" id="dives_last_year" name="dives_last_year" value="<?= e((string) $row['dives_last_year']) ?>"></div>
      <div class="col-4 col-md-6"><?php ui_date_field('last_dive_on', $row['last_dive_on'], tr('Last dive', 'Último buceo')); ?></div>
      <div class="col-6 col-md-6"><label class="form-label" for="dan_number"><?= e(tr('DAN number', 'Número DAN')) ?></label><input class="form-control" id="dan_number" name="dan_number" value="<?= e((string) $row['dan_number']) ?>"></div>
      <div class="col-6 col-md-6"><?php ui_date_field('dan_expires_on', $row['dan_expires_on'], tr('DAN expires', 'DAN vence')); ?></div>
    </div>
  </div>

  <div class="st-card mb-3">
    <h2 class="st-card__title mb-3"><?= e(tr('Emergency contact', 'Contacto de emergencia')) ?></h2>
    <div class="row g-3">
      <div class="col-12 col-md-5"><label class="form-label" for="emergency_name"><?= e(tr('Name', 'Nombre')) ?></label><input class="form-control" id="emergency_name" name="emergency_name" value="<?= e((string) ($ec['name'] ?? '')) ?>" required></div>
      <div class="col-6 col-md-3"><label class="form-label" for="emergency_relationship"><?= e(tr('Relationship', 'Parentesco')) ?></label><input class="form-control" id="emergency_relationship" name="emergency_relationship" value="<?= e((string) ($ec['relationship'] ?? '')) ?>"></div>
      <div class="col-6 col-md-4"><label class="form-label" for="emergency_phone"><?= e(tr('Phone (with country code)', 'Teléfono (con código de país)')) ?></label><input class="form-control" id="emergency_phone" name="emergency_phone" value="<?= e((string) ($ec['phone'] ?? '')) ?>" placeholder="+1 …" required></div>
    </div>
  </div>

  <?php
    $sizeOptions = ['none' => tr("Don't need one / have my own", 'No necesito / tengo el mío'), 'XS' => 'X-Small', 'S' => 'Small', 'M' => 'Medium', 'L' => 'Large', 'XL' => 'X-Large', 'XXL' => 'XX-Large', 'other' => tr('Other', 'Otra')];
    $sizeSelect = static function (string $name, string $label, ?string $value) use ($sizeOptions): void {
        $value = (string) $value;
        if ($value !== '' && !isset($sizeOptions[$value])) { $value = 'other'; }   // a size typed before the list existed
        echo '<label class="form-label" for="', $name, '">', e($label), '</label><select class="form-select form-control" id="', $name, '" name="', $name, '">';
        echo '<option value="" disabled ', $value === '' ? 'selected' : '', '>', e(tr('Choose…', 'Elige…')), '</option>';
        foreach ($sizeOptions as $k => $l) { echo '<option value="', $k, '" ', $value === $k ? 'selected' : '', '>', e($l), '</option>'; }
        echo '</select>';
    };
    $imperial = ($row['nationality'] ?? '') === 'US';
    $hCm = $row['height_cm'] !== null ? (int) $row['height_cm'] : null;
    $wKg = $row['weight_kg'] !== null ? (int) $row['weight_kg'] : null;
    $totalIn = $hCm !== null ? (int) round($hCm / 2.54) : null;
  ?>
  <div class="st-card mb-3">
    <h2 class="st-card__title mb-3"><?= e(tr('Gear sizes', 'Tallas de equipo')) ?></h2>
    <div class="row g-3">
      <div class="col-6 col-md-3"><?php $sizeSelect('wetsuit_size', tr('Wetsuit', 'Traje'), $row['wetsuit_size']); ?></div>
      <div class="col-6 col-md-3"><?php $sizeSelect('bcd_size', 'BCD', $row['bcd_size']); ?></div>
      <div class="col-6 col-md-3"><label class="form-label" for="fin_size"><?= e(tr('Fins', 'Aletas')) ?></label><input class="form-control" id="fin_size" name="fin_size" value="<?= e((string) $row['fin_size']) ?>"></div>
      <div class="col-6 col-md-3"><label class="form-label" for="boot_size"><?= e(tr('Boots', 'Botines')) ?></label><input class="form-control" id="boot_size" name="boot_size" value="<?= e((string) $row['boot_size']) ?>"></div>
    </div>
    <div class="row g-3 mt-0" id="body-metrics">
      <div class="col-12 col-md-3"><label class="form-label" for="units"><?= e(tr('Units', 'Unidades')) ?></label>
        <select class="form-select form-control" id="units" name="units" onchange="stUnits(this.value)"><option value="metric" <?= !$imperial ? 'selected' : '' ?>>cm / kg</option><option value="imperial" <?= $imperial ? 'selected' : '' ?>>ft in / lb</option></select></div>
      <div class="col-6 col-md-3 u-metric"><label class="form-label" for="height_cm"><?= e(tr('Height', 'Estatura')) ?> <span class="st-muted">cm</span></label><input class="form-control" type="number" min="0" max="250" id="height_cm" name="height_cm" value="<?= e((string) $hCm) ?>"></div>
      <div class="col-6 col-md-3 u-metric"><label class="form-label" for="weight_kg"><?= e(tr('Weight', 'Peso')) ?> <span class="st-muted">kg</span></label><input class="form-control" type="number" min="0" max="300" id="weight_kg" name="weight_kg" value="<?= e((string) $wKg) ?>"></div>
      <div class="col-6 col-md-3 u-imperial"><label class="form-label"><?= e(tr('Height', 'Estatura')) ?> <span class="st-muted">ft in</span></label>
        <div class="input-group"><input class="form-control" type="number" min="0" max="8" name="height_ft" aria-label="feet" value="<?= $totalIn !== null ? intdiv($totalIn, 12) : '' ?>"><span class="input-group-text">ft</span><input class="form-control" type="number" min="0" max="11" name="height_in" aria-label="inches" value="<?= $totalIn !== null ? $totalIn % 12 : '' ?>"><span class="input-group-text">in</span></div></div>
      <div class="col-6 col-md-3 u-imperial"><label class="form-label" for="weight_lb"><?= e(tr('Weight', 'Peso')) ?> <span class="st-muted">lb</span></label><input class="form-control" type="number" min="0" max="660" id="weight_lb" name="weight_lb" value="<?= $wKg !== null ? (int) round($wKg * 2.20462) : '' ?>"></div>
    </div>
    <script>
    function stUnits(u) { document.querySelectorAll('#body-metrics .u-metric').forEach(function (el) { el.hidden = u !== 'metric'; }); document.querySelectorAll('#body-metrics .u-imperial').forEach(function (el) { el.hidden = u !== 'imperial'; }); }
    stUnits(document.getElementById('units').value);
    </script>
  </div>

  <button class="st-btn st-btn--primary st-btn--block" type="submit"><?= e(tr('Save', 'Guardar')) ?></button>
</form>
<?php shell_end($currentUser, 'diver');
