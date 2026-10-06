<?php

declare(strict_types=1);

/** Shared list page. The including file sets $kind ('excursion'|'training') and has required the permission. */

if (!defined('SANATEC') || !isset($kind)) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../src/Events.php';

$base = $kind === 'training' ? '/admin/training/' : '/admin/excursions/';
$label = event_kind_label($kind);
$labels = event_kind_label($kind, true);
$catalog = catalog_published($kind === 'training' ? 'courses' : 'excursions');
$team = array_filter(team_list(), static fn (array $m): bool => (bool) $m['is_active']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $id = event_create($kind, $_POST, (int) ($currentUser['team']['id'] ?? 0) ?: null);
        audit('create', 'event', $id, $kind . ' ' . post('date'));
        flash($label . ' created. Add divers and the team.');
        redirect($base . 'event.php?id=' . $id);
    } catch (Throwable $e) {
        flash($e->getMessage(), 'warn');
        redirect($base . '?new=1');
    }
}

$when = ($_GET['when'] ?? 'upcoming') === 'past' ? 'past' : 'upcoming';
$rows = events_list($kind, $when);

// ?copy=<id>: the new form opens filled in from that day (item, places, dives, time,
// lead), with the date left for you. Divers and the day plan are not copied.
$copy = null;
if (isset($_GET['copy']) && ($src = event_find((int) $_GET['copy'])) !== null && $src['kind'] === $kind) {
    $first = event_sessions((int) $src['id'])[0] ?? null;
    $lead = array_values(array_filter(event_team((int) $src['id']), static fn (array $m): bool => in_array($m['role'], ['lead', 'instructor'], true)))[0] ?? null;
    $copy = [
        'catalog_id'   => (int) ($kind === 'training' ? $src['course_id'] : $src['excursion_id']),
        'dives_count'  => (int) $src['dives_count'],
        'capacity'     => (int) $src['capacity'],
        'meet_time'    => $first ? date('H:i', strtotime($first['starts_at'])) : '07:30',
        'lead_team_id' => (int) ($lead['team_member_id'] ?? 0),
        'title'        => $src['title_en'],
    ];
}

shell_start($labels, $currentUser);
echo shell_page($labels, 'Scheduling', '<a class="btn btn-primary" href="' . $base . '?new=1">' . ui_icon('plus') . 'New ' . strtolower($label) . '</a>');
?>
<?php if (isset($_GET['new']) || $copy !== null): ?>
<form method="post" class="st-card mb-4">
  <?= csrf_field() ?>
  <h2 class="st-card__title mb-3">New <?= strtolower($label) ?><?= $copy ? ' <span class="st-muted fw-normal">· like ' . e($copy['title']) . '</span>' : '' ?></h2>
  <div class="row g-3">
    <div class="col-12 col-md-5"><label class="form-label" for="catalog_id"><?= $kind === 'training' ? 'Course Name' : 'Excursion Name' ?></label>
      <select class="form-select" id="catalog_id" name="catalog_id" required>
        <option value="">—</option>
        <?php foreach ($catalog as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $copy && $copy['catalog_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name_en']) ?><?= $kind === 'training' ? ' · ' . e($c['duration_en']) : '' ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-6 col-md-3"><?php ui_date_field('date', null, $kind === 'training' ? 'First day' : 'Date', true, '', ['min' => date('Y-m-d')]); ?></div>
    <div class="col-6 col-md-2"><label class="form-label" for="meet_time">Time</label><input class="form-control" type="time" id="meet_time" name="meet_time" value="<?= e($copy['meet_time'] ?? '07:30') ?>"></div>
    <?php if ($kind === 'excursion'): ?>
    <div class="col-6 col-md-2"><label class="form-label" for="dives_count">Dives</label><select class="form-select" id="dives_count" name="dives_count"><?php foreach ([1 => '1 dive', 2 => '2 dives', 3 => '3 dives'] as $n => $l): ?><option value="<?= $n ?>" <?= ($copy['dives_count'] ?? 2) === $n ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="col-6 col-md-2"><label class="form-label" for="capacity">Max Divers</label><input class="form-control" type="number" min="1" max="60" id="capacity" name="capacity" value="<?= (int) ($copy['capacity'] ?? ($kind === 'training' ? 4 : 8)) ?>"></div>
    <div class="col-12 col-md-4"><label class="form-label" for="lead_team_id"><?= $kind === 'training' ? 'Instructor' : 'Lead guide' ?></label>
      <select class="form-select" id="lead_team_id" name="lead_team_id"><option value="">—</option>
        <?php foreach ($team as $m): if ($kind === 'training' ? $m['is_instructor'] : ($m['is_cave_guide'] || $m['is_cavern_guide'] || $m['is_instructor'] || $m['is_divemaster'])): ?><option value="<?= (int) $m['id'] ?>" <?= ($copy['lead_team_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option><?php endif; endforeach; ?>
      </select></div>
  </div>
  <div class="d-flex gap-2 mt-3">
    <button class="btn btn-primary" type="submit">Create</button>
    <a class="btn btn-outline-secondary" href="<?= e($base) ?>">Cancel</a>
  </div>
  <p class="st-muted small mt-3 mb-0"><?= $kind === 'training' ? 'A session is created for each day of the course; edit times and places on the next page.' : 'A day plan is created — meet at the shop, then one line per dive — and the price is fixed from the catalogue for every diver added.' ?></p>
</form>
<?php endif; ?>

<div class="st-tabs mb-3">
  <a href="<?= e($base) ?>" <?= $when === 'upcoming' ? 'aria-current="page"' : '' ?>>Upcoming</a>
  <a href="<?= e($base) ?>?when=past" <?= $when === 'past' ? 'aria-current="page"' : '' ?>>Past</a>
</div>

<?php if ($rows === []): ?>
  <div class="st-card"><p class="st-muted mb-0">No <?= strtolower($labels) ?> <?= $when === 'past' ? 'yet' : 'scheduled' ?>.</p></div>
<?php else: ?>
<ul class="st-rows st-card" style="padding:0 16px">
  <?php foreach ($rows as $r): $money = event_money((int) $r['id']); ?>
  <li>
    <div style="min-width:52px;text-align:center"><span class="st-serif" style="font-size:22px;display:block;line-height:1"><?= e(date('j', strtotime($r['starts_on']))) ?></span><span class="st-muted small"><?= e(date('M', strtotime($r['starts_on']))) ?></span></div>
    <div><a class="st-rows__t st-link" style="text-decoration:none" href="<?= e($base) ?>event.php?id=<?= (int) $r['id'] ?>"><?= e($r['title_en']) ?></a>
      <span class="st-rows__s"><?= (int) $r['taken'] ?> of <?= (int) $r['capacity'] ?> places · <?= (int) $r['team_count'] ?> team<?= $money['due'] > 0 ? ' · ' . e(money($money['due'])) . ' due' : '' ?><?= $r['status'] !== 'open' ? ' · ' . e($r['status']) : '' ?></span></div>
    <span class="d-flex gap-1 align-items-center">
      <a class="st-iconbtn" href="<?= e($base) ?>?copy=<?= (int) $r['id'] ?>" title="Duplicate — schedule another day like this one" aria-label="Duplicate"><?= ui_icon('copy', 'st-icon st-muted') ?></a>
      <a class="st-iconbtn" href="<?= e($base) ?>event.php?id=<?= (int) $r['id'] ?>" aria-label="Open"><?= ui_icon('chevron', 'st-icon st-muted') ?></a>
    </span>
  </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
<?php shell_end($currentUser);
