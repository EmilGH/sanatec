<?php

declare(strict_types=1);

/** Shared event page: day plan, team, divers, money. The including file sets $kind. */

if (!defined('SANATEC') || !isset($kind)) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../src/Events.php';
require_once __DIR__ . '/../../src/Customers.php';

$base = $kind === 'training' ? '/admin/training/' : '/admin/excursions/';
$id = (int) ($_GET['id'] ?? 0);
$event = event_find($id);
if ($event === null || $event['kind'] !== $kind) {
    flash('That event does not exist.', 'warn');
    redirect($base);
}
$self = $base . 'event.php?id=' . $id;
$teamId = (int) ($currentUser['team']['id'] ?? 0) ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    try {
        switch ($action) {
            case 'update':
                event_update($id, $_POST);
                audit('update', 'event', $id, $event['title_en']);
                flash('Saved.');
                break;
            case 'session_save':
                event_session_save($id, (int) ($_POST['session_id'] ?? 0) ?: null, $_POST);
                flash('Day plan updated.');
                break;
            case 'session_delete':
                event_session_delete($id, (int) ($_POST['session_id'] ?? 0));
                flash('Session removed.');
                break;
            case 'team_add':
                event_team_add($id, (int) ($_POST['team_member_id'] ?? 0), post('role', 'guide'));
                flash('Team member added.');
                break;
            case 'team_status':
                event_team_set_status($id, (int) ($_POST['row_id'] ?? 0), post('status'));
                break;
            case 'team_remove':
                event_team_remove($id, (int) ($_POST['row_id'] ?? 0));
                flash('Team member removed.');
                break;
            case 'diver_add':
                $cid = (int) ($_POST['customer_id'] ?? 0);
                if ($cid === 0 && post('search') !== '') {
                    $found = customers_search(post('search'), 2);
                    if (count($found) !== 1) {
                        throw new RuntimeException(count($found) === 0 ? 'No customer matches. Add them under Customers first.' : 'More than one customer matches — be more specific.');
                    }
                    $cid = (int) $found[0]['id'];
                }
                $pid = event_participant_add($id, $cid, $teamId);
                audit('book', 'event', $id, 'diver added #' . $cid);
                flash('Diver added.');
                break;
            case 'diver_status':
                event_participant_set_status($id, (int) ($_POST['row_id'] ?? 0), post('status'));
                break;
            case 'diver_remove':
                event_participant_remove($id, (int) ($_POST['row_id'] ?? 0));
                flash('Diver removed.');
                break;
            case 'payment_add':
                payment_add((int) ($_POST['participant_id'] ?? 0), $_POST, $teamId);
                audit('payment', 'event', $id, money(parse_money(post('amount')) ?? 0) . ' ' . post('method'));
                flash('Payment recorded.');
                break;
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'warn');
    }
    redirect($self);
}

$sessions = event_sessions($id);
$siteNames = array_column(dive_sites(false), 'name_en', 'id');
$team = event_team($id);
$divers = event_participants($id);
$money = event_money($id);
$taken = event_places_taken($id);
$allTeam = array_filter(team_list(), static fn (array $m): bool => (bool) $m['is_active']);
$onTeam = array_column($team, 'team_member_id');
$docsMissing = 0;
foreach ($divers as $d) {
    if (in_array($d['status'], ['invited', 'confirmed'], true) && participant_documents((int) $d['customer_id'], $kind)['missing'] !== []) {
        $docsMissing++;
    }
}
$dateLabel = date('l j F Y', strtotime($event['starts_on']));
$payPill = ['paid' => ['ok', 'Paid'], 'deposit' => ['warn', 'Deposit'], 'unpaid' => ['danger', 'Unpaid'], 'n/a' => ['neutral', '—']];

