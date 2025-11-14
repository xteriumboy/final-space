<?php
/**
 * Final Space License Server — Hybrid Seal Test Controller v1.0
 * ----------------------------------------------------------------
 * For now this is a simple TEST endpoint that:
 *   - Accepts ?license=&domain=&store_id=
 *   - Seals a fixed list of files from protected_src/ to protected_out/*.fs
 *   - Does NOT upload automatically (the admin/agent copies files to the store).
 *
 * Later, an upload step (FTP/HTTP) can be added, but the core sealing logic
 * lives in inc/fs_hybrid_seal.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/fs_hybrid_seal.php';

header('Content-Type: text/html; charset=utf-8');

$license = trim($_GET['license'] ?? ($CFG['default_test_license'] ?? 'FS-DEMO-000000'));
$domain  = trim($_GET['domain']  ?? ($CFG['default_test_domain']  ?? '2.final-space.com'));
$storeId = trim($_GET['store_id'] ?? 'FS_STORE_MAIN');

if ($license === '' || $domain === '') {
    http_response_code(400);
    echo '<h2 style="color:#f87171">❌ Missing license or domain.</h2>';
    exit;
}

$files = [
    'api/get_download.php',
    'api/send_download.php',
];

$ok = [];
$err = [];

foreach ($files as $rel) {
    try {
        $out = fs_hybrid_seal_file($rel, $license, $domain, $storeId);
        $ok[] = htmlspecialchars($rel . ' → ' . $out, ENT_QUOTES, 'UTF-8');
    } catch (Throwable $e) {
        fs_hybrid_log('ERROR sealing ' . $rel . ': ' . $e->getMessage());
        $err[] = htmlspecialchars($rel . ' — ' . $e->getMessage(), ENT_QUOTES, 'UTF-8');
    }
}

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Hybrid Seal Test</title>';
echo '<style>body{background:#020617;color:#e5e7eb;font-family:system-ui,Segoe UI,sans-serif;padding:20px;}';
echo 'h1{color:#22c55e;} .card{background:#0b1120;border-radius:12px;padding:16px;margin-bottom:16px;border:1px solid #1f2937;}';
echo '.err{color:#f97373;} .ok{color:#4ade80;} code{background:#020617;padding:2px 4px;border-radius:4px;}</style></head><body>';

echo '<h1>Final Space — Hybrid Seal Test</h1>';
echo '<div class="card"><p>License: <code>' . htmlspecialchars($license,ENT_QUOTES,'UTF-8') . '</code><br>';
echo 'Domain: <code>' . htmlspecialchars($domain,ENT_QUOTES,'UTF-8') . '</code><br>';
echo 'Store ID: <code>' . htmlspecialchars($storeId,ENT_QUOTES,'UTF-8') . '</code></p></div>';

if ($ok) {
    echo '<div class="card"><h2 class="ok">✅ Sealed files</h2><ul>';
    foreach ($ok as $line) {
        echo '<li>' . $line . '</li>';
    }
    echo '</ul></div>';
}

if ($err) {
    echo '<div class="card"><h2 class="err">❌ Errors</h2><ul>';
    foreach ($err as $line) {
        echo '<li>' . $line . '</li>';
    }
    echo '</ul><p>Check <code>/data/logs/hybrid_seal.log</code> for details.</p></div>';
} else {
    echo '<div class="card"><p>Copy sealed files from <code>protected_out/api/*.php.fs</code> ';
    echo 'to the client store under <code>/protected/api/</code> and update wrappers to use ';
    echo '<code>include_sealed_fs()</code> for testing.</p></div>';
}

echo '</body></html>';
