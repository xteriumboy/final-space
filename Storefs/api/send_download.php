<?php
/**
 * Final Space Store — Wrapper for sealed api/send_download.php
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE);

require_once __DIR__ . '/../protected/inc/sealed_loader_v2.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    include_sealed_fs('protected/api/send_download.php.fs');
} catch (Throwable $e) {
    fs_hybrid2_log('ERROR send_download wrapper: ' . $e->getMessage());
    http_response_code(500);
    echo 'sealed_loader_error: ' . $e->getMessage();
}
