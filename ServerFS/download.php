<?php
require_once __DIR__.'/inc/boot.php'; require_once __DIR__.'/inc/ratelimit.php';
ls_rate_limit('download_'.($_GET['token']??'t'));
$token=$_GET['token']??''; if(!$token){http_response_code(400);echo'missing';exit;}
list($h,$p,$s)=explode('.',$token)+[null,null,null]; if(!$h||!$p||!$s){http_response_code(400);echo'invalid';exit;}
$dec=function($x){$pad=strlen($x)%4; if($pad)$x.=str_repeat('=',4-$pad); return json_decode(base64_decode(strtr($x,'-_','+/')),true);};
$hdr=$dec($h); $pl=$dec($p); $sig=base64_decode(strtr($s,'-_','+/'));
$pub=__DIR__.'/keys/server_public.pem'; if(!is_file($pub)){http_response_code(500);echo'no_pub';exit;}
$vk=openssl_pkey_get_public(file_get_contents($pub)); $ok=openssl_verify($h.'.'.$p,$sig,$vk,OPENSSL_ALGO_SHA256); if(!$ok){http_response_code(403);echo'bad_sig';exit;}
if(($pl['exp']??0)<time()){http_response_code(403);echo'expired';exit;}
$pid=intval($pl['product_id']??0); $storage=realpath(__DIR__.'/storage'); if(!$storage){http_response_code(500);echo'no_storage';exit;}
$path=realpath($storage.'/product_'.$pid.'.bin'); if(!$path||strpos($path,$storage)!==0||!is_file($path)){http_response_code(404);echo'missing';exit;}
header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="product_'.$pid.'.bin"');
$fp=fopen($path,'rb'); while(!feof($fp)){ echo fread($fp,8192); flush(); } fclose($fp); exit;
