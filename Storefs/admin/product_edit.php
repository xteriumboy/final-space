<?php
/**
 * Final Space Store — admin/product_edit.php v6.1
 * ------------------------------------------------
 * - Uploads images to /image/
 * - Uploads archives to /downloads/
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
$CFG = require __DIR__ . '/../config.php';
if(empty($_SESSION['fs_admin'])){header('Location: login.php');exit;}

try{
  $pdo=new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',$CFG['db_host'],$CFG['db_name']),
    $CFG['db_user'],$CFG['db_pass'],
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
  );
}catch(Throwable $e){die('DB error: '.htmlspecialchars($e->getMessage()));}

$id=(int)($_GET['id']??0);
$edit=$id>0?$pdo->query("SELECT * FROM products WHERE id=$id")->fetch():null;

function handle_upload($f,$isImage){
  $base = __DIR__ . ($isImage ? '/../image' : '/../downloads');
  @mkdir($base,0755,true);
  if(empty($_FILES[$f]['name'])) return '';
  $safe=preg_replace('/[^a-zA-Z0-9._-]/','_',basename($_FILES[$f]['name']));
  $target=$base.'/'.$safe;
  if(move_uploaded_file($_FILES[$f]['tmp_name'],$target)){
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

  if($id>0){
    $sql="UPDATE products SET name=?,price=?,description=?";
    $params=[$name,$price,$desc];
    if($img){$sql.=",image=?";$params[]=$img;}
    if($file){$sql.=",file_path=?";$params[]=$file;}
    $sql.=" WHERE id=?";$params[]=$id;
    $pdo->prepare($sql)->execute($params);
    $msg='✅ Product updated.';
  }else{
    $pdo->prepare("INSERT INTO products (name,price,description,image,file_path) VALUES (?,?,?,?,?)")
        ->execute([$name,$price,$desc,$img,$file]);
    $msg='✅ Product added.';
  }
  header('Refresh:1; url=index.php');
}
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><title><?= $id?'Edit':'Add' ?> Product - Final Space</title>
<style>body{font-family:Inter,Segoe UI,Arial;background:#0f172a;color:#e2e8f0;margin:0}
header{background:#1e293b;padding:20px 40px;display:flex;justify-content:space-between;align-items:center}
main{padding:40px;max-width:700px;margin:auto}input,textarea{width:100%;padding:8px;margin-top:4px;border:none;border-radius:8px;background:#334155;color:#fff}
button{margin-top:15px;background:#2563eb;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-weight:600;cursor:pointer}
button:hover{background:#1d4ed8}.msg{margin-top:10px;color:#a5b4fc;font-weight:500}</style></head>
<body>
<header><h1><?= $id?'Edit':'Add' ?> Product</h1><div><a href="index.php">← Back</a></div></header>
<main>
<form method="post" enctype="multipart/form-data">
<label>Name:</label><input name="name" required value="<?= htmlspecialchars($edit['name']??'') ?>">
<label>Price (£):</label><input name="price" type="number" step="0.01" required value="<?= htmlspecialchars($edit['price']??'') ?>">
<label>Description:</label><textarea name="description" rows="3"><?= htmlspecialchars($edit['description']??'') ?></textarea>
<label>Image (JPG/PNG/WebP):</label><input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
<?php if(!empty($edit['image'])): ?><img src="<?= htmlspecialchars($edit['image']) ?>" style="max-width:100%;margin-top:8px"><?php endif; ?>
<label>Product File (ZIP/RAR):</label><input type="file" name="file" accept=".zip,.rar">
<?php if(!empty($edit['file_path'])): ?><div style="margin-top:5px;color:#93c5fd">Current: <?= htmlspecialchars(basename($edit['file_path'])) ?></div><?php endif; ?>
<button type="submit"><?= $id?'Update':'Add' ?> Product</button>
<?php if($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
</form></main></body></html>
