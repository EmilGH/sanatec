<?php

declare(strict_types=1);

require __DIR__ . '/../_init.php';
require __DIR__ . '/../_layout.php';

$currentUser = require_permission('can_manage_excursions');
shell_start('Excursions', $currentUser);
echo shell_page('Excursions', 'Scheduling', '<a class="btn btn-primary" href="/admin/catalog-excursions.php">' . ui_icon('tag') . 'Cenote price list</a>');
?>
<div class="st-card">
  <h2 class="st-card__title">Not built yet</h2>
  <p class="st-muted mb-0">Dated cenote trips with a day plan, the team and divers on them, who has paid and who has signed what. It is the next thing to build; the price list lives under Catalog until then.</p>
</div>
<?php shell_end($currentUser);
