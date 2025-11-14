<?php
/**
 * Final Space Store — register_purchase.php v5.5 (CFG Array Fix)
 * Compatible with your `purchases` schema.
 * Fixes undefined $CFG warnings and inserts correctly.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

$config = require __DIR__ . '/../config.php';
if (!is_array($config)) {
    echo json_encode(['ok' => false, 'error' => 'config_not_loaded']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

function json_response($arr) {
    echo json_encode($arr, JSON_UNESCAPED_SLASHES);
    exit;
}

$debugFile = __DIR__ . '/../data/register_purchase_debug.txt';

try {
    $raw = file_get_contents('php://input');
    file_put_contents($debugFile, date('Y-m-d H:i:s') . " RAW: " . $raw . "\n", FILE_APPEND);

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        file_put_contents($debugFile, "Invalid JSON input\n", FILE_APPEND);
        json_response(['ok' => false, 'error' => 'invalid_json']);
    }

    $wallet     = trim($data['wallet'] ?? '');
    $txhash     = trim($data['tx'] ?? '');
    $product_id = intval($data['product_id'] ?? 0);

    file_put_contents($debugFile, "Wallet=$wallet Tx=$txhash Product=$product_id\n", FILE_APPEND);

    if (!$wallet || !$txhash || !$product_id) {
        file_put_contents($debugFile, "Missing fields detected\n", FILE_APPEND);
        json_response(['ok' => false, 'error' => 'missing_fields']);
    }

    // --- DB connect using loaded config array ---
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // --- Check for duplicates ---
    $check = $pdo->prepare("SELECT id FROM purchases WHERE txhash=? LIMIT 1");
    $check->execute([$txhash]);
    if ($check->fetch()) {
        file_put_contents($debugFile, "Duplicate TX found\n", FILE_APPEND);
        json_response(['ok' => true, 'note' => 'already_registered']);
    }

    // --- Insert purchase ---
    $ins = $pdo->prepare("
        INSERT INTO purchases (wallet, product_id, txhash, verified, created_at)
        VALUES (?, ?, ?, 1, NOW())
    ");
    $ok = $ins->execute([$wallet, $product_id, $txhash]);

    if (!$ok) {
        $err = $ins->errorInfo();
        file_put_contents($debugFile, "Insert failed: " . print_r($err, true) . "\n", FILE_APPEND);
        json_response(['ok' => false, 'error' => 'insert_failed']);
    }

    file_put_contents($debugFile, "Success inserted\n", FILE_APPEND);
    json_response(['ok' => true, 'product_id' => $product_id, 'tx' => $txhash]);
} catch (Throwable $e) {
    file_put_contents($debugFile, "EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
    json_response(['ok' => false, 'error' => 'server_error']);
}
