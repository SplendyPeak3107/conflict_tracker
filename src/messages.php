<?php
/** Admin -> reporter messages (one-way) and quick-reply templates. */
function add_message(int $reportId, int $adminId, string $body): bool
{
    $body = trim($body);
    if ($body === '') {
        return false;
    }
    $r = get_report($reportId);
    if (!$r) {
        return false;
    }
    $st = db()->prepare('INSERT INTO messages (report_id, admin_id, body) VALUES (?,?,?)');
    $st->execute([$reportId, $adminId, mb_substr($body, 0, 500, 'UTF-8')]);
    create_notification((int) $r['user_id'], $reportId, 'message', 'New message about report #' . $reportId . ': ' . short_text($body, 120));
    audit_log($adminId, 'message_sent', $reportId, (int) $r['user_id'], short_text($body, 200));
    return true;
}

function get_messages(int $reportId): array
{
    $st = db()->prepare('SELECT m.*, a.name AS admin_name FROM messages m JOIN users a ON a.id = m.admin_id WHERE m.report_id = ? ORDER BY m.id ASC');
    $st->execute([$reportId]);
    return $st->fetchAll();
}

function get_quick_replies(): array
{
    return db()->query('SELECT * FROM quick_replies ORDER BY sort_order, id')->fetchAll();
}

function add_quick_reply(string $body): void
{
    $max = (int) db()->query('SELECT COALESCE(MAX(sort_order),0) FROM quick_replies')->fetchColumn();
    $st = db()->prepare('INSERT INTO quick_replies (body, sort_order) VALUES (?, ?)');
    $st->execute([mb_substr(trim($body), 0, 500, 'UTF-8'), min(255, $max + 1)]);
}

function delete_quick_reply(int $id): void
{
    $st = db()->prepare('DELETE FROM quick_replies WHERE id = ?');
    $st->execute([$id]);
}
