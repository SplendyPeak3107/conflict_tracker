<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin();

$q = get_str('q', 100);
$users = search_reporters($q);

$pageTitle = 'Reporters';
include ROOT_PATH . '/includes/layout/admin_header.php';
render_flashes();
?>
<h1>Reporters</h1>
<form method="get" class="filters">
  <label>Search
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Name, email, phone or community">
  </label>
  <div class="filter-actions">
    <button class="btn btn-primary" type="submit">Search</button>
    <a class="btn" href="<?= e(url('admin/users.php')) ?>">Reset</a>
  </div>
</form>

<div class="table-wrap">
<table class="table">
  <thead><tr><th>Name</th><th>Phone</th><th>Community</th><th>Reports</th><th>False alarms</th><th>Account</th><th>Joined</th></tr></thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <tr>
      <td><a href="<?= e(url('admin/user.php?id=' . (int) $u['id'])) ?>"><?= e($u['name']) ?></a><br><small class="muted"><?= e($u['email']) ?></small></td>
      <td><?= e($u['phone']) ?></td>
      <td><?= e($u['community'] ?: '—') ?></td>
      <td><?= (int) $u['total_reports'] ?></td>
      <td class="<?= (int) $u['false_alarms'] > 0 ? 'warn-text' : '' ?>"><?= (int) $u['false_alarms'] ?></td>
      <td><?= $u['account_status'] === 'suspended' ? '<span class="badge st-false_alarm">Suspended</span>' : 'Active' ?></td>
      <td><?= e(fmt_dt($u['created_at'])) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$users): ?><tr><td colspan="7" class="muted">No reporters found.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php include ROOT_PATH . '/includes/layout/admin_footer.php'; ?>
