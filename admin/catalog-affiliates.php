<?php

declare(strict_types=1);

/** Affiliates: who sends divers, on what terms, and how it is going. */

require __DIR__ . '/_init.php';
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../src/Affiliates.php';
require_once __DIR__ . '/../src/Team.php';
require __DIR__ . '/team/_parts.php';

$currentUser = require_permission('can_manage_affiliates');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$self = '/admin/catalog-affiliates.php' . ($id ? '?id=' . $id : '?new=1');
$signInLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    try {
        switch ($action) {
            case 'save':
                $savedId = affiliate_save($id ?: null, $_POST);
                audit($id ? 'update' : 'create', 'affiliate', $savedId, post('name'));
                flash($id ? 'Saved.' : 'Affiliate added. Now add a way to reach the contact, and hand them their link.');
                redirect('/admin/catalog-affiliates.php?id=' . $savedId);
            case 'logo':
                affiliate_logo_set($id, ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? null : $_FILES['logo']);
                flash('Logo saved.');
                redirect($self);
            case 'logo_remove':
                affiliate_logo_set($id, null);
                flash('Logo removed.');
                redirect($self);
            case 'toggle':
                db()->prepare('UPDATE affiliates SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => $id]);
                redirect('/admin/catalog-affiliates.php');
            case 'login_link':
                $a = affiliate_find($id);
                $ch = $a && $a['contact_person_id'] ? (person_channels((int) $a['contact_person_id'])[0] ?? null) : null;
                if ($ch === null) {
                    throw new RuntimeException('Add a contact with an email or mobile first.');
                }
                $signInLink = login_issue_link((int) $a['contact_person_id'], (int) $ch['id'], 60 * 24, 'partner');
                audit('login_link', 'affiliate', $id, 'partner sign-in link issued');
                break;
            default:
                $a = affiliate_find($id);
                if ($a && $a['contact_person_id'] && team_handle_subforms($action, (int) $a['contact_person_id'], null, $currentUser)) {
                    redirect($self);
                }
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'warn');
        redirect($self);
    }
}

$editing = null;
if (isset($_GET['new'])) {
    $editing = ['is_active' => 1, 'pricing_mode' => 'commission', 'share_pct' => '50.00'];
} elseif ($id) {
    $editing = affiliate_find($id);
    if ($editing === null) {
        flash('That affiliate no longer exists.', 'warn');
        redirect('/admin/catalog-affiliates.php');
    }
}
$monthStart = date('Y-m-01');

shell_start('Affiliates', $currentUser);
echo shell_page('Affiliates', 'Catalog', '<a class="btn btn-primary" href="/admin/catalog-affiliates.php?new=1">' . ui_icon('plus') . 'Add</a>');
?>
<p class="st-lede mb-3">Hotels, concierges and operators who send divers. Each gets a link; divers who arrive through it are counted for ninety days, and every booking they make carries the affiliate's share.</p>
<div class="st-tabs mb-3">
  <a href="/admin/catalog-courses.php">Courses</a>
  <a href="/admin/catalog-excursions.php">Excursions</a>
  <a href="/admin/catalog-sites.php">Dive sites</a>
  <a href="/admin/catalog-affiliates.php" aria-current="page">Affiliates</a>
</div>

