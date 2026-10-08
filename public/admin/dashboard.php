<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$admin = require_admin();

// ---- Filters (GET) ----
$f = [
    'severity' => array_key_exists(get_str('severity', 10), severities()) ? get_str('severity', 10) : '',
    'status'   => get_str('status', 20) ?: 'active',
    'from'     => valid_date(get_str('from', 10)) ? get_str('from', 10) : '',
    'to'       => valid_date(get_str('to', 10)) ? get_str('to', 10) : '',
    'q'        => get_str('q', 100),
];
if (!in_array($f['status'], ['active', 'closed', 'all'], true) && !array_key_exists($f['status'], statuses())) {
    $f['status'] = 'active';
}

$reports = admin_list_reports($f, 150);
$counts  = report_counts();
$overdue = get_overdue_reports();
mark_overdue_alerted(array_column($overdue, 'id'));

// ---- Data for the map script and the alert logic ----
$markers = [];
foreach ($reports as $r) {
    if ($r['latitude'] === null || $r['longitude'] === null) {
        continue;
    }
    $markers[] = [
        'id'     => (int) $r['id'],
        'lat'    => (float) $r['latitude'],
        'lng'    => (float) $r['longitude'],
        'sev'    => $r['severity'],
        'status' => $r['status'],
        'name'   => $r['reporter_name'],
        'desc'   => short_text($r['description'], 120),
        'ago'    => time_ago($r['created_at']),
        'statusLabel' => status_label($r['status']),
    ];
}
$feed = admin_alert_feed();
$data = [
    'markers'        => $markers,
    'recent_reports' => $feed['recent_reports'],
    'recent_updates' => $feed['recent_updates'],
    'latest_report'  => $feed['latest_report'],
    'latest_update'  => $feed['latest_update'],
    'overdue'        => array_map('intval', array_column($overdue, 'id')),
];

