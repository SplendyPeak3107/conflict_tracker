<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_admin();

$rid = (int) ($_GET['report'] ?? 0);
$rows = get_audit($rid ?: null, 300);

$pageTitle = 'Audit log';
include ROOT_PATH . '/includes/layout/admin_header.php';
render_flashes();
?>
<h1>Audit log</h1>
<form method="get" class="filters">
  <label>Only case number
    <input type="number" name="report" min="1" value="<?= $rid ?: '' ?>" placeholder="e.g. 12">
  </label>
  <div class="filter-actions">
    <button class="btn btn-primary" type="submit">Show</button>
    <a class="btn" href="<?= e(url('admin/audit_log.php')) ?>">Reset</a>
  </div>
</form>
<p class="muted small">The last 300 admin actions, newest first.</p>

<div class="table-wrap">
<table class="table">
  <thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Case</th><th>Reporter</th><th>Detail</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $l): ?>
    <tr>
      <td><?= e(fmt_dt($l['created_at'])) ?></td>
      <td><?= e($l['admin_name']) ?></td>
      <td><code><?= e($l['action']) ?></code></td>
      <td><?= $l['report_id'] ? '<a href="' . e(url('admin/report.php?id=' . (int) $l['report_id'])) . '">#' . (int) $l['report_id'] . '</a>' : '—' ?></td>
      <td><?= $l['target_user'] ? '<a href="' . e(url('admin/user.php?id=' . (int) $l['target_user'])) . '">' . e($l['target_name']) . '</a>' : '—' ?></td>
      <td><?= e($l['detail'] ?: '') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="muted">Nothing logged yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php include ROOT_PATH . '/includes/layout/admin_footer.php'; ?>
