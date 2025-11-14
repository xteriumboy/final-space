<?php
/**
 * Final Space AES System Status
 * -----------------------------
 * Checks AES key, manifest, and signature validity.
 */

header('Content-Type:text/plain; charset=utf-8');

echo "=== Final Space AES System Status ===\n\n";

// Auto-detect which site
$base = basename(getcwd());
echo "Site: {$base}\n\n";

// ---- AES Key Check ----
$aesKeyFile = __DIR__ . '/protected/keys/aes_secret.key';
if (is_file($aesKeyFile)) {
    echo "[AES] Key file found: " . basename($aesKeyFile) . "\n";
    echo "      Size: " . filesize($aesKeyFile) . " bytes\n";
} else {
    echo "[AES] ❌ Key file missing: {$aesKeyFile}\n";
}

// ---- Manifest Check ----
$manifestFile = __DIR__ . '/protected/manifest.json';
$manifestSig  = __DIR__ . '/protected/manifest.sig';
if (is_file($manifestFile) && is_file($manifestSig)) {
    echo "[Manifest] Found manifest.json + manifest.sig\n";

    $manifest = json_decode(file_get_contents($manifestFile), true);
    if ($manifest) {
        echo "           Contains " . count($manifest['files'] ?? []) . " entries.\n";
    } else {
        echo "           ⚠️ manifest.json not readable or invalid JSON.\n";
    }

    // Optional RSA verify check (if key + OpenSSL available)
    $pubKeyFile = __DIR__ . '/protected/keys/server_public.pem';
    if (is_file($pubKeyFile)) {
        $pubKey = openssl_pkey_get_public(file_get_contents($pubKeyFile));
        $sig = file_get_contents($manifestSig);
        $ok = openssl_verify(file_get_contents($manifestFile), $sig, $pubKey, OPENSSL_ALGO_SHA256);
        echo "           Signature: " . ($ok === 1 ? "✅ Valid" : "❌ Invalid") . "\n";
    } else {
        echo "           ⚠️ No public key found for verification.\n";
    }
} else {
    echo "[Manifest] ❌ Missing manifest or signature files.\n";
}

// ---- File Permissions ----
$paths = [__DIR__ . '/protected', __DIR__ . '/protected/keys', __DIR__ . '/data'];
echo "\n[Permissions]\n";
foreach ($paths as $p) {
    echo basename($p) . ': ' . (is_writable($p) ? "✅ writable" : "⚠️ not writable") . "\n";
}

echo "\n=== End of Status Report ===\n";
