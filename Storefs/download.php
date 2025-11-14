<?php
require_once __DIR__ . '/inc/license_core.php';
$token = preg_replace('/[^a-z0-9]/','', $_GET['token'] ?? '');
if(!$token){ http_response_code(404); exit('Not found'); }
$dir = __DIR__ . '/data'; $file = $dir . '/download_tokens.json';
if(!is_file($file)){ http_response_code(404); exit('Not found'); }
$store = json_decode(file_get_contents($file),true) ?: [];
if(empty($store[$token])){ http_response_code(404); exit('Not found'); }
$rec = $store[$token]; if($rec['expires']<time()){ unset($store[$token]); file_put_contents($file,json_encode($store)); http_response_code(403); exit('Expired'); }
$CFG = require __DIR__ . '/config.php';
$pdo = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',$CFG['db_host'],$CFG['db_name']),$CFG['db_user'],$CFG['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$st = $pdo->prepare("SELECT file_path FROM products WHERE id=? LIMIT 1"); $st->execute([(int)$rec['product_id']]); $row=$st->fetch();
if(!$row||empty($row['file_path'])){ http_response_code(404); exit('File not set'); }
$path = __DIR__ . $row['file_path']; if(!is_file($path)){ http_response_code(404); exit('File missing'); }
unset($store[$token]); file_put_contents($file,json_encode($store));
header('Content-Description: File Transfer'); header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.basename($path).'"'); header('Content-Length: '.filesize($path));
readfile($path);
