<?php
declare(strict_types=1);
require_once __DIR__ . '/boot.php';

$errors = [];
if (!is_dir(FS_KEYS_DIR)) $errors[] = "Keys dir not found: " . FS_KEYS_DIR;
if (!is_dir(FS_DATA_DIR)) $errors[] = "Data dir not found: " . FS_DATA_DIR;
if (!is_file(FS_KEYS_DIR . '/server_public.pem')) $errors[] = "Missing server_public.pem";
if (!is_file(FS_KEYS_DIR . '/aes_secret.key')) $errors[] = "Missing AES secret";

header('Content-Type: application/json');
echo json_encode([
  'ok' => empty($errors),
  'keys_dir' => FS_KEYS_DIR,
  'data_dir' => FS_DATA_DIR,
  'has_public_key' => is_file(FS_KEYS_DIR . '/server_public.pem'),
  'has_aes_secret' => is_file(FS_KEYS_DIR . '/aes_secret.key'),
  'errors' => $errors
], JSON_PRETTY_PRINT);
