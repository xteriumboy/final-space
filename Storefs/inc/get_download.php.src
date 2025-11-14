<?php
/**
 * Final Space Store — api/get_download.php v3.7
 * -------------------------------------------------------------
 * Secure product delivery via HMAC token + wallet verification.
 * Compatible with both GET and JSON POST requests.
 *
 * Input:
 *   - wallet: user's wallet address
 *   - pid / product_id: numeric product id
 *
 * Returns JSON:
 *   { ok:true, url:"/api/send_download.php?token=..." }
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/license_core.php';
header('Content-Type: application/json; charset=utf-8');

$CFG = $CFG ?? require __DIR__ . '/../config.php';
$secret = $CFG['auto_seal_secret'] ?? 'fsAutoSeal@2025!';

/* ---------- read params (supports POST JSON + GET) ---------- */
$input = file_get_contents('php://input');
$data  = json_decode($input, true) ?: [];

$wallet = trim($data['wallet'] ?? ($_GET['wallet'] ?? ''));
$pid    = (int)($data['product_id'] ?? ($_GET['pid'] ?? ($_GET['id'] ?? 0)));

if (!$wallet || !$pid) {
    echo json_encode(['ok'=>false,'error'=>'missing_params']);
    exit;
}

/* ---------- connect to DB ---------- */
try {
    $pdo = new PDO(
        "mysql:host={$CFG['db_host']};dbname={$CFG['db_name']};charset=utf8mb4",
        $CFG['db_user'], $CFG['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'db_connection_failed']);
    exit;
}

/* ---------- verify purchase ---------- */
$stmt = $pdo->prepare("SELECT id FROM purchases WHERE wallet=? AND product_id=? AND verified=1");
$stmt->execute([$wallet, $pid]);
$purchase = $stmt->fetch();

if (!$purchase) {
    echo json_encode(['ok'=>false,'error'=>'not_purchased']);
    exit;
}

/* ---------- locate product file ---------- */
$stmt = $pdo->prepare("SELECT file_path,name FROM products WHERE id=?");
$stmt->execute([$pid]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo json_encode(['ok'=>false,'error'=>'product_missing']);
    exit;
}

$file = __DIR__ . '/../' . ltrim($product['file_path'] ?? '', '/');
if (!is_file($file)) {
    echo json_encode(['ok'=>false,'error'=>'file_missing','path'=>$file]);
    exit;
}

/* ---------- create short-lived token ---------- */
$exp = time() + 300; // valid 5 min
$signature = hash_hmac('sha256', $pid . '|' . $wallet . '|' . $exp, $secret);
$token = base64_encode(json_encode([
    'pid' => $pid,
    'wallet' => $wallet,
    'exp' => $exp,
    'sig' => $signature
]));

/* ---------- respond ---------- */
echo json_encode([
    'ok' => true,
    'url' => '/api/send_download.php?token=' . urlencode($token),
    'filename' => basename($file)
], JSON_UNESCAPED_SLASHES);
exit;
