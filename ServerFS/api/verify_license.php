<?php
/**
 * Final Space License Server — verify_license.php v5.3
 * ----------------------------------------------------
 * - Works standalone (no fs_pdo dependency)
 * - Accepts both GET and POST JSON input
 * - Validates license, domain, and status
 * - Optional manifest hash integrity check
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/security.php';

// ---------- INPUT ----------
$input  = json_decode(file_get_contents('php://input'), true);
$domain = strtolower(trim($_GET['domain'] ?? $input['domain'] ?? ''));
$license = strtoupper(trim($_GET['license'] ?? $input['license'] ?? ''));
$hash    = trim($input['manifest_hash'] ?? '');
$ip      = $_SERVER['REMOTE_ADDR'] ?? '';

if (!$domain || !$license) {
    echo json_encode(['ok'=>false,'error'=>'missing_params']);
    exit;
}

try {
    // Connect manually to DB (same logic as fs_pdo)
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $CFG['db_host'], $CFG['db_name']);
    $pdo = new PDO($dsn, $CFG['db_user'], $CFG['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Find license
    $st = $pdo->prepare("SELECT * FROM licenses WHERE code=? LIMIT 1");
    $st->execute([$license]);
    $lic = $st->fetch();
    if (!$lic) {
        echo json_encode(['ok'=>false,'error'=>'license_not_found']);
        exit;
    }

    // Check domain match
    if (strtolower($lic['domain']) !== $domain) {
        echo json_encode(['ok'=>false,'error'=>'domain_mismatch','expected'=>$lic['domain']]);
        exit;
    }

    // Check status
    if ($lic['status'] !== 'active') {
        echo json_encode(['ok'=>false,'error'=>'license_invalid','details'=>'License inactive or revoked']);
        exit;
    }

    // Optional manifest hash verification
    if ($hash) {
        $localManifest = __DIR__ . "/../data/integrity/{$domain}/manifest.json";
        if (is_file($localManifest)) {
            $data = json_decode(file_get_contents($localManifest), true);
            $calc = sha1(json_encode($data['files'] ?? []));
            if ($calc !== $hash) {
                echo json_encode(['ok'=>false,'error'=>'manifest_mismatch','details'=>'Integrity check failed']);
                exit;
            }
        }
    }

    // Everything ok
    echo json_encode([
        'ok' => true,
        'status' => $lic['status'],
        'domain' => $lic['domain'],
        'license' => $lic['code'],
        'wallet' => $lic['wallet'] ?? null,
        'time' => time(),
        'ip' => $ip
    ]);

} catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'server_error','details'=>$e->getMessage()]);
}
