<?php
/**
 * Final Space Store — sealed_loader_v2.php (Hybrid AES+RSA, no manifest)
 * ----------------------------------------------------------------------
 * This loader works with files produced by the license server's
 * inc/fs_hybrid_seal.php helper.
 *
 * Format of each sealed blob:
 *   FSSEAL1 | metaLen(2 bytes) | metaJSON | IV(16) | sigLen(2) | signature | ciphertext
 *
 * metaJSON (UTF-8) contains: { v, license, domain, store_id, path }.
 *
 * Runtime checks:
 *  - AES decryption using /protected/keys/aes_secret.key
 *  - RSA signature verification using /protected/keys/server_public.pem
 *  - License equality: meta.license == $CFG['license_code']
 *  - Domain equality:  meta.domain  == current HTTP_HOST (normalized)
 */

declare(strict_types=1);

if (!function_exists('fs_hybrid2_log')) {
    function fs_hybrid2_log(string $msg): void {
        $logFile = __DIR__ . '/../data/fs_hybrid2.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
        @file_put_contents($logFile, $line, FILE_APPEND);
    }
}

/**
 * Load global config ($CFG) without side effects.
 *
 * @return array
 */
function fs_hybrid2_cfg(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $root = dirname(__DIR__, 2); // /home/.../2.final-space.com
    $cfgFile = $root . '/config.php';
    $cfg = is_file($cfgFile) ? (require $cfgFile) : [];
    if (!is_array($cfg)) $cfg = [];
    return $cfg;
}

/**
 * Get AES key used by the license server for this store.
 *
 * @throws RuntimeException
 */
function fs_hybrid2_get_aes_key(): string {
    $keyFile = __DIR__ . '/../keys/aes_secret.key';
    $key = @file_get_contents($keyFile);
    if ($key === false || strlen(trim($key)) < 32) {
        throw new RuntimeException('[FS-HYBRID2] AES key missing or invalid at ' . $keyFile);
    }
    return trim($key);
}

/**
 * Get RSA public key from the license server.
 *
 * @throws RuntimeException
 */
function fs_hybrid2_get_public_key() {
    $file = __DIR__ . '/../keys/server_public.pem';
    $pem  = @file_get_contents($file);
    if ($pem === false || $pem === '') {
        throw new RuntimeException('[FS-HYBRID2] Missing RSA public key at ' . $file);
    }
    $key = openssl_pkey_get_public($pem);
    if (!$key) {
        throw new RuntimeException('[FS-HYBRID2] Invalid RSA public key.');
    }
    return $key;
}

/**
 * Normalize host/domain for comparison.
 */
function fs_hybrid2_norm_host(string $h): string {
    $h = strtolower(trim($h));
    if (strpos($h, ':') !== false) {
        $h = explode(':', $h, 2)[0];
    }
    if (strpos($h, 'www.') === 0) {
        $h = substr($h, 4);
    }
    return $h;
}

/**
 * Include a sealed file produced by the license server.
 *
 * @param string $relPath Relative path from the WEB ROOT,
 *                        e.g. 'protected/api/get_download.php.fs'
 *
 * @throws RuntimeException
 */
function include_sealed_fs(string $relPath) {
    $root = dirname(__DIR__, 2); // web root
    $file = $root . '/' . ltrim($relPath, '/');

    if (!is_file($file)) {
        throw new RuntimeException('[FS-HYBRID2] Sealed file not found: ' . $relPath);
    }

    $raw = file_get_contents($file);
    if ($raw === false) {
        throw new RuntimeException('[FS-HYBRID2] Failed reading sealed file: ' . $relPath);
    }

    if (substr($raw, 0, 7) !== 'FSSEAL1') {
        throw new RuntimeException('[FS-HYBRID2] Invalid sealed header for ' . $relPath);
    }

    $offset  = 7;
    $metaLen = unpack('n', substr($raw, $offset, 2))[1] ?? 0;
    $offset += 2;

    $metaJson = substr($raw, $offset, $metaLen);
    $offset += $metaLen;

    $meta = json_decode($metaJson, true) ?: [];
    $iv   = substr($raw, $offset, 16); $offset += 16;

    $sigLen = unpack('n', substr($raw, $offset, 2))[1] ?? 0;
    $offset += 2;

    $signature = substr($raw, $offset, $sigLen);
    $offset += $sigLen;

    $cipher = substr($raw, $offset);

    $cfg = fs_hybrid2_cfg();
    $expectedLicense = (string)($cfg['license_code'] ?? '');
    $runtimeHost     = fs_hybrid2_norm_host($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''));
    $metaLicense     = (string)($meta['license'] ?? '');
    $metaDomain      = fs_hybrid2_norm_host((string)($meta['domain'] ?? ''));

    if ($expectedLicense === '' || $metaLicense === '' || $metaLicense !== $expectedLicense) {
        throw new RuntimeException('[FS-HYBRID2] License mismatch for ' . ($meta['path'] ?? $relPath));
    }

    if ($metaDomain === '' || $runtimeHost === '' || $metaDomain !== $runtimeHost) {
        throw new RuntimeException('[FS-HYBRID2] Domain mismatch (' . $metaDomain . ' vs ' . $runtimeHost . ').');
    }

    $aesKey  = fs_hybrid2_get_aes_key();
    $pubKey  = fs_hybrid2_get_public_key();
    $plain   = openssl_decrypt($cipher, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);

    if ($plain === false) {
        throw new RuntimeException('[FS-HYBRID2] AES decryption failed for ' . ($meta['path'] ?? $relPath));
    }

    $hashData = $plain . '|' . $metaLicense . '|' . $metaDomain;
    $hashBin  = hash('sha256', $hashData, true);
    $verify   = openssl_verify($hashBin, $signature, $pubKey, OPENSSL_ALGO_SHA256);

    if ($verify !== 1) {
        throw new RuntimeException('[FS-HYBRID2] RSA signature invalid for ' . ($meta['path'] ?? $relPath));
    }

    // All checks passed — execute the original PHP code.
    fs_hybrid2_log('OK ' . ($meta['path'] ?? $relPath) . ' (license=' . $metaLicense . ', domain=' . $metaDomain . ')');

    return eval('?>' . $plain);
}
