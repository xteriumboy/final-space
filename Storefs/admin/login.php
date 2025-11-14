<?php
session_start();
$CFG = require __DIR__ . '/../config.php';
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $u = $_POST['u']??''; $p = $_POST['p']??'';
  if ($u === $CFG['admin_user'] && password_verify($p, $CFG['admin_pass'])) {
    $_SESSION['fs_admin']=1; header('Location: index.php'); exit;
  } else { $err='Invalid'; }
}
?><!doctype html><html><head><meta charset="utf-8"><title>Admin Login</title>
<style>body{background:#0b0f19;color:#fff;font-family:Inter,Arial;display:flex;align-items:center;justify-content:center;height:100vh}
.card{background:#101622;border-radius:12px;padding:24px;min-width:320px}input,button{width:100%;padding:10px;border-radius:8px;border:none;margin:8px 0}button{background:#2563eb;color:#fff;font-weight:600}</style>
</head><body><div class="card"><h2>Admin Login</h2>
<form method="post"><input name="u" placeholder="Username"><input name="p" type="password" placeholder="Password"><button>Login</button></form>
<?php if(!empty($err)) echo '<div style="color:#fca5a5">'.$err.'</div>'; ?>
</div></body></html>
