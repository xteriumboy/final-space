<?php
/**
 * admin/seal_now.php
 *
 * - Dynamic sealing for a given license/domain.
 * - Creates encrypted blobs in ../protected_out/...
 * - Builds and signs manifest.json
 * - Uploads manifest + blobs + public key to the store /api/receive_manifest.php
 *
 * Usage (admin panel): /admin/seal_now.php?id=3
 *
 * IMPORTANT: run as admin (fs_require_admin will block otherwise).
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 0);

$ROOT = realpath(__DIR__ . '/..');
require_once $ROOT . '/inc/boot.php';
fs_require_admin(); // ensure admin user

header('Content-Type: application/json; charset=utf-8');

/* ---------------------- params ---------------------- */
$id     = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$domain = trim($_GET['domain'] ?? $_POST['domain'] ?? '');
$license_code = trim($_GET['license'] ?? $_POST['license'] ?? '');

if (!$id && !$domain) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'missing_id_or_domain']);
    exit;
}

/* ---------------------- fetch license row if id given ---------------------- */
if ($id && !$domain) {
    $stmt = $DB->prepare("SELECT id,code,domain,status FROM licenses WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'license_not_found']); exit; }
    $domain = $row['domain'];
    $license_code = $row['code'];
}

/* ---------------------- config paths & file list ---------------------- */
$protected_src = realpath($ROOT . '/../protected_src'); // source originals
$protected_out = realpath($ROOT . '/../protected_out');
if ($protected_out === false) $protected_out = $ROOT . '/../protected_out';

$files = [
    'inc/boot.php',
    'inc/integrity.php',
    'inc/license_core.php',
    'inc/receive_manifest.php',
    'api/fetch_products.php',
    'api/get_purchases.php',
    'api/register_purchase.php',
    'api/get_download.php',
    'api/send_download.php',
];

$cek_store_dir = $ROOT . '/data/ceks';
@mkdir($cek_store_dir, 0755, true);
@mkdir($protected_out, 0755, true);

/* ---------------------- helpers ---------------------- */
function json_err(string $msg, $extra = []) {
    http_response_code(500);
    echo json_encode(array_merge(['ok'=>false,'error'=>$msg], (array)$extra), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    exit;
}

function fs_hkdf_sha256(string $ikm, string $salt, string $info, int $length = 32): string {
    if ($salt === '') $salt = str_repeat("\0", 32);
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $okm = ''; $t = ''; $i = 0;
    while (strlen($okm) < $length) {
        $i++;
        $t = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
        $okm .= $t;
    }
    return substr($okm, 0, $length);
}

/* ---------------------- CEK management ---------------------- */
$cekFile = $cek_store_dir . '/' . preg_replace('/[^a-z0-9.-]/i','_',$domain) . '.bin';
if (!is_file($cekFile)) {
    // create a CEK for this domain
    $cek = random_bytes(32);
    if (@file_put_contents($cekFile, $cek) === false) json_err('create_cek_failed', ['file'=>$cekFile]);
} else {
    $cek = @file_get_contents($cekFile);
    if ($cek === false || strlen($cek) !== 32) json_err('invalid_cek');
}

/* ---------------------- create protected_out paths ---------------------- */
foreach ($files as $rel) {
    $dstDir = dirname($protected_out . '/' . $rel);
    @mkdir($dstDir, 0755, true);
}

/* ---------------------- read+seal each source file ---------------------- */
$results = [];
$manifest_files = [];

foreach ($files as $rel) {
    $src = rtrim($protected_src, '/\\') . '/' . $rel;
    if (!is_file($src)) {
        $results[] = ['file'=>$rel,'status'=>'missing_src','src'=>$src];
        continue;
    }
    $plain = @file_get_contents($src);
    if ($plain === false) { $results[] = ['file'=>$rel,'status'=>'read_fail']; continue; }

    // manifest should store SHA256 of plaintext (so store can verify file integrity on its side after unsealing)
    $sha = hash('sha256', $plain);
    $manifest_files[$rel] = $sha;

    // derive per-file key
    $relNoExt = preg_replace('#\.php$#','',$rel);
    $salt = $domain . '|' . $relNoExt;
    $perkey = fs_hkdf_sha256($cek, $salt, 'FS:SealedPHP', 32);

    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', $perkey, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ct === false) { $results[] = ['file'=>$rel,'status'=>'encrypt_fail']; continue; }

    $blob = 'FSSL' . chr(1) . $iv . $ct . $tag;

    $outPath = rtrim($protected_out, '/\\') . '/' . preg_replace('#\.php$#','.php.enc',$rel);
    @mkdir(dirname($outPath), 0755, true);
    $w = @file_put_contents($outPath, $blob);
    if ($w === false) { $results[] = ['file'=>$rel,'status'=>'write_fail','out'=>$outPath]; continue; }

    // verify decrypt locally as sanity check
    $iv2 = substr($blob, 5, 12);
    $tag2 = substr($blob, -16);
    $ct2 = substr($blob, 17, -16);
    $dec = openssl_decrypt($ct2, 'aes-256-gcm', $perkey, OPENSSL_RAW_DATA, $iv2, $tag2);
    if ($dec !== $plain) { $results[] = ['file'=>$rel,'status'=>'verify_failed']; continue; }

    $results[] = ['file'=>$rel,'status'=>'ok','out'=>$outPath,'bytes'=>$w];
}

/* ---------------------- build manifest.json ---------------------- */
/**
 * Manifest format:
 * {
 *   "domain":"2.final-space.com",
 *   "license":"FS-XXXXX",
 *   "generated_at": 1234567890,
 *   "files": { "inc/boot.php": "sha256...", ... }
 * }
 */
$manifest = [
    'domain' => $domain,
    'license' => $license_code ?: '',
    'generated_at' => time(),
    'files' => $manifest_files
];

$manifest_json = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if ($manifest_json === false) json_err('manifest_encode_failed');

/* ---------------------- sign manifest using server_private.pem ---------------------- */
$privKeyPath = $ROOT . '/keys/server_private.pem';
if (!is_file($privKeyPath)) json_err('private_key_missing');

$priv = @file_get_contents($privKeyPath);
if ($priv === false) json_err('private_key_read_failed');

$pkey = openssl_pkey_get_private($priv);
if (!$pkey) json_err('private_key_invalid');

$sig = '';
openssl_sign($manifest_json, $sig, $pkey, OPENSSL_ALGO_SHA256);
openssl_free_key($pkey);
if ($sig === '') json_err('manifest_sign_failed');

$manifest_sig_b64 = base64_encode($sig);
$public_key_path = $ROOT . '/keys/server_public.pem';
if (!is_file($public_key_path)) json_err('public_key_missing');

/* ---------------------- create a ZIP of protected_out (optional) ---------------------- */
/* Not strictly necessary — we'll upload files directly, but zipping may be faster to transport.
   We'll upload manifest + manifest.sig + public key + all .php.enc files via multipart POST.
*/

$upload_endpoint = 'https://' . $domain . '/api/receive_manifest.php'; // store endpoint
// allow admin override?
if (!empty($_POST['override_endpoint'])) $upload_endpoint = trim($_POST['override_endpoint']);

/* ---------------------- prepare multipart POST ---------------------- */
$ch = curl_init();
$boundary = '----FSSEAL' . bin2hex(random_bytes(6));
$headers = [
    'Content-Type: multipart/form-data; boundary=' . $boundary,
    'Expect:' // disable 100-continue
];

// prepare body (we'll use CURLFile for files and normal fields)
$post = [];

// attach manifest (as blob)
$tmpManifest = tempnam(sys_get_temp_dir(), 'fs_manifest_');
file_put_contents($tmpManifest, $manifest_json);
$post['manifest'] = new CURLFile($tmpManifest, 'application/json', 'manifest.json');

// attach signature
$tmpSig = tempnam(sys_get_temp_dir(), 'fs_sig_');
file_put_contents($tmpSig, $manifest_sig_b64);
$post['manifest_sig'] = new CURLFile($tmpSig, 'text/plain', 'manifest.sig');

// attach public key
$post['server_public'] = new CURLFile($public_key_path, 'application/x-pem-file', 'server_public.pem');

// attach sealed files
foreach ($files as $rel) {
    $encPath = rtrim($protected_out, '/\\') . '/' . preg_replace('#\.php$#','.php.enc',$rel);
    if (is_file($encPath)) {
        $field = 'file_' . str_replace(['/','\\'], '_', $rel);
        $post[$field] = new CURLFile($encPath, 'application/octet-stream', basename($encPath));
    }
}

// include metadata fields
$post['domain'] = $domain;
$post['license'] = $license_code;
$post['source'] = 'license_server';

/* ---------------------- POST to store ---------------------- */
curl_setopt_array($ch, [
    CURLOPT_URL => $upload_endpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $post,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER => [], // let cURL build proper multipart
]);

$resp = curl_exec($ch);
$errno = curl_errno($ch);
$err = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// cleanup temp files
@unlink($tmpManifest);
@unlink($tmpSig);

if ($errno) {
    echo json_encode([
        'ok'=>false,
        'error'=>'upload_failed',
        'curl_errno'=>$errno,
        'curl_error'=>$err,
        'http_code'=>$http_code,
        'details'=>$results
    ], JSON_PRETTY_PRINT);
    exit;
}

// try parse store response as json
$store_response = json_decode($resp, true);
if ($store_response === null) {
    // store returned non-json — include raw
    echo json_encode([
        'ok'=>true,
        'upload'=>[
            'endpoint'=>$upload_endpoint,
            'http_code'=>$http_code,
            'response_raw'=>substr($resp,0,4000)
        ],
        'results'=>$results
    ], JSON_PRETTY_PRINT);
    exit;
}

// return store response and sealing results
echo json_encode([
    'ok'=>true,
    'store_response'=>$store_response,
    'results'=>$results
], JSON_PRETTY_PRINT);
exit;
