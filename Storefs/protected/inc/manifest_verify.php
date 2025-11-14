<?php
/**
 * store_protected_inc/manifest_verify.php — verify manifest.json against manifest.sig
 */
declare(strict_types=1);

function fs_manifest_verify(string $manifestPath, string $sigPath, string $pubKeyPath): bool {
    if (!is_file($manifestPath) || !is_file($sigPath) || !is_file($pubKeyPath)) {
        return false;
    }
    $manifest = (string)@file_get_contents($manifestPath);
    $sigB64   = trim((string)@file_get_contents($sigPath));
    $sig      = base64_decode($sigB64, true);
    if ($sig === false) return false;

    $pkey = @openssl_pkey_get_public('file://' . $pubKeyPath);
    if (!$pkey) return false;
    $ok = openssl_verify($manifest, $sig, $pkey, OPENSSL_ALGO_SHA256) === 1;
    openssl_pkey_free($pkey);
    return $ok;
}
