<?php
/**
 * Final Space Store — receive_manifest.php v5.5
 * Accepts: manifest.json + manifest.sig + server_public.pem
 * Auth: X-FS-Auth header (shared secret)
 */

declare(strict_types=1);
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

$base     = dirname(__DIR__);
$logFile  = $base . '/data/receive_log.txt';
$protected= $base . '/protected';
$keysDir  = $protected . '/keys';
@mkdir($keysDir, 0755, true);

function fs_log(string $s){global $logFile;@file_put_contents($logFile,'['.gmdate('Y-m-d H:i:s').' UTC] '.$s."\n",FILE_APPEND);}

/* Auth */
$CFG           = @include $base . '/config.php';
$expectedAuth  = (string)($CFG['auto_seal_secret'] ?? 'fsAutoSeal@2025!');
$authHeader    = $_SERVER['HTTP_X_FS_AUTH'] ?? '';
if ($authHeader !== $expectedAuth){
    http_response_code(403);
    fs_log("[DENY] Bad auth from IP ".($_SERVER['REMOTE_ADDR'] ?? ''));
    echo json_encode(['ok'=>false,'error'=>'auth_failed']); exit;
}

/* Dynamic allow (host or posted domain) */
$ip     = $_SERVER['REMOTE_ADDR'] ?? '';
$host   = gethostbyaddr($ip) ?: '';
$domain = strtolower(trim($_POST['domain'] ?? ''));
$allowedHosts = array_values(array_unique(array_filter([
    'licenses.final-space.com',
    $host ?: null,
    parse_url('https://'.$domain, PHP_URL_HOST),
])));

if (!in_array($host, $allowedHosts,true) && !in_array($domain,$allowedHosts,true)){
    http_response_code(403);
    fs_log("[DENY] Not allowed – IP: $ip, Host: $host, Domain: $domain");
    echo json_encode(['ok'=>false,'error'=>'ip_not_allowed']); exit;
}

/* Files */
$mf = $_FILES['manifest']['tmp_name']  ?? '';
$sf = $_FILES['signature']['tmp_name'] ?? '';
$pf = $_FILES['public_key']['tmp_name']?? '';
if (!$mf || !$sf || !$pf){
    http_response_code(400); fs_log("[FAIL] Missing upload files from $domain");
    echo json_encode(['ok'=>false,'error'=>'missing_files']); exit;
}

$manifest  = @file_get_contents($mf);
$signature = base64_decode(@file_get_contents($sf), true);
$pubKey    = @file_get_contents($pf);

/* Verify */
$ok=false;
if ($manifest && $signature && $pubKey){
    $k = openssl_pkey_get_public($pubKey);
    if ($k){
        $vr = openssl_verify($manifest, $signature, $k, OPENSSL_ALGO_SHA256);
        if ($vr === 1) $ok = true;
        // DO NOT CALL openssl_pkey_free() — deprecated in PHP 8.2+
// if (is_resource($publicKey)) {
//     openssl_pkey_free($publicKey);
// }

    }
}
if (!$ok){ http_response_code(400); fs_log("[FAIL] Signature verify failed from $domain");
    echo json_encode(['ok'=>false,'error'=>'signature_invalid']); exit; }

/* Save */
@file_put_contents($protected.'/manifest.json',$manifest);
@file_put_contents($protected.'/manifest.sig', base64_encode($signature));
@file_put_contents($keysDir.'/server_public.pem',$pubKey);

fs_log("[OK] Manifest saved from $domain");
echo json_encode(['ok'=>true,'msg'=>'Manifest accepted','domain'=>$domain]);
