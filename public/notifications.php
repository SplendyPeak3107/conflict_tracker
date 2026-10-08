<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_reporter();
$uid  = (int) $user['id'];

$items = get_notifications($uid);
mark_all_read($uid); // shown as unread below, marked read for next time

$pageTitle = 'Alerts';
include ROOT_PATH . '/includes/layout/header.php';
?>
<h1>Alerts</h1>
<?php if (!$items): ?>
  <div class="empty"><p>Nothing yet. You will see an alert here every time the status of your report changes.</p></div>
<?php endif; ?>
<ul class="notif-list">
<?php foreach ($items as $n):
    $page = ($n['report_status'] !== null && !is_closed_status($n['report_status'])) ? 'dashboard.php' : 'history.php';
?>
  <li class="<?= $n['is_read'] ? '' : 'unread' ?>">
    <a href="<?= e(url($page . ($n['report_id'] ? '#report-' . (int) $n['report_id'] : ''))) ?>">
      <span><?= e($n['body']) ?></span>
      <small><?= e(fmt_dt($n['created_at'])) ?> · <?= e(time_ago($n['created_at'])) ?></small>
    </a>
  </li>
<?php endforeach; ?>
</ul>
<?php include ROOT_PATH . '/includes/layout/footer.php'; ?>
