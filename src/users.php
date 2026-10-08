<?php
function find_user(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function find_user_by_email(string $email): ?array
{
    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([mb_strtolower($email, 'UTF-8')]);
    return $st->fetch() ?: null;
}

function create_reporter(array $d): int
{
    $st = db()->prepare(
        'INSERT INTO users (name, email, phone, community, emergency_name, emergency_phone, password_hash, role)
         VALUES (?,?,?,?,?,?,?,\'reporter\')'
    );
    $st->execute([
        $d['name'],
        mb_strtolower($d['email'], 'UTF-8'),
        $d['phone'],
        $d['community'] !== '' ? $d['community'] : null,
        $d['emergency_name'] !== '' ? $d['emergency_name'] : null,
        $d['emergency_phone'] !== '' ? $d['emergency_phone'] : null,
        password_hash($d['password'], PASSWORD_DEFAULT),
    ]);
    return (int) db()->lastInsertId();
}

function update_profile(int $id, array $d): void
{
    $st = db()->prepare('UPDATE users SET name=?, phone=?, community=?, emergency_name=?, emergency_phone=? WHERE id=?');
    $st->execute([
        $d['name'],
        $d['phone'],
        $d['community'] !== '' ? $d['community'] : null,
        $d['emergency_name'] !== '' ? $d['emergency_name'] : null,
        $d['emergency_phone'] !== '' ? $d['emergency_phone'] : null,
        $id,
    ]);
}

function change_password(int $id, string $new): void
{
    $st = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $st->execute([password_hash($new, PASSWORD_DEFAULT), $id]);
}

function set_account_status(int $id, string $status, ?string $reason): void
{
    $st = db()->prepare('UPDATE users SET account_status = ?, suspended_reason = ? WHERE id = ?');
    $st->execute([$status, $status === 'suspended' ? $reason : null, $id]);
}

function user_stats(int $id): array
{
    $st = db()->prepare(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(status = 'false_alarm'),0) AS false_alarms,
                COALESCE(SUM(status = 'resolved'),0) AS resolved,
                COALESCE(SUM(severity = 'critical'),0) AS critical
         FROM reports WHERE user_id = ?"
    );
    $st->execute([$id]);
    return $st->fetch();
}

function search_reporters(string $q = '', int $limit = 200): array
{
    $sql = "SELECT u.*,
                   (SELECT COUNT(*) FROM reports r WHERE r.user_id = u.id) AS total_reports,
                   (SELECT COUNT(*) FROM reports r WHERE r.user_id = u.id AND r.status = 'false_alarm') AS false_alarms
            FROM users u WHERE u.role = 'reporter'";
    $p = [];
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $sql .= ' AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.community LIKE ?)';
        array_push($p, $like, $like, $like, $like);
    }
    $sql .= ' ORDER BY u.id DESC LIMIT ' . (int) $limit;
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}
