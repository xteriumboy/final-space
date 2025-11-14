<?php
/**
 * Final Space Admin — products.php v2.5 (protected+image)
 * ------------------------------------------------------
 * - Product list + add form
 * - Image upload → /image/
 * - File upload → /downloads/
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
if(empty($_SESSION['fs_admin'])){header('Location: login.php');exit;}
$CFG = require __DIR__ . '/../config.php';

try {
  $pdo = new PDO(
    "mysql:host={$CFG['db_host']};dbname={$CFG['db_name']};charset=utf8mb4",
    $CFG['db_user'], $CFG['db_pass'],
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
  );
} catch(Throwable $e) {
  die('<pre style="color:#f87171">DB ERROR: '.$e->getMessage().'</pre>');
}

function fs_safe($n){return preg_replace('/[^a-zA-Z0-9._-]/','_',basename($n));}
function handle_upload($field,$isImage){
  if(empty($_FILES[$field]['name'])) return '';
  $safe=fs_safe($_FILES[$field]['name']);
  $dir=$isImage?__DIR__.'/../image':__DIR__.'/../downloads';
  @mkdir($dir,0755,true);
  $path="$dir/$safe";
  if(move_uploaded_file($_FILES[$field]['tmp_name'],$path)){
    return ($isImage?'/image/':'/downloads/').$safe;
  }
  return '';
}

$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $name=trim($_POST['name']??'');
  $price=(float)($_POST['price']??0);
  $desc=trim($_POST['description']??'');
  $img=handle_upload('image',true);
  $file=handle_upload('file',false);

  $pdo->prepare("INSERT INTO products (name,price,description,image,file_path)
                 VALUES (?,?,?,?,?)")
      ->execute([$name,$price,$desc,$img,$file]);
  $msg="✅ Product added successfully.";
}

$rows=$pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><title>Products - Final Space</title>
<style>
body{font-family:Inter,Segoe UI,Arial;background:#0f172a;color:#e2e8f0;margin:0}
header{background:#1e293b;padding:20px 40px;display:flex;justify-content:space-between;align-items:center}
main{padding:40px;max-width:1000px;margin:auto}
input,textarea{width:100%;padding:8px;margin-top:4px;border:none;border-radius:8px;background:#334155;color:#fff}
button{margin-top:15px;background:#2563eb;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-weight:600;cursor:pointer}
button:hover{background:#1d4ed8}.msg{margin-top:10px;color:#a5b4fc;font-weight:500}
table{width:100%;border-collapse:collapse;margin-top:30px}
th,td{padding:10px;border-bottom:1px solid #334155;text-align:left}
th{background:#334155}
tr:hover{background:#2d3d57}
img.thumb{width:80px;border-radius:8px}
</style></head>
<body>
<header><h1>Products</h1><a href="index.php">← Back</a></header>
<main>
<form method="post" enctype="multipart/form-data">
<label>Name:</label><input name="name" required>
<label>Price ($):</label><input name="price" type="number" step="0.01" required>
<label>Description:</label><textarea name="description" rows="3"></textarea>
<label>Image (JPG/PNG/WebP):</label><input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
<label>Product File (ZIP/RAR):</label><input type="file" name="file" accept=".zip,.rar">
<button type="submit">Add Product</button>
<?php if($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
</form>

<h2>Existing Products</h2>
<table><tr><th>ID</th><th>Image</th><th>Name</th><th>Price</th><th>File</th><th>Actions</th></tr>
<?php foreach($rows as $r): ?>
<tr>
<td><?= $r['id'] ?></td>
<td><?php if($r['image']): ?><img src="<?= htmlspecialchars($r['image']) ?>" class="thumb"><?php endif; ?></td>
<td><?= htmlspecialchars($r['name']) ?></td>
<td>$<?= htmlspecialchars($r['price']) ?></td>
<td><?= htmlspecialchars(basename($r['file_path'])) ?></td>
<td><a href="product_edit.php?id=<?= $r['id'] ?>" style="color:#93c5fd">Edit</a></td>
</tr>
<?php endforeach; ?></table>
</main></body></html>