<?php if ($editing !== null): ?>
  <?php if ($signInLink !== null): ?>
  <div class="alert alert-info">
    <div class="fw-semibold mb-1">One-time sign-in link for <?= e($editing['contact_name']) ?> — their own numbers at /partner/. Works once, valid 24 hours.</div>
    <input class="form-control font-monospace small" readonly value="<?= e($signInLink) ?>" onclick="this.select()">
  </div>
  <?php endif; ?>

  <form method="post" class="st-card mb-3">
    <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
    <h2 class="st-card__title mb-3"><?= isset($editing['id']) ? 'Edit' : 'New' ?> affiliate</h2>
    <div class="row g-3">
      <div class="col-12 col-md-6"><label class="form-label" for="name">Business name</label><input class="form-control" id="name" name="name" value="<?= e((string) ($editing['name'] ?? '')) ?>" required placeholder="Casa Malca"></div>
      <div class="col-12 col-md-6"><label class="form-label" for="code">Referral code</label>
        <div class="input-group"><span class="input-group-text">/a/</span><input class="form-control" id="code" name="code" value="<?= e((string) ($editing['code'] ?? '')) ?>" placeholder="made from the name if blank" pattern="[a-z0-9-]{2,40}"></div>
        <?php if (isset($editing['id'])): ?><div class="form-text">Link: <a href="<?= e(affiliate_link($editing)) ?>" target="_blank"><?= e(affiliate_link($editing)) ?></a> · Spanish: <a href="<?= e(affiliate_link($editing, 'es')) ?>" target="_blank"><?= e(affiliate_link($editing, 'es')) ?></a></div><?php endif; ?></div>
      <div class="col-12 col-md-6"><label class="form-label" for="contact_name">Contact name</label><input class="form-control" id="contact_name" name="contact_name" value="<?= e((string) ($editing['contact_name'] ?? '')) ?>" placeholder="Who we deal with"></div>
      <div class="col-6 col-md-3"><label class="form-label" for="pricing_mode">Arrangement</label>
        <select class="form-select form-control" id="pricing_mode" name="pricing_mode"><?php foreach (AFFILIATE_MODES as $k => $l): ?><option value="<?= $k ?>" <?= ($editing['pricing_mode'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="col-6 col-md-3"><label class="form-label" for="share_pct">Share of retail − floor</label>
        <div class="input-group"><input class="form-control" id="share_pct" name="share_pct" inputmode="decimal" value="<?= e((string) ($editing['share_pct'] ?? '50')) ?>"><span class="input-group-text">%</span></div>
        <div class="form-text">Commission: what they earn. Net: what comes off their invoice.</div></div>
    </div>
    <div class="mt-3"><?php field_pair('description', 'Description', $editing, 'textarea', 'Shown to divers who arrive through the link, next to the logo.'); ?></div>
    <div class="mb-3"><label class="form-label" for="notes">Internal notes</label><textarea class="form-control" id="notes" name="notes" rows="2"><?= e((string) ($editing['notes'] ?? '')) ?></textarea></div>
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= !empty($editing['is_active']) ? 'checked' : '' ?>><label class="form-check-label" for="is_active">Active — the link works and bookings are credited</label></div>
    <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Save</button><a class="btn btn-outline-secondary" href="/admin/catalog-affiliates.php">Back</a></div>
  </form>

  <?php if (isset($editing['id'])): ?>
    <div class="st-card mb-3">
      <h2 class="st-card__title mb-1">Logo</h2>
      <p class="st-muted small">Shown on the site to divers who arrive through the link. PNG with transparency works best.</p>
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <?php if ($editing['logo_path']): ?><img src="/admin/customers/file.php?kind=affiliate_logo&id=<?= (int) $editing['id'] ?>&v=<?= e(substr(md5((string) $editing['logo_path']), 0, 6)) ?>" alt="" style="max-height:80px;max-width:240px;background:#fff;padding:6px;border-radius:8px"><?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="d-flex gap-2 align-items-center"><?= csrf_field() ?><input type="hidden" name="action" value="logo"><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
          <input class="form-control form-control-sm" type="file" name="logo" accept="image/*" required style="max-width:260px"><button class="btn btn-sm btn-outline-secondary" type="submit">Upload</button></form>
        <?php if ($editing['logo_path']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="logo_remove"><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form><?php endif; ?>
      </div>
    </div>

    <?php if ($editing['contact_person_id']): ?>
      <?php team_channels_card((int) $editing['contact_person_id'], $self); ?>
      <form method="post" class="mb-4"><?= csrf_field() ?><input type="hidden" name="action" value="login_link"><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
        <button class="btn btn-outline-info btn-sm" type="submit"><i class="fa-solid fa-link me-1"></i>Sign-in link for the contact (their own numbers)</button></form>
    <?php else: ?>
      <p class="st-muted small mb-4">Give the contact a name above to add their email or mobile.</p>
    <?php endif; ?>

    <?php $month = affiliate_stats((int) $editing['id'], $monthStart, date('Y-m-d')); $all = affiliate_stats((int) $editing['id']); ?>
    <div class="st-card mb-3">
      <h2 class="st-card__title mb-2">Performance</h2>
      <div class="st-tablewrap"><table class="st-table">
        <thead><tr><th></th><th class="text-end">Link opens</th><th class="text-end">Sign-ups</th><th class="text-end">Bookings</th><th class="text-end">Attended</th><th class="text-end">Revenue</th><th class="text-end"><?= $editing['pricing_mode'] === 'net' ? 'Off invoices' : 'Owed to them' ?></th></tr></thead>
        <tbody>
          <?php foreach (['This month' => $month, 'All time' => $all] as $label => $st): ?>
          <tr><td data-label="Period" class="st-table__main"><?= e($label) ?></td><td data-label="Link opens" class="text-md-end st-num"><?= $st['visits'] ?></td><td data-label="Sign-ups" class="text-md-end st-num"><?= $st['signups'] ?></td><td data-label="Bookings" class="text-md-end st-num"><?= $st['bookings'] ?></td><td data-label="Attended" class="text-md-end st-num"><?= $st['attended'] ?></td><td data-label="Revenue" class="text-md-end st-num"><?= e(money($st['revenue'])) ?></td><td data-label="Share" class="text-md-end st-num"><?= e(money($st['affiliate_amount'])) ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php $bookings = affiliate_bookings((int) $editing['id'], 20); if ($bookings !== []): ?>
      <h3 class="st-h3 mt-3 mb-2">Recent bookings</h3>
      <ul class="st-rows">
        <?php foreach ($bookings as $b): ?><li><span class="st-num st-muted"><?= e(date('j M', strtotime($b['starts_on']))) ?></span><span class="st-rows__t"><?= e($b['first_name']) ?> · <?= e($b['title_en']) ?><span class="st-rows__s"><?= e($b['status']) ?> · <?= e(money($b['price_mxn']) ?? '—') ?> MXN</span></span><span class="st-num"><?= e(money($b['affiliate_amount_mxn']) ?? '—') ?></span></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<div class="st-card" style="padding:0 16px">
  <div class="st-tablewrap"><table class="st-table">
    <thead><tr><th>Affiliate</th><th>Code</th><th>Arrangement</th><th class="text-end">This month</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php $rows = affiliates_list(); if ($rows === []): ?><tr><td colspan="6" class="st-muted">No affiliates yet.</td></tr><?php endif; ?>
    <?php foreach ($rows as $row): $st = affiliate_stats((int) $row['id'], $monthStart, date('Y-m-d')); ?>
      <tr class="<?= $row['is_active'] ? '' : 'opacity-50' ?>">
        <td data-label="Affiliate" class="st-table__main"><a class="fw-semibold text-decoration-none" href="/admin/catalog-affiliates.php?id=<?= (int) $row['id'] ?>"><?= e($row['name']) ?></a><?php if ($row['contact_name']): ?><br><small class="st-muted"><?= e($row['contact_name']) ?></small><?php endif; ?></td>
        <td data-label="Code"><code>/a/<?= e($row['code']) ?></code></td>
        <td data-label="Arrangement" class="st-muted"><?= e($row['pricing_mode'] === 'net' ? 'Net' : 'Commission') ?> · <?= e(rtrim(rtrim((string) $row['share_pct'], '0'), '.')) ?>%</td>
        <td data-label="This month" class="text-md-end st-num"><?= $st['bookings'] ?> booked · <?= e(money($st['affiliate_amount'])) ?></td>
        <td data-label="Status"><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button class="st-pill <?= $row['is_active'] ? 'st-pill--ok' : 'st-pill--neutral' ?>" type="submit" style="border:0;cursor:pointer"><?= $row['is_active'] ? ui_icon('check', 'st-icon') . 'Active' : 'Inactive' ?></button></form></td>
        <td data-label="" class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/admin/catalog-affiliates.php?id=<?= (int) $row['id'] ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php shell_end($currentUser);
