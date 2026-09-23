<?php

declare(strict_types=1);

require __DIR__ . '/../_init.php';
require __DIR__ . '/../_layout.php';
require_once __DIR__ . '/../../src/Customers.php';
require_once __DIR__ . '/../../src/Team.php';
require __DIR__ . '/../team/_parts.php';

$currentUser = require_permission('can_manage_customers');
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$row = $id ? customer_find($id) : null;
if ($id && $row === null) {
    flash('That customer no longer exists.', 'warn');
    redirect('/admin/customers/');
}
$self = '/admin/customers/edit.php' . ($id ? '?id=' . $id : '?new=1');
$teamId = (int) ($currentUser['team']['id'] ?? 0) ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    try {
        switch ($action) {
            case 'save':
                $savedId = customer_save($id, $_POST);
                audit($id ? 'update' : 'create', 'customer', $savedId, post('name'));
                flash($id ? 'Saved.' : 'Customer added. Now add a way to reach them.');
                redirect('/admin/customers/edit.php?id=' . $savedId);
            case 'archive':
                customer_archive($id);
                audit('archive', 'customer', $id, $row['name']);
                flash('Customer archived.');
                redirect('/admin/customers/');
            case 'guardian':
                customer_set_guardian($id, post('guardian'));
                flash(post('guardian') === '' ? 'Guardian removed.' : 'Guardian linked.');
                redirect($self);
            case 'note_add':
                customer_note_add($id, (int) $currentUser['id'], post('body'));
                flash('Note added.');
                redirect($self);
            case 'ec_save':
                emergency_contact_save($id, null, $_POST);
                flash('Emergency contact added.');
                redirect($self);
            case 'ec_delete':
                emergency_contact_delete($id, (int) ($_POST['ec_id'] ?? 0));
                flash('Emergency contact removed.');
                redirect($self);
            case 'cert_save':
                certification_save($id, null, $_POST, $teamId);
                audit('update', 'customer', $id, 'certification added: ' . post('agency') . ' ' . post('level_code'));
                flash('Certification added.');
                redirect($self);
            case 'cert_delete':
                certification_delete($id, (int) ($_POST['cert_id'] ?? 0));
                flash('Certification removed.');
                redirect($self);
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'warn');
        redirect($self);
    }
    if ($row !== null && team_handle_subforms($action, (int) $row['person_id'], null, $currentUser)) {
        redirect($self);
    }
}

$minor = $row !== null && is_minor($row['date_of_birth']);
$statusBadge = ['signed' => 'success', 'expired' => 'danger', 'draft' => 'warning', 'missing' => 'secondary'];

shell_start($row ? $row['name'] : 'New customer', $currentUser);
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <div>
    <a class="small text-secondary text-decoration-none" href="/admin/customers/"><i class="fa-solid fa-arrow-left me-1"></i>Customers</a>
    <h1 class="h3 mb-0"><?= $row ? e($row['name']) : 'New customer' ?>
      <?php if ($minor): ?><span class="badge text-bg-warning fs-6 align-middle">minor</span><?php endif; ?></h1>
  </div>
  <?php if ($row): ?>
  <form method="post" onsubmit="return confirm('Archive <?= e(addslashes($row['name'])) ?>? They disappear from lists; nothing is deleted.')">
    <?= csrf_field() ?><input type="hidden" name="action" value="archive">
    <button class="btn btn-outline-danger btn-sm" type="submit"><i class="fa-solid fa-box-archive me-1"></i>Archive</button>
  </form>
  <?php endif; ?>
</div>

<?php if ($minor && $row['guardian_person_id'] === null): ?>
  <div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>Under <?= ADULT_AGE ?>: a parent or guardian must sign their forms. Link one below.</div>
<?php endif; ?>

