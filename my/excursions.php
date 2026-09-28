<?php

declare(strict_types=1);

/** The diver's excursions and courses: what is coming, with the day plan, and what has been. */

require __DIR__ . '/_init.php';
require_once __DIR__ . '/../src/Events.php';

$events = array_filter(customer_events((int) $customer['id']), static fn (array $e): bool => !in_array($e['participation'], ['cancelled'], true));
$upcoming = array_filter($events, static fn (array $e): bool => $e['starts_on'] >= date('Y-m-d') && $e['status'] !== 'cancelled');
$past = array_filter($events, static fn (array $e): bool => $e['starts_on'] < date('Y-m-d'));
$steps = onboarding_steps($customer, 'auto', $lang);
$todo = array_filter($steps, static fn (array $s): bool => !$s['done']);
$payLabel = static fn (string $ps): string => $ps === 'paid' ? tr('paid', 'pagado') : ($ps === 'deposit' ? tr('deposit paid', 'anticipo pagado') : tr('payment pending', 'pago pendiente'));

shell_start(tr('Excursions', 'Excursiones'), $currentUser, 'diver', ['nav' => 'excursions']);
?>
<h1 class="st-h1 mb-2"><?= e(tr('Your dives with us', 'Tus buceos con nosotros')) ?></h1>

<?php if ($todo !== []): ?>
<div class="st-alert st-alert--warn mb-3"><?= ui_icon('warn') ?><div><strong><?= e(sprintf(tr('%d thing(s) to do before you dive', '%d pendiente(s) antes de bucear'), count($todo))) ?></strong> <a class="st-link" href="/my/forms.php"><?= e(tr('Go to Forms & Waivers', 'Ir a Formularios')) ?></a></div></div>
<?php endif; ?>

<?php if ($upcoming === []): ?><p class="st-muted"><?= e(tr('Nothing scheduled right now. Message us to plan your next dive.', 'Nada programado por ahora. Escríbenos para planear tu próximo buceo.')) ?></p><?php endif; ?>
<?php foreach (array_reverse($upcoming) as $ev): $ps = participant_payment_state(['price_mxn' => $ev['agreed_price'], 'paid_mxn' => $ev['paid_mxn']]); $plan = event_sessions((int) $ev['id']); ?>
<div class="st-card mb-3">
  <div class="st-card__head">
    <div><span class="st-muted small"><?= e(date('l j F Y', strtotime($ev['starts_on']))) ?></span>
      <h2 class="st-h2 mb-0"><?= e($lang === 'es' ? $ev['title_es'] : $ev['title_en']) ?></h2>
      <span class="st-muted small"><?= e($ev['kind'] === 'training' ? tr('Course', 'Curso') : tr('Cenote trip', 'Salida a cenotes')) ?><?= $ev['agreed_price'] !== null ? ' · ' . e(money($ev['agreed_price'])) . ' MXN · ' . e($payLabel($ps)) : '' ?></span></div>
    <?= $ps === 'paid' ? ui_icon('check', 'st-icon') : '' ?>
  </div>
  <?php if ($plan !== []): ?>
  <ul class="st-rows mt-2">
    <?php foreach ($plan as $s): ?>
    <li><span class="st-num st-muted"><?= e(date('H:i', strtotime($s['starts_at']))) ?><?php if (date('Y-m-d', strtotime($s['starts_at'])) !== $ev['starts_on']): ?><br><small><?= e(date('D j', strtotime($s['starts_at']))) ?></small><?php endif; ?></span>
      <span class="st-rows__t"><?= e($lang === 'es' && $s['title_es'] ? $s['title_es'] : $s['title_en']) ?><span class="st-rows__s"><?= e((string) $s['location']) ?></span></span></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if ($past !== []): ?>
<h2 class="st-h3 mt-4 mb-2"><?= e(tr('Past', 'Anteriores')) ?></h2>
<ul class="st-rows st-card mb-3" style="padding:0 16px">
  <?php foreach ($past as $ev): ?>
  <li><span class="st-num st-muted"><?= e(date('j M Y', strtotime($ev['starts_on']))) ?></span>
    <span class="st-rows__t"><?= e($lang === 'es' ? $ev['title_es'] : $ev['title_en']) ?><span class="st-rows__s"><?= e($ev['participation'] === 'attended' ? tr('attended', 'asististe') : $ev['participation']) ?></span></span>
    <?php if ($ev['participation'] === 'attended'): ?><a class="st-link small" href="/my/passport.php"><?= e(tr('passport', 'pasaporte')) ?></a><?php endif; ?></li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>

<div class="st-contact">
  <a href="https://wa.me/<?= e(setting('whatsapp_number')) ?>"><?= ui_icon('whatsapp') ?><span><?= e(tr('Questions? Message us on WhatsApp', '¿Dudas? Escríbenos por WhatsApp')) ?><br><small><?= e(setting('phone_display')) ?></small></span><?= ui_icon('chevron') ?></a>
</div>
<?php shell_end($currentUser, 'diver');
