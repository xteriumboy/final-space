<?php
require_once __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/security.php';
header('Content-Type: application/json');

$wallet = trim($_POST['wallet'] ?? '');
$tx = trim($_POST['tx'] ?? '');
if(!$wallet || !$tx){echo json_encode(['ok'=>false,'error'=>'missing']);exit;}

// Demo-only: in production verify on-chain
if(strlen($tx) < 32){echo json_encode(['ok'=>false,'error'=>'invalid_tx']);exit;}

echo json_encode(['ok'=>true,'confirmed'=>true]);
