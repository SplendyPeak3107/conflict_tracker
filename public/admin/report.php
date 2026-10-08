<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$admin = require_admin();
$aid = (int) $admin['id'];

$id = (int) ($_GET['id'] ?? 0);
$r = $id ? admin_get_report($id) : null;
if (!$r) {
    http_response_code(404);
    exit('Case not found. <a href="' . e(url('admin/dashboard.php')) . '">Back to the live map</a>');
}

if (is_post()) {
    csrf_check();
    $do = post_str('do', 20);

    switch ($do) {
        case 'status':
            $new = post_str('status', 20);
            if (in_array($new, ['received', 'acknowledged', 'dispatched', 'on_scene'], true)) {
                if (change_status($id, $aid, $new)) {
                    flash('success', 'Status changed to: ' . status_label($new) . '. The reporter has been notified.');
                } else {
                    flash('info', 'The case was already at that status.');
                }
            }
            break;

        case 'resolve':
            $outcome = post_str('outcome', 3000);
            if ($outcome === '') {
                flash('error', 'Please write an outcome note: what happened and what action was taken.');
            } elseif (change_status($id, $aid, 'resolved', $outcome)) {
                flash('success', 'Case resolved.');
            } else {
                flash('info', 'The case was already resolved.');
            }
            break;

        case 'false_alarm':
            if (change_status($id, $aid, 'false_alarm', post_str('reason', 500))) {
                flash('success', 'Case closed as a false alarm.');
            } else {
                flash('info', 'The case was already marked as a false alarm.');
            }
            break;

        case 'severity':
            if (change_severity($id, $aid, post_str('severity', 10))) {
                flash('success', 'Severity updated.');
            } else {
                flash('info', 'Severity was not changed.');
            }
            break;

        case 'note':
            if (add_note($id, $aid, post_str('note', 4000))) {
                flash('success', 'Internal note saved (reporters cannot see it).');
            } else {
                flash('error', 'Write a note first.');
            }
            break;

        case 'message':
            if (add_message($id, $aid, post_str('body', 500))) {
                flash('success', 'Message sent to the reporter.');
            } else {
                flash('error', 'Write a message first.');
            }
            break;
    }
    redirect('admin/report.php?id=' . $id);
}

$closed   = is_closed_status($r['status']);
$hasLoc   = ($r['latitude'] !== null && $r['longitude'] !== null);
$history  = get_status_history($id);
$updates  = get_updates($id);
$messages = get_messages($id);
$notes    = get_notes($id);
$quick    = get_quick_replies();
$stats    = user_stats((int) $r['user_id']);

$pageTitle = 'Case #' . $id;
$mainClass = 'case-page';
$leaflet   = $hasLoc;
$scripts   = ['js/live.js', 'js/case.js'];
$scriptAttrs = ['js/live.js' => 'data-interval="5"'];
include ROOT_PATH . '/includes/layout/admin_header.php';
?>
<?php render_flashes(); ?>

<div class="case-head">
  <a href="<?= e(url('admin/dashboard.php')) ?>" class="back">&larr; Live map</a>
  <h1>Case #<?= $id ?></h1>
  <div data-live="case-badges">
    <?= badge_severity($r['severity']) ?> <?= badge_status($r['status']) ?>
    <small class="muted">Received <?= e(fmt_dt($r['created_at'])) ?> (<?= e(time_ago($r['created_at'])) ?>)</small>
  </div>
</div>

