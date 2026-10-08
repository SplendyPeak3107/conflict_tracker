<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_reporter();
$uid  = (int) $user['id'];
$suspended = is_suspended($user);

if (is_post()) {
    csrf_check();
    $do = post_str('do', 30);

    if ($suspended) {
        flash('error', 'Your account is suspended, so new reports and updates are switched off. You can still see your history.');
        redirect('dashboard.php');
    }

    if ($do === 'report') {
        // Guard against accidental double taps
        $last = seconds_since_last_report($uid);
        if ($last && $last['seconds'] < REPORT_COOLDOWN_SECONDS) {
            flash('info', 'Your report was already sent a moment ago. Help is being alerted.');
            redirect('dashboard.php?new=' . $last['id'] . '#report-' . $last['id']);
        }
        $id = create_report($uid, $_POST['lat'] ?? '', $_POST['lng'] ?? '', $_POST['acc'] ?? '');
        $r = get_report($id);
        if ($r && (int) $r['location_available'] === 1) {
            flash('success', 'Report sent with your location. You can add details below.');
        } else {
            flash('warning', 'Report sent, but your location could not be read. Please say where you are in the details below.');
        }
        redirect('dashboard.php?new=' . $id . '#report-' . $id);
    }

    if ($do === 'details') {
        $rid = (int) ($_POST['report_id'] ?? 0);
        if (update_report_details($rid, $uid, post_str('description', 2000), post_str('severity', 10))) {
            flash('success', 'Details saved. The officer can see them.');
        } else {
            flash('error', 'That report can no longer be edited.');
        }
        redirect('dashboard.php#report-' . $rid);
    }

    if ($do === 'update') {
        $rid = (int) ($_POST['report_id'] ?? 0);
        if (add_reporter_update($rid, $uid, post_str('body', 2000))) {
            flash('success', 'Update posted.');
        } else {
            flash('error', 'Write something first, or the report is already closed.');
        }
        redirect('dashboard.php#report-' . $rid);
    }

    redirect('dashboard.php');
}

$reports = get_dashboard_reports($uid);
$newId   = (int) ($_GET['new'] ?? 0);

// Key used by live.js: when it changes (a report closed or a new one appeared) the page reloads itself.
$keyParts = [];
foreach ($reports as $r) {
    $keyParts[] = $r['id'] . ':' . (is_closed_status($r['status']) ? 'c' : 'o');
}
$reloadKey = implode(',', $keyParts);

$pageTitle = 'Home';
$bodyClass = 'reporter-home';
$scripts = ['js/gps.js', 'js/live.js'];
$scriptAttrs = ['js/live.js' => 'data-interval="' . (int) REPORTER_REFRESH_SECONDS . '"'];
include ROOT_PATH . '/includes/layout/header.php';
?>

<?php if ($suspended): ?>
  <div class="flash flash-error">Your account is suspended<?= $user['suspended_reason'] ? ': ' . e($user['suspended_reason']) : '' ?>. You can view your history but cannot send new reports.</div>
<?php endif; ?>

<section class="report-zone">
  <form id="report-form" method="post" action="<?= e(url('dashboard.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="report">
    <input type="hidden" name="lat" id="lat" value="">
    <input type="hidden" name="lng" id="lng" value="">
    <input type="hidden" name="acc" id="acc" value="">
    <button type="submit" id="report-btn" class="report-btn" <?= $suspended ? 'disabled' : '' ?>>
      <span class="report-btn-label">REPORT</span>
      <span class="report-btn-sub">Tap once. Your location is sent.</span>
    </button>
  </form>
  <p id="gps-status" class="gps-status" role="status" aria-live="polite">
    <?= $suspended ? 'Reporting is switched off for this account.' : 'In danger? Tap and run. You can add details later.' ?>
  </p>
</section>

<section id="active" data-reload-key="<?= e($reloadKey) ?>">
  <h2>Your reports</h2>
  <?php if (!$reports): ?>
    <div class="empty">
      <p>No active reports.</p>
      <p class="muted">When you press Report, you can follow here how the officer is handling it.</p>
    </div>
  <?php endif; ?>

  <?php foreach ($reports as $r):
      $rid     = (int) $r['id'];
      $closed  = is_closed_status($r['status']);
      $history = get_status_history($rid);
      $msgs    = get_messages($rid);
      $ups     = get_updates($rid);
      $isNew   = ($rid === $newId);
  ?>
  <article class="card report-card <?= $closed ? 'is-closed' : '' ?> sev-border-<?= e($r['severity']) ?>" id="report-<?= $rid ?>">
    <header class="card-head">
      <div>
        <h3>Report #<?= $rid ?></h3>
        <small class="muted">Sent <?= e(fmt_dt($r['created_at'])) ?></small>
      </div>
      <div class="badges">
        <?= badge_severity($r['severity']) ?>
      </div>
    </header>

    <p class="loc <?= (int) $r['location_available'] ? 'ok' : 'warn' ?>">
      <?php if ((int) $r['location_available']): ?>
        Location shared<?= $r['accuracy_m'] !== null ? ' (within about ' . e($r['accuracy_m']) . ' m)' : '' ?>
      <?php else: ?>
        Location not available. Please describe where you are.
      <?php endif; ?>
    </p>

    <div data-live="live-<?= $rid ?>">
      <?php render_timeline($r, $history); ?>
      <?php render_officer_messages($msgs); ?>
      <?php if ($r['description']): ?>
        <div class="desc"><h4>What you reported</h4><p><?= nl2br(e($r['description'])) ?></p></div>
      <?php endif; ?>
      <?php render_updates($ups, 'Updates you posted'); ?>
      <?php if ($closed && $r['outcome_note']): ?>
        <div class="outcome"><h4>Outcome</h4><p><?= nl2br(e($r['outcome_note'])) ?></p></div>
      <?php endif; ?>
    </div>

    <?php if (!$closed && !$suspended): ?>
    <details class="fold" <?= $isNew ? 'open' : '' ?>>
      <summary>Add details or change how serious it is</summary>
      <form method="post" action="<?= e(url('dashboard.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="details">
        <input type="hidden" name="report_id" value="<?= $rid ?>">
        <label>What is happening? <span class="opt">optional</span>
          <textarea name="description" rows="4" maxlength="2000" placeholder="Who is there, what they are doing, which direction…"><?= e($r['description']) ?></textarea>
        </label>
        <fieldset class="sev-pick">
          <legend>How serious is it?</legend>
          <?php foreach (severities() as $key => $label): ?>
            <label class="sev-opt sev-opt-<?= e($key) ?>">
              <input type="radio" name="severity" value="<?= e($key) ?>" <?= $r['severity'] === $key ? 'checked' : '' ?>>
              <span><?= e($label) ?></span>
            </label>
          <?php endforeach; ?>
        </fieldset>
        <button class="btn btn-primary" type="submit">Save details</button>
      </form>
    </details>

    <details class="fold">
      <summary>Post an update on this report</summary>
      <form method="post" action="<?= e(url('dashboard.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="update">
        <input type="hidden" name="report_id" value="<?= $rid ?>">
        <label>What has changed?
          <textarea name="body" rows="3" maxlength="2000" required placeholder="For example: it is getting worse, they have left, more people arrived…"></textarea>
        </label>
        <button class="btn btn-primary" type="submit">Post update</button>
      </form>
    </details>
    <?php endif; ?>
  </article>
  <?php endforeach; ?>

  <p class="center"><a href="<?= e(url('history.php')) ?>">See all past reports</a></p>
</section>

<?php include ROOT_PATH . '/includes/layout/footer.php'; ?>
