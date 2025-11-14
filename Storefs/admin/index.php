<?php
/**
 * Final Space Store — admin/index.php v7.0 ANIMATED
 * -------------------------------------------------
 * - Image hover zoom + description overlay
 * - Modern buttons
 * - Smooth modal confirmation for delete
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

$CFG = require __DIR__ . '/../config.php';
if (empty($_SESSION['fs_admin'])) {
    header('Location: login.php');
    exit;
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $CFG['db_host'], $CFG['db_name']),
        $CFG['db_user'], $CFG['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    die('<pre style="color:#f87171">DB connection failed: ' . htmlspecialchars($e->getMessage()) . '</pre>');
}

$msg = '';
if (isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
    $msg = "🗑️ Product deleted.";
}

$rows = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Final Space — Admin Dashboard</title>
<style>
body {
  font-family: 'Inter', 'Segoe UI', Arial;
  background: #0f172a;
  color: #e2e8f0;
  margin: 0;
  overflow-x: hidden;
}
header {
  background: #1e293b;
  padding: 20px 40px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
h1 {
  margin: 0;
  font-size: 22px;
  color: #93c5fd;
}
a {
  color: #93c5fd;
  text-decoration: none;
}
.add-btn {
  background: #10b981;
  border: none;
  color: #fff;
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: background .2s;
}
.add-btn:hover { background: #059669; }
main {
  padding: 40px;
  max-width: 1300px;
  margin: auto;
}
.msg {
  color: #a5b4fc;
  margin-bottom: 20px;
  font-weight: 500;
}
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 25px;
}
.card {
  background: #1e293b;
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 0 15px rgba(0,0,0,.35);
  position: relative;
  transition: transform .3s;
}
.card:hover { transform: translateY(-6px); }
.imgwrap {
  position: relative;
  overflow: hidden;
  height: 180px;
}
.imgwrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform .5s ease;
}
.imgwrap:hover img { transform: scale(1.15); }
.overlay {
  position: absolute;
  bottom: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(15,23,42,.85);
  color: #dbeafe;
  opacity: 0;
  transition: opacity .3s;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 15px;
  font-size: 14px;
  line-height: 1.4;
}
.imgwrap:hover .overlay { opacity: 1; }
.name {
  font-weight: 600;
  font-size: 18px;
  margin: 10px 0 4px;
}
.price {
  color: #fbbf24;
  font-weight: 700;
  font-size: 15px;
  margin-bottom: 6px;
}
.actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0 12px;
}
.btn {
  padding: 8px 14px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  font-size: 14px;
  transition: all .25s;
}
.btn-edit {
  background: #2563eb;
  color: #fff;
}
.btn-edit:hover {
  box-shadow: 0 0 10px #2563eb99;
}
.btn-del {
  background: #dc2626;
  color: #fff;
}
.btn-del:hover {
  box-shadow: 0 0 10px #dc262699;
}
.file-tag {
  position: absolute;
  top: 10px;
  right: 10px;
  background: #334155;
  color: #93c5fd;
  padding: 4px 6px;
  border-radius: 6px;
  font-size: 13px;
  cursor: help;
}
.file-tag:hover::after {
  content: attr(data-file);
  position: absolute;
  top: 22px;
  right: 0;
  background: #0b1220;
  color: #38bdf8;
  padding: 6px 8px;
  border-radius: 6px;
  white-space: nowrap;
  box-shadow: 0 0 10px rgba(0,0,0,.5);
}

/* ---------- Modal ---------- */
.modal-bg {
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(15,23,42,.85);
  display: none;
  align-items: center;
  justify-content: center;
  animation: fadeIn .3s forwards;
  z-index: 50;
}
.modal {
  background: #1e293b;
  padding: 25px;
  border-radius: 12px;
  text-align: center;
  width: 320px;
  box-shadow: 0 0 25px rgba(0,0,0,.6);
}
.modal h3 {
  margin-top: 0;
  color: #f87171;
}
.modal button {
  margin: 8px 10px;
  padding: 8px 16px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}
.modal .yes {
  background: #dc2626;
  color: #fff;
}
.modal .no {
  background: #334155;
  color: #fff;
}
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
</style>
</head>
<body>
<header>
  <h1>Final Space — Admin Dashboard</h1>
  <div>
    <a href="product_edit.php" class="add-btn">+ Add New Product</a> |
    <a href="check_sealed_files.php" class="add-btn">Sealed</a> |
    <a href="logout.php" style="color:#f87171">Logout</a>
  </div>
</header>
<main>
<?php if($msg): ?>
  <div class="msg"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if(!$rows): ?>
  <p>No products yet. <a href="product_edit.php">Add one</a>.</p>
<?php else: ?>
<div class="grid">
<?php foreach($rows as $p): ?>
  <div class="card">
    <?php if($p['file_path']): ?>
      <div class="file-tag" data-file="<?= htmlspecialchars(basename($p['file_path'])) ?>">📦</div>
    <?php endif; ?>
    <div class="imgwrap">
      <img src="<?= htmlspecialchars($p['image'] ?: '/assets/img/placeholder.jpg') ?>" alt="">
      <div class="overlay"><?= htmlspecialchars($p['description']) ?></div>
    </div>
    <div style="padding:12px 14px">
      <div class="name"><?= htmlspecialchars($p['name']) ?></div>
      <div class="price">£<?= htmlspecialchars($p['price']) ?> / USDT</div>
      <div class="actions">
        <a href="product_edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-edit">Edit</a>
        <button class="btn btn-del" onclick="confirmDelete(<?= (int)$p['id'] ?>)">Delete</button>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</main>

<!-- Delete Modal -->
<div class="modal-bg" id="modal-bg">
  <div class="modal">
    <h3>Delete Product?</h3>
    <p>This action cannot be undone.</p>
    <form method="post" id="deleteForm">
      <input type="hidden" name="delete_id" id="delete_id">
      <button type="submit" class="yes">Delete</button>
      <button type="button" class="no" onclick="closeModal()">Cancel</button>
    </form>
  </div>
</div>

<script>
const modalBg=document.getElementById('modal-bg');
const deleteId=document.getElementById('delete_id');
function confirmDelete(id){
  deleteId.value=id;
  modalBg.style.display='flex';
}
function closeModal(){
  modalBg.style.animation='fadeOut .3s forwards';
  setTimeout(()=>{modalBg.style.display='none';modalBg.style.animation='fadeIn .3s';},250);
}
</script>
</body>
</html>
