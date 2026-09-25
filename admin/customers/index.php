<?php

declare(strict_types=1);

require __DIR__ . '/../_init.php';
require __DIR__ . '/../_layout.php';
require_once __DIR__ . '/../../src/Customers.php';

$currentUser = require_permission('can_manage_customers');
$q = trim((string) ($_GET['q'] ?? ''));
$rows = customers_search($q);

shell_start('Customers', $currentUser);
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
  <h1 class="h3 mb-0">Customers</h1>
  <a class="btn btn-aqua" href="/admin/customers/edit.php?new=1"><i class="fa-solid fa-user-plus me-1"></i>Add customer</a>
</div>

<form method="get" class="mb-3">
  <div class="input-group">
    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
    <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Name, email or mobile" autofocus>
    <?php if ($q !== ''): ?><a class="btn btn-outline-secondary" href="/admin/customers/">Clear</a><?php endif; ?>
  </div>
</form>

<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Name</th><th class="d-none d-md-table-cell">Contact</th><th class="d-none d-lg-table-cell">Certification</th><th class="d-none d-lg-table-cell">Dives</th><th>Documents</th><th></th></tr></thead>
  <tbody>
  <?php if ($rows === []): ?><tr><td colspan="6" class="text-secondary"><?= $q === '' ? 'No customers yet.' : 'Nobody matches.' ?></td></tr><?php endif; ?>
  <?php foreach ($rows as $r): $complete = customer_documents_complete((int) $r['id']); ?>
    <tr>
      <td><a class="fw-semibold text-decoration-none" href="/admin/customers/edit.php?id=<?= (int) $r['id'] ?>"><?= e($r['name']) ?></a>
        <?php if (is_minor($r['date_of_birth'])): ?><span class="badge text-bg-warning ms-1">minor</span><?php endif; ?>
        <?php if ($r['discount_pct'] !== null): ?><span class="badge text-bg-dark border ms-1">−<?= e(rtrim(rtrim($r['discount_pct'], '0'), '.')) ?>%</span><?php endif; ?></td>
      <td class="d-none d-md-table-cell small text-secondary"><?= $r['email'] ? e($r['email']) . '<br>' : '' ?><?= e((string) $r['mobile']) ?></td>
      <td class="d-none d-lg-table-cell"><?= e((string) ($r['top_cert'] ?? '—')) ?></td>
      <td class="d-none d-lg-table-cell text-secondary"><?= $r['total_dives'] !== null ? (int) $r['total_dives'] : '—' ?><?= $r['last_dive_on'] ? '<div class="small">last ' . e($r['last_dive_on']) . '</div>' : '' ?></td>
      <td><?= $complete ? '<span class="badge text-bg-success">complete</span>' : '<span class="badge text-bg-secondary">incomplete</span>' ?></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/admin/customers/edit.php?id=<?= (int) $r['id'] ?>">Open</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
<?php shell_end($currentUser);