<form method="post">
  <?= csrf_field() ?><input type="hidden" name="action" value="save">
  <?php team_identity_card($row ?? [], true); ?>

  <div class="card mb-3"><div class="card-body">
    <h2 class="h6 text-aqua text-uppercase mb-3">Diving</h2>
    <div class="row g-3">
      <div class="col-4 col-md-2"><label class="form-label" for="total_dives">Total dives</label><input class="form-control" type="number" min="0" id="total_dives" name="total_dives" value="<?= e((string) ($row['total_dives'] ?? '')) ?>"></div>
      <div class="col-4 col-md-2"><label class="form-label" for="dives_last_year">Last year</label><input class="form-control" type="number" min="0" id="dives_last_year" name="dives_last_year" value="<?= e((string) ($row['dives_last_year'] ?? '')) ?>"></div>
      <div class="col-4 col-md-3"><label class="form-label" for="last_dive_on">Last dive</label><input class="form-control" type="date" id="last_dive_on" name="last_dive_on" value="<?= e((string) ($row['last_dive_on'] ?? '')) ?>"></div>
      <div class="col-6 col-md-2"><label class="form-label" for="discount_pct">Discount %</label><input class="form-control" type="number" min="0" max="100" step="0.5" id="discount_pct" name="discount_pct" value="<?= e((string) ($row['discount_pct'] ?? '')) ?>"></div>
      <div class="col-6 col-md-3"><label class="form-label" for="source">How they found us</label><input class="form-control" id="source" name="source" value="<?= e((string) ($row['source'] ?? '')) ?>" placeholder="Instagram, hotel, referral"></div>
      <div class="col-12"><label class="form-label" for="local_address">Hotel / address in Mexico <span class="text-secondary fw-normal">— this trip</span></label><input class="form-control" id="local_address" name="local_address" value="<?= e((string) ($row['local_address'] ?? '')) ?>"></div>
    </div>
    <h3 class="h6 text-secondary text-uppercase mt-4 mb-2">Gear</h3>
    <div class="row g-3">
      <?php foreach (['wetsuit_size' => 'Wetsuit', 'bcd_size' => 'BCD', 'fin_size' => 'Fins', 'boot_size' => 'Boots'] as $k => $l): ?>
        <div class="col-6 col-md-2"><label class="form-label" for="<?= $k ?>"><?= $l ?></label><input class="form-control" id="<?= $k ?>" name="<?= $k ?>" value="<?= e((string) ($row[$k] ?? '')) ?>"></div>
      <?php endforeach; ?>
      <div class="col-6 col-md-2"><label class="form-label" for="height_cm">Height cm</label><input class="form-control" type="number" min="0" id="height_cm" name="height_cm" value="<?= e((string) ($row['height_cm'] ?? '')) ?>"></div>
      <div class="col-6 col-md-2"><label class="form-label" for="weight_kg">Weight kg</label><input class="form-control" type="number" min="0" id="weight_kg" name="weight_kg" value="<?= e((string) ($row['weight_kg'] ?? '')) ?>"></div>
    </div>
  </div></div>

  <div class="d-flex gap-2 mb-4">
    <button class="btn btn-aqua" type="submit"><?= $row ? 'Save' : 'Create' ?></button>
    <a class="btn btn-outline-secondary" href="/admin/customers/">Back</a>
  </div>
</form>

