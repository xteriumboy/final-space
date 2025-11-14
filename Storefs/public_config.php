<?php
/**
 * Final Space Store — public_config.php
 * Safe front-end configuration endpoint (read-only)
 * ------------------------------------------------
 * Returns public details for app.js (wallet, network, currency, etc.)
 * Does NOT expose database credentials or secrets.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Compose safe public output
echo json_encode([
    'ok' => true,
    'merchant_address' => $CFG['merchant_address'] ?? '',
    'chain_id_hex'     => '0x38',       // BNB Smart Chain mainnet
    'chain_id_num'     => 56,
    'currency'         => 'USDT',
    'license_server'   => $CFG['license_server'] ?? '',
    'license_code'     => $CFG['license_code'] ?? '',
], JSON_UNESCAPED_SLASHES);
