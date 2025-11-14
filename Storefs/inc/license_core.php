<?php
/**
 * Final Space Store — license_core.php v7.1 (fixed loader)
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

/* ---------- Load config ---------- */
if (!isset($CFG) || !is_array($CFG)) {
    $configFile = dirname(__DIR__) . '/config.php';
    if (is_file($configFile)) {
        require $configFile;
    } else {
        die('Missing config.php');
    }
}

/* ---------- PATHS ---------- */
$BASE      = realpath(__DIR__ . '/..');
$PROT_DIR  = $BASE . '/protected';
$KEYS_DIR  = $BASE . '/keys';
$DATA_DIR  = $BASE . '/data';

$MANIFEST  = $PROT_DIR . '/manifest.json';
$SIGFILE   = $PROT_DIR . '/manifest.sig';
$PUBKEY    = $KEYS_DIR . '/server_public.pem';
$CACHE_OK_FILE = $DATA_DIR . '/.integrity_ok.cache';
$CACHE_LICENSE = $DATA_DIR . '/license_cache.json';
$CACHE_TTL  = 180;
$DEBUG      = isset($_GET['fsdebug']);
