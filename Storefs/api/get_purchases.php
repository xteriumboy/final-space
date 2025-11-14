<?php
/**
 * Final Space Store — api/get_purchases.php v3.3 (Full Fix)
 * ---------------------------------------------------------
 * Returns purchased product IDs for a wallet.
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/license_core.php';

$wallet = trim($_GET['wallet'] ?? '');
if ($wallet === '') {
    echo json_encode(['ok'=>false,'error'=>'missing_wallet','tip'=>'use ?wallet=0x...']);
    exit;
}

try {
    $stmt = $DB->prepare('SELECT DISTINCT product_id FROM purchases WHERE wallet = :w AND verified = 1');
    $stmt->execute([':w'=>$wallet]);
    $ids = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ids[] = (int)$r['product_id'];
    }

    echo json_encode(['ok'=>true,'ids'=>$ids], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'db_error','detail'=>$e->getMessage()]);
}