<div class="case-grid">
  <div class="col">

    <section class="card pad">
      <h2>Reporter</h2>
      <p class="big"><a href="<?= e(url('admin/user.php?id=' . (int) $r['user_id'])) ?>"><?= e($r['reporter_name']) ?></a>
        <?php if ($r['reporter_account_status'] === 'suspended'): ?><span class="badge st-false_alarm">Suspended</span><?php endif; ?></p>
      <dl class="kv">
        <dt>Phone</dt><dd><a href="tel:<?= e($r['reporter_phone']) ?>"><?= e($r['reporter_phone']) ?></a></dd>
        <dt>Email</dt><dd><?= e($r['reporter_email']) ?></dd>
        <dt>Community</dt><dd><?= e($r['reporter_community'] ?: 'Not given') ?></dd>
        <dt>Emergency contact</dt>
        <dd><?php if ($r['emergency_name'] || $r['emergency_phone']): ?>
              <?= e($r['emergency_name']) ?> <?php if ($r['emergency_phone']): ?><a href="tel:<?= e($r['emergency_phone']) ?>"><?= e($r['emergency_phone']) ?></a><?php endif; ?>
            <?php else: ?>Not given<?php endif; ?></dd>
        <dt>Track record</dt>
        <dd><?= (int) $stats['total'] ?> report(s), <?= (int) $stats['false_alarms'] ?> false alarm(s)</dd>
      </dl>
    </section>

    <section class="card pad">
      <h2>Location</h2>
      <?php if ($hasLoc): ?>
        <div id="case-map" data-lat="<?= e($r['latitude']) ?>" data-lng="<?= e($r['longitude']) ?>" data-sev="<?= e($r['severity']) ?>"></div>
        <p class="small"><?= e($r['latitude']) ?>, <?= e($r['longitude']) ?>
          <?= $r['accuracy_m'] !== null ? '(accuracy about ' . (int) $r['accuracy_m'] . ' m)' : '' ?>
          · <a href="<?= e(maps_link($r['latitude'], $r['longitude'])) ?>" target="_blank" rel="noopener">Open in Google Maps</a></p>
      <?php else: ?>
        <p class="warn-text">The reporter's phone could not provide a location. Call them: <a href="tel:<?= e($r['reporter_phone']) ?>"><?= e($r['reporter_phone']) ?></a></p>
      <?php endif; ?>
    </section>

    <section class="card pad" data-live="case-story">
      <h2>What the reporter says</h2>
      <?php if ($r['description']): ?>
        <p class="desc-big"><?= nl2br(e($r['description'])) ?></p>
      <?php else: ?>
        <p class="muted">No description yet.</p>
      <?php endif; ?>
      <?php render_updates($updates, 'Updates and changes (newest at the bottom)'); ?>
    </section>

  </div>

  <div class="col">

    <section class="card pad">
      <h2>Handle this case</h2>

      <form method="post" class="row-form">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="status">
        <label>Status
          <select name="status">
            <?php foreach (['received', 'acknowledged', 'dispatched', 'on_scene'] as $s): ?>
              <option value="<?= e($s) ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
            <?php endforeach; ?>
            <?php if ($closed): ?><option value="" selected disabled><?= e(status_label($r['status'])) ?> (closed)</option><?php endif; ?>
          </select>
        </label>
        <button class="btn btn-primary" type="submit"><?= $closed ? 'Re-open with this status' : 'Update status' ?></button>
      </form>
      <?php if ($r['status'] === 'received'): ?>
        <form method="post" class="inline-block">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="status">
          <input type="hidden" name="status" value="acknowledged">
          <button class="btn btn-big btn-primary" type="submit">Acknowledge this report</button>
        </form>
      <?php endif; ?>

      <form method="post" class="row-form">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="severity">
        <label>Severity (your ranking)
          <select name="severity">
            <?php foreach (severities() as $k => $l): ?>
              <option value="<?= e($k) ?>" <?= $r['severity'] === $k ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="btn" type="submit">Re-rank</button>
      </form>

      <?php if (!$closed): ?>
      <details class="fold" open>
        <summary>Resolve with an outcome note</summary>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="resolve">
          <label>What happened and what action was taken?
            <textarea name="outcome" rows="4" required maxlength="3000"></textarea>
          </label>
          <button class="btn btn-success" type="submit">Mark resolved</button>
        </form>
      </details>
      <details class="fold">
        <summary>Mark as false alarm</summary>
        <form method="post" onsubmit="return confirm('Close this case as a false alarm? This adds to the reporter\'s false-alarm count.');">
          <?= csrf_field() ?>
          <input type="hidden" name="do" value="false_alarm">
          <label>Reason <span class="opt">optional</span>
            <input type="text" name="reason" maxlength="500">
          </label>
          <button class="btn btn-danger" type="submit">Close as false alarm</button>
        </form>
      </details>
      <?php elseif ($r['outcome_note']): ?>
        <div class="outcome"><h4>Outcome note</h4><p><?= nl2br(e($r['outcome_note'])) ?></p></div>
      <?php endif; ?>
    </section>

    <section class="card pad">
      <h2>Progress</h2>
      <div data-live="case-timeline"><?php render_timeline($r, $history); ?></div>
    </section>

    <section class="card pad">
      <h2>Message the reporter</h2>
      <p class="muted small">One-way: the reporter reads it on their status screen and cannot reply.</p>
      <div data-live="case-messages">
        <?php if ($messages): ?>
          <?php foreach ($messages as $m): ?>
            <div class="officer-msg"><p><?= nl2br(e($m['body'])) ?></p><small><?= e($m['admin_name']) ?> · <?= e(fmt_dt($m['created_at'])) ?></small></div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="muted">No messages sent yet.</p>
        <?php endif; ?>
      </div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="message">
        <div class="chips">
          <?php foreach ($quick as $q): ?>
            <button type="button" class="chip" data-fill="<?= e($q['body']) ?>"><?= e(short_text($q['body'], 45)) ?></button>
          <?php endforeach; ?>
        </div>
        <label>Message
          <textarea name="body" id="msg-body" rows="3" maxlength="500" required></textarea>
        </label>
        <button class="btn btn-primary" type="submit">Send message</button>
      </form>
    </section>

    <section class="card pad">
      <h2>Internal notes</h2>
      <p class="muted small">Only admins can see these.</p>
      <?php foreach ($notes as $n): ?>
        <div class="note"><p><?= nl2br(e($n['note'])) ?></p><small><?= e($n['admin_name']) ?> · <?= e(fmt_dt($n['created_at'])) ?></small></div>
      <?php endforeach; ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="note">
        <label>Add a note
          <textarea name="note" rows="3" maxlength="4000" required></textarea>
        </label>
        <button class="btn" type="submit">Save note</button>
      </form>
    </section>

  </div>
</div>
<?php include ROOT_PATH . '/includes/layout/admin_footer.php'; ?>
