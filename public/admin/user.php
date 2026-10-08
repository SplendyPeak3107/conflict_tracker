<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$admin = require_admin();
$aid = (int) $admin['id'];

$uid = (int) ($_GET['id'] ?? 0);
$u = $uid ? find_user($uid) : null;
if (!$u || $u['role'] !== 'reporter') {
    http_response_code(404);
    exit('Reporter not found. <a href="' . e(url('admin/users.php')) . '">Back</a>');
}

if (is_post()) {
    csrf_check();
    $do = post_str('do', 20);
    if ($do === 'suspend') {
        $reason = post_str('reason', 250);
        if ($reason === '') {
            flash('error', 'Please give a reason for the suspension.');
        } else {
            set_account_status($uid, 'suspended', $reason);
            audit_log($aid, 'suspend_user', null, $uid, $reason);
            flash('success', 'Account suspended. The reporter can still log in and see history, but cannot send reports.');
        }
    } elseif ($do === 'reinstate') {
        set_account_status($uid, 'active', null);
        audit_log($aid, 'reinstate_user', null, $uid, null);
        flash('success', 'Account reinstated.');
    }
    redirect('admin/user.php?id=' . $uid);
}

$stats   = user_stats($uid);
$reports = get_user_reports($uid, 100);

$pageTitle = $u['name'];
$mainClass = 'narrow-wide';
include ROOT_PATH . '/includes/layout/admin_header.php';
render_flashes();
?>
<a href="<?= e(url('admin/users.php')) ?>" class="back">&larr; All reporters</a>
<h1><?= e($u['name']) ?> <?= $u['account_status'] === 'suspended' ? '<span class="badge st-false_alarm">Suspended</span>' : '' ?></h1>

<div class="case-grid">
  <section class="card pad">
    <h2>Profile</h2>
    <dl class="kv">
      <dt>Email</dt><dd><?= e($u['email']) ?></dd>
      <dt>Phone</dt><dd><a href="tel:<?= e($u['phone']) ?>"><?= e($u['phone']) ?></a></dd>
      <dt>Community</dt><dd><?= e($u['community'] ?: 'Not given') ?></dd>
      <dt>Emergency contact</dt>
      <dd><?php if ($u['emergency_name'] || $u['emergency_phone']): ?><?= e($u['emergency_name']) ?> <?= e($u['emergency_phone']) ?><?php else: ?>Not given<?php endif; ?></dd>
      <dt>Joined</dt><dd><?= e(fmt_dt($u['created_at'])) ?></dd>
      <dt>Last login</dt><dd><?= e($u['last_login_at'] ? fmt_dt($u['last_login_at']) : 'Never') ?></dd>
    </dl>
  </section>

  <section class="card pad">
    <h2>Trust record</h2>
    <div class="stats stats-4">
      <div class="stat"><b><?= (int) $stats['total'] ?></b><span>Reports</span></div>
      <div class="stat"><b><?= (int) $stats['resolved'] ?></b><span>Resolved</span></div>
      <div class="stat <?= (int) $stats['false_alarms'] > 0 ? 'sev-medium' : '' ?>"><b><?= (int) $stats['false_alarms'] ?></b><span>False alarms</span></div>
      <div class="stat"><b><?= (int) $stats['critical'] ?></b><span>Marked critical</span></div>
    </div>

    <?php if ($u['account_status'] === 'suspended'): ?>
      <p><strong>Suspended:</strong> <?= e($u['suspended_reason']) ?></p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="reinstate">
        <button class="btn btn-success" type="submit">Reinstate account</button>
      </form>
    <?php else: ?>
      <form method="post" onsubmit="return confirm('Suspend this account? They will not be able to send new reports.');">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="suspend">
        <label>Suspend this account (for abuse or repeated false alarms)
          <input type="text" name="reason" maxlength="250" required placeholder="Reason, kept in the audit log">
        </label>
        <button class="btn btn-danger" type="submit">Suspend account</button>
      </form>
    <?php endif; ?>
  </section>
</div>

<h2>Past reports</h2>
<div class="table-wrap">
<table class="table">
  <thead><tr><th>Case</th><th>When</th><th>Severity</th><th>Status</th><th>Description</th></tr></thead>
  <tbody>
  <?php foreach ($reports as $r): ?>
    <tr>
      <td><a href="<?= e(url('admin/report.php?id=' . (int) $r['id'])) ?>">#<?= (int) $r['id'] ?></a></td>
      <td><?= e(fmt_dt($r['created_at'])) ?></td>
      <td><?= badge_severity($r['severity']) ?></td>
      <td><?= badge_status($r['status']) ?></td>
      <td><?= e(short_text($r['description'], 120) ?: '—') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$reports): ?><tr><td colspan="5" class="muted">No reports yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php include ROOT_PATH . '/includes/layout/admin_footer.php'; ?>
