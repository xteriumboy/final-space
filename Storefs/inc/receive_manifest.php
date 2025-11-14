<?php
/**
 * Final Space Store — receive_manifest.php v5.2 (Stable, 2025-11-06)
 *
 * Accepts uploads from License Server:
 *   - manifest.json
 *   - manifest.sig
 *   - server_public.pem
 *
 * Validates sender by domain/token or IP allowlist.
 * Saves files into /protected/ and logs actions to /data/receive_log.txt.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

// ----------------------------------------------------
// CONFIGURATION
// ----------------------------------------------------
$ROOT = dirname(__DIR__);
$DATA_DIR = $ROOT . '/data';
$PROTECTED_DIR = $ROOT . '/protected';
$KEYS_DIR = $ROOT . '/keys';
$LOG_FILE = $DATA_DIR . '/receive_log.txt';

// allowed tokens and IPs
$ALLOWED_TOKENS = ['fsAutoSeal@2025!']; // must match license server reseal script
$ALLOWED_IPS = ['127.0.0.1', '::1']; // add license server IPs here

// ensure dirs
@mkdir($DATA_DIR, 0775, true);
@mkdir($PROTECTED_DIR, 0775, true);
@mkdir($KEYS_DIR, 0775, true);

// ----------------------------------------------------
// HELPERS
// ----------------------------------------------------
function fs_log($msg)
{
    global $LOG_FILE;
    $line = '[' . date('Y-m-d H:i:s') . ' UTC] ' . $msg . PHP_EOL;
    @file_put_contents($LOG_FILE, $line, FILE_APPEND);
}

function respond($ok, $data = [])
{
    http_response_code($ok ? 200 : 400);
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_SLASHES);
    exit;
}

// ----------------------------------------------------
// VERIFY METHOD
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, ['error' => 'POST only']);
}

// sender info
$remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$token = $_POST['token'] ?? '';
$domain = $_POST['domain'] ?? '';

// ----------------------------------------------------
// AUTH CHECK
// ----------------------------------------------------
$authorized = false;
if (in_array($token, $ALLOWED_TOKENS, true)) {
    $authorized = true;
} elseif (in_array($remote_ip, $ALLOWED_IPS, true)) {
    $authorized = true;
}

if (!$authorized) {
    fs_log("Rejected unauthorized attempt from IP={$remote_ip}, domain={$domain}");
    respond(false, ['error' => 'unauthorized']);
}

// ----------------------------------------------------
// FILE VALIDATION
// ----------------------------------------------------
if (
    !isset($_FILES['manifest']) ||
    !isset($_FILES['signature'])
) {
    fs_log("Missing manifest or signature from {$remote_ip} / {$domain}");
    respond(false, ['error' => 'Missing manifest or signature']);
}

$manifest_tmp = $_FILES['manifest']['tmp_name'] ?? '';
$sig_tmp      = $_FILES['signature']['tmp_name'] ?? '';
$pub_tmp      = $_FILES['pubkey']['tmp_name'] ?? '';

if (!is_uploaded_file($manifest_tmp) || !is_uploaded_file($sig_tmp)) {
    fs_log("Upload incomplete or invalid temp files for {$domain}");
    respond(false, ['error' => 'Upload failed']);
}

// ----------------------------------------------------
// SAVE FILES
// ----------------------------------------------------
$manifest_dst = $PROTECTED_DIR . '/manifest.json';
$sig_dst      = $PROTECTED_DIR . '/manifest.sig';
$pub_dst      = $KEYS_DIR . '/server_public.pem';

// move uploaded files
@move_uploaded_file($manifest_tmp, $manifest_dst);
@move_uploaded_file($sig_tmp, $sig_dst);
if ($pub_tmp && is_uploaded_file($pub_tmp)) {
    @move_uploaded_file($pub_tmp, $pub_dst);
}

$ok_manifest = is_file($manifest_dst);
$ok_sig      = is_file($sig_dst);

// ----------------------------------------------------
// LOG + RESPOND
// ----------------------------------------------------
if ($ok_manifest && $ok_sig) {
    fs_log("✅ Received manifest+sig from {$domain} ({$remote_ip})");
    respond(true, ['message' => 'Manifest received and stored']);
} else {
    fs_log("❌ Save error for {$domain} ({$remote_ip})");
    respond(false, ['error' => 'Failed to save files']);
}
