<?php
/**
 * Final Space Store — config.php v6.0 (Hybrid Compatible)
 * -------------------------------------------------------
 * Works with:
 *  - app.js / license_core.php (expects $CFG)
 *  - install.php / legacy includes (expects return array)
 *  - reseal/receive_manifest.php (uses $CFG keys)
 */

$db_host = "localhost";
$db_user = "yvettefashi_0";
$db_pass = "Finalspace@%87";
$db_name = "yvettefashi_2";

/* =========================================================
   GLOBAL CONFIG
========================================================= */
$CFG = [

    /* ---------- DATABASE ---------- */
    'db_host' => $db_host,
    'db_name' => $db_name,
    'db_user' => $db_user,
    'db_pass' => $db_pass,

    /* ---------- LICENSE ---------- */
    'license_server' => 'https://licenses.final-space.com',
    'license_api'    => 'https://licenses.final-space.com/api/verify_license.php',
    'license_code'   => 'FS-DEMO-000000',

    /* ---------- ADMIN ---------- */
    'email'       => 'owner@finalspace.com',
    'admin_user'  => 'admin',
    'admin_pass'  => '$2y$10$JwTSTVCpBFxTkKRxTmleJO8.N61xeCEgeqK9tbXF7huShvCvd7tOC',

    /* ---------- WALLET ---------- */
    'wallet'           => '0x66e07ee81e8f7a0d3c7a7d96406d287709ad2d9d',
    'merchant_address' => '0x66e07ee81e8f7a0d3c7a7d96406d287709ad2d9d',

    /* ---------- LICENSE SERVER SECURITY ---------- */
    'auto_seal_secret' => 'fsAutoSeal@2025!',
    'allowed_license_server_hosts' => [
        'licenses.final-space.com',
        'https://licenses.final-space.com'
    ],
    'allowed_license_server_ips' => [
        '89.44.109.103'
    ],

    /* ---------- STORE SETTINGS ---------- */
    'site_name' => 'Final Space Store',
    'base_url'  => 'https://2.final-space.com',
    'timezone'  => 'UTC',

    /* ---------- PATHS ---------- */
    'protected_dir' => __DIR__ . '/../protected',
    'data_dir'      => __DIR__ . '/../data',
    'keys_dir'      => __DIR__ . '/../keys',

    /* ---------- EXTRAS ---------- */
    'debug'       => true,
    'wallet_enabled' => true,
    'supported_tokens' => ['BNB', 'USDT', 'ETH'],
    'seal_extra'  => ['index.php','app.js','style.css']
];

/* =========================================================
   BACKWARD COMPATIBILITY FOR OLD LOADERS
========================================================= */
// Provide simple globals if old scripts expect them
$license_server = $CFG['license_server'];
$license_code   = $CFG['license_code'];
$auto_seal_secret = $CFG['auto_seal_secret'];

// Maintain timezone and return array if required
date_default_timezone_set($CFG['timezone']);

// Return for legacy include() style
return $CFG;
