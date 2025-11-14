<?php
require_once __DIR__.'/../inc/boot.php';
ls_require_admin();

$id = intval($_GET['id'] ?? 0);
if (!$id) die('Missing ID');

$stmt = $DB->prepare("SELECT code FROM licenses WHERE id=?");
$stmt->execute([$id]);
$lic = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$lic) die('License not found');

$DB->prepare("DELETE FROM licenses WHERE id=?")->execute([$id]);

// Remove integrity and reference folder if exists
@exec('rm -rf ' . escapeshellarg(__DIR__ . '/../data/integrity/' . $lic['code']));
@exec('rm -rf ' . escapeshellarg(__DIR__ . '/../data/reference/' . $lic['code']));

echo "<!doctype html><meta charset='utf-8'>
<style>body{background:#0b0f14;color:#e7ebf0;font-family:system-ui;text-align:center;padding:60px}
.msg{background:#0f172a;padding:20px;border-radius:12px;display:inline-block}
a{color:#8ab4ff}</style>
<div class='msg'><h2>🗑️ License Deleted</h2>
<p>Code: ".htmlspecialchars($lic['code'])."</p>
<p>Redirecting...</p></div>
<script>setTimeout(()=>location.href='/admin/index.php',1500)</script>";
