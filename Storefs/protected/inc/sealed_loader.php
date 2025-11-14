<?php
/**
 * Final Space — sealed_loader.php v3.9
 * Universal AES loader for wrappers. JSON-safe, PHP 8.0–8.3.
 */
declare(strict_types=1);
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors','0');

$aesKeyFile = __DIR__ . '/keys/aes_secret.key';
if (!is_file($aesKeyFile)){
    http_response_code(500); echo json_encode(['ok'=>false,'error'=>'aes_secret_missing']); exit;
}
$aesKey = trim(file_get_contents($aesKeyFile));
if ($aesKey === '' || strlen($aesKey) < 16){
    http_response_code(500); echo json_encode(['ok'=>false,'error'=>'invalid_aes_key']); exit;
}

function fs_aes_encrypt(string $plain): string|false{
    global $aesKey; $iv=random_bytes(16);
    $c=openssl_encrypt($plain,'AES-256-CBC',$aesKey,OPENSSL_RAW_DATA,$iv);
    if($c===false) return false; return base64_encode($iv.$c);
}
function fs_aes_decrypt(string $b64): string|false{
    global $aesKey; $bin=base64_decode($b64,true);
    if($bin===false || strlen($bin)<17) return false;
    $iv=substr($bin,0,16); $c=substr($bin,16);
    $p=openssl_decrypt($c,'AES-256-CBC',$aesKey,OPENSSL_RAW_DATA,$iv);
    return ($p===false)?false:$p;
}

function include_sealed(string $path): void{
    $full = dirname(__DIR__) . '/' . ltrim($path,'/');
    if(!is_file($full)){ http_response_code(404); echo json_encode(['ok'=>false,'error'=>'sealed_file_missing','path'=>$path]); exit; }
    $cipher = file_get_contents($full);
    $plain  = fs_aes_decrypt($cipher);
    if($plain===false || trim($plain)===''){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'decryption_failed','file'=>$path]); exit; }

    ob_start();
    try { eval('?>'.$plain); } catch(Throwable $e){ ob_end_clean(); http_response_code(500); echo json_encode(['ok'=>false,'error'=>'eval_failed','msg'=>$e->getMessage()]); exit; }
    $out = ob_get_clean();

    if(strlen($out) && ($out[0]==='{' || $out[0]==='[')){
        header('Content-Type: application/json; charset=utf-8'); echo $out;
    } else {
        echo $out;
    }
}
