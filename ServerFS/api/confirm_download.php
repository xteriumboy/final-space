<?php
require_once __DIR__.'/../inc/boot.php'; require_once __DIR__.'/../inc/ratelimit.php'; require_once __DIR__.'/../inc/eth_verify.php';
ls_cors(); ls_rate_limit('confirm_'.($_SERVER['REMOTE_ADDR']??'ip'));
$wallet=strtolower(trim($_POST['wallet']??'')); $product_id=intval($_POST['product_id']??0); $license=trim($_POST['license']??''); $domain=strtolower(trim($_POST['domain']??''));
$message=(string)($_POST['message']??''); $sig=trim($_POST['signature_hex']??''); $pub=trim($_POST['public_key']??'');
if(!$wallet||!$product_id||!$license||!$domain||!$message||!$sig||!$pub) ls_json(['ok'=>false,'error'=>'missing_params'],400);
$st=$DB->prepare('SELECT * FROM licenses WHERE code=? LIMIT 1'); $st->execute([$license]); $lic=$st->fetch();
if(!$lic||strtolower($lic['status'])!=='active') ls_json(['ok'=>false,'error'=>'license_invalid'],403);
$pu=$DB->prepare('SELECT verified FROM purchases WHERE wallet=? AND product_id=? LIMIT 1'); $pu->execute([$wallet,$product_id]); $p=$pu->fetch();
if(!$p||intval($p['verified'])!==1) ls_json(['ok'=>false,'error'=>'not_purchased'],403);
$okf=(strpos($message,'FinalSpace Download Confirmation')===0)&&(strpos($message,'Domain: '.$domain)!==false)&&(strpos($message,'License: '.$license)!==false)&&(strpos($message,'Product: '.$product_id)!==false);
if(!$okf) ls_json(['ok'=>false,'error'=>'bad_message_format'],400);
if(preg_match('/Time:\s*(\d{10})/',$message,$mm)){ $t=intval($mm[1]); if(abs(time()-$t)>300) ls_json(['ok'=>false,'error'=>'stale_request'],403); }
$ok=verify_eth_signature_with_pubkey($message,$sig,$pub); if(!$ok) ls_json(['ok'=>false,'error'=>'verify_failed'],403);
$derived=strtolower(eth_address_from_pubkey($pub)); if($derived!==$wallet) ls_json(['ok'=>false,'error'=>'address_mismatch','derived'=>$derived],403);
$priv=__DIR__.'/../keys/server_private.pem'; if(!is_file($priv)) ls_json(['ok'=>false,'error'=>'no_private_key'],500);
$CFG = require __DIR__.'/../config.php';
$ttl=intval(($CFG['jwt']['ttl']??600)); $pl=['iss'=>$CFG['jwt']['issuer']??($_SERVER['HTTP_HOST']??'licenses'),'sub'=>'download','domain'=>$domain,'product_id'=>$product_id,'wallet'=>$wallet,'iat'=>time(),'exp'=>time()+$ttl];
$hdr=['alg'=>'RS256','typ'=>'JWT']; $enc=function($d){return rtrim(strtr(base64_encode(json_encode($d)),'+/','-_'),'=');}; $tos=$enc($hdr).'.'.$enc($pl);
$pk=openssl_pkey_get_private(file_get_contents($priv)); $sb=''; openssl_sign($tos,$sb,$pk,OPENSSL_ALGO_SHA256);
$jwt=$tos.'.'.rtrim(strtr(base64_encode($sb),'+/','-_'),'='); $dl='/download.php?token='.urlencode($jwt);
ls_log('confirm_ok','wallet='.$wallet.' product='.$product_id); ls_json(['ok'=>true,'download'=>$dl,'token'=>$jwt]);
