<?php
/**
 * Final Space License Server — Simple Hybrid Sealer for 2 Files
 * -------------------------------------------------------------
 * Seals:
 *   protected_src/api/get_download.php
 *   protected_src/api/send_download.php
 *
 * Outputs sealed blobs to:
 *   protected_out/api/get_download.php.fs
 *   protected_out/api/send_download.php.fs
 *
 * Format:
 *   "FSV2:" + IV(16) + sigLen(2) + signature + ciphertext
 *
 * - AES-256-CBC encryption
 * - RSA-SHA256 signature over plaintext
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

$baseDir   = dirname(__DIR__); // /home/.../licenses.final-space.com
$srcDir    = $baseDir . '/protected_src/api';
$outDir    = $baseDir . '/protected_out/api';
$keysDir   = $baseDir . '/keys';
$logFile   = $baseDir . '/data/logs/seal_two_files.log';
$aesKeyFile = $keysDir . '/aes_secret.key';
$privKeyFile = $keysDir . '/server_private.pem';

function seal_log(string $msg, string $logFile): void {
    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
}

function safe_mkdir(string $dir): void {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

safe_mkdir(dirname($logFile));
safe_mkdir($outDir);

seal_log('--- Sealing started ---', $logFile);

// Load AES key
if (!is_file($aesKeyFile)) {
    seal_log("Missing AES key file at $aesKeyFile", $logFile);
    die("<pre>❌ Missing AES key file: $aesKeyFile</pre>");
}
$aesKey = file_get_contents($aesKeyFile);
if ($aesKey === false || strlen($aesKey) < 32) {
    seal_log("Invalid AES key file content", $logFile);
    die("<pre>❌ Invalid AES key content in $aesKeyFile</pre>");
}
$aesKey = substr(hash('sha256', $aesKey, true), 0, 32); // normalize to 32 bytes

// Load RSA private key
if (!is_file($privKeyFile)) {
    seal_log("Missing RSA private key at $privKeyFile", $logFile);
    die("<pre>❌ Missing RSA private key: $privKeyFile</pre>");
}
$privKeyPem = file_get_contents($privKeyFile);
$privKey = openssl_pkey_get_private($privKeyPem);
if (!$privKey) {
    seal_log("Failed to load RSA private key", $logFile);
    die("<pre>❌ Failed to load RSA private key.</pre>");
}

$files = [
    'get_download.php',
    'send_download.php',
];

$ok = [];
$fail = [];

foreach ($files as $fname) {
    $src = $srcDir . '/' . $fname;
    $out = $outDir . '/' . $fname . '.fs';

    if (!is_file($src)) {
        $msg = "Source file missing: $src";
        $fail[] = $msg;
        seal_log($msg, $logFile);
        continue;
    }

    $plain = file_get_contents($src);
    if ($plain === false) {
        $msg = "Cannot read source file: $src";
        $fail[] = $msg;
        seal_log($msg, $logFile);
        continue;
    }

    // Sign plaintext
    $hash = hash('sha256', $plain, true);
    $signature = '';
    if (!openssl_sign($hash, $signature, $privKey, OPENSSL_ALGO_SHA256)) {
        $msg = "RSA sign failed for $src";
        $fail[] = $msg;
        seal_log($msg, $logFile);
        continue;
    }

    $sigLen = strlen($signature);
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        $msg = "AES encrypt failed for $src";
        $fail[] = $msg;
        seal_log($msg, $logFile);
        continue;
    }

    $sealed = 'FSV2:' . $iv . pack('n', $sigLen) . $signature . $cipher;

    if (file_put_contents($out, $sealed) === false) {
        $msg = "Failed to write sealed file: $out";
        $fail[] = $msg;
        seal_log($msg, $logFile);
        continue;
    }

    $msg = "Sealed $fname → $out";
    $ok[] = $msg;
    seal_log($msg, $logFile);
}

seal_log('--- Sealing finished ---', $logFile);

// Simple HTML report
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Final Space — Seal Two Files</title>
    <style>
        body{background:#050816;color:#e5e7eb;font-family:system-ui,Segoe UI,sans-serif;padding:20px;}
        h1{color:#a5b4fc;}
        .box{background:#111827;border-radius:8px;padding:16px;margin-bottom:16px;}
        .ok{color:#22c55e;}
        .fail{color:#f97373;}
        code{background:#020617;border-radius:4px;padding:2px 4px;}
    </style>
</head>
<body>
<h1>Final Space — Hybrid Seal (2 Files)</h1>

<div class="box">
    <h2 class="ok">✅ Sealed</h2>
    <?php if ($ok): ?>
        <ul>
            <?php foreach ($ok as $line): ?>
                <li><code><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></code></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No files sealed.</p>
    <?php endif; ?>
</div>

<div class="box">
    <h2 class="fail">⚠️ Problems</h2>
    <?php if ($fail): ?>
        <ul>
            <?php foreach ($fail as $line): ?>
                <li><code><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></code></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No errors.</p>
    <?php endif; ?>
</div>

<div class="box">
    <p>Output dir: <code><?= htmlspecialchars($outDir, ENT_QUOTES, 'UTF-8') ?></code></p>
    <p>Log file: <code><?= htmlspecialchars($logFile, ENT_QUOTES, 'UTF-8') ?></code></p>
</div>
</body>
</html>
