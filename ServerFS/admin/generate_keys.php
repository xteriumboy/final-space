<?php
/**
 * Final Space License Server — generate_keys.php v2.1
 * ---------------------------------------------------
 * - Adds fs_h() helper
 * - Does NOT auto-generate keys on load
 * - If keys exist: shows details + Generate New Keys button
 * - If not: shows Create Keys Now
 * - After creation: shows toast with copyable public key
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/security.php';
fs_require_admin();

/* ---------- Helpers ---------- */
if (!function_exists('fs_h')) {
    function fs_h(string $v): string {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}

$keysDir   = realpath(__DIR__ . '/../keys') ?: (__DIR__ . '/../keys');
$privPath  = $keysDir . '/server_private.pem';
$pubPath   = $keysDir . '/server_public.pem';
$toastPub  = '';
$created   = false;
$errorMsg  = '';

if (!is_dir($keysDir)) {
    @mkdir($keysDir, 0775, true);
}

/* -------- Handle POST: generate -------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    try {
        $config = [
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA
        ];
        $res = openssl_pkey_new($config);
        if (!$res) throw new RuntimeException('openssl_pkey_new failed');

        // Export private key
        $privOut = '';
        if (!openssl_pkey_export($res, $privOut)) {
            throw new RuntimeException('openssl_pkey_export failed');
        }

        // Extract public key
        $pubDetails = openssl_pkey_get_details($res);
        if (!$pubDetails || empty($pubDetails['key'])) {
            throw new RuntimeException('openssl_pkey_get_details failed');
        }
        $pubOut = $pubDetails['key'];

        // Write files
        if (@file_put_contents($privPath, $privOut) === false) {
            throw new RuntimeException('Unable to write private key');
        }
        if (@file_put_contents($pubPath, $pubOut) === false) {
            throw new RuntimeException('Unable to write public key');
        }
        @chmod($privPath, 0600);
        @chmod($pubPath, 0644);

        $toastPub = $pubOut;
        $created  = true;

    } catch (Throwable $e) {
        $errorMsg = $e->getMessage();
    }
}

/* -------- Helper: fingerprint -------- */
function fp_sha256($path): string {
    if (!is_file($path)) return '-';
    $data = @file_get_contents($path);
    return strtoupper(hash('sha256', $data ?: ''));
}

/* -------- Determine state -------- */
$privExists = is_file($privPath);
$pubExists  = is_file($pubPath);
$privSize   = $privExists ? filesize($privPath) : 0;
$pubSize    = $pubExists  ? filesize($pubPath)  : 0;
$privFP     = fp_sha256($privPath);
$pubFP      = fp_sha256($pubPath);

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Generate Keys — Final Space</title>
<style>
:root{
 --bg:#0b0c10;--panel:#0f172a;--panel2:#111827;--line:#1f2937;
 --text:#e5e7eb;--muted:#9ca3af;--ok:#10b981;--bad:#f87171;--blue:#2563eb;
}
body{margin:0;font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--text);}
header{padding:18px 24px;background:var(--panel2);border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;}
header h1{margin:0;font-size:22px;font-weight:700;color:#cbd5e1;}
nav{display:flex;gap:14px;}
nav a{color:#93c5fd;text-decoration:none;font-size:14px;font-weight:500}
nav a:hover{color:#60a5fa;text-decoration:underline}
.container{max-width:960px;margin:28px auto;}
.card{background:var(--panel);border-radius:12px;box-shadow:0 0 20px rgba(0,0,0,.35);padding:22px;}
h2{margin:6px 0 14px 0;font-size:28px;color:#c7d2fe}
.kv{margin:8px 0;color:#cbd5e1}
.kv code{color:#93c5fd}
.btn{display:inline-block;background:#22c55e;color:#fff;border:none;border-radius:8px;padding:10px 14px;font-weight:700;cursor:pointer;text-decoration:none}
.btn-blue{background:#2563eb}
.btn-danger{background:#ef4444}
textarea{width:100%;min-height:180px;border:1px solid #1f2937;border-radius:10px;background:#0c1322;color:#e5e7eb;padding:12px;font-family:ui-monospace,Consolas,Menlo,monospace}
.meta{font-size:13px;color:#9ca3af}
.sep{height:1px;background:#1f2937;margin:16px 0}
.copybtn{background:#111827;border:1px solid #2b364e;border-radius:8px;color:#cbd5e1;padding:6px 10px;cursor:pointer}
.copybtn:hover{color:#fff}
.toast{
 position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);
 min-width:340px;max-width:520px;background:#0b1220;color:#e5e7eb;border:1px solid #22304a;border-radius:12px;
 box-shadow:0 10px 35px rgba(0,0,0,.6);padding:16px 18px;display:<?php echo $created && !$errorMsg ? 'block':'none'; ?>;z-index:9999;
}
.toast h3{margin:0 0 8px 0;font-size:18px;color:#c7d2fe}
.toast .close{position:absolute;top:8px;right:10px;background:#111827;color:#9ca3af;border:1px solid #2b364e;border-radius:6px;padding:2px 6px;cursor:pointer}
.toast .close:hover{color:#fff}
</style>
</head>
<body>

<header>
 <h1>🪐 Final Space — License Administration</h1>
 <nav>
   <a href="index.php">Dashboard</a>
   <a href="generate_keys.php">Generate Keys</a>
   <a href="add_license.php">Add License</a>
   <a href="logout.php">Logout</a>
 </nav>
</header>

<div class="container">
  <div class="card">
    <h2>RSA Keypair</h2>

    <?php if ($errorMsg): ?>
      <p style="color:#fca5a5;font-weight:600">❌ Error: <?= fs_h($errorMsg) ?></p>
    <?php else: ?>
      <p>Private key path: <code><?= fs_h($privPath) ?></code></p>
      <p>Public key path: <code><?= fs_h($pubPath) ?></code></p>

      <p class="meta">Private: <?= $privExists ? ($privSize.' bytes') : 'missing' ?> • SHA256: <code><?= fs_h($privFP) ?></code></p>
      <p class="meta">Public: <?= $pubExists ? ($pubSize.' bytes') : 'missing' ?> • SHA256: <code><?= fs_h($pubFP) ?></code></p>

      <div class="sep"></div>

      <?php if (!$privExists || !$pubExists): ?>
        <form method="post" onsubmit="return confirm('Generate RSA keys now?');">
          <input type="hidden" name="action" value="generate">
          <button class="btn" type="submit">Create Keys Now</button>
        </form>
      <?php else: ?>
        <form method="post" onsubmit="return confirm('Generate NEW keys and overwrite existing?');">
          <input type="hidden" name="action" value="generate">
          <button class="btn btn-danger" type="submit">Generate New Keys</button>
        </form>
      <?php endif; ?>

      <?php if ($pubExists): ?>
        <div class="sep"></div>
        <button class="copybtn" onclick="copyText('pubArea')">Copy Public Key</button>
        <textarea id="pubArea" readonly><?php echo fs_h(@file_get_contents($pubPath)); ?></textarea>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</div>

<div class="toast" id="toastBox">
  <button class="close" onclick="document.getElementById('toastBox').style.display='none'">✕</button>
  <h3>Public Key (copy & save)</h3>
  <button class="copybtn" type="button" onclick="copyText('toastPub')">Copy</button>
  <textarea id="toastPub" readonly><?php echo $created && !$errorMsg ? fs_h($toastPub) : ''; ?></textarea>
</div>

<script>
function copyText(id){var ta=document.getElementById(id);ta.select();document.execCommand('copy');}
</script>
</body>
</html>
