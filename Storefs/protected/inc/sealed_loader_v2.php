<?php
/**
 * Final Space Store — sealed_loader_v2.php
 * ----------------------------------------
 * Decrypts and executes .fs sealed PHP files.
 *
 * Format created by seal_two_files.php:
 *   "FSV2:" + IV(16) + sigLen(2) + signature + ciphertext
 *
 * - AES-256-CBC
 * - RSA verify over SHA256(plaintext)
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE);

function fs_hybrid2_log(string $msg): void {
    $logDir  = __DIR__ . '/../data';
    $logFile = $logDir . '/fs_hybrid2.log';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

function fs_hybrid2_get_aes_key(): string {
    $keyFile = __DIR__ . '/../keys/aes_secret.key';
    if (!is_file($keyFile)) {
        throw new RuntimeException('[FSV2] AES key file missing: ' . $keyFile);
    }
    $raw = file_get_contents($keyFile);
    if ($raw === false || strlen($raw) < 32) {
        throw new RuntimeException('[FSV2] AES key invalid length.');
    }
    return substr(hash('sha256', $raw, true), 0, 32); // normalize
}

function fs_hybrid2_get_public_key() {
    $pubFile = __DIR__ . '/../keys/server_public.pem';
    if (!is_file($pubFile)) {
        throw new RuntimeException('[FSV2] Public key file missing: ' . $pubFile);
    }
    $pem = file_get_contents($pubFile);
    $res = openssl_pkey_get_public($pem);
    if (!$res) {
        throw new RuntimeException('[FSV2] Invalid public key.');
    }
    return $res;
}

/**
 * Include a sealed .fs file (relative to web root).
 *
 * Example:
 *   include_sealed_fs('protected/api/get_download.php.fs');
 */
function include_sealed_fs(string $relativePath) {
    $root = dirname(__DIR__); // /home/.../2.final-space.com
    $file = $root . '/' . ltrim($relativePath, '/');

    if (!is_file($file)) {
        throw new RuntimeException('[FSV2] Sealed file not found: ' . $relativePath);
    }

    $raw = file_get_contents($file);
    if ($raw === false) {
        throw new RuntimeException('[FSV2] Cannot read sealed file: ' . $relativePath);
    }

    if (substr($raw, 0, 5) !== 'FSV2:') {
        throw new RuntimeException('[FSV2] Invalid sealed header.');
    }

    $offset = 5;
    $iv     = substr($raw, $offset, 16); $offset += 16;

    if (strlen($iv) !== 16) {
        throw new RuntimeException('[FSV2] Invalid IV length.');
    }

    $sigLenBin = substr($raw, $offset, 2);
    if (strlen($sigLenBin) !== 2) {
        throw new RuntimeException('[FSV2] Invalid signature length field.');
    }
    $sigLen = unpack('n', $sigLenBin)[1];
    $offset += 2;

    $signature = substr($raw, $offset, $sigLen);
    $offset += $sigLen;

    $cipher = substr($raw, $offset);
    if ($cipher === '') {
        throw new RuntimeException('[FSV2] Empty ciphertext.');
    }

    $key = fs_hybrid2_get_aes_key();
    $pub = fs_hybrid2_get_public_key();

    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($plain === false) {
        throw new RuntimeException('[FSV2] AES decryption failed.');
    }

    $hash = hash('sha256', $plain, true);
    $ok   = openssl_verify($hash, $signature, $pub, OPENSSL_ALGO_SHA256);

    if ($ok !== 1) {
        throw new RuntimeException('[FSV2] RSA signature invalid.');
    }

    // Finally execute the original PHP code
    return eval('?>' . $plain);
}
