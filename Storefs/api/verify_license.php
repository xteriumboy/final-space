<?php
/**
 * Final Space Store — /api/verify_license.php v5.6
 * Contacts the central License Server to validate license.
 * Responds with JSON {ok:true|false, message:"..."}.
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

$CFG = require __DIR__ . '/../config.php';

$domain  = $_SERVER['HTTP_HOST'] ?? '';
$license = trim($CFG['license_code'] ?? '');
$server  = rtrim($CFG['license_server'] ?? '', '/');

if (!$license || !$server || !$domain) {
    echo json_encode([
        'ok' => false,
        'error' => 'missing_config',
        'message' => 'Missing license_server or license_code in config.'
    ]);
    exit;
}

// ---- call central license server properly ----
$url = $server . '/api/verify_license.php?domain=' . urlencode($domain) . '&license=' . urlencode($license);

$context = stream_context_create(['http' => ['timeout' => 8]]);
$response = @file_get_contents($url, false, $context);

if ($response === false) {
    echo json_encode([
        'ok' => false,
        'error' => 'no_response',
        'message' => 'License server not reachable.'
    ]);
    exit;
}

$data = json_decode($response, true);
if (!$data) {
    echo json_encode([
        'ok' => false,
        'error' => 'invalid_json',
        'message' => 'License server returned invalid data.'
    ]);
    exit;
}

// ---- pass-through simplified result ----
if (!empty($data['ok'])) {
    echo json_encode([
        'ok' => true,
        'message' => 'License verified successfully.'
    ]);
} else {
    echo json_encode([
        'ok' => false,
        'error' => $data['error'] ?? 'license_invalid',
        'message' => $data['message'] ?? 'License invalid or revoked.'
    ]);
}
