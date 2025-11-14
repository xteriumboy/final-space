<?php
/**
 * Final Space Store — api/fetch_products.php v5.5
 * Returns product list if license valid.
 */
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

$CFG = require __DIR__ . '/../config.php';

// --- License check ---
$verifyUrl = __DIR__ . '/verify_license.php';
$domain = $_SERVER['HTTP_HOST'] ?? '';
$license = trim($CFG['license_code'] ?? '');
if (!is_file($verifyUrl)) {
    echo json_encode(['ok'=>false,'error'=>'missing_license','details'=>'verify_license.php missing']); exit;
}
$verify = @file_get_contents("https://{$domain}/api/verify_license.php");
$ver = json_decode($verify, true);
if (!$ver || empty($ver['ok'])) {
    echo json_encode(['ok'=>false,'error'=>'license_invalid','details'=>$ver['message'] ?? 'Invalid license']); exit;
}

// --- DB connection ---
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $CFG['db_host'], $CFG['db_name']),
        $CFG['db_user'],
        $CFG['db_pass'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
} catch(Throwable $e){
    echo json_encode(['ok'=>false,'error'=>'db_connect','details'=>$e->getMessage()]); exit;
}

// --- Fetch products ---
try {
    $rows = $pdo->query("SELECT id,name,description,price,price_bnb,image,file_path FROM products ORDER BY id DESC")->fetchAll();
    echo json_encode(['ok'=>true,'count'=>count($rows),'products'=>$rows],JSON_UNESCAPED_SLASHES);
} catch(Throwable $e){
    echo json_encode(['ok'=>false,'error'=>'db_error','details'=>$e->getMessage()]);
}
