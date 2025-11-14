<?php
/**
 * server_inc/manifest_sign_example.php
 * Reference: how the server signs a manifest with RSA-SHA256.
 * Do NOT deploy to store. This is a reference for LICENSE SERVER side.
 */
declare(strict_types=1);

function fs_sign_manifest(string $manifestJson, string $serverPrivatePemPath): string {
    $pkey = @openssl_pkey_get_private('file://' . $serverPrivatePemPath);
    if (!$pkey) {
        throw new RuntimeException('Server private key not readable: ' . $serverPrivatePemPath);
    }
    $sig = '';
    if (!openssl_sign($manifestJson, $sig, $pkey, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('openssl_sign failed using SHA256');
    }
    openssl_pkey_free($pkey);
    return base64_encode($sig);
}
