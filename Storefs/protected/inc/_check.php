<?php
declare(strict_types=1);
require_once __DIR__ . '/boot.php';

$errors = [];
if (!is_dir(FS_KEYS_DIR)) $errors[] = "Keys dir not found: " . FS_KEYS_DIR;
if (!is_dir(FS_DATA_DIR)) $errors[] = "Data dir not found: " . FS_DATA_DIR;
if (!is_file(FS_AES_SECRET_FILE)) $errors[] = "AES secret missing: " . FS_AES_SECRET_FILE;

header('Content-Type: application/json');
echo json_encode([
  'ok' => empty($errors),
  'keys_dir' => FS_KEYS_DIR,
  'data_dir' => FS_DATA_DIR,
  'aes_secret_exists' => is_file(FS_AES_SECRET_FILE),
  'errors' => $errors
], JSON_PRETTY_PRINT);
