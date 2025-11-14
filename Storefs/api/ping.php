<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/protected/inc/boot.php';

echo json_encode([
  'ok'        => true,
  'keys_dir'  => FS_KEYS_DIR,
  'data_dir'  => FS_DATA_DIR,
  'has_pub'   => is_file(FS_KEYS_DIR . '/server_public.pem'),
  'has_aes'   => is_file(FS_KEYS_DIR . '/aes_secret.key'),
  'manifest'  => [
      'json' => is_file(FS_ROOT . '/manifest.json'),
      'sig'  => is_file(FS_ROOT . '/manifest.sig'),
  ]
], JSON_UNESCAPED_SLASHES);
