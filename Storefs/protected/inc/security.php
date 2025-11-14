<?php
/**
 * store_protected_inc/security.php — shared utilities for STORE
 */
declare(strict_types=1);

function fs_path(string $p): string {
    return str_replace(['\\', '//'], ['/', '/'], $p);
}
function fs_dirname(string $p): string {
    return fs_path(dirname($p));
}
function fs_require(string $file): void {
    if (!is_file($file)) {
        http_response_code(500);
        die("<pre style='color:#ff9b9b'>[FS] Required file missing: " . htmlspecialchars($file) . "</pre>");
    }
    require_once $file;
}
