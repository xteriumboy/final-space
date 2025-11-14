<?php
/**
 * Final Space License Server — Hybrid AES+RSA Sealing Helper v1.0
 * ----------------------------------------------------------------
 * - No manifest.json
 * - Each file becomes an independent sealed blob:
 *      FSSEAL1 | metaLen | metaJSON | IV | sigLen | RSA(signature) | AES(ciphertext)
 * - Metadata contains: license, domain, optional store_id.
 * - RSA signs hash(plaintext + '|' + license + '|' + domain).
 *
 * This file does NOT perform uploads; it only creates sealed files in
 * /protected_out/. A separate script (admin/seal_hybrid_test.php) calls
 * these helpers and may upload the results to the client store.
 */

declare(strict_types=1);

if (!function_exists('fs_hybrid_log')) {
    function fs_hybrid_log(string $msg): void {
        $logFile = __DIR__ . '/../data/logs/hybrid_seal.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
        @file_put_contents($logFile, $line, FILE_APPEND);
    }
}

/**
 * Load AES secret used to encrypt sealed files.
 *
 * @throws RuntimeException
 */
function fs_hybrid_get_aes_key(): string {
    $keyFile = __DIR__ . '/../keys/aes_secret.key';
    $key = @file_get_contents($keyFile);
    if ($key === false || strlen(trim($key)) < 32) {
        throw new RuntimeException('[FS-HYBRID] AES key missing or invalid at ' . $keyFile);
    }
    return trim($key);
}

/**
 * Load RSA private key for signing.
 *
 * @throws RuntimeException
 */
function fs_hybrid_get_private_key() {
    $file = __DIR__ . '/../keys/server_private.pem';
    $pem  = @file_get_contents($file);
    if ($pem === false || $pem === '') {
        throw new RuntimeException('[FS-HYBRID] Missing RSA private key at ' . $file);
    }
    $key = openssl_pkey_get_private($pem);
    if (!$key) {
        throw new RuntimeException('[FS-HYBRID] Invalid RSA private key.');
    }
    return $key;
}

/**
 * Seal a single file from protected_src to protected_out.
 *
 * @param string $relPath   Relative path inside protected_src/, e.g. 'api/get_download.php'
 * @param string $license   License code to bind to
 * @param string $domain    Domain to bind to (e.g. '2.final-space.com')
 * @param string $storeId   Optional store identifier
 *
 * @throws RuntimeException
 */
function fs_hybrid_seal_file(string $relPath, string $license, string $domain, string $storeId = 'FS_STORE_1'): string {
    $src = __DIR__ . '/../protected_src/' . ltrim($relPath, '/');
    $out = __DIR__ . '/../protected_out/' . ltrim($relPath, '/');
    $out .= '.fs'; // new sealed extension

    if (!is_file($src)) {
        throw new RuntimeException('[FS-HYBRID] Source not found: ' . $src);
    }

    $plain = file_get_contents($src);
    if ($plain === false) {
        throw new RuntimeException('[FS-HYBRID] Failed reading source file: ' . $src);
    }

    $aesKey = fs_hybrid_get_aes_key();
    $priv   = fs_hybrid_get_private_key();

    $meta = [
        'v'        => 1,
        'license'  => (string)$license,
        'domain'   => (string)$domain,
        'store_id' => (string)$storeId,
        'path'     => $relPath,
    ];
    $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES);
    if ($metaJson === false) {
        throw new RuntimeException('[FS-HYBRID] Failed to encode metadata JSON.');
    }

    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        throw new RuntimeException('[FS-HYBRID] AES encryption failed for ' . $relPath);
    }

    // Bind to license + domain so they cannot be swapped
    $hashData = $plain . '|' . $license . '|' . strtolower($domain);
    $hashBin  = hash('sha256', $hashData, true);

    $signature = '';
    if (!openssl_sign($hashBin, $signature, $priv, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('[FS-HYBRID] RSA signing failed for ' . $relPath);
    }
    $sigLen = strlen($signature);

    $metaLen = strlen($metaJson);
    $blob    = 'FSSEAL1'                        // 7-byte header
             . pack('n', $metaLen) . $metaJson  // 2-byte meta length + JSON
             . $iv                              // 16-byte IV
             . pack('n', $sigLen) . $signature  // 2-byte signature length + signature
             . $cipher;                         // ciphertext

    $outDir = dirname($out);
    if (!is_dir($outDir)) {
        if (!@mkdir($outDir, 0775, true) && !is_dir($outDir)) {
            throw new RuntimeException('[FS-HYBRID] Failed creating directory: ' . $outDir);
        }
    }

    if (@file_put_contents($out, $blob) === false) {
        throw new RuntimeException('[FS-HYBRID] Failed writing sealed file: ' . $out);
    }

    fs_hybrid_log('Sealed ' . $relPath . ' → ' . $out . ' (license=' . $license . ', domain=' . $domain . ')');
    return $out;
}
