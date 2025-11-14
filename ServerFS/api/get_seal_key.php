<?php
/**
 * Final Space License Server — get_seal_key.php v1.5
 * --------------------------------------------------
 * Secure API endpoint used by the store's sealed_loader.php.
 *
 * It:
 *  - verifies domain + license_code pair in DB
 *  - loads CEK (Code Encryption Key) for that domain
 *  - creates a short-lived signed token (JSON + RSA-SHA256)
 *  - returns: { ok:true, domain, license, cek_b64, issued, exp, sig }
 *
 * Store then verifies this using /keys/server_public.pem.
 */

declare(strict_types=1);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$ROOT = realpath(__DIR__ . '/..');
require_once $ROOT . '/config.php';
require_once $ROOT . '/inc/boot.php'; // ensures $DB, $CFG available

/* ---------- basic helpers ---------- */
function json_out(array $arr): void {
    echo json_encode($arr, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function fail(string $msg): void {
    http_response_code(400);
    json_out(['ok'=>false,'error'=>$msg]);
}

/* ---------- input ---------- */
$domain  = trim($_GET['domain'] ?? '');
$license = trim($_GET['license'] ?? '');

if ($domain === '' || $license === '') {
    fail('missing_domain_or_license');
}

/* ---------- validate license from DB ---------- */
try {
    $stmt = $DB->prepare("SELECT id,status,domain,code,notes FROM licenses WHERE code=? LIMIT 1");
    $stmt->execute([$license]);
    $lic = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    fail('db_error');
}

if (!$lic) {
    fail('license_not_found');
}
if (strcasecmp(trim($lic['domain']), $domain) !== 0) {
    fail('domain_mismatch');
}
if (!in_array(strtolower((string)$lic['status']), ['active','ok','valid','1'], true)) {
    fail('license_not_active');
}

/* ---------- load CEK (shared encryption key) ---------- */
$cekDir = realpath($ROOT . '/data') ?: ($ROOT . '/data');
$cekFile = $cekDir . '/ceks/' . preg_replace('/[^a-z0-9.-]/i', '_', $domain) . '.bin';
if (!is_file($cekFile)) {
    fail('cek_not_found — run seal_encrypt.php first for this domain');
}
$cek = @file_get_contents($cekFile);
if ($cek === false || strlen($cek) !== 32) {
    fail('cek_invalid');
}

/* ---------- build short-lived token ---------- */
$issued = time();
$ttl    = 300; // valid for 5 minutes
$exp    = $issued + $ttl;

$payload = [
    'ok'       => true,
    'domain'   => $domain,
    'license'  => $license,
    'cek_b64'  => base64_encode($cek),
    'issued'   => $issued,
    'exp'      => $exp
];

/* ---------- sign ---------- */
$privKeyPath = $ROOT . '/keys/server_private.pem';
if (!is_file($privKeyPath)) {
    fail('private_key_missing');
}
$priv = @file_get_contents($privKeyPath);
if ($priv === false) {
    fail('private_key_read_error');
}
$pkey = openssl_pkey_get_private($priv);
if (!$pkey) {
    fail('private_key_invalid');
}

$payloadJSON = json_encode($payload, JSON_UNESCAPED_SLASHES);
$sig = '';
openssl_sign($payloadJSON, $sig, $pkey, OPENSSL_ALGO_SHA256);
openssl_free_key($pkey);

if (!$sig) {
    fail('sign_error');
}

/* ---------- response ---------- */
$payload['sig'] = base64_encode($sig);
json_out($payload);
