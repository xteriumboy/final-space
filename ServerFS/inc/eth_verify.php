<?php
function keccak256($msg){ return hash('sha3-256',$msg); }
function eth_address_from_pubkey($pubkey_hex){
  if (strpos($pubkey_hex,'0x')===0) $pubkey_hex=substr($pubkey_hex,2);
  if (strpos($pubkey_hex,'04')===0) $pubkey_hex=substr($pubkey_hex,2);
  $bin=hex2bin($pubkey_hex); $hash=keccak256($bin); return '0x'.substr($hash,-40);
}
function secp256k1_pubkey_to_pem($pubkey_bin){
  $algo=hex2bin('3056301006072a8648ce3d020106052b8104000a034200');
  $spki=$algo.$pubkey_bin;
  return "-----BEGIN PUBLIC KEY-----
".chunk_split(base64_encode($spki),64,"
")."-----END PUBLIC KEY-----
";
}
function rs_to_der($r_hex,$s_hex){
  $r=ltrim($r_hex,'0'); if($r==='')$r='00'; if(hexdec(substr($r,0,2))>=0x80)$r='00'.$r;
  $s=ltrim($s_hex,'0'); if($s==='')$s='00'; if(hexdec(substr($s,0,2))>=0x80)$s='00'.$s;
  $r_bin=hex2bin($r); $s_bin=hex2bin($s);
  $seq = "\x30".chr(4+strlen($r_bin)+strlen($s_bin))."\x02".chr(strlen($r_bin)).$r_bin."\x02".chr(strlen($s_bin)).$s_bin;
  return $seq;
}
function verify_eth_signature_with_pubkey($message,$sig_hex,$pub_hex){
  $prefix="\x19Ethereum Signed Message:\n".strlen($message);
  $hash_hex=keccak256($prefix.$message); $bin_hash=hex2bin($hash_hex);
  if (strpos($pub_hex,'0x')===0) $pub_hex=substr($pub_hex,2);
  if (strpos($pub_hex,'04')!==0) $pub_hex='04'.$pub_hex;
  $pem=secp256k1_pubkey_to_pem(hex2bin($pub_hex));
  $r=substr($sig_hex,2,64); $s=substr($sig_hex,66,64);
  $der=rs_to_der($r,$s);
  $ok=openssl_verify($bin_hash,$der,$pem,OPENSSL_ALGO_SHA256);
  return $ok===1;
}
