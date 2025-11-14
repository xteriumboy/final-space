<?php
require_once __DIR__.'/../inc/boot.php';
header('Content-Type: application/json');
$code=trim($_POST['license']??''); $domain=strtolower(trim($_POST['domain']??'')); 
$wallet=trim($_POST['wallet']??''); $email=trim($_POST['email']??'');
if(!$code||!$domain){ echo json_encode(['ok'=>false,'error'=>'missing_params']); exit; }
$st=$DB->prepare('SELECT * FROM licenses WHERE code=? LIMIT 1'); $st->execute([$code]);
$lic=$st->fetch(PDO::FETCH_ASSOC);
if(!$lic){ echo json_encode(['ok'=>false,'error'=>'not_found']); exit; }
// Always bind the first time
if(empty($lic['domain'])) $lic['domain']=$domain;
$u=$DB->prepare('UPDATE licenses SET client_wallet=?, client_email=?, domain=? WHERE id=?');
$u->execute([$wallet,$email,$domain,$lic['id']]);
echo json_encode(['ok'=>true,'domain'=>$domain]);
