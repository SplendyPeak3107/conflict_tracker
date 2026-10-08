<?php
// Escape for HTML output
function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return APP_BASE . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = ROOT_PATH . '/public/assets/' . $path;
    $v = is_file($file) ? '?v=' . filemtime($file) : '';
    return url('assets/' . $path) . $v;
}

function redirect(string $path)
{
    header('Location: ' . url($path));
    exit();
}

function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** Trimmed POST text, cut to $max characters. Keeps line breaks. */
function post_str(string $key, int $max = 255): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    $v = trim(str_replace("\0", '', $v));
    return mb_substr($v, 0, $max, 'UTF-8');
}

function get_str(string $key, int $max = 100): string
{
    $v = $_GET[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    return mb_substr(trim($v), 0, $max, 'UTF-8');
}

// ---- Flash messages ----
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function pull_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---- Severity and status vocabulary ----
function severities(): array
{
    return ['critical' => 'Critical', 'medium' => 'Medium', 'low' => 'Low'];
}

function severity_label(string $s): string
{
    $all = severities();
    return $all[$s] ?? ucfirst($s);
}

function statuses(): array
{
    return [
        'received' => 'Received',
        'acknowledged' => 'Acknowledged',
        'dispatched' => 'Officer dispatched',
        'on_scene' => 'On scene',
        'resolved' => 'Resolved',
        'false_alarm' => 'False alarm',
    ];
}

function status_label(string $s): string
{
    $all = statuses();
    return $all[$s] ?? ucfirst($s);
}

function is_closed_status(string $s): bool
{
    return $s === 'resolved' || $s === 'false_alarm';
}

// ---- Time ----
function time_ago(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return '';
    }
    $d = time() - $ts;
    if ($d < 45) {
        return 'just now';
    }
    if ($d < 3600) {
        $m = max(1, (int) round($d / 60));
        return $m . ' min ago';
    }
    if ($d < 86400) {
        return (int) floor($d / 3600) .
            ' h ' .
            (int) floor(($d % 3600) / 60) .
            ' min ago';
    }
    if ($d < 86400 * 7) {
        return (int) floor($d / 86400) . ' day(s) ago';
    }
    return date('d M Y', $ts);
}

function fmt_dt(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    return $ts === false ? '' : date('d M Y, H:i', $ts);
}

function fmt_time(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    return $ts === false ? '' : date('H:i', $ts);
}

// ---- Misc ----
function maps_link($lat, $lng): string
{
    return 'https://www.google.com/maps?q=' .
        rawurlencode((string) $lat) .
        ',' .
        rawurlencode((string) $lng);
}

/** Safe JSON for embedding inside a <script type="application/json"> tag. */
function json_for_script($data): string
{
    return json_encode(
        $data,
        JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT |
            JSON_UNESCAPED_UNICODE |
            JSON_INVALID_UTF8_SUBSTITUTE,
    );
}

function valid_date(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

function short_text(?string $t, int $len = 140): string
{
    $t = trim((string) $t);
    if (mb_strlen($t, 'UTF-8') <= $len) {
        return $t;
    }
    return mb_substr($t, 0, $len - 1, 'UTF-8') . '…';
}
