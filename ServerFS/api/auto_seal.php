<?php
/**
 * Final Space License Server — auto_seal.php
 * Triggers sealing for the domain automatically (used by store installer).
 */
require_once __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/security.php';

error_reporting(E_ALL);
ini_set('display_errors',1);
header('Content-Type: application/json; charset=utf-8');

$license = trim($_POST['license'] ?? '');
$domain  = trim($_POST['domain'] ?? '');
if(!$license || !$domain){
  echo json_encode(['ok'=>false,'error'=>'missing_params']);exit;
}

$st = $DB->prepare("SELECT id FROM licenses WHERE code=? LIMIT 1");
$st->execute([$license]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if(!$row){
  echo json_encode(['ok'=>false,'error'=>'license_not_found']);exit;
}

$id = intval($row['id']);
$cmd = PHP_BINARY . ' ' . escapeshellarg(__DIR__.'/../admin/reseal_and_verify.php') . ' ' . escapeshellarg("--id=$id") . ' > /dev/null 2>&1 &';
exec($cmd);

echo json_encode(['ok'=>true,'message'=>"Sealing triggered for $domain"]);
