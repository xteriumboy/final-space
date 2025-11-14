<?php
require_once __DIR__ . '/../../inc/boot.php';
ls_require_admin();

header('Content-Type: application/json');

$domain = strtolower(trim($_POST['domain'] ?? ''));
$manifest = $_POST['manifest'] ?? '';

if (!$domain || !$manifest) {
    echo json_encode(['ok' => false, 'error' => 'missing_params']);
    exit;
}

$target = __DIR__ . '/../../data/integrity/' . $domain;
@mkdir($target, 0775, true);
file_put_contents($target . '/manifest.json', $manifest);

echo json_encode(['ok' => true, 'path' => $target . '/manifest.json']);
