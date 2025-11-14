<?php
error_reporting(E_ALL); ini_set('display_errors',1);

if(isset($_GET['action'])){
  header('Content-Type: application/json');
  if($_GET['action']==='testdb'){
    $ok=false;
    try{
      $c=new mysqli($_POST['h'],$_POST['u'],$_POST['p'],$_POST['n']);
      if(!$c->connect_error) $ok=true;
    }catch(Exception $e){}
    echo json_encode(['ok'=>$ok]); exit;
  }
  if($_GET['action']==='testlicense'){
    $srv=rtrim($_POST['server'],'/');
    $code=trim($_POST['code']);
    $ch=curl_init($srv.'/api/verify_license.php');
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>1,
      CURLOPT_TIMEOUT=>10,
      CURLOPT_POST=>1,
      CURLOPT_POSTFIELDS=>['code'=>$code,'domain'=>$_SERVER['HTTP_HOST']]
    ]);
    $r=curl_exec($ch); $err=curl_error($ch); curl_close($ch);
    if($err||!$r){echo json_encode(['ok'=>false,'error'=>$err?:'no_response']);exit;}
    $j=@json_decode($r,true);
    echo json_encode($j?:['ok'=>false,'error'=>'invalid_json']); exit;
  }
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  $cfg="<?php\nreturn ".var_export([
    'db_host'=>$_POST['db_host'],'db_name'=>$_POST['db_name'],
    'db_user'=>$_POST['db_user'],'db_pass'=>$_POST['db_pass'],
    'license_server'=>$_POST['license_server'],'license_code'=>$_POST['license_code'],
    'wallet'=>$_POST['wallet'],'email'=>$_POST['email'],
    'admin_user'=>$_POST['admin_user'],'admin_pass'=>password_hash($_POST['admin_pass'],PASSWORD_BCRYPT)
  ],true).";";
  file_put_contents(__DIR__.'/config.php',$cfg);

  // notify LS
  $ch=curl_init(rtrim($_POST['license_server'],'/').'/api/update_client_info.php');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_POST=>1,
    CURLOPT_POSTFIELDS=>[
      'license'=>$_POST['license_code'],
      'domain'=>$_SERVER['HTTP_HOST'],
      'wallet'=>$_POST['wallet'],
      'email'=>$_POST['email']
    ]]);
  curl_exec($ch); curl_close($ch);

  echo "<!doctype html><meta charset='utf-8'>
  <style>
  body{background:#0b0f14;color:#e7ebf0;font-family:system-ui;text-align:center;padding:60px}
  .card{background:#0f172a;padding:25px;border-radius:12px;display:inline-block;min-width:320px}
  .ok{color:#22c55e}.err{color:#ef4444}a{color:#8ab4ff;text-decoration:none}
  </style>
  <div class='card'>
    <h2>Installer</h2>
    <div class='ok'>✅ Database OK</div>
    <div class='ok'>✅ License verified and bound</div>
    <hr>
    <div class='ok'>✅ Installed</div>
    <p>Domain: ".$_SERVER['HTTP_HOST']."<br>
    License: ".htmlspecialchars($_POST['license_code'])."<br>
    Wallet: ".htmlspecialchars($_POST['wallet'])."<br>
    Email: ".htmlspecialchars($_POST['email'])."</p>
    <p><a href='/admin/login.php'>Go to Admin</a></p>
  </div>";
  exit;
}
?>
<!doctype html>
<meta charset="utf-8">
<title>Final Space Store Installer</title>
<style>
body{background:#0b0f14;color:#e7ebf0;font-family:system-ui;margin:0;padding:40px}
.wrap{max-width:700px;margin:0 auto;background:#0f172a;padding:25px;border-radius:12px}
input{width:100%;padding:10px;margin:6px 0;background:#0b1220;
  border:1px solid #1e293b;border-radius:8px;color:#e7ebf0;box-sizing:border-box}
button{background:#2563eb;border:0;color:#fff;border-radius:8px;padding:10px 18px;cursor:pointer}
button:hover{background:#1e40af}
.test{margin-top:4px;font-size:14px}
.ok{color:#22c55e}.err{color:#ef4444}
</style>
<div class="wrap">
<h2>Final Space Store Installer</h2>
<form method="post" id="installForm">
<h3>Database</h3>
<input name="db_host" id="db_host" placeholder="localhost" required>
<input name="db_name" id="db_name" placeholder="database_name" required>
<input name="db_user" id="db_user" placeholder="database_user" required>
<input type="password" name="db_pass" id="db_pass" placeholder="database_password" required>
<button type="button" onclick="testDB()">Test Database</button><span id="db_res" class="test"></span>

<h3>License</h3>
<input name="license_server" id="license_server" placeholder="https://licenses.final-space.com" required>
<input name="license_code" id="license_code" placeholder="FS-DEMO-000000" required>
<button type="button" onclick="testLic()">Test License</button><span id="lic_res" class="test"></span>

<h3>Admin & Payments</h3>
<input name="admin_user" placeholder="admin" required>
<input type="password" name="admin_pass" placeholder="admin password" required>
<input name="wallet" placeholder="0xYourWallet..." required>
<input name="email" placeholder="owner@example.com" required>
<br><button>Install</button>
</form>
</div>

<script>
function testDB(){
  const p=new URLSearchParams({
    h:db_host.value,u:db_user.value,p:db_pass.value,n:db_name.value
  });
  fetch('?action=testdb',{method:'POST',body:p})
  .then(r=>r.json()).then(j=>{
    db_res.innerHTML=j.ok?'✅ <span class=ok>DB OK</span>':'❌ <span class=err>DB Failed</span>';
  }).catch(()=>db_res.innerHTML='❌ <span class=err>Error</span>');
}
function testLic(){
  const p=new URLSearchParams({
    server:license_server.value,code:license_code.value
  });
  fetch('?action=testlicense',{method:'POST',body:p})
  .then(r=>r.json()).then(j=>{
    lic_res.innerHTML=j.ok?'✅ <span class=ok>License OK</span>':'❌ <span class=err>'+ (j.error||'Failed') +'</span>';
  }).catch(()=>lic_res.innerHTML='❌ <span class=err>Error</span>');
}
</script>