shell_start($event['title_en'], $currentUser, 'admin', ['back' => $base]);
?>
<p class="st-eyebrow"><?= e(event_kind_label($kind)) ?> · <?= e($dateLabel) ?></p>
<h1 class="st-h1 mb-1"><?= e($event['title_en']) ?></h1>
<p class="st-muted mb-2"><?= $taken ?> of <?= (int) $event['capacity'] ?> places taken<?= $event['price_mxn'] !== null ? ' · ' . e(money($event['price_mxn'])) . ' per diver' : '' ?><?= $event['status'] !== 'open' ? ' · <b>' . e($event['status']) . '</b>' : '' ?></p>
<div class="st-chips mb-3">
  <?php if ($docsMissing > 0): ?><span class="st-pill st-pill--warn"><?= ui_icon('warn') ?><?= $docsMissing ?> with documents missing</span><?php endif; ?>
  <?php if ($money['due'] > 0): ?><span class="st-pill st-pill--warn"><?= e(money($money['due'])) ?> due</span><?php endif; ?>
  <?php if ($docsMissing === 0 && $money['due'] <= 0 && $divers !== []): ?><span class="st-pill st-pill--ok"><?= ui_icon('check') ?>All set</span><?php endif; ?>
</div>
<div class="st-page__actions mb-4">
  <a class="btn btn-outline-secondary btn-sm" href="#message">Message divers</a>
  <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#settings">Settings</button>
</div>

<form method="post" class="st-card mb-3 collapse" id="settings">
  <?= csrf_field() ?><input type="hidden" name="action" value="update">
  <div class="row g-3">
    <div class="col-6 col-md-3"><label class="form-label">Places</label><input class="form-control" type="number" min="1" max="60" name="capacity" value="<?= (int) $event['capacity'] ?>"></div>
    <div class="col-6 col-md-3"><label class="form-label">Price per diver (MXN)</label><input class="form-control" name="price_mxn" value="<?= e((string) (money($event['price_mxn']) ?? '')) ?>"><div class="form-text">New divers only; agreed prices stay.</div></div>
    <div class="col-6 col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['open', 'draft', 'done', 'cancelled'] as $s): ?><option value="<?= $s ?>" <?= $event['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><label class="form-label">Internal notes</label><textarea class="form-control" name="internal_notes" rows="2"><?= e((string) $event['internal_notes']) ?></textarea></div>
  </div>
  <button class="btn btn-primary mt-3" type="submit">Save</button>
</form>

