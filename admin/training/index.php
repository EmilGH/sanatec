<?php

declare(strict_types=1);

require __DIR__ . '/../_init.php';
require __DIR__ . '/../_layout.php';

$currentUser = require_permission('can_manage_training');
shell_start('Training', $currentUser);
echo shell_page('Training', 'Courses', '<a class="btn btn-primary" href="/admin/catalog-courses.php">' . ui_icon('tag') . 'Course price list</a>');
?>
<div class="st-card">
  <h2 class="st-card__title">Not built yet</h2>
  <p class="st-muted mb-0">Scheduled courses over one or more days, the instructors and students on them, who has paid and who has signed what. It is the next thing to build; the price list lives under Catalog until then.</p>
</div>
<?php shell_end($currentUser);
