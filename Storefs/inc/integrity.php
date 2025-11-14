<?php
declare(strict_types=1);

if (!function_exists('fs_int_log')) {
    function fs_int_log(string $msg, array $ctx = []): void {
        $dir = __DIR__ . '/../data';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
        if ($ctx) $line .= ' | ' . json_encode($ctx, JSON_UNESCAPED_SLASHES);
        $line .= PHP_EOL;
        @file_put_contents($dir . '/integrity.log', $line, FILE_APPEND);
    }
}
if (!function_exists('fs_sha256_file')) {
    function fs_sha256_file(string $path): ?string { $h = @hash_file('sha256', $path); return $h ?: null; }
}
if (!function_exists('fs_verify_manifest_signature')) {
    function fs_verify_manifest_signature(string $manifestFile, string $sigFile, string $pubKeyFile): bool {
        $manifest = @file_get_contents($manifestFile);
        $sig      = @file_get_contents($sigFile);
        $pubKey   = @file_get_contents($pubKeyFile);
        if ($manifest === false || $sig === false || $pubKey === false) {
            fs_int_log('files_missing', compact('manifestFile','sigFile','pubKeyFile')); return false;
        }
        $sigBin = base64_decode(trim($sig), true);
        if ($sigBin === false) { fs_int_log('sig_base64_invalid'); return false; }
        $res = openssl_pkey_get_public($pubKey);
        if (!$res) { fs_int_log('pubkey_load_failed'); return false; }
        $ok = (openssl_verify($manifest, $sigBin, $res, OPENSSL_ALGO_SHA256) === 1);
        openssl_free_key($res);
        if (!$ok) fs_int_log('sig_verify_failed');
        return $ok;
    }
}
if (!function_exists('fs_compare_manifest_files')) {
    function fs_compare_manifest_files(string $manifestFile, string $baseDir): array {
        $j = json_decode(@file_get_contents($manifestFile), true);
        if (!is_array($j) || empty($j['files']) || !is_array($j['files'])) {
            fs_int_log('manifest_json_invalid'); return ['ok'=>false,'errors'=>['manifest_json_invalid']];
        }
        $errors = [];
        foreach ($j['files'] as $rel => $expectedHash) {
            $rel = ltrim($rel, '/');
            $p = realpath($baseDir . '/' . $rel) ?: $baseDir . '/' . $rel;
            $hash = fs_sha256_file($p);
            if (!$hash || strtolower($hash) !== strtolower($expectedHash)) {
                $errors[] = ['file'=>$rel, 'expected'=>$expectedHash, 'got'=>$hash];
            }
        }
        return ['ok'=>count($errors)===0, 'errors'=>$errors];
    }
}
