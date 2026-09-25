<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$steps = onboarding_steps($customer, 'all', $lang);
$done = count(array_filter($steps, static fn (array $s): bool => $s['done']));
$signer = onboarding_signer($customer);
$applies = ['training' => tr('for courses', 'para cursos'), 'excursion' => tr('for cenote trips', 'para salidas a cenotes'), 'all' => ''];

shell_start(tr('My documents', 'Mis documentos'), $currentUser, 'diver');
?>
<p class="st-eyebrow"><?= e(tr('Before your dive', 'Antes de tu buceo')) ?></p>
<h1 class="st-h1 mb-2"><?= e(tr('Hello', 'Hola')) ?>, <?= e($currentUser['name']) ?></h1>
<p class="st-lede mb-3"><?= e(tr('Finish these before your dive day, so the day is about diving and not paperwork.', 'Completa esto antes del día de buceo, para que ese día sea de buceo y no de papeleo.')) ?></p>

<?php if (is_minor($customer['date_of_birth']) && $signer === null): ?>
  <div class="st-alert st-alert--warn mb-3"><?= ui_icon('warn') ?><div><strong><?= e(tr('Under 18', 'Menor de 18')) ?></strong><?= e(tr('A parent or guardian needs to sign your forms. Add them on the information page.', 'Un padre, madre o tutor debe firmar tus formularios. Añádelo en la página de información.')) ?></div></div>
<?php endif; ?>

<div class="st-meter mb-1" style="--n:<?= count($steps) ?>" role="progressbar" aria-valuenow="<?= $done ?>" aria-valuemin="0" aria-valuemax="<?= count($steps) ?>">
  <?php for ($i = 0; $i < count($steps); $i++): ?><i class="<?= $i < $done ? 'on' : '' ?>"></i><?php endfor; ?>
</div>
<p class="st-muted small mb-3"><?= e(sprintf(tr('%d of %d done', '%d de %d listos'), $done, count($steps))) ?></p>

<ul class="st-docs mb-4">
<?php foreach ($steps as $i => $s): $t = $s['template'] ?? null;
    $state = $s['done'] ? 'done' : ((($s['outcome'] ?? null) === 'physician_required') ? 'warn' : ($s['status'] === '' || str_starts_with($s['status'], 'missing') ? 'missing' : ''));
?>
  <li><a class="st-doc st-doc--<?= $state ?>" href="<?= e($s['href']) ?>">
    <span class="st-doc__n"><?= $s['done'] ? ui_icon('check') : ($i + 1) ?></span>
    <span><span class="st-doc__t"><?= e($s['title']) ?></span>
      <span class="st-doc__s">
        <?= $t ? e($applies[$t['applies_to']] ?? '') : '' ?>
        <?php if (($s['outcome'] ?? null) === 'physician_required'): ?><?= e(tr("a physician must sign before you dive", 'un médico debe firmar antes de bucear')) ?>
        <?php elseif ($s['done']): ?><?= e(tr('done', 'listo')) ?>
        <?php elseif ($s['status'] !== ''): ?><?= e($s['status']) ?>
        <?php else: ?><?= e(tr('to do', 'pendiente')) ?><?php endif; ?>
      </span></span>
    <?= ui_icon('chevron', 'st-doc__go') ?>
  </a></li>
<?php endforeach; ?>
</ul>

<div class="st-contact">
  <a href="https://wa.me/<?= e(setting('whatsapp_number')) ?>"><?= ui_icon('whatsapp') ?><span><?= e(tr('Questions? Message us on WhatsApp', '¿Dudas? Escríbenos por WhatsApp')) ?><br><small><?= e(setting('phone_display')) ?></small></span><?= ui_icon('chevron') ?></a>
</div>
<?php shell_end($currentUser, 'diver');
