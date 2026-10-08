<?php
/** In-app notifications for reporters. */
function create_notification(int $userId, ?int $reportId, string $type, string $body): void
{
    $st = db()->prepare('INSERT INTO notifications (user_id, report_id, type, body) VALUES (?,?,?,?)');
    $st->execute([$userId, $reportId, $type, mb_substr($body, 0, 255, 'UTF-8')]);
}

function unread_count(int $userId): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $st->execute([$userId]);
    return (int) $st->fetchColumn();
}

function get_notifications(int $userId, int $limit = 60): array
{
    $st = db()->prepare(
        'SELECT n.*, r.status AS report_status
         FROM notifications n
         LEFT JOIN reports r ON r.id = n.report_id
         WHERE n.user_id = ?
         ORDER BY n.id DESC LIMIT ' . (int) $limit
    );
    $st->execute([$userId]);
    return $st->fetchAll();
}

function mark_all_read(int $userId): void
{
    $st = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $st->execute([$userId]);
}
