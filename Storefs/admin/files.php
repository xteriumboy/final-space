<?php
/**
 * Final Space Admin — files.php v2.1 (protected+image)
 * ---------------------------------------------------
 * - Lists all uploaded files (images + archives)
 * - Uploads to correct folders
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
if (empty($_SESSION['fs_admin'])) { header('Location: login.php'); exit; }

$CFG = require __DIR__ . '/../config.php';

function fs_safe_name($n) {
  return preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($n));
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
  $f = $_FILES['file'];
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  $safe = fs_safe_name($f['name']);

  $allowedImg = ['jpg','jpeg','png','webp'];
  $allowedArc = ['zip','rar'];

  if (in_array($ext, $allowedImg)) {
    $targetDir = __DIR__ . '/../image';
  } elseif (in_array($ext, $allowedArc)) {
    $targetDir = __DIR__ . '/../protected';
  } else {
    $msg = "❌ Unsupported file type: $ext";
    $targetDir = null;
  }

  if ($targetDir) {
    @mkdir($targetDir,0755,true);
    $target = "$targetDir/$safe";
    if (move_uploaded_file($f['tmp_name'], $target)) {
      $msg = "✅ Uploaded to " . basename($targetDir) . ": $safe";
    } else {
      $msg = "❌ Upload failed (permissions?)";
    }
  }
}

function list_files($dir) {
  $out = [];
  if (is_dir($dir)) {
    foreach (array_diff(scandir($dir), ['.','..']) as $f) {
      $path = "$dir/$f";
      if (is_file($path)) {
        $out[] = [
          'name' => $f,
          'size' => round(filesize($path)/1024,1),
          'time' => date('Y-m-d H:i', filemtime($path))
        ];
      }
    }
  }
  return $out;
}

$images = list_files(__DIR__ . '/../image');
$protected = list_files(__DIR__ . '/../protected');
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><title>File Manager</title>
<style>
body{font-family:Inter,Segoe UI,Arial;background:#0f172a;color:#e2e8f0;margin:0}
header{background:#1e293b;padding:20px 40px;display:flex;justify-content:space-between;align-items:center}
h1{margin:0;font-size:22px;color:#93c5fd}
main{padding:40px;max-width:900px;margin:auto}
input[type=file]{padding:10px;background:#334155;color:#fff;border:none;border-radius:8px;width:70%}
button{background:#2563eb;color:#fff;border:none;padding:10px 20px;border-radius:8px;cursor:pointer}
button:hover{background:#1d4ed8}
table{width:100%;border-collapse:collapse;margin-top:20px}
th,td{padding:10px;border-bottom:1px solid #334155;text-align:left}
th{background:#334155}
.msg{margin-top:10px;color:#a5b4fc;font-weight:500}
</style></head>
<body>
<header><h1>File Manager</h1><a href="index.php">← Back</a></header>
<main>
<form method="post" enctype="multipart/form-data">
  <input type="file" name="file" required>
  <button type="submit">Upload</button>
  <?php if($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
</form>

<h2>Protected Files</h2>
<table><tr><th>Name</th><th>Size (KB)</th><th>Modified</th></tr>
<?php foreach($protected as $f): ?>
<tr><td><?= htmlspecialchars($f['name']) ?></td><td><?= $f['size'] ?></td><td><?= $f['time'] ?></td></tr>
<?php endforeach; ?></table>

<h2>Images</h2>
<table><tr><th>Name</th><th>Size (KB)</th><th>Modified</th></tr>
<?php foreach($images as $f): ?>
<tr><td><?= htmlspecialchars($f['name']) ?></td><td><?= $f['size'] ?></td><td><?= $f['time'] ?></td></tr>
<?php endforeach; ?></table>
</main></body></html>
