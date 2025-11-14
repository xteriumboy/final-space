<?php
/**
 * Final Space Store Installer
 * - Reads config.php and creates tables + folders
 */
error_reporting(E_ALL); ini_set('display_errors',1);
$CFG = require __DIR__ . '/config.php';
function out($s){ echo '<pre style="margin:0">'.htmlspecialchars($s)."</pre>"; flush(); }
try{
  $pdo = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',$CFG['db_host'],$CFG['db_name']),$CFG['db_user'],$CFG['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  out("DB connected: ".$CFG['db_name']);
}catch(Throwable $e){ out("DB connect failed: ".$e->getMessage()); exit; }

$sql = [];
$sql[] = "CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  price_bnb DECIMAL(30,18) NULL,
  image VARCHAR(255) DEFAULT NULL,
  description TEXT NULL,
  file_path VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$sql[] = "CREATE TABLE IF NOT EXISTS purchases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  wallet VARCHAR(100) NOT NULL,
  txhash VARCHAR(100) NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'confirmed',
  price_bnb DECIMAL(30,18) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(product_id), INDEX(wallet)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

foreach($sql as $q){ $pdo->exec($q); out('OK: '.substr($q,0,60).'...'); }

@mkdir(__DIR__.'/uploads',0755,true);
@mkdir(__DIR__.'/data',0755,true);

out('Installer complete. You can now open /admin/login.php (user: '.$CFG['admin_user'].')');
