<?php
/**
 * Final Space — License Administration (index.php) v10.7 UX
 * - Keeps original dark layout and table structure
 * - Replaces old info popup with centered step-toasts for reseal
 * - Uses ajax_reseal.php (POST: domain, license) and reloads on success
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/security.php';
fs_require_admin();

/** Utilities */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function is_sealed(string $domain): bool {
    $dir = __DIR__ . '/../data/integrity/' . $domain;
    return is_file($dir.'/manifest.json') && is_file($dir.'/manifest.sig');
}

global $DB;
$rows = [];
try {
    $stmt = $DB->query("SELECT * FROM licenses ORDER BY id DESC");
    if ($stmt) $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rows = [];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Final Space — License Administration</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#0b0c10; --panel:#0f172a; --panel2:#111827; --line:#1f2937;
  --text:#e5e7eb; --muted:#9ca3af; --ok:#10b981; --warn:#a78bfa; --bad:#f87171;
  --blue:#2563eb; --green:#22c55e; --cyan:#06b6d4;
}
*{box-sizing:border-box}
html,body{margin:0;background:var(--bg);color:var(--text);font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,"Helvetica Neue",Arial,sans-serif}
header{padding:18px 24px;border-bottom:1px solid var(--line);background:var(--panel2);display:flex;align-items:center;justify-content:space-between}
header h1{margin:0;font-weight:800;letter-spacing:.2px;color:#cbd5e1;font-size:22px}
nav{display:flex;gap:14px}
nav a{color:#93c5fd;text-decoration:none;font-weight:600;font-size:14px}
nav a:hover{text-decoration:underline;color:#60a5fa}
.container{max-width:1200px;margin:26px auto}
.card{background:var(--panel);border-radius:12px;box-shadow:0 0 20px rgba(0,0,0,.35);overflow:hidden}
.card .title{padding:18px 22px;border-bottom:1px solid var(--line);font-size:28px;color:#a5b4fc;font-weight:800}
table{width:100%;border-collapse:collapse}
thead{background:var(--panel2);color:#b6c1d6}
th,td{padding:12px 14px;border-bottom:1px solid var(--line);text-align:left;font-size:14px}
tbody tr:hover{background:rgba(255,255,255,0.02)}
.btn{display:inline-block;padding:6px 10px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;font-size:13px;border:none;cursor:pointer}
.btn-edit{background:var(--blue)}
.btn-seal{background:var(--green)}
.btn-check{background:var(--cyan)}

.statusline{display:flex;align-items:center;gap:8px;font-size:13px}
.dot{width:8px;height:8px;border-radius:50%;display:inline-block}
.dot.on{background:var(--ok)} .dot.off{background:var(--bad)}
.dot.sealed{background:var(--ok)} .dot.nsealed{background:var(--warn)}

/* Center toasts */
#fsToast{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%) scale(.98);
  min-width:360px;max-width:560px;background:#0b1220;border:1px solid #22304a;
  color:var(--text);border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.6);
  padding:18px 20px;display:none;z-index:9999;opacity:0;transition:opacity .18s ease, transform .18s ease}
#fsToast.fs-show{display:block;opacity:1;transform:translate(-50%,-50%) scale(1)}
.fs-head{font-weight:800;margin-bottom:8px;color:#c7d2fe}
.fs-line{margin:8px 0;padding:10px 12px;border-radius:10px;border:1px solid #1f2937;background:#0d1424}
.fs-ok{background:#0e1b14;border-color:#0c3b29;color:#adf7c8}
.fs-bad{background:#1b0e12;border-color:#4a1f2b;color:#fca5a5}
.fs-actions{margin-top:10px;text-align:right}
.fs-close{background:#111827;color:#9ca3af;border:1px solid #2b364e;border-radius:8px;padding:4px 8px;font-weight:700;cursor:pointer}
.fs-close:hover{color:#fff}
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
    <div class="title">Final Space — License Administration</div>
    <table>
      <thead>
        <tr>
          <th width="60">ID</th>
          <th>Code</th>
          <th>Name</th>
          <th>Domain</th>
          <th>Status</th>
          <th>Seal</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r):
            $id = (int)($r['id'] ?? 0);
            $code = (string)($r['code'] ?? '');
            $name = (string)($r['name'] ?? '');
            $domain = (string)($r['domain'] ?? '');
            $status = strtolower((string)($r['status'] ?? 'active'));
            $active = ($status === 'active');
            $sealed = $domain ? is_sealed($domain) : false;
        ?>
        <tr>
          <td><?= $id ?></td>
          <td><?= h($code) ?></td>
          <td><?= h($name) ?></td>
          <td><?= h($domain) ?></td>
          <td>
            <div class="statusline">
              <span class="dot <?= $active?'on':'off' ?>"></span>
              <?= $active?'active':'inactive' ?>
            </div>
          </td>
          <td>
            <div class="statusline">
              <span class="dot <?= $sealed?'sealed':'nsealed' ?>"></span>
              <?= $sealed?'Sealed':'Not sealed' ?>
            </div>
          </td>
          <td>
            <a class="btn btn-edit" href="edit_license.php?id=<?= $id ?>">Edit</a>
            <a class="btn btn-seal" href="javascript:void(0)" onclick="sealNow('<?= h($domain) ?>','<?= h($code) ?>')">Seal Now</a>
            <a class="btn btn-check" href="javascript:void(0)" onclick="checkHost('<?= h($domain) ?>')">Check</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Center Toast -->
<div id="fsToast" role="alert" aria-live="polite">
  <div class="fs-head" id="tHead">Reseal</div>
  <div class="fs-line" id="tProgress" style="display:none"></div>
  <div class="fs-line fs-ok" id="tOk" style="display:none"></div>
  <div class="fs-line fs-bad" id="tBad" style="display:none"></div>
  <div class="fs-actions"><button class="fs-close" onclick="hideToast()">Close</button></div>
</div>

<script>
const toast = document.getElementById('fsToast');
const tHead = document.getElementById('tHead');
const tProgress = document.getElementById('tProgress');
const tOk = document.getElementById('tOk');
const tBad = document.getElementById('tBad');
let autoT = null;

function showToast(){ toast.classList.add('fs-show'); clearTimeout(autoT); }
function hideToast(){ toast.classList.remove('fs-show'); clearTimeout(autoT); }

function setStep(txt){ tProgress.style.display='block'; tProgress.textContent = txt; tOk.style.display='none'; tBad.style.display='none'; }
function setOk(txt){ tProgress.style.display='none'; tBad.style.display='none'; tOk.style.display='block'; tOk.textContent = txt; }
function setBad(txt){ tProgress.style.display='none'; tOk.style.display='none'; tBad.style.display='block'; tBad.textContent = txt; }

async function sealNow(domain, license){
  if(!domain){ return; }
  tHead.textContent = 'Reseal — ' + domain;
  setStep('🔒 Sealing files and preparing manifest…');
  showToast();

  const fd = new FormData();
  fd.append('domain', domain);
  fd.append('license', license);

  try{
    const r = await fetch('ajax_reseal.php', { method:'POST', body:fd, credentials:'same-origin' });
    let j = null;
    try{ j = await r.json(); }catch(e){}

    if(!j){
      setBad('🚫 Unexpected response (no JSON). HTTP '+r.status);
      return;
    }

    if(j.ok){
      setOk(`✅ Sealed & sent — ${j.files_sent ?? j.sealed_count ?? 0} file(s). Reloading…`);
      autoT = setTimeout(()=>location.reload(), 2200);
    }else{
      const msg = j.message || j.error || j.response || 'Failed';
      setBad(`🚫 Send failed: ${msg}`);
    }
  }catch(err){
    setBad('🚫 Request error: ' + err);
  }
}

async function checkHost(domain){
  tHead.textContent = 'Check — ' + domain;
  setStep('🔎 Contacting host…');
  showToast();
  try{
    const r = await fetch('ajax_check_integrity.php?domain='+encodeURIComponent(domain), {credentials:'same-origin'});
    const j = await r.json();
    if(j.ok){
      setOk('✅ '+(j.message||'Integrity OK'));
    }else{
      setBad('❌ '+(j.message||'Integrity failed'));
    }
  }catch(e){
    setBad('❌ Check failed: '+e);
  }
}
</script>
</body>
</html>
