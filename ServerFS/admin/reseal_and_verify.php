<?php
/**
 * Final Space License Server — admin/index.php v3.6
 * -------------------------------------------------
 * - Status column now shows: active/inactive, sealed/not sealed, and a Check button
 * - Check calls AJAX (ajax_check_integrity.php) and shows result in the centered toast
 * - Toast shows: Description, Client Email, Client Wallet, Created (and dynamic Check result)
 */

require_once __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/security.php';
fs_require_admin();

global $DB;
error_reporting(E_ALL);
ini_set('display_errors', 0);

/* ---------- Helpers ---------- */
function fs_h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function val($r,$k,$d=''){ return isset($r[$k]) ? (string)$r[$k] : $d; }
function is_domain_sealed(string $domain): bool {
    $path = __DIR__ . '/../data/integrity/' . $domain;
    return (is_file($path.'/manifest.json') && is_file($path.'/manifest.sig'));
}

/* ---------- Fetch ---------- */
$stmt = $DB->query("SELECT * FROM licenses ORDER BY id DESC");
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Final Space — License Administration</title>
<style>
:root{
 --bg:#0b0c10;--panel:#0f172a;--panel2:#111827;--line:#1f2937;
 --text:#e5e7eb;--muted:#9ca3af;--ok:#10b981;--warn:#a78bfa;--bad:#f87171;
 --blue:#2563eb;--green:#22c55e;--cyan:#06b6d4;
}
body{margin:0;font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--text);}
header{padding:18px 24px;background:var(--panel2);border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;}
header h1{margin:0;font-size:22px;font-weight:700;color:#cbd5e1;}
nav{display:flex;gap:14px;}
nav a{color:#93c5fd;text-decoration:none;font-size:14px;font-weight:500}
nav a:hover{color:#60a5fa;text-decoration:underline}
.container{max-width:1200px;margin:26px auto;}
.card{background:var(--panel);border-radius:12px;overflow:hidden;box-shadow:0 0 20px rgba(0,0,0,.35);}
.card-header{padding:18px 22px;border-bottom:1px solid var(--line);font-size:28px;font-weight:800;color:#a5b4fc;}
table{width:100%;border-collapse:collapse;}
thead{background:var(--panel2);color:var(--muted);font-size:13px;}
th,td{padding:12px 14px;text-align:left;font-size:14px;}
tbody tr{border-bottom:1px solid var(--line);}
tbody tr:hover{background:var(--panel2);}
.status{font-size:13px;display:flex;flex-direction:column;gap:4px;}
.substatus{display:flex;align-items:center;gap:6px;}
.dot{width:8px;height:8px;border-radius:50%;display:inline-block;}
.dot.active{background:var(--ok);}
.dot.inactive{background:var(--bad);}
.dot.sealed{background:var(--ok);}
.dot.notsealed{background:var(--warn);}
.btn{padding:6px 10px;font-size:13px;border-radius:6px;border:none;cursor:pointer;font-weight:600;color:#fff;text-decoration:none;display:inline-block;}
.btn-edit{background:var(--blue);}
.btn-seal{background:var(--green);}
.btn-check{background:var(--cyan);}
.btn:hover{opacity:.88;}
.code-cell{display:flex;align-items:center;gap:8px;}
.info-btn{width:22px;height:22px;border-radius:50%;background:#1f2937;color:#93c5fd;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;border:1px solid #243146;font-size:13px;line-height:1;}
.info-btn:hover{background:#192135;}
.toast{
 position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);
 min-width:360px;max-width:520px;background:#0b1220;color:var(--text);
 border:1px solid #22304a;border-radius:12px;box-shadow:0 10px 35px rgba(0,0,0,.6);
 padding:16px 18px;display:none;z-index:9999;text-align:left;
}
.toast strong{color:#c7d2fe;}
.toast .row{display:flex;gap:8px;margin:6px 0;}
.toast .key{width:140px;color:#9ca3af;font-size:12px;text-align:right;}
.toast .val{font-size:13px;word-break:break-word;}
.toast .close{position:absolute;top:8px;right:10px;background:#111827;color:#9ca3af;
 border:1px solid #2b364e;border-radius:6px;font-size:12px;padding:2px 6px;cursor:pointer;}
.toast .close:hover{color:#fff;}
.resultline{margin-top:8px;padding:8px 10px;border-radius:8px;border:1px solid #1f2937}
.result-ok{background:#081a11;border-color:#0f3f2a;color:#9ef7c4}
.result-bad{background:#1b0e12;border-color:#4a1f2b;color:#fca5a5}
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
    <div class="card-header">Final Space — License Administration</div>
    <table>
      <thead>
        <tr>
          <th width="60">ID</th>
          <th>Code</th>
          <th>Name</th>
          <th>Domain</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r):
        $id=(int)$r['id'];
        $code=val($r,'code');
        $domain=val($r,'domain');
        $status=strtolower(val($r,'status'));
        $name=val($r,'name');
        $desc=val($r,'description');
        $clientEmail=val($r,'client_email');
        $clientWallet=val($r,'client_wallet');
        $created=val($r,'created_at');
        $isActive=($status==='active');
        $isSealed=is_domain_sealed($domain);
      ?>
        <tr>
          <td><?= $id ?></td>
          <td>
            <div class="code-cell">
              <button class="info-btn"
                data-description="<?= fs_h($desc) ?>"
                data-email="<?= fs_h($clientEmail) ?>"
                data-wallet="<?= fs_h($clientWallet) ?>"
                data-created="<?= fs_h($created) ?>"
                onclick="showInfo(this)">i</button>
              <span><?= fs_h($code) ?></span>
            </div>
          </td>
          <td><?= fs_h($name) ?></td>
          <td><?= fs_h($domain) ?></td>
          <td class="status">
            <div class="substatus">
              <span class="dot <?= $isActive?'active':'inactive' ?>"></span>
              <?= $isActive?'active':'inactive' ?>
            </div>
            <div class="substatus">
              <?php if($isSealed): ?>
                <span class="dot sealed"></span> Sealed
              <?php else: ?>
                <span class="dot notsealed"></span> Not sealed
              <?php endif; ?>
            </div>
            <div class="substatus">
              <a class="btn btn-check" href="javascript:void(0)" onclick="checkSeal('<?= fs_h($domain) ?>')">🔍 Check</a>
            </div>
          </td>
          <td>
            <a class="btn btn-edit" href="edit_license.php?id=<?= $id ?>">Edit</a>
            <a class="btn btn-seal" href="javascript:void(0)" onclick="sealNow('<?= fs_h($domain) ?>','<?= fs_h($code) ?>')">Seal Now</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Center Toast -->
<div id="toast" class="toast">
 <button class="close" onclick="hideToast()">✕</button>
 <div style="font-weight:700;margin-bottom:6px"><strong>License Info</strong></div>
 <div class="row"><div class="key">Description</div><div class="val" id="t_desc">—</div></div>
 <div class="row"><div class="key">Client Email</div><div class="val" id="t_email">—</div></div>
 <div class="row"><div class="key">Client Wallet</div><div class="val" id="t_wallet">—</div></div>
 <div class="row"><div class="key">Created</div><div class="val" id="t_created">—</div></div>
 <div class="resultline" id="t_result" style="display:none"></div>
</div>

<script>
function sealNow(domain,license){
 const url=`reseal_and_verify.php?domain=${encodeURIComponent(domain)}&license=${encodeURIComponent(license)}&autoclose=1`;
 const w=620,h=480,y=(screen.height-h)/2,x=(screen.width-w)/2;
 window.open(url,'resealPopup',`width=${w},height=${h},left=${x},top=${y}`);
}
async function checkSeal(domain){
  try{
    const r = await fetch('ajax_check_integrity.php?domain='+encodeURIComponent(domain), {credentials:'same-origin'});
    const j = await r.json();
    const box = document.getElementById('toast');
    const res = document.getElementById('t_result');
    res.style.display='block';
    res.textContent = j.message || 'No result';
    res.className = 'resultline ' + (j.ok ? 'result-ok':'result-bad');
    // keep previous info lines untouched; just show toast
    showToast();
  }catch(e){
    alert('Check failed: '+e);
  }
}
function showInfo(btn){
  document.getElementById('t_desc').textContent   = btn.dataset.description || '—';
  document.getElementById('t_email').textContent  = btn.dataset.email || '—';
  document.getElementById('t_wallet').textContent = btn.dataset.wallet || '—';
  document.getElementById('t_created').textContent= btn.dataset.created || '—';
  document.getElementById('t_result').style.display='none';
  showToast();
}
let tmr=null;
function showToast(){
  const t=document.getElementById('toast');
  t.style.display='block';
  clearTimeout(tmr); tmr=setTimeout(hideToast, 7000);
}
function hideToast(){
  const t=document.getElementById('toast');
  t.style.display='none';
  clearTimeout(tmr);
}
window.addEventListener('message',e=>{
 if(!e.data||e.data.type!=='reseal-done')return;
 const {domain,ok,msg}=e.data;
 if(ok){ alert(`✅ ${domain} resealed successfully.`); location.reload(); }
 else{ alert(`❌ Reseal failed for ${domain}\n${msg}`); }
});
</script>
</body>
</html>
