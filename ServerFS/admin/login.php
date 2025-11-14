<!doctype html><meta charset="utf-8"><title>License Server — Login</title>
<style>
body{background:#0b0f14;color:#e7ebf0;font-family:system-ui;margin:0}
.top{background:#111827;padding:12px 16px;display:flex;justify-content:space-between}
.wrap{padding:18px}
.card{background:#0f172a;border:1px solid #1e293b;border-radius:12px;padding:18px;max-width:520px;margin:24px auto}
input{width:100%;padding:10px;border-radius:8px;border:1px solid #243244;background:#0b1323;color:#e7ebf0;margin:8px 0}
.btn{background:#2563eb;border:0;padding:10px 16px;color:#fff;border-radius:8px;cursor:pointer}
.msg{margin-top:6px;color:#f87171}
a{color:#8ab4ff}
</style>
<div class="top"><div><b>License Server</b></div><nav><a href="/admin/index.php">Dashboard</a></nav></div>
<div class="wrap">
<?php
require_once __DIR__.'/../inc/boot.php';
require_once __DIR__.'/../inc/security.php';

$msg='';
if(isset($_GET['logout'])){ fs_logout(); $msg='Logged out'; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['user'] ?? '');
    $p = trim($_POST['pass'] ?? '');
    if (fs_login($u, $p)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid';
    }
}

?>
<div class="card">
  <h3>Admin Login</h3>
  <form method="post" autocomplete="off">
    <input name="user" placeholder="Username" value="<?php echo htmlspecialchars($_POST['user']??''); ?>">
    <input type="password" name="pass" placeholder="Password">
    <button class="btn" type="submit">Login</button>
    <?php if($msg) echo '<div class="msg">'.htmlspecialchars($msg).'</div>'; ?>
  </form>
</div>
</div>
