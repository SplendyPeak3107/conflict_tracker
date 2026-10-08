<?php
function current_user(): ?array
{
    static $loaded = false, $user = null;
    if (!$loaded) {
        $loaded = true;
        if (!empty($_SESSION['uid'])) {
            $st = db()->prepare('SELECT * FROM users WHERE id = ?');
            $st->execute([(int) $_SESSION['uid']]);
            $row = $st->fetch();
            if ($row) {
                $user = $row;
            } else {
                unset($_SESSION['uid']);
            }
        }
    }
    return $user;
}

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $user['id'];
    $st = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
    $st->execute([(int) $user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        redirect('login.php');
    }
    return $u;
}

/** Reporter pages: admins are sent to their own dashboard. */
function require_reporter(): array
{
    $u = require_login();
    if ($u['role'] === 'admin') {
        redirect('admin/dashboard.php');
    }
    return $u;
}

/** Every admin page must call this first. */
function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
    return $u;
}

function is_suspended(array $u): bool
{
    return $u['account_status'] === 'suspended';
}