$pageTitle = 'Live map';
$bodyClass = 'admin-dash';
$mainClass = 'full';
$leaflet   = true;
$scripts   = ['js/admin-dashboard.js'];
include ROOT_PATH . '/includes/layout/admin_header.php';
?>
<div id="admin-app"
     data-refresh="<?= (int) ADMIN_REFRESH_SECONDS ?>"
     data-case-url="<?= e(url('admin/report.php?id=')) ?>"
     data-lat="<?= e(MAP_DEFAULT_LAT) ?>" data-lng="<?= e(MAP_DEFAULT_LNG) ?>" data-zoom="<?= (int) MAP_DEFAULT_ZOOM ?>">

  <?php render_flashes(); ?>

  <div class="alertbar">
    <button type="button" id="enable-alerts" class="btn btn-warn">Turn on sound and desktop alerts</button>
    <span id="conn" class="conn ok">Live</span>
    <span class="muted small">Refreshes every <?= (int) ADMIN_REFRESH_SECONDS ?> seconds. Keep this page open.</span>
  </div>

  <div id="overdue-banner" class="overdue-wrap">
    <?php if ($overdue): ?>
      <div class="overdue-banner" role="alert">
        <strong>OVERDUE:</strong>
        <?= count($overdue) ?> critical report<?= count($overdue) > 1 ? 's' : '' ?> not acknowledged for more than <?= (int) OVERDUE_MINUTES ?> minutes.
        <?php foreach ($overdue as $o): ?>
          <a href="<?= e(url('admin/report.php?id=' . (int) $o['id'])) ?>" target="_blank" rel="noopener">#<?= (int) $o['id'] ?> <?= e($o['name']) ?> (<?= e(time_ago($o['created_at'])) ?>)</a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <form method="get" class="filters">
    <label>Search
      <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Name, phone, community, text, #id">
    </label>
    <label>Severity
      <select name="severity">
        <option value="">All</option>
        <?php foreach (severities() as $k => $l): ?>
          <option value="<?= e($k) ?>" <?= $f['severity'] === $k ? 'selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Status
      <select name="status">
        <option value="active" <?= $f['status'] === 'active' ? 'selected' : '' ?>>Open cases</option>
        <option value="closed" <?= $f['status'] === 'closed' ? 'selected' : '' ?>>Closed cases</option>
        <option value="all" <?= $f['status'] === 'all' ? 'selected' : '' ?>>Everything</option>
        <?php foreach (statuses() as $k => $l): ?>
          <option value="<?= e($k) ?>" <?= $f['status'] === $k ? 'selected' : '' ?>>Only: <?= e($l) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>From <input type="date" name="from" value="<?= e($f['from']) ?>"></label>
    <label>To <input type="date" name="to" value="<?= e($f['to']) ?>"></label>
    <div class="filter-actions">
      <button type="submit" class="btn btn-primary">Filter</button>
      <a class="btn" href="<?= e(url('admin/dashboard.php')) ?>">Reset</a>
    </div>
  </form>

  <div class="dash-grid">
    <section class="map-col">
      <div id="map" aria-label="Map of reports"></div>
      <div class="legend">
        <span><i class="dot sev-critical"></i> Critical</span>
        <span><i class="dot sev-medium"></i> Medium</span>
        <span><i class="dot sev-low"></i> Low</span>
        <button type="button" id="fit-all" class="btn btn-small">Show all reports</button>
      </div>
    </section>

    <aside class="feed-col">
      <div id="stats" class="stats">
        <div class="stat sev-critical"><b><?= (int) $counts['critical'] ?></b><span>Critical open</span></div>
        <div class="stat sev-medium"><b><?= (int) $counts['medium'] ?></b><span>Medium open</span></div>
        <div class="stat sev-low"><b><?= (int) $counts['low'] ?></b><span>Low open</span></div>
        <div class="stat"><b><?= (int) $counts['today'] ?></b><span>Reports today</span></div>
      </div>

      <div id="feed" class="feed">
        <?php if (!$reports): ?>
          <div class="empty"><p>No reports match these filters.</p></div>
        <?php endif; ?>
        <?php foreach ($reports as $r):
            $hasLoc = ($r['latitude'] !== null && $r['longitude'] !== null);
        ?>
        <article class="feed-item sev-<?= e($r['severity']) ?> <?= is_closed_status($r['status']) ? 'closed' : '' ?>" data-id="<?= (int) $r['id'] ?>">
          <div class="fi-top">
            <?= badge_severity($r['severity']) ?>
            <?= badge_status($r['status']) ?>
            <time><?= e(time_ago($r['created_at'])) ?></time>
          </div>
          <div class="fi-who"><strong>#<?= (int) $r['id'] ?> <?= e($r['reporter_name']) ?></strong>
            <span class="muted"><?= e($r['reporter_community'] ?: '') ?></span>
          </div>
          <p class="fi-desc <?= $r['description'] ? '' : 'none' ?>"><?= $r['description'] ? e(short_text($r['description'], 160)) : 'No description yet' ?></p>
          <div class="fi-meta">
            <?php if ($hasLoc): ?>
              <span><?= e(number_format((float) $r['latitude'], 5)) ?>, <?= e(number_format((float) $r['longitude'], 5)) ?><?= $r['accuracy_m'] !== null ? ' (±' . (int) $r['accuracy_m'] . ' m)' : '' ?></span>
            <?php else: ?>
              <span class="warn-text">Location unavailable</span>
            <?php endif; ?>
            <?php if ((int) $r['update_count'] > 0): ?><span><?= (int) $r['update_count'] ?> update<?= (int) $r['update_count'] > 1 ? 's' : '' ?></span><?php endif; ?>
          </div>
          <a class="btn btn-small btn-primary" href="<?= e(url('admin/report.php?id=' . (int) $r['id'])) ?>" target="_blank" rel="noopener">Open case</a>
        </article>
        <?php endforeach; ?>
      </div>
    </aside>
  </div>
</div>

<div id="toasts" class="toasts" aria-live="assertive"></div>
<script id="pw-data" type="application/json"><?= json_for_script($data) ?></script>
<?php include ROOT_PATH . '/includes/layout/admin_footer.php'; ?>
