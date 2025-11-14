<?php
/**
 * Final Space License Server — Config (Fixed)
 */
$CFG['seal_auth']           = 'FS-SEAL-AUTH-2025';
$CFG['seal_aes_key']        = 'FS-SEAL-ENC-2025';

$CFG = [

    /* ---------- DATABASE ---------- */
    'db_host' => 'localhost',
    'db_name' => 'yvettefashi_licenses',
    'db_user' => 'yvettefashi_0',
    'db_pass' => 'Finalspace@%87',

    /* ---------- ADMIN LOGIN ---------- */
    // Username:
    'admin_user' => 'admin',
    // Password hash for "admin123"
    'admin_pass_hash' => '$2y$10$0hXWUY4ywJE3JWlb2KJjDeqsHtDXEXMLue1kDyTbeFabwSx3nloJu',

    /* ---------- AUTO SEAL ---------- */
    'auto_seal_secret' => 'fsAutoSeal@2025!',

    /* ---------- JWT & TIME ---------- */
    'jwt_ttl' => 600,
    'jwt_issuer' => 'licenses.final-space.com',
    'time_zone' => 'UTC',

    /* ---------- PATHS ---------- */
    'paths' => [
        'logs'      => __DIR__ . '/data/logs',
        'integrity' => __DIR__ . '/data/integrity',
        'sessions'  => __DIR__ . '/data/sessions',
    ],
];
