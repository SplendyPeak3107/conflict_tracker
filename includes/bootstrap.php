<?php
/**
 * Loaded first by every page: config, session, database helpers.
 */
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/config.php';

date_default_timezone_set(APP_TIMEZONE);
error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1');
if (is_dir(ROOT_PATH . '/storage/logs') && is_writable(ROOT_PATH . '/storage/logs')) {
    ini_set('error_log', ROOT_PATH . '/storage/logs/error.log');
}

// ---- Base URL (works whether public/ is the web root or a sub-folder) ----
if (BASE_URL === 'auto') {
    $base = '';
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $pubDir  = realpath(ROOT_PATH . '/public');
    if ($docRoot && $pubDir) {
        $docRoot = str_replace('\\', '/', $docRoot);
        $pubDir  = str_replace('\\', '/', $pubDir);
        if (strncasecmp($pubDir, $docRoot, strlen($docRoot)) === 0) {
            $base = substr($pubDir, strlen($docRoot));
        }
    }
    define('APP_BASE', rtrim($base, '/'));
} else {
    define('APP_BASE', rtrim(BASE_URL, '/'));
}

// ---- Long-lived login session (reporters stay logged in) ----
$lifetime = SESSION_DAYS * 86400;
$sessDir = ROOT_PATH . '/storage/sessions';
if (is_dir($sessDir) && is_writable($sessDir)) {
    session_save_path($sessDir);
}
ini_set('session.gc_maxlifetime', (string) $lifetime);
ini_set('session.use_strict_mode', '1');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
session_set_cookie_params([
    'lifetime' => $lifetime,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name('peacewatch_sid');
session_start();

require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/helpers.php';
require_once ROOT_PATH . '/includes/csrf.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/partials.php';
require_once ROOT_PATH . '/src/users.php';
require_once ROOT_PATH . '/src/reports.php';
require_once ROOT_PATH . '/src/messages.php';
require_once ROOT_PATH . '/src/notifications.php';
require_once ROOT_PATH . '/src/audit.php';