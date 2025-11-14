<?php
/**
 * Final Space Store — api/send_download.php v3.7
 * Streams product file for valid HMAC download token.
 */

declare(strict_types=1);
error_reporting(0);

require_once __DIR__ . '/../inc/boot.php';
$CFG = $CFG ?? require __DIR__ . '/../config.php';
$secret = $CFG['auto_seal_secret'] ?? 'fsAutoSeal@2025!';

$tokenRaw = $_GET['token'] ?? '';
if (!$tokenRaw) {
    http_response_code(403);
    exit('Missing token');
}

$data = json_decode(base64_decode($tokenRaw), true);
if (!is_array($data) || empty($data['pid']) || empty($data['exp']) || empty($data['sig'])) {
    http_response_code(403);
    exit('Malformed token');
}

$pid    = (int)$data['pid'];
$wallet = trim($data['wallet'] ?? '');
$exp    = (int)$data['exp'];

if (time() > $exp) {
    http_response_code(403);
    exit('Token expired');
}

$expected = hash_hmac('sha256', $pid . '|' . $wallet . '|' . $exp, $secret);
if (!hash_equals($expected, $data['sig'])) {
    http_response_code(403);
    exit('Signature mismatch');
}

/* ---------- fetch file path ---------- */
try {
    $pdo = new PDO(
        "mysql:host={$CFG['db_host']};dbname={$CFG['db_name']};charset=utf8mb4",
        $CFG['db_user'], $CFG['db_pass'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
    );
    $stmt = $pdo->prepare("SELECT file_path FROM products WHERE id=?");
    $stmt->execute([$pid]);
    $path = $stmt->fetchColumn();
} catch (Throwable $e) {
    http_response_code(500);
    exit('Database error');
}

$file = __DIR__ . '/../' . ltrim($path, '/');
if (!is_file($file)) {
    http_response_code(404);
    exit('File not found');
}

/* ---------- send file ---------- */
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.basename($file).'"');
header('Content-Length: '.filesize($file));
readfile($file);
exit;
