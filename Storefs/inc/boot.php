<?php
/**
 * Final Space Store — inc/boot.php
 * Core bootstrap for all client-side API scripts.
 * ---------------------------------------------------------
 *  - Loads config.php
 *  - Initializes PDO database connection
 *  - Declares helpers: json_ok(), json_err()
 *  - UTF-8 safe output
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$config = $root . '/config.php';
if (!is_file($config)) {
    echo json_encode(['ok'=>false,'error'=>'config_missing']);
    exit;
}
require_once $config;

/* ---------- Database ---------- */
try {
    $DB = new PDO(
        "mysql:host={$CFG['db_host']};dbname={$CFG['db_name']};charset=utf8mb4",
        $CFG['db_user'],
        $CFG['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'db_connect_failed','detail'=>$e->getMessage()]);
    exit;
}

/* ---------- Helpers ---------- */
if (!function_exists('json_ok')) {
    function json_ok(array $a=[]): void {
        echo json_encode(['ok'=>true]+$a, JSON_UNESCAPED_SLASHES);
        exit;
    }
}
if (!function_exists('json_err')) {
    function json_err(string $msg): void {
        echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_SLASHES);
        exit;
    }
}