<div class="row g-3">
  <div class="col-12 col-lg-7">

    <div class="st-card mb-3">
      <div class="st-card__head"><h2 class="st-card__title">Divers · <?= count(array_filter($divers, static fn (array $d): bool => in_array($d['status'], ['invited', 'confirmed', 'attended'], true))) ?></h2>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#add-diver"><?= ui_icon('plus', 'st-icon') ?>Add diver</button></div>
      <form method="post" class="collapse mb-3" id="add-diver"><?= csrf_field() ?><input type="hidden" name="action" value="diver_add">
        <div class="input-group"><input class="form-control" name="search" placeholder="Customer name, email or mobile" required><button class="btn btn-primary" type="submit">Add</button></div>
        <div class="form-text">Must already be a customer. The price is fixed for them now from the list price less their discount.</div></form>
      <?php if ($divers === []): ?><p class="st-muted mb-0">Nobody yet.</p><?php else: ?>
      <div class="st-tablewrap"><table class="st-table">
        <thead><tr><th>Diver</th><th>Payment</th><th>Documents</th><th>Wetsuit · fins</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($divers as $d): $docs = participant_documents((int) $d['customer_id'], $kind); $ps = participant_payment_state($d); [$pc, $pl] = $payPill[$ps]; $off = in_array($d['status'], ['cancelled', 'no_show'], true); ?>
          <tr <?= $off ? 'style="opacity:.55"' : '' ?>>
            <td class="st-table__main" data-label="Diver"><a href="/admin/customers/edit.php?id=<?= (int) $d['customer_id'] ?>" class="st-link" style="text-decoration:none"><?= e($d['name']) ?></a>
              <?php if ($d['top_cert']): ?><span class="st-cert ms-2"><?= e(preg_match('/advanced/i', $d['top_cert']) ? 'AOW' : (preg_match('/open water/i', $d['top_cert']) ? 'OW' : $d['top_cert'])) ?></span><?php endif; ?>
              <?php if ($d['status'] !== 'confirmed'): ?><span class="st-pill st-pill--neutral ms-1"><?= e(PARTICIPANT_STATUSES[$d['status']]) ?></span><?php endif; ?>
              <?php if (is_minor($d['date_of_birth'])): ?><span class="st-pill st-pill--warn ms-1">minor</span><?php endif; ?></td>
            <td data-label="Payment"><span class="st-pill st-pill--<?= $pc ?>"><?= $ps === 'paid' ? ui_icon('check') : '' ?><?= $pl ?></span>
              <?php if ($d['price_mxn'] !== null): ?><span class="st-muted small ms-1"><?= e(money($d['paid_mxn'])) ?> / <?= e(money($d['price_mxn'])) ?></span><?php endif; ?></td>
            <td data-label="Documents"><span class="st-pill st-pill--<?= $docs['missing'] === [] ? 'ok' : ($docs['ok'] === 0 ? 'danger' : 'warn') ?>" title="<?= e(implode(', ', $docs['missing'])) ?>"><?= ui_icon('file') ?><?= $docs['ok'] ?>/<?= $docs['total'] ?></span></td>
            <td data-label="Wetsuit · fins" class="st-muted"><?= e(trim(($d['wetsuit_size'] ?? '—') . ' · ' . ($d['fin_size'] ?? '—'))) ?></td>
            <td data-label="" class="text-end text-nowrap">
              <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#diver-<?= (int) $d['id'] ?>">…</button>
            </td>
          </tr>
          <tr class="collapse" id="diver-<?= (int) $d['id'] ?>"><td colspan="5" style="border-bottom:1px solid var(--line)">
            <div class="d-flex flex-wrap gap-2 align-items-end py-2">
              <form method="post" class="d-flex gap-2 align-items-end flex-wrap"><?= csrf_field() ?><input type="hidden" name="action" value="payment_add"><input type="hidden" name="participant_id" value="<?= (int) $d['id'] ?>">
                <div><label class="form-label small">Payment</label><input class="form-control form-control-sm" name="amount" placeholder="<?= e((string) (money(max(0, (float) $d['price_mxn'] - (float) $d['paid_mxn'])) ?? '')) ?>" style="width:120px"></div>
                <div><label class="form-label small">Method</label><select class="form-select form-select-sm" name="method"><?php foreach (PAYMENT_METHODS as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></div>
                <div><label class="form-label small">Currency</label><input class="form-control form-control-sm" name="currency" value="MXN" style="width:70px" list="currencies"></div>
                <div><label class="form-label small" title="Pesos per unit of that currency on the day. Blank for MXN.">Rate → MXN</label><input class="form-control form-control-sm" name="fx_rate" placeholder="1" inputmode="decimal" style="width:90px"></div>
                <label class="form-check small mb-1"><input class="form-check-input" type="checkbox" name="refund" value="1"> refund</label>
                <button class="btn btn-sm btn-primary" type="submit">Record</button></form>
              <form method="post" class="d-flex gap-1 ms-auto"><?= csrf_field() ?><input type="hidden" name="action" value="diver_status"><input type="hidden" name="row_id" value="<?= (int) $d['id'] ?>">
                <?php foreach (['attended' => 'Attended', 'no_show' => 'No-show', 'cancelled' => 'Cancel'] as $st => $lbl): ?><button class="btn btn-sm btn-outline-secondary" name="status" value="<?= $st ?>"><?= $lbl ?></button><?php endforeach; ?></form>
              <form method="post" onsubmit="return confirm('Remove <?= e(addslashes($d['name'])) ?> from this event?')"><?= csrf_field() ?><input type="hidden" name="action" value="diver_remove"><input type="hidden" name="row_id" value="<?= (int) $d['id'] ?>"><button class="btn btn-sm st-btn--danger" style="border-radius:var(--radius-pill)">Remove</button></form>
            </div>
            <?php $pays = participant_payments((int) $d['id']); if ($pays !== []): ?><div class="small st-muted">Payments: <?php foreach ($pays as $pm): ?><span class="me-2"><?= e(money($pm['amount'])) ?> <?= e($pm['currency']) ?><?= $pm['currency'] !== 'MXN' ? ' (' . e(money($pm['amount_mxn'])) . ' MXN @ ' . e(rtrim(rtrim((string) $pm['fx_rate'], '0'), '.')) . ')' : '' ?> <?= e($pm['method']) ?> · <?= e(substr($pm['received_at'], 0, 10)) ?><?= $pm['received_by_name'] ? ' · ' . e($pm['received_by_name']) : '' ?></span><?php endforeach; ?></div><?php endif; ?>
            <?php if ($docs['missing'] !== []): ?><div class="small" style="color:var(--warn)">Missing: <?= e(implode(' · ', $docs['missing'])) ?></div><?php endif; ?>
          </td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </div>

    <datalist id="currencies"><option value="MXN"><option value="USD"><option value="EUR"><option value="CAD"><option value="GBP"></datalist>
    <div class="st-card mb-3" id="message">
      <h2 class="st-card__title">Message divers</h2>
      <p class="st-muted small">One tap per diver opens WhatsApp with a prefilled message. Sending from the system arrives with the messaging provider.</p>
      <?php $msg = 'Hi {name}! About your ' . strtolower(event_kind_label($kind)) . ' on ' . date('j M', strtotime($event['starts_on'])) . ' — ' . $event['title_en'] . '. '; ?>
      <div class="st-chips">
        <?php foreach ($divers as $d): if (in_array($d['status'], ['cancelled', 'no_show'], true)) continue; ?>
          <?php if ($d['mobile']): ?><a class="st-chip" href="https://wa.me/<?= e(ltrim($d['mobile'], '+')) ?>?text=<?= rawurlencode(str_replace('{name}', explode(' ', $d['name'])[0], $msg)) ?>" target="_blank" rel="noopener"><?= ui_icon('whatsapp', 'st-icon') ?><?= e($d['name']) ?></a>
          <?php else: ?><span class="st-chip" style="opacity:.5" title="No mobile on file"><?= e($d['name']) ?> · no mobile</span><?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-5">
    <div class="st-card mb-3">
      <div class="st-card__head"><h2 class="st-card__title">Day plan</h2><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#add-session"><?= ui_icon('plus', 'st-icon') ?>Add</button></div>
      <form method="post" class="collapse mb-3 row g-2" id="add-session"><?= csrf_field() ?><input type="hidden" name="action" value="session_save">
        <div class="col-7"><input class="form-control form-control-sm" type="datetime-local" name="starts_at" value="<?= e($event['starts_on']) ?>T09:00" required></div>
        <div class="col-5"><input class="form-control form-control-sm" name="location" placeholder="Where"></div>
        <div class="col-12"><select class="form-select form-select-sm" name="dive_site_id"><option value="">Not a dive (briefing, meet, theory)</option><?php foreach (dive_sites(false) as $site): ?><option value="<?= (int) $site['id'] ?>"><?= e($site['name_en']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 d-flex gap-2"><input class="form-control form-control-sm" name="title_en" placeholder="What (e.g. Dive 3)" required><button class="btn btn-sm btn-primary" type="submit">Add</button></div></form>
      <ul class="st-rows">
        <?php foreach ($sessions as $s): ?>
        <li><span class="st-num st-muted"><?= e(date('H:i', strtotime($s['starts_at']))) ?><?php if (count($sessions) > 1 && date('Y-m-d', strtotime($s['starts_at'])) !== $event['starts_on']): ?><br><small><?= e(date('D j', strtotime($s['starts_at']))) ?></small><?php endif; ?></span>
          <span class="st-rows__t"><?= e($s['title_en']) ?><span class="st-rows__s"><?= e((string) $s['location']) ?><?= $s['dive_site_id'] ? ($s['location'] ? ' · ' : '') . '<span title="Counts as a dive in the passport">' . ui_icon('wave', 'st-icon') . e($siteNames[(int) $s['dive_site_id']] ?? '') . '</span>' : '' ?></span></span>
          <form method="post" onsubmit="return confirm('Remove this session?')"><?= csrf_field() ?><input type="hidden" name="action" value="session_delete"><input type="hidden" name="session_id" value="<?= (int) $s['id'] ?>"><button class="st-iconbtn" title="Remove"><?= ui_icon('x', 'st-icon st-muted') ?></button></form></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="st-card mb-3">
      <div class="st-card__head"><h2 class="st-card__title">Team</h2><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#add-team"><?= ui_icon('plus', 'st-icon') ?>Add</button></div>
      <form method="post" class="collapse mb-3" id="add-team"><div class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="team_add">
        <select class="form-select form-select-sm" name="team_member_id" required><option value="">Who</option><?php foreach ($allTeam as $m): if (in_array($m['id'], $onTeam)) continue; ?><option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?></option><?php endforeach; ?></select>
        <select class="form-select form-select-sm" name="role"><?php foreach (EVENT_ROLES as $k => $l): ?><option value="<?= $k ?>" <?= ($kind === 'training' ? $k === 'instructor' : $k === 'guide') ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
        <button class="btn btn-sm btn-primary" type="submit">Add</button></div></form>
      <?php if ($team === []): ?><p class="st-muted mb-0">Nobody assigned.</p><?php else: ?>
      <ul class="st-rows">
        <?php foreach ($team as $m): ?>
        <li><span class="st-avatar"><?= e(mb_strtoupper(mb_substr($m['name'], 0, 2))) ?></span>
          <span class="st-rows__t"><?= e($m['name']) ?><span class="st-rows__s"><?= e(EVENT_ROLES[$m['role']]) ?></span></span>
          <span class="d-flex gap-1 align-items-center">
            <?php if ($m['status'] === 'confirmed'): ?><span class="st-pill st-pill--ok"><?= ui_icon('check') ?>Confirmed</span>
            <?php else: ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="team_status"><input type="hidden" name="row_id" value="<?= (int) $m['id'] ?>"><button class="st-pill st-pill--<?= $m['status'] === 'declined' ? 'danger' : 'warn' ?>" name="status" value="confirmed" style="border:0;cursor:pointer" title="Mark confirmed"><?= e(ucfirst($m['status'])) ?></button></form><?php endif; ?>
            <form method="post" onsubmit="return confirm('Remove <?= e(addslashes($m['name'])) ?> from this event?')"><?= csrf_field() ?><input type="hidden" name="action" value="team_remove"><input type="hidden" name="row_id" value="<?= (int) $m['id'] ?>"><button class="st-iconbtn" title="Remove"><?= ui_icon('x', 'st-icon st-muted') ?></button></form>
          </span></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>

    <div class="st-card mb-3">
      <h2 class="st-card__title">Payments</h2>
      <dl class="st-kv">
        <dt><?= count(array_filter($divers, static fn (array $d): bool => in_array($d['status'], ['invited', 'confirmed', 'attended'], true) && $d['price_mxn'] !== null)) ?> divers</dt><dd class="st-num text-end"><?= e(money($money['total'])) ?></dd>
        <dt>Paid (<?= $money['n_paid'] ?>)</dt><dd class="st-num text-end"><?= e(money($money['paid'])) ?></dd>
        <?php if ($money['n_deposit'] > 0): ?><dt>Deposit (<?= $money['n_deposit'] ?>)</dt><dd class="st-num text-end st-muted">partial</dd><?php endif; ?>
        <dt><b>Still due</b></dt><dd class="st-num text-end"><b><?= e(money($money['due'])) ?></b></dd>
      </dl>
    </div>
  </div>
</div>
<?php shell_end($currentUser);
