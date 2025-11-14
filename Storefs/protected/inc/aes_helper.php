<?php
/**
 * Final Space — AES Helper v4.2 (Legacy-Compatible, client)
 * Matches the server helper exactly. Adds include_sealed().
 */

declare(strict_types=1);
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

$keyFile = __DIR__ . '/../keys/aes_secret.key';
if (!is_file($keyFile)) { error_log("[FS-AES] Missing AES key at $keyFile"); die('AES key missing'); }
$aesKey = trim((string)@file_get_contents($keyFile));
if (strlen($aesKey) < 16) { die('Invalid AES key length'); }

function fs_aes_encrypt($plain){
    global $aesKey;
    try{ $iv=random_bytes(16); $c=openssl_encrypt($plain,'AES-256-CBC',$aesKey,OPENSSL_RAW_DATA,$iv); if($c===false) return false; return base64_encode($iv.$c); }
    catch(Exception $e){ error_log('[FS-AES] Encrypt error: '.$e->getMessage()); return false; }
}
function fs_aes_decrypt($ciphertext){
    global $aesKey;
    try{ $d=base64_decode($ciphertext,true); if($d===false||strlen($d)<17) return false; $iv=substr($d,0,16); $enc=substr($d,16); $p=openssl_decrypt($enc,'AES-256-CBC',$aesKey,OPENSSL_RAW_DATA,$iv); return ($p!==false)?$p:false; }
    catch(Exception $e){ error_log('[FS-AES] Decrypt error: '.$e->getMessage()); return false; }
}
function include_sealed($relPath){
    $baseDir = dirname(__DIR__,1); $path=$baseDir.'/'.ltrim($relPath,'/\\');
    if(substr($path,-8)==='.php.aes'){
        if(!is_file($path)) die("Missing sealed file: $path");
        $enc=@file_get_contents($path); if($enc===false) die("Cannot read sealed file: $path");
        $plain=fs_aes_decrypt($enc); if($plain===false) die("Decrypt fail: $path");
        $manifestFile=$baseDir.'/manifest.json';
        if(is_file($manifestFile)){
            $man=json_decode(@file_get_contents($manifestFile),true);
            $relClient='protected/'.str_replace($baseDir.'/','',$path);
            $expected=isset($man['files'][$relClient])?$man['files'][$relClient]:'';
            if($expected){ $actual=base64_encode(hash('sha256',$enc,true)); if($expected!==$actual) die("Manifest hash mismatch for $relClient"); }
        }
        $tmp=tempnam(sys_get_temp_dir(),'fsd_'); file_put_contents($tmp,$plain); include $tmp; @unlink($tmp); return;
    }
    include $path;
}
