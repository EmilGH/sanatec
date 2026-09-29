<?php

declare(strict_types=1);

/** An affiliate's numbers: their link, this month and all time, recent bookings by first name. */

require __DIR__ . '/_init.php';

$monthStart = date('Y-m-01');
$month = affiliate_stats((int) $affiliate['id'], $monthStart, date('Y-m-d'));
$all = affiliate_stats((int) $affiliate['id']);
$bookings = affiliate_bookings((int) $affiliate['id'], 30);
$net = $affiliate['pricing_mode'] === 'net';

shell_start($affiliate['name'], $currentUser, 'partner');
?>
<p class="st-eyebrow"><?= e(setting('business_name') ?: 'SANA TEC DIVING') ?> · Partner</p>
<h1 class="st-h1 mb-1"><?= e($affiliate['name']) ?></h1>
<p class="st-lede mb-3"><?= $net ? 'Net rate' : 'Commission' ?> · <?= e(rtrim(rtrim((string) $affiliate['share_pct'], '0'), '.')) ?>% of the room between retail and floor</p>

<div class="st-card mb-3">
  <h2 class="st-card__title mb-1">Your link</h2>
  <p class="st-muted small">Anyone who opens it is counted as yours for ninety days, and so is every dive they book.</p>
  <input class="form-control font-monospace" readonly value="<?= e(affiliate_link($affiliate)) ?>" onclick="this.select()">
  <p class="small mt-2 mb-0">Spanish: <a class="st-link" href="<?= e(affiliate_link($affiliate, 'es')) ?>"><?= e(affiliate_link($affiliate, 'es')) ?></a></p>
</div>

<div class="st-stats mb-3">
  <div class="st-stat"><b><?= $month['bookings'] ?></b><span>bookings this month</span></div>
  <div class="st-stat"><b><?= e(money($month['affiliate_amount'])) ?></b><span><?= $net ? 'off invoices this month' : 'earned this month (MXN)' ?></span></div>
</div>

<div class="st-card mb-3">
  <div class="st-tablewrap"><table class="st-table">
    <thead><tr><th></th><th class="text-end">Link opens</th><th class="text-end">Sign-ups</th><th class="text-end">Bookings</th><th class="text-end">Dived</th><th class="text-end"><?= $net ? 'Off invoices' : 'Earned' ?></th></tr></thead>
    <tbody>
      <?php foreach (['This month' => $month, 'All time' => $all] as $label => $st): ?>
      <tr><td data-label="Period" class="st-table__main"><?= e($label) ?></td><td data-label="Link opens" class="text-md-end st-num"><?= $st['visits'] ?></td><td data-label="Sign-ups" class="text-md-end st-num"><?= $st['signups'] ?></td><td data-label="Bookings" class="text-md-end st-num"><?= $st['bookings'] ?></td><td data-label="Dived" class="text-md-end st-num"><?= $st['attended'] ?></td><td data-label="Share" class="text-md-end st-num"><?= e(money($st['affiliate_amount'])) ?> MXN</td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
</div>

<h2 class="st-h3 mb-2">Recent bookings</h2>
<?php if ($bookings === []): ?><p class="st-muted">None yet. Share your link.</p><?php else: ?>
<ul class="st-rows st-card mb-3" style="padding:0 16px">
  <?php foreach ($bookings as $b): ?>
  <li><span class="st-num st-muted"><?= e(date('j M', strtotime($b['starts_on']))) ?></span>
    <span class="st-rows__t"><?= e($b['first_name']) ?> · <?= e($b['title_en']) ?><span class="st-rows__s"><?= e(str_replace('_', ' ', $b['status'])) ?></span></span>
    <span class="st-num"><?= e(money($b['affiliate_amount_mxn']) ?? '—') ?></span></li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
<p class="st-muted small">Bookings show first names only. Amounts are Mexican pesos and are settled by the shop; cancellations and no-shows are not counted.</p>
<?php shell_end($currentUser, 'partner');
