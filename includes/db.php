<?php
// Single PDO connection shared by the whole request
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = require ROOT_PATH . '/config/database.php';
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['name'], $c['charset']);
    try {
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('<h2>Cannot connect to the database.</h2><p>Check <code>config/database.php</code> and make sure the database exists and <code>database/schema.sql</code> was imported.</p>');
    }
    // Keep MySQL clock in the same timezone as PHP
    $offset = (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('P');
    $pdo->exec("SET time_zone = '" . $offset . "'");
    return $pdo;
}
