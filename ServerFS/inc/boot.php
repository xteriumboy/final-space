<?php
/**
 * Final Space License Server — boot.php v7.2 FINAL
 * Safe initialization for all admin/API scripts.
 * - Loads root /config.php
 * - Initializes PDO (UTF-8, exception mode)
 * - Starts secure session in /data/sessions
 * - Loads legacy compatibility helpers
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

/* ---------- Root paths ---------- */
$root = dirname(__DIR__);
$cfgFile = $root . '/config.php';
if (!is_file($cfgFile)) {
    die("<pre style='color:#f87171;background:#000;padding:10px'>❌ Missing config.php in root</pre>");
}

/* ---------- Load configuration ---------- */
require $cfgFile;
if (!isset($CFG) || !is_array($CFG)) {
    die("<pre style='color:#f87171;background:#000;padding:10px'>❌ Invalid config.php (missing \$CFG array)</pre>");
}

/* ---------- Timezone ---------- */
date_default_timezone_set($CFG['time_zone'] ?? 'UTC');

/* ---------- Database connection ---------- */
try {
    $dbHost = $CFG['db_host'] ?? null;
    $dbName = $CFG['db_name'] ?? null;
    $dbUser = $CFG['db_user'] ?? null;
    $dbPass = $CFG['db_pass'] ?? null;

    if (!$dbHost || !$dbName || !$dbUser) {
        throw new Exception("Missing database setting: db_host/db_name/db_user");
    }

    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $DB = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (Throwable $e) {
    $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    die("<pre style='color:#f87171;background:#000;padding:10px'>❌ DB connection failed:<br>$msg</pre>");
}

/* ---------- Sessions ---------- */
$sessionDir = $CFG['paths']['sessions'] ?? ($root . '/data/sessions');
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0775, true);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_save_path($sessionDir);
    @session_start();
}

/* ---------- Utility helpers ---------- */
if (!function_exists('fs_val')) {
    function fs_val($v): string {
        return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('fs_json')) {
    function fs_json($data, $exit = true) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        if ($exit) exit;
    }
}

/* ---------- Optional paths ---------- */
$logDir = $CFG['paths']['logs'] ?? ($root . '/data/logs');
if (!is_dir($logDir)) @mkdir($logDir, 0775, true);

/* ---------- Include Security + Legacy ---------- */
require_once __DIR__ . '/security.php';
$legacy = __DIR__ . '/compat_legacy.php';
if (is_file($legacy)) require_once $legacy;

/* ---------- Done ---------- */
