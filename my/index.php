<?php

declare(strict_types=1);

require __DIR__ . '/_init.php';

$steps = onboarding_steps($customer, 'all', $lang);
$done = count(array_filter($steps, static fn (array $s): bool => $s['done']));
$signer = onboarding_signer($customer);
$appliesLabel = ['training' => tr('for courses', 'para cursos'), 'excursion' => tr('for cenote trips', 'para salidas a cenotes'), 'all' => ''];

shell_start(tr('My documents', 'Mis documentos'), $currentUser, 'diver');
?>
<h1 class="h3 mb-1"><?= e(tr('Hello', 'Hola')) ?>, <?= e($currentUser['name']) ?></h1>
<p class="text-secondary mb-4"><?= e(tr('Finish these before your dive day so the day is about diving, not paperwork.', 'Completa esto antes del día de buceo para que ese día sea de buceo, no de papeleo.')) ?></p>

<?php if (is_minor($customer['date_of_birth']) && $signer === null): ?>
  <div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i><?= e(tr('Because you are under 18, a parent or guardian needs to sign your forms. Add them on the information page.', 'Como eres menor de 18 años, un padre, madre o tutor debe firmar tus formularios. Añádelo en la página de información.')) ?></div>
<?php endif; ?>

<div class="progress mb-4" role="progressbar" aria-valuenow="<?= $done ?>" aria-valuemin="0" aria-valuemax="<?= count($steps) ?>" style="height:6px">
  <div class="progress-bar" style="width:<?= count($steps) ? (int) round($done / count($steps) * 100) : 0 ?>%;background:var(--st-aqua)"></div>
</div>

<div class="list-group mb-4">
<?php foreach ($steps as $i => $s): $t = $s['template'] ?? null; ?>
  <a class="list-group-item list-group-item-action bg-transparent d-flex align-items-center gap-3 py-3" href="<?= e($s['href']) ?>">
    <span class="fs-4 <?= $s['done'] ? 'text-success' : 'text-secondary' ?>"><i class="fa-<?= $s['done'] ? 'solid fa-circle-check' : 'regular fa-circle' ?>"></i></span>
    <span class="flex-grow-1">
      <span class="d-block"><?= e($s['title']) ?></span>
      <span class="small text-secondary">
        <?= $t ? e($appliesLabel[$t['applies_to']] ?? '') : '' ?>
        <?php if (!$s['done'] && $s['status'] !== ''): ?> · <?= e($s['status']) ?><?php endif; ?>
        <?php if (($s['outcome'] ?? null) === 'physician_required'): ?> · <span class="text-warning"><?= e(tr('a physician must sign before you dive', 'un médico debe firmar antes de bucear')) ?></span><?php endif; ?>
      </span>
    </span>
    <i class="fa-solid fa-chevron-right text-secondary"></i>
  </a>
<?php endforeach; ?>
</div>

<p class="text-secondary small"><?= e(tr('Questions? Message us on WhatsApp:', '¿Dudas? Escríbenos por WhatsApp:')) ?> <a href="https://wa.me/<?= e(setting('whatsapp_number')) ?>"><?= e(setting('phone_display')) ?></a></p>
<?php shell_end();
