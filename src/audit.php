<?php
/** Admin audit trail (table: report_log). */
function audit_log(int $adminId, string $action, ?int $reportId = null, ?int $targetUser = null, ?string $detail = null): void
{
    $st = db()->prepare('INSERT INTO report_log (admin_id, report_id, target_user, action, detail) VALUES (?,?,?,?,?)');
    $st->execute([$adminId, $reportId, $targetUser, $action, $detail !== null ? mb_substr($detail, 0, 500, 'UTF-8') : null]);
}

function get_audit(?int $reportId = null, int $limit = 300): array
{
    $sql = 'SELECT l.*, a.name AS admin_name, t.name AS target_name
            FROM report_log l
            JOIN users a ON a.id = l.admin_id
            LEFT JOIN users t ON t.id = l.target_user';
    $p = [];
    if ($reportId) {
        $sql .= ' WHERE l.report_id = ?';
        $p[] = $reportId;
    }
    $sql .= ' ORDER BY l.id DESC LIMIT ' . (int) $limit;
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}
