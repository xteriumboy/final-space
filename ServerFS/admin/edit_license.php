<?php
require_once __DIR__ . '/../inc/boot.php'; ls_require_admin();
$id = intval($_GET['id'] ?? 0); if(!$id) die('Missing license ID.');
$s=$DB->prepare("SELECT * FROM licenses WHERE id=? LIMIT 1"); $s->execute([$id]); $lic=$s->fetch(PDO::FETCH_ASSOC);
if(!$lic) die('License not found.');

if($_SERVER['REQUEST_METHOD']==='POST'){
  $code=trim($_POST['code']??''); $domain=trim($_POST['domain']??'');
  $status=trim($_POST['status']??'active'); $name=trim($_POST['name']??''); $desc=trim($_POST['description']??'');
  $q=$DB->prepare("UPDATE licenses SET code=?, domain=?, status=?, name=?, description=? WHERE id=?");
  $q->execute([$code,$domain,$status,$name,$desc,$id]);
  echo "<!doctype html><meta charset='utf-8'><style>body{background:#0b0f14;color:#e7ebf0;font-family:system-ui;text-align:center;padding:60px}.msg{background:#0f172a;padding:20px;border-radius:12px;display:inline-block}a{color:#8ab4ff}</style><div class='msg'><h2>✅ License Updated</h2><p>Redirecting…</p></div><script>setTimeout(()=>location.href='/admin/index.php',1800)</script>"; exit;
}
?>
<!doctype html><meta charset="utf-8"><title>Edit License #<?=htmlspecialchars($id)?></title>
<style>body{background:#0b0f14;color:#e7ebf0;font-family:system-ui;margin:0;padding:40px}.wrap{max-width:700px;margin:0 auto;background:#0f172a;padding:25px;border-radius:12px}input,textarea,select{width:100%;padding:10px;margin:6px 0;background:#0b1220;border:1px solid #1e293b;border-radius:8px;color:#e7ebf0;box-sizing:border-box}textarea{min-height:80px;resize:vertical}button{background:#2563eb;border:0;color:#fff;border-radius:8px;padding:10px 18px;cursor:pointer}button:hover{background:#1e40af}a{color:#60a5fa;text-decoration:none}.topnav{text-align:right;margin-bottom:15px}</style>
<div class="topnav"><a href="/admin/index.php">Dashboard</a> | <a href="/admin/add_license.php">Add</a> | <a href="/admin/reseal_and_verify.php">Reseal</a></div>
<div class="wrap"><h2>Edit License #<?=$id?></h2>
<form method="post">
  <label>Name</label><input name="name" value="<?=htmlspecialchars($lic['name'] ?? '')?>">
  <label>Description</label><textarea name="description"><?=htmlspecialchars($lic['description'] ?? '')?></textarea>
  <label>License Code</label><input name="code" value="<?=htmlspecialchars($lic['code'])?>" required>
  <label>Domain</label><input name="domain" value="<?=htmlspecialchars($lic['domain'])?>">
  <label>Status</label>
  <select name="status">
    <option value="active" <?=($lic['status']==='active'?'selected':'')?>>active</option>
    <option value="blocked" <?=($lic['status']==='blocked'?'selected':'')?>>blocked</option>
    <option value="expired" <?=($lic['status']==='expired'?'selected':'')?>>expired</option>
  </select>
  <br><button>Save</button>
</form></div>
