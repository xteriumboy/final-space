<?php
/**
 * Final Space License Server — Reset Admin Password Tool
 * Use once, then delete this file for security.
 */

$configFile = __DIR__ . '/../config.php';
if (!is_file($configFile)) {
    die("<pre style='color:#f87171'>❌ config.php not found</pre>");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPass = trim($_POST['pass'] ?? '');
    if ($newPass === '') die("<pre style='color:#f87171'>❌ Empty password not allowed</pre>");
    $hash = password_hash($newPass, PASSWORD_BCRYPT);

    $config = file_get_contents($configFile);
    $config = preg_replace(
        "/'admin_pass_hash'\s*=>\s*'[^']*'/",
        "'admin_pass_hash' => '" . addslashes($hash) . "'",
        $config
    );
    file_put_contents($configFile, $config);
    echo "<pre style='color:#22c55e'>✅ Password updated successfully! 
New password: {$newPass}</pre>";
    exit;
}
?>
<!doctype html><meta charset="utf-8">
<title>Reset Admin Password</title>
<body style="background:#0b0f14;color:#e7ebf0;font-family:system-ui;display:flex;align-items:center;justify-content:center;height:100vh">
<form method="post" style="background:#0f172a;padding:30px;border-radius:12px;width:360px;text-align:center">
<h3>Reset Admin Password</h3>
<input type="password" name="pass" placeholder="New password" style="width:100%;padding:10px;margin:10px 0;border-radius:8px;border:1px solid #243244;background:#0b1323;color:#e7ebf0">
<button style="background:#2563eb;color:#fff;border:0;padding:10px 18px;border-radius:8px;cursor:pointer">Reset Password</button>
</form>
</body>
