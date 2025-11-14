<?php require_once __DIR__.'/../inc/boot.php'; ls_require_admin(); global $DB;
if($_SERVER['REQUEST_METHOD']==='POST'){
  $name=trim($_POST['name']??''); $desc=trim($_POST['description']??'');
  $code=trim($_POST['code']??''); $domain=trim($_POST['domain']??''); $status=trim($_POST['status']??'active');
  $st=$DB->prepare('INSERT INTO licenses(name,description,code,domain,status) VALUES(?,?,?,?,?)');
  $st->execute([$name,$desc,$code,$domain,$status]);
  echo "<!doctype html><meta charset='utf-8'><style>body{background:#0b0f14;color:#e7ebf0;font-family:system-ui;text-align:center;padding:60px}.msg{background:#0f172a;padding:20px;border-radius:12px;display:inline-block}a{color:#8ab4ff}</style><div class='msg'><h2>✅ Saved</h2><p><b>Code:</b> ".htmlspecialchars($code)."<br><b>Domain:</b> ".htmlspecialchars($domain?:'—')."</p><p>Redirecting…</p></div><script>setTimeout(()=>location.href='/admin/index.php',1800)</script>";
  exit;
}
?><!doctype html><meta charset="utf-8"><title>Add License</title>
<style>body{background:#0b0f14;color:#e7ebf0;font-family:system-ui;padding:40px}input,textarea,select{background:#0f172a;border:1px solid #1f2937;color:#e7ebf0;border-radius:8px;padding:8px;width:100%;box-sizing:border-box;margin:8px 0}button{background:#2563eb;border:0;color:#fff;border-radius:8px;padding:10px 16px;cursor:pointer}</style>
<h1>Add License</h1>
<form method="post">
  <input name="name" placeholder="Name (optional)">
  <textarea name="description" placeholder="Description (optional)"></textarea>
  <input name="code" placeholder="License Code" required>
  <input name="domain" placeholder="Domain (leave empty for auto-bind)">
  <select name="status"><option value="active">active</option><option value="blocked">blocked</option><option value="expired">expired</option></select>
  <button>Save</button>
</form>
