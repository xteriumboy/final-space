<?php
/**
 * Final Space — AES Helper v4.2 (Legacy-Compatible)
 * AES-256-CBC for both server & client. Works PHP 7.2+.
 */

declare(strict_types=1);
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

/* ---------- Load AES secret ---------- */
$keyFile = __DIR__ . '/../keys/aes_secret.key';
if (!is_file($keyFile)) {
    error_log("[FS-AES] Missing AES key at $keyFile");
    die('AES key missing');
}
$aesKey = trim((string)@file_get_contents($keyFile));
if (strlen($aesKey) < 16) {
    die('Invalid AES key length');
}

/* ---------- Encrypt ---------- */
function fs_aes_encrypt($plain)
{
    global $aesKey;
    try {
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) return false;
        return base64_encode($iv . $cipher);
    } catch (Exception $e) {
        error_log('[FS-AES] Encrypt error: ' . $e->getMessage());
        return false;
    }
}

/* ---------- Decrypt ---------- */
function fs_aes_decrypt($ciphertext)
{
    global $aesKey;
    try {
        $data = base64_decode($ciphertext, true);
        if ($data === false || strlen($data) < 17) return false;
        $iv  = substr($data, 0, 16);
        $enc = substr($data, 16);
        $plain = openssl_decrypt($enc, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
        return ($plain !== false) ? $plain : false;
    } catch (Exception $e) {
        error_log('[FS-AES] Decrypt error: ' . $e->getMessage());
        return false;
    }
}
