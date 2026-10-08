<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_reporter();
$uid  = (int) $user['id'];
$reports = get_user_reports($uid, 50);

$pageTitle = 'History';
include ROOT_PATH . '/includes/layout/header.php';
?>
<h1>Your report history</h1>
<?php if (!$reports): ?>
  <div class="empty"><p>You have not sent any reports yet.</p></div>
<?php endif; ?>

<?php foreach ($reports as $r):
    $rid = (int) $r['id'];
?>
<details class="card hist" id="report-<?= $rid ?>">
  <summary>
    <span class="hist-title">Report #<?= $rid ?> <small class="muted"><?= e(fmt_dt($r['created_at'])) ?></small></span>
    <span class="badges"><?= badge_severity($r['severity']) ?> <?= badge_status($r['status']) ?></span>
  </summary>
  <div class="hist-body">
    <p class="loc <?= (int) $r['location_available'] ? 'ok' : 'warn' ?>"><?= (int) $r['location_available'] ? 'Location shared' : 'Location not available' ?></p>
    <?php render_timeline($r, get_status_history($rid)); ?>
    <?php if ($r['description']): ?><div class="desc"><h4>What you reported</h4><p><?= nl2br(e($r['description'])) ?></p></div><?php endif; ?>
    <?php render_updates(get_updates($rid), 'Updates you posted'); ?>
    <?php render_officer_messages(get_messages($rid)); ?>
    <?php if ($r['outcome_note']): ?><div class="outcome"><h4>Outcome</h4><p><?= nl2br(e($r['outcome_note'])) ?></p></div><?php endif; ?>
  </div>
</details>
<?php endforeach; ?>
<?php include ROOT_PATH . '/includes/layout/footer.php'; ?>
