<?php
/**
 * Everything about reports: create, read, update, status changes.
 */

// ------------------------------------------------------------------ create

function add_status_history(int $reportId, string $status, int $changedBy): void
{
    $st = db()->prepare('INSERT INTO report_status_history (report_id, status, changed_by) VALUES (?,?,?)');
    $st->execute([$reportId, $status, $changedBy]);
}

// ne-tap report: only the location is needed. Severity defaults to Medium
function create_report(int $userId, $lat, $lng, $acc): int
{$lat = is_numeric($lat) ? (float) $lat : null;
    $lng = is_numeric($lng) ? (float) $lng : null;
    if ($lat !== null && ($lat < -90 || $lat > 90)) {
        $lat = null;
    }
    if ($lng !== null && ($lng < -180 || $lng > 180)) {
        $lng = null;
    }
    if ($lat === null || $lng === null) {
        $lat = $lng = null;
    }
    $acc = is_numeric($acc) ? min(1000000, max(0, (int) round((float) $acc))) : null;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('INSERT INTO reports (user_id, latitude, longitude, accuracy_m, location_available) VALUES (?,?,?,?,?)');
        $st->execute([
            $userId,
            $lat !== null ? number_format($lat, 7, '.', '') : null,
            $lng !== null ? number_format($lng, 7, '.', '') : null,
            $lat !== null ? $acc : null,
            $lat !== null ? 1 : 0,
        ]);
        $id = (int) $pdo->lastInsertId();
        add_status_history($id, 'received', $userId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $id;
}

/** Seconds since this user's last report, or null if none. Used for the double-tap guard. */
function seconds_since_last_report(int $userId): ?array
{
    $st = db()->prepare('SELECT id, created_at FROM reports WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$userId]);
    $r = $st->fetch();
    if (!$r) {
        return null;
    }
    return ['id' => (int) $r['id'], 'seconds' => time() - strtotime($r['created_at'])];
}

// ------------------------------------------------------------ reporter side

function get_report(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM reports WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function get_user_report(int $id, int $userId): ?array
{
    $st = db()->prepare('SELECT * FROM reports WHERE id = ? AND user_id = ?');
    $st->execute([$id, $userId]);
    return $st->fetch() ?: null;
}

/** Add description / change severity after the one-tap send. Each change is logged as an update for the admin. */
function update_report_details(int $reportId, int $userId, string $description, string $severity): bool
{
    $r = get_user_report($reportId, $userId);
    if (!$r || is_closed_status($r['status'])) {
        return false;
    }
    if (!array_key_exists($severity, severities())) {
        $severity = $r['severity'];
    }
    // An empty box never erases what was already written
    $newDesc = $description !== '' ? $description : (string) $r['description'];

    $descChanged = ($description !== '' && $description !== (string) $r['description']);
    $sevChanged  = ($severity !== $r['severity']);
    if (!$descChanged && !$sevChanged) {
        return true;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('UPDATE reports SET description = ?, severity = ? WHERE id = ?');
        $st->execute([$newDesc !== '' ? $newDesc : null, $severity, $reportId]);
        if ($descChanged) {
            insert_report_update($reportId, $userId, 'Details added: ' . $description);
        }
        if ($sevChanged) {
            insert_report_update($reportId, $userId, 'Reporter changed severity: ' . severity_label($r['severity']) . ' → ' . severity_label($severity));
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return true;
}

function insert_report_update(int $reportId, int $userId, string $body): void
{
    $st = db()->prepare('INSERT INTO report_updates (report_id, user_id, body) VALUES (?,?,?)');
    $st->execute([$reportId, $userId, mb_substr($body, 0, 2000, 'UTF-8')]);
    $st = db()->prepare('UPDATE reports SET updated_at = NOW() WHERE id = ?');
    $st->execute([$reportId]);
}

/** "Post an update" - follow-up on the same case. */
function add_reporter_update(int $reportId, int $userId, string $body): bool
{
    $r = get_user_report($reportId, $userId);
    if (!$r || is_closed_status($r['status']) || trim($body) === '') {
        return false;
    }
    insert_report_update($reportId, $userId, $body);
    return true;
}

function get_status_history(int $reportId): array
{
    $st = db()->prepare('SELECT status, created_at FROM report_status_history WHERE report_id = ? ORDER BY id ASC');
    $st->execute([$reportId]);
    return $st->fetchAll();
}

function get_updates(int $reportId): array
{
    $st = db()->prepare('SELECT * FROM report_updates WHERE report_id = ? ORDER BY id ASC');
    $st->execute([$reportId]);
    return $st->fetchAll();
}

/** Reporter home: open reports plus anything closed in the last 24 hours. Open ones first. */
function get_dashboard_reports(int $userId): array
{
    $cutoff = date('Y-m-d H:i:s', time() - 86400);
    $st = db()->prepare(
        "SELECT * FROM reports
         WHERE user_id = ?
           AND (status NOT IN ('resolved','false_alarm') OR resolved_at >= ?)
         ORDER BY (status IN ('resolved','false_alarm')) ASC, id DESC
         LIMIT 20"
    );
    $st->execute([$userId, $cutoff]);
    return $st->fetchAll();
}

function get_user_reports(int $userId, int $limit = 50): array
{
    $st = db()->prepare('SELECT * FROM reports WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int) $limit);
    $st->execute([$userId]);
    return $st->fetchAll();
}

// --------------------------------------------------------------- admin side

function admin_get_report(int $id): ?array
{
    $st = db()->prepare(
        'SELECT r.*, u.name AS reporter_name, u.email AS reporter_email, u.phone AS reporter_phone,
                u.community AS reporter_community, u.emergency_name, u.emergency_phone,
                u.account_status AS reporter_account_status
         FROM reports r JOIN users u ON u.id = r.user_id WHERE r.id = ?'
    );
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/**
 * Live feed for the admin dashboard, newest first.
 * Filters: severity, status (active|closed|all|<status>), from, to, q
 */
function admin_list_reports(array $f, int $limit = 150): array
{
    $where = [];
    $p = [];
    if (!empty($f['severity'])) {
        $where[] = 'r.severity = ?';
        $p[] = $f['severity'];
    }
    $s = $f['status'] ?? 'active';
    if ($s === 'active') {
        $where[] = "r.status NOT IN ('resolved','false_alarm')";
    } elseif ($s === 'closed') {
        $where[] = "r.status IN ('resolved','false_alarm')";
    } elseif (array_key_exists($s, statuses())) {
        $where[] = 'r.status = ?';
        $p[] = $s;
    }
    if (!empty($f['from'])) {
        $where[] = 'r.created_at >= ?';
        $p[] = $f['from'] . ' 00:00:00';
    }
    if (!empty($f['to'])) {
        $where[] = 'r.created_at <= ?';
        $p[] = $f['to'] . ' 23:59:59';
    }
    if (!empty($f['q'])) {
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        $cond = 'u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.community LIKE ? OR r.description LIKE ?';
        array_push($p, $like, $like, $like, $like, $like);
        if (ctype_digit($f['q'])) {
            $cond .= ' OR r.id = ?';
            $p[] = (int) $f['q'];
        }
        $where[] = '(' . $cond . ')';
    }
    $sql = 'SELECT r.*, u.name AS reporter_name, u.phone AS reporter_phone, u.community AS reporter_community,
                   (SELECT COUNT(*) FROM report_updates ru WHERE ru.report_id = r.id) AS update_count
            FROM reports r JOIN users u ON u.id = r.user_id';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY r.id DESC LIMIT ' . (int) $limit;
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}

function report_counts(): array
{
    $out = ['critical' => 0, 'medium' => 0, 'low' => 0, 'today' => 0];
    $rows = db()->query("SELECT severity, COUNT(*) c FROM reports WHERE status NOT IN ('resolved','false_alarm') GROUP BY severity")->fetchAll();
    foreach ($rows as $row) {
        $out[$row['severity']] = (int) $row['c'];
    }
    $st = db()->prepare('SELECT COUNT(*) FROM reports WHERE created_at >= ?');
    $st->execute([date('Y-m-d') . ' 00:00:00']);
    $out['today'] = (int) $st->fetchColumn();
    return $out;
}

/** Data the dashboard script uses to decide whether to sound an alert (never filtered). */
function admin_alert_feed(): array
{
    $rr = db()->query(
        'SELECT r.id, r.severity, r.status, r.description, r.created_at, u.name
         FROM reports r JOIN users u ON u.id = r.user_id ORDER BY r.id DESC LIMIT 20'
    )->fetchAll();
    $uu = db()->query(
        'SELECT ru.id, ru.report_id, ru.body, u.name
         FROM report_updates ru JOIN users u ON u.id = ru.user_id ORDER BY ru.id DESC LIMIT 20'
    )->fetchAll();

    $reports = [];
    foreach ($rr as $r) {
        $reports[] = [
            'id' => (int) $r['id'], 'severity' => $r['severity'], 'name' => $r['name'],
            'text' => short_text($r['description'], 100),
        ];
    }
    $updates = [];
    foreach ($uu as $u) {
        $updates[] = [
            'id' => (int) $u['id'], 'report_id' => (int) $u['report_id'], 'name' => $u['name'],
            'text' => short_text($u['body'], 100),
        ];
    }
    return [
        'recent_reports' => $reports,
        'recent_updates' => $updates,
        'latest_report'  => $reports ? $reports[0]['id'] : 0,
        'latest_update'  => $updates ? $updates[0]['id'] : 0,
    ];
}

/** Critical reports nobody has acknowledged within OVERDUE_MINUTES. */
function get_overdue_reports(): array
{
    $cutoff = date('Y-m-d H:i:s', time() - OVERDUE_MINUTES * 60);
    $st = db()->prepare(
        "SELECT r.id, r.created_at, r.overdue_alerted_at, u.name
         FROM reports r JOIN users u ON u.id = r.user_id
         WHERE r.severity = 'critical' AND r.status = 'received' AND r.created_at <= ?
         ORDER BY r.id ASC"
    );
    $st->execute([$cutoff]);
    return $st->fetchAll();
}

/** Record (once) that the OVERDUE alarm has been raised. */
function mark_overdue_alerted(array $ids): void
{
    if (!$ids) {
        return;
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("UPDATE reports SET overdue_alerted_at = NOW() WHERE overdue_alerted_at IS NULL AND id IN ($in)");
    $st->execute(array_map('intval', $ids));
}

/**
 * Change a case's status. 'resolved' needs an outcome note; 'false_alarm' may carry a reason.
 * Writes history, tells the reporter and logs the admin action.
 */
function change_status(int $reportId, int $adminId, string $new, ?string $note = null): bool
{
    if (!array_key_exists($new, statuses())) {
        return false;
    }
    $r = get_report($reportId);
    if (!$r || $r['status'] === $new) {
        return false;
    }
    if ($new === 'resolved' && ($note === null || trim($note) === '')) {
        return false;
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($new === 'resolved' || $new === 'false_alarm') {
            $st = $pdo->prepare(
                'UPDATE reports SET status = ?, outcome_note = ?, resolved_at = NOW(),
                        acknowledged_at = COALESCE(acknowledged_at, NOW()) WHERE id = ?'
            );
            $st->execute([$new, ($note !== null && trim($note) !== '') ? $note : null, $reportId]);
        } elseif ($new === 'received') {
            $st = $pdo->prepare('UPDATE reports SET status = ?, resolved_at = NULL WHERE id = ?');
            $st->execute([$new, $reportId]);
        } else {
            $st = $pdo->prepare(
                'UPDATE reports SET status = ?, resolved_at = NULL,
                        acknowledged_at = COALESCE(acknowledged_at, NOW()) WHERE id = ?'
            );
            $st->execute([$new, $reportId]);
        }
        add_status_history($reportId, $new, $adminId);

        if ($new === 'resolved') {
            $msg = 'Report #' . $reportId . ' has been resolved.';
        } elseif ($new === 'false_alarm') {
            $msg = 'Report #' . $reportId . ' was closed as a false alarm.';
        } else {
            $msg = 'Report #' . $reportId . ' is now: ' . status_label($new) . '.';
        }
        create_notification((int) $r['user_id'], $reportId, 'status_change', $msg);

        $detail = status_label($r['status']) . ' → ' . status_label($new);
        if ($note !== null && trim($note) !== '') {
            $detail .= ' | ' . short_text($note, 200);
        }
        audit_log($adminId, $new === 'resolved' ? 'resolve' : ($new === 'false_alarm' ? 'false_alarm' : 'status_change'), $reportId, (int) $r['user_id'], $detail);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return true;
}

/** Admin re-ranks severity (may differ from the reporter's own view). */
function change_severity(int $reportId, int $adminId, string $sev): bool
{
    if (!array_key_exists($sev, severities())) {
        return false;
    }
    $r = get_report($reportId);
    if (!$r || $r['severity'] === $sev) {
        return false;
    }
    $st = db()->prepare('UPDATE reports SET severity = ? WHERE id = ?');
    $st->execute([$sev, $reportId]);
    audit_log($adminId, 'severity_change', $reportId, (int) $r['user_id'], severity_label($r['severity']) . ' → ' . severity_label($sev));
    return true;
}

// ----------------------------------------------------------- internal notes

function add_note(int $reportId, int $adminId, string $note): bool
{
    $note = trim($note);
    if ($note === '' || !get_report($reportId)) {
        return false;
    }
    $st = db()->prepare('INSERT INTO report_notes (report_id, admin_id, note) VALUES (?,?,?)');
    $st->execute([$reportId, $adminId, mb_substr($note, 0, 4000, 'UTF-8')]);
    audit_log($adminId, 'note_added', $reportId, null, short_text($note, 200));
    return true;
}

function get_notes(int $reportId): array
{
    $st = db()->prepare('SELECT n.*, a.name AS admin_name FROM report_notes n JOIN users a ON a.id = n.admin_id WHERE n.report_id = ? ORDER BY n.id DESC');
    $st->execute([$reportId]);
    return $st->fetchAll();
}