<?php if ($row): ?>

  <?php team_channels_card((int) $row['person_id'], $self); ?>

  <div class="card mb-3"><div class="card-body">
    <h2 class="h6 text-aqua text-uppercase mb-1">Documents</h2>
    <p class="text-secondary small mb-3">Signed forms on file. Medical is only "ok" once cleared — a signature alone is not clearance.</p>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><tbody>
    <?php foreach (customer_document_status((int) $row['id']) as $d): $s = $d['submission']; ?>
      <tr>
        <td><?= e($d['template']['title']) ?> <span class="text-secondary small">v<?= e($d['template']['version']) ?></span></td>
        <td><span class="badge text-bg-<?= $statusBadge[$d['status']] ?>"><?= e($d['status']) ?></span>
          <?php if ($d['template']['code'] === 'medical' && $d['outcome']): ?>
            <span class="badge text-bg-<?= in_array($d['outcome'], ['cleared', 'physician_cleared'], true) ? 'success' : 'danger' ?> ms-1"><?= e(str_replace('_', ' ', $d['outcome'])) ?></span>
          <?php endif; ?></td>
        <td class="small text-secondary text-nowrap"><?= $s && $s['signed_at'] ? 'signed ' . e(substr($s['signed_at'], 0, 10)) : '' ?><?= $s && $s['expires_on'] ? ' · expires ' . e($s['expires_on']) : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <p class="text-secondary small mt-3 mb-0"><i class="fa-solid fa-circle-info me-1"></i>Divers fill and sign these themselves once onboarding is built; staff will be able to record a paper form here.</p>
  </div></div>

  <div class="card mb-3"><div class="card-body">
    <h2 class="h6 text-aqua text-uppercase mb-3">Certifications</h2>
    <?php $certs = certifications((int) $row['id']); if ($certs !== []): ?>
    <div class="table-responsive"><table class="table table-sm align-middle mb-3">
      <thead><tr><th>Agency</th><th>Level</th><th>Number</th><th>Issued</th><th></th></tr></thead><tbody>
      <?php foreach ($certs as $c): ?>
        <tr><td><?= e($c['agency']) ?></td>
          <td><?= e($c['level']) ?> <span class="text-secondary small">(<?= e(CERT_LEVELS[$c['level_code']][0] ?? $c['level_code']) ?>)</span><?= $c['verified_at'] ? ' <i class="fa-solid fa-circle-check text-success" title="Card seen"></i>' : '' ?></td>
          <td class="text-secondary"><?= e((string) $c['number']) ?></td><td class="text-secondary"><?= e((string) $c['issued_on']) ?></td>
          <td class="text-end"><form method="post" class="d-inline" onsubmit="return confirm('Remove this certification?')"><?= csrf_field() ?><input type="hidden" name="action" value="cert_delete"><input type="hidden" name="cert_id" value="<?= (int) $c['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-xmark"></i></button></form></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
    <form method="post" class="row g-2 align-items-end">
      <?= csrf_field() ?><input type="hidden" name="action" value="cert_save">
      <div class="col-6 col-md-2"><label class="form-label small">Agency</label><select class="form-select form-control" name="agency"><?php foreach (CERT_AGENCIES as $a): ?><option><?= e($a) ?></option><?php endforeach; ?></select></div>
      <div class="col-6 col-md-2"><label class="form-label small">Level</label><select class="form-select form-control" name="level_code"><?php foreach (CERT_LEVELS as $k => [$l]): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="col-12 col-md-3"><label class="form-label small">As the card says</label><input class="form-control" name="level" placeholder="optional — e.g. Advanced Open Water Diver"></div>
      <div class="col-6 col-md-2"><label class="form-label small">Number</label><input class="form-control" name="number"></div>
      <div class="col-6 col-md-2"><label class="form-label small">Issued</label><input class="form-control" type="date" name="issued_on"></div>
      <div class="col-12 col-md-auto d-flex gap-2 align-items-center ms-md-auto">
        <div class="form-check"><input class="form-check-input" type="checkbox" id="cert_verified" name="verified" value="1"><label class="form-check-label small" for="cert_verified">Seen</label></div>
        <button class="btn btn-sm btn-aqua ms-auto" type="submit">Add</button>
      </div>
    </form>
  </div></div>

  <div class="card mb-3"><div class="card-body">
    <h2 class="h6 text-aqua text-uppercase mb-3">Emergency contacts</h2>
    <?php $ecs = emergency_contacts((int) $row['id']); if ($ecs !== []): ?>
    <div class="table-responsive"><table class="table table-sm align-middle mb-3"><tbody>
      <?php foreach ($ecs as $c): ?>
        <tr><td><?= e($c['name']) ?><?= $c['is_primary'] ? ' <span class="badge text-bg-info">primary</span>' : '' ?><div class="small text-secondary"><?= e((string) $c['relationship']) ?></div></td>
          <td><?= e($c['phone']) ?><?= $c['email'] ? '<div class="small text-secondary">' . e($c['email']) . '</div>' : '' ?></td>
          <td class="text-end"><form method="post" class="d-inline" onsubmit="return confirm('Remove this contact?')"><?= csrf_field() ?><input type="hidden" name="action" value="ec_delete"><input type="hidden" name="ec_id" value="<?= (int) $c['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-xmark"></i></button></form></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
    <form method="post" class="row g-2 align-items-end">
      <?= csrf_field() ?><input type="hidden" name="action" value="ec_save">
      <div class="col-12 col-md-3"><label class="form-label small">Name</label><input class="form-control" name="name" required></div>
      <div class="col-6 col-md-2"><label class="form-label small">Relationship</label><input class="form-control" name="relationship" placeholder="spouse, parent"></div>
      <div class="col-6 col-md-3"><label class="form-label small">Phone</label><input class="form-control" name="phone" placeholder="+1…" required></div>
      <div class="col-12 col-md-3"><label class="form-label small">Email</label><input class="form-control" name="email"></div>
      <div class="col-12 col-md-auto d-flex gap-2 align-items-center ms-md-auto">
        <div class="form-check"><input class="form-check-input" type="checkbox" id="ec_primary" name="is_primary" value="1"><label class="form-check-label small" for="ec_primary">Primary</label></div>
        <button class="btn btn-sm btn-aqua ms-auto" type="submit">Add</button>
      </div>
    </form>
  </div></div>

  <div class="card mb-3"><div class="card-body">
    <h2 class="h6 text-aqua text-uppercase mb-1">Guardian</h2>
    <p class="text-secondary small mb-3">For minors. The guardian signs the forms and is reached through their own contact channels — they must already exist as a person.</p>
    <?php if ($row['guardian_person_id']): ?><p class="mb-2"><i class="fa-solid fa-user-shield text-aqua me-1"></i><?= e($row['guardian_name']) ?></p><?php endif; ?>
    <form method="post" class="row g-2 align-items-end">
      <?= csrf_field() ?><input type="hidden" name="action" value="guardian">
      <div class="col-12 col-md-6"><label class="form-label small">Guardian's email or mobile</label><input class="form-control" name="guardian" placeholder="leave blank to remove"></div>
      <div class="col-12 col-md-2"><button class="btn btn-sm btn-aqua" type="submit">Link</button></div>
    </form>
  </div></div>

  <div class="card mb-3"><div class="card-body">
    <h2 class="h6 text-aqua text-uppercase mb-3">Notes</h2>
    <form method="post" class="mb-3">
      <?= csrf_field() ?><input type="hidden" name="action" value="note_add">
      <textarea class="form-control mb-2" name="body" rows="2" placeholder="Prefers morning dives. Left a regulator here in March." required></textarea>
      <button class="btn btn-sm btn-aqua" type="submit">Add note</button>
    </form>
    <?php foreach (customer_notes((int) $row['id']) as $n): ?>
      <div class="border-top pt-2 mt-2">
        <div class="small text-secondary"><?= e($n['author'] ?? 'unknown') ?> · <?= e(date('j M Y, H:i', strtotime((string) $n['created_at']))) ?></div>
        <div><?= nl2br(e($n['body'])) ?></div>
      </div>
    <?php endforeach; ?>
  </div></div>

<?php else: ?>
  <p class="text-secondary small">Contact channels, certifications, emergency contacts and notes can be added once the customer is created.</p>
<?php endif; ?>
<?php shell_end();
