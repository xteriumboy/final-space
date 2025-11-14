<?php
/**
 * Final Space Store — admin/check_sealed_files.php v2.3
 * ------------------------------------------------------
 * Secure diagnostic for license server admin
 * Scans /protected/ for all sealed inc/api files
 * Displays visual status with dark Inter theme
 */

declare(strict_types=1);
error_reporting(0);
$domain = $_SERVER['HTTP_HOST'] ?? 'unknown';
$base = realpath(__DIR__ . '/../');
$prot = $base . '/protected';
$expected = [
    'api/fetch_products',
    'api/get_purchases',
    'api/register_purchase',
    'api/get_download',
    'api/send_download',
    'inc/boot',
    'inc/integrity',
    'inc/license_core',
    'inc/receive_manifest',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Sealed Audit — <?= htmlspecialchars($domain) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
body{background:#0f172a;color:#e2e8f0;font-family:'Inter',sans-serif;margin:0;padding:30px;}
h1{color:#60a5fa;margin-bottom:20px;}
table{width:100%;border-collapse:collapse;background:#1e293b;border-radius:10px;overflow:hidden;}
th,td{padding:12px 14px;border-bottom:1px solid #334155;text-align:left;}
th{background:#1e293b;color:#94a3b8;font-weight:600;}
.ok{color:#4ade80;font-weight:600;}
.miss{color:#f87171;font-weight:600;}
.bad{color:#facc15;font-weight:600;}
.footer{margin-top:25px;color:#94a3b8;text-align:center;}
</style>
</head>
<body>
<h1>🔐 <?= htmlspecialchars($domain) ?> — Sealed File Audit</h1>
<table>
<tr><th>File</th><th>Status</th><th>Size</th></tr>
<?php
foreach ($expected as $f) {
    $p = "$prot/$f";
    if (is_file($p)) {
        $size = filesize($p);
        echo "<tr><td>$f</td><td class='ok'>✅ sealed</td><td>$size bytes</td></tr>";
    } else {
        echo "<tr><td>$f</td><td class='miss'>❌ missing</td><td>-</td></tr>";
    }
}
?>
</table>
<div class="footer">If any file is missing, reseal from the License Server panel.</div>
</body>
</html>
