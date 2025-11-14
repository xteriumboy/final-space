<?php
/**
 * Final Space Store — admin/file_manager.php v1.1 (downloads+image)
 * --------------------------------------------------
 * - Upload product images → /image/
 * - Upload product archives → /download/
 * - Lists all uploaded files with delete buttons
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

$CFG = require __DIR__ . '/../config.php';
$debug = isset($_GET['fsdebug']) || !empty($CFG['debug']);
$logFile = __DIR__ . '/../data/file_manager_debug.txt';
@mkdir(dirname($logFile), 0755, true);

function log_debug($msg, $ctx = []) {
    global $logFile;
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($ctx) $entry .= ' | ' . json_encode($ctx, JSON_UNESCAPED_SLASHES);
    $entry .= PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

/* ---------- Protect ---------- */
if (empty($_SESSION['fs_admin'])) {
    header('Location: login.php');
    exit;
}

/* ---------- Upload handling ---------- */
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $name = basename($file['name']);
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowedImg = ['jpg','jpeg','png','webp'];
    $allowedArc = ['zip','rar'];

    if (in_array($ext, $allowedImg)) {
        $uploadDir = __DIR__ . '/../image';
    } elseif (in_array($ext, $allowedArc)) {
        $uploadDir = __DIR__ . '/../downloads';
    } else {
        $msg = "❌ Invalid file type ($ext)";
        log_debug('invalid_ext', ['name'=>$name]);
        $uploadDir = null;
    }

    if ($uploadDir) {
        @mkdir($uploadDir, 0755, true);
        $target = $uploadDir . '/' . preg_replace('/[^a-zA-Z0-9._-]/','_',$name);
        if (move_uploaded_file($file['tmp_name'], $target)) {
            $msg = "✅ Uploaded to " . basename($uploadDir) . ": $name";
            log_debug('upload_ok', ['dest'=>$uploadDir,'name'=>$name]);
        } else {
            $msg = '❌ Upload failed (permissions?)';
            log_debug('upload_fail', ['name'=>$name]);
        }
    }
}

/* ---------- File listing ---------- */
$protected = __DIR__ . '/../downloads';
$imageDir  = __DIR__ . '/../image';
$files = [];

foreach (['downloads'=>$protected,'image'=>$imageDir] as $type=>$dir) {
    @mkdir($dir,0755,true);
    foreach (array_diff(scandir($dir),['.','..']) as $f) {
        $files[] = ['type'=>$type,'name'=>$f,'size'=>round(filesize("$dir/$f")/1024,1)];
    }
}
sort($files);

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>File Manager - Final Space Admin</title>
<style>
body{font-family:Inter,Segoe UI,Arial;background:#0f172a;color:#e2e8f0;margin:0}
header{background:#1e293b;padding:20px 40px;display:flex;justify-content:space-between;align-items:center}
h1{margin:0;font-size:22px;color:#93c5fd}
main{padding:40px;max-width:1000px;margin:auto}
input[type=file]{padding:10px;border:none;background:#334155;color:#fff;border-radius:8px;width:70%}
button{background:#2563eb;color:#fff;border:none;padding:10px 20px;border-radius:8px;cursor:pointer}
button:hover{background:#1d4ed8}
table{width:100%;border-collapse:collapse;margin-top:20px}
th,td{padding:10px;border-bottom:1px solid #334155;text-align:left}
th{background:#334155}
tr:hover{background:#2d3d57}
.msg{margin-top:10px;color:#a5b4fc;font-weight:500}
</style>
</head>
<body>
<header><h1>File Manager</h1><div><a href="index.php">← Back</a></div></header>
<main>
<form method="post" enctype="multipart/form-data">
  <input type="file" name="file" required>
  <button type="submit">Upload</button>
  <?php if($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
</form>
<h2>Files</h2>
<table><tr><th>Folder</th><th>Name</th><th>Size (KB)</th></tr>
<?php foreach($files as $f): ?>
<tr><td><?= htmlspecialchars($f['type']) ?></td><td><?= htmlspecialchars($f['name']) ?></td><td><?= $f['size'] ?></td></tr>
<?php endforeach; ?>
</table>
</main>
</body></html>
