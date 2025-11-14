<?php
/**
 * Final Space sealed_loader.php — AES-enabled with include_sealed helper
 */
declare(strict_types=1);
require_once __DIR__ . '/aes_helper.php';

function fs_load_sealed(string $path): bool {
    $AES_KEY = defined('FS_SEAL_AES_KEY') ? FS_SEAL_AES_KEY : 'FS-SEAL-ENC-2025';
    if (!is_file($path)) return false;
    $data = file_get_contents($path);
    if (strpos($data, 'Final Space sealed AES blob') === false) return false;
    $enc = trim(preg_replace('/^.*\?>/s','',$data));
    $plain = fs_aes_decrypt($enc, $AES_KEY);
    if ($plain === '') return false;
    try { eval("?>".$plain); return true; }
    catch (Throwable $e) { error_log('Sealed load error: '.$e->getMessage()); return false; }
}

/**
 * include_sealed('protected/api/fetch_products');
 * Automatically appends .php if missing and loads from document root.
 */
function include_sealed(string $relNoExt): bool {
    $root = dirname(__DIR__);
    $path = $root . '/' . rtrim($relNoExt, '.php');
    if (substr($path, -4) !== '.php') $path .= '.php';
    return fs_load_sealed($path);
}
