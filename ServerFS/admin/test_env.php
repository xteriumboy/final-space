<?php
/**
 * Final Space License Server — Environment Diagnostic Tool (v1.3)
 * Checks PDO, MySQL, config.php, database, keys, folder permissions, and admin password hash.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<!doctype html><meta charset='utf-8'><title>Final Space — Environment Test</title>
<style>
body{background:#0b0f14;color:#e7ebf0;font-family:system-ui;padding:20px}
.ok{color:#22c55e}.err{color:#f87171}.warn{color:#facc15}
h1{font-size:22px;margin:0 0 20px}
h3{margin:10px 0}
.box{background:#0f172a;border:1px solid #1e293b;border-radius:12px;padding:16px;margin-bottom:20px;max-width:800px}
pre{background:#0003;padding:10px;border-radius:8px;overflow:auto;color:#9ca3af}
</style>";

function check($label, $ok, $msg='') {
    $cls = $ok ? 'ok' : 'err';
    echo "<div><b>$label:</b> <span class='$cls'>".($ok?'✔ OK':'❌ FAIL')."</span>";
    if ($msg) echo " <small style='color:#9ca3af'>($msg)</small>";
    echo "</div>";
}

/* ---------- CONFIG ---------- */
echo "<h1>🔍 Final Space Environment Test</h1>";
echo "<div class='box'>";
$configFile = __DIR__ . '/../config.php';
$cfgOk = is_file($configFile);
check('config.php exists', $cfgOk, $configFile);
if ($cfgOk) {
    include $configFile;
    $cfgValid = isset($CFG['db_host'], $CFG['db_name'], $CFG['db_user']);
    check('config.php format', $cfgValid, 'Found '.count($CFG).' entries');
}
echo "</div>";

/* ---------- PHP EXTENSIONS ---------- */
echo "<div class='box'><h3>PHP Extensions</h3>";
check('PDO extension', class_exists('PDO'), 'Needed for database access');
check('PDO MySQL driver', in_array('mysql', PDO::getAvailableDrivers()), implode(', ', PDO::getAvailableDrivers()));
check('OpenSSL extension', extension_loaded('openssl'));
check('JSON extension', extension_loaded('json'));
check('cURL extension', extension_loaded('curl'));
check('mbstring extension', extension_loaded('mbstring'));
check('Session extension', extension_loaded('session'));
check('File permissions OK', is_writable(__DIR__.'/..'));
echo "</div>";

/* ---------- DATABASE ---------- */
echo "<div class='box'><h3>Database Connection</h3>";
try {
    $dsn = "mysql:host={$CFG['db_host']};dbname={$CFG['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $CFG['db_user'], $CFG['db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    check('PDO MySQL connection', true);
    $res = $pdo->query("SHOW TABLES")->fetchAll();
    check('Database query', true, count($res).' tables found');
} catch (Throwable $e) {
    check('PDO MySQL connection', false, $e->getMessage());
}
echo "</div>";

/* ---------- KEYS ---------- */
echo "<div class='box'><h3>RSA Keys</h3>";
$pubKey = __DIR__ . '/../keys/server_public.pem';
$privKey = __DIR__ . '/../keys/server_private.pem';
check('Public key file', is_file($pubKey), $pubKey);
check('Private key file', is_file($privKey), $privKey);
if (is_file($pubKey)) {
    $pubValid = openssl_pkey_get_public(file_get_contents($pubKey));
    check('Public key readable', $pubValid ? true : false);
}
if (is_file($privKey)) {
    $privValid = openssl_pkey_get_private(file_get_contents($privKey));
    check('Private key readable', $privValid ? true : false);
}
echo "</div>";

/* ---------- FOLDERS ---------- */
echo "<div class='box'><h3>Folders</h3>";
$paths = [
    'logs' => __DIR__ . '/../data/logs',
    'integrity' => __DIR__ . '/../data/integrity',
    'sessions' => __DIR__ . '/../data/sessions'
];
foreach ($paths as $name => $path) {
    check("$name directory exists", is_dir($path), $path);
    check("$name writable", is_writable($path), $path);
}
echo "</div>";

/* ---------- AUTO SEAL SECRET ---------- */
echo "<div class='box'><h3>Seal Secret</h3>";
$secret = $CFG['auto_seal_secret'] ?? '';
check('auto_seal_secret defined', !empty($secret), $secret ? 'Hidden for security' : '');
echo "</div>";

/* ---------- PASSWORD HASH TEST ---------- */
echo "<div class='box'><h3>Admin Password Hash Test</h3>";
$pass = 'admin123';
$hash = $CFG['admin_pass_hash'] ?? '$2y$10$hLkUpr6NupW2xAmPMDMoeO.fZtHHTYV7HzSpGuFQpB3FyBtY1U2zG'; // fallback
$verify = password_verify($pass, $hash);
check('Password "admin123" against hash', $verify, $verify ? '✅ Matches' : '❌ Invalid hash or password');
echo "</div>";

/* ---------- SUMMARY ---------- */
echo "<div class='box'><h3>Summary</h3>";
echo "<pre>PHP Version: ".PHP_VERSION."
Server: ".$_SERVER['SERVER_SOFTWARE']."
Loaded Extensions: ".implode(', ', get_loaded_extensions())."</pre>";
echo "</div>";

echo "<div class='ok'><b>✅ Diagnostic complete.</b> Review FAIL items above if any.</div>";
