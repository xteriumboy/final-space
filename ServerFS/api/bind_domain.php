<?php
/**
 * Final Space License Server — bind_domain.php v9.3.1
 * ---------------------------------------------------
 * - Uses your existing DB columns: client_wallet, client_email
 * - Automatically binds domain + stores client wallet/email
 * - Updates bound_domain, bound_ip, status
 * - Logs all actions in /data/bind_log.txt
 * - Compatible with PHP 8.2 + admin v3.0
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/security.php';

/* ---------- Helpers ---------- */
function respond(bool $ok, array $extra = []): never {
    echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_SLASHES);
    exit;
}
function log_bind(string $msg): void {
    $log = __DIR__ . '/../data/bind_log.txt';
    @file_put_contents($log, '[' . date('Y-m-d H:i:s') . ' UTC] ' . $msg . PHP_EOL, FILE_APPEND);
}
function clean(?string $v): string {
    return trim((string)$v);
}

/* ---------- Input ---------- */
$license = clean($_POST['license'] ?? '');
$domain  = clean($_POST['domain']  ?? '');
$email   = clean($_POST['email']   ?? '');
$wallet  = clean($_POST['wallet']  ?? '');
$ip      = $_SERVER['REMOTE_ADDR'] ?? '';

if ($license === '' || $domain === '') {
    respond(false, ['error' => 'missing_parameters']);
}

global $DB;
if (!$DB) respond(false, ['error' => 'db_not_connected']);

/* ---------- Lookup license ---------- */
$st = $DB->prepare("SELECT * FROM licenses WHERE code=:code LIMIT 1");
$st->execute([':code' => $license]);
$lic = $st->fetch(PDO::FETCH_ASSOC);
if (!$lic) {
    log_bind("❌ Invalid license $license from $domain ($ip)");
    respond(false, ['error' => 'invalid_license']);
}

/* ---------- Check existing binding ---------- */
$bound = trim($lic['bound_domain'] ?? '');
if ($bound !== '' && strcasecmp($bound, $domain) !== 0) {
    log_bind("⚠️ Domain mismatch for $license — existing: $bound, new: $domain ($ip)");
    respond(false, ['error' => 'domain_already_bound', 'bound_domain' => $bound]);
}

/* ---------- Update record ---------- */
try {
    $q = $DB->prepare("
        UPDATE licenses
           SET bound_domain   = :domain,
               bound_ip       = :ip,
               client_email   = :email,
               client_wallet  = :wallet,
               status         = 'active'
         WHERE code = :code
         LIMIT 1
    ");
    $q->execute([
        ':domain' => $domain,
        ':ip'     => $ip,
        ':email'  => $email,
        ':wallet' => $wallet,
        ':code'   => $license
    ]);

    log_bind("✅ Bound license $license to $domain ($ip) — client_email=$email, client_wallet=$wallet");
    respond(true, [
        'message' => 'License bound successfully',
        'license' => $license,
        'domain'  => $domain,
        'ip'      => $ip
    ]);
} catch (Throwable $e) {
    log_bind("❌ DB error while binding $license ($domain): " . $e->getMessage());
    respond(false, ['error' => 'db_error']);
}
