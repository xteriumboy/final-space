<?php
/**
 * Final Space License Server — ajax_reseal.php v13.0
 * End-to-end reseal + send:
 *   protected_src/{api,inc} --(AES)--> protected_out/{api,inc}
 *   protected_out --(hashes)--> manifest.json
 *   RSA-sign manifest → POST to client receiver with X-FS-Auth
 *
 * JSON-only responses. No stray output.
 */

declare(strict_types=1);
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../inc/boot.php';
require_once __DIR__ . '/../inc/security.php';
fs_require_admin();

/* ------------------------------------------------------------- */
/*  Paths + Config                                               */
/* ------------------------------------------------------------- */
$CFG           = $CFG ?? [];
$authSecret    = (string)($CFG['auto_seal_secret'] ?? 'fsAutoSeal@2025!');

$root          = dirname(__DIR__);                // /admin -> / (license server root)
$srcRoot       = $root . '/protected_src';
$outRoot       = $root . '/protected_out';
$dataRoot      = $root . '/data';
$integrityRoot = $dataRoot . '/integrity';
$keysDir       = $root . '/keys';
$privKeyFile   = $keysDir . '/server_private.pem';
$pubKeyFile    = $keysDir . '/server_public.pem';

@mkdir($outRoot . '/api', 0755, true);
@mkdir($outRoot . '/inc', 0755, true);
@mkdir($integrityRoot, 0755, true);

/* ------------------------------------------------------------- */
/*  Input                                                        */
/* ------------------------------------------------------------- */
$domain  = trim($_POST['domain']  ?? $_GET['domain']  ?? '');
$license = trim($_POST['license'] ?? $_GET['license'] ?? '');
if ($domain === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_domain']);
    exit;
}

/* Accept plain host ("2.final-space.com") or full URL */
if (stripos($domain, 'http://') !== 0 && stripos($domain, 'https://') !== 0) {
    $domainUrl = 'https://' . $domain;
} else {
    $domainUrl = rtrim($domain, '/');
    $domain    = parse_url($domainUrl, PHP_URL_HOST) ?: $domain;
}
$receiverUrl = $domainUrl . '/api/receive_manifest.php';

/* ------------------------------------------------------------- */
/*  AES Helper (uses the SAME secret as the client)              */
/* ------------------------------------------------------------- */
$aesHelper = $root . '/inc/aes_helper.php';
if (!is_file($aesHelper)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'aes_helper_missing']);
    exit;
}
require_once $aesHelper;

if (!function_exists('fs_aes_encrypt')) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'aes_helper_incomplete']);
    exit;
}

/* ------------------------------------------------------------- */
/*  Utilities                                                    */
/* ------------------------------------------------------------- */
function b64sha256(string $file): string {
    $d = @file_get_contents($file);
    return base64_encode(hash('sha256', (string)$d, true));
}

function encrypt_file_to(string $in, string $out, ?bool &$changed = null): bool {
    $changed = false;
    $plain = @file_get_contents($in);
    if ($plain === false) return false;

    $cipher = fs_aes_encrypt($plain);
    if ($cipher === false || $cipher === '') return false;

    $prev = @file_get_contents($out);
    if ($prev === $cipher) return true;

    @mkdir(dirname($out), 0755, true);
    if (@file_put_contents($out, $cipher) === false) return false;
    @chmod($out, 0644);
    $changed = true;
    return true;
}

/* ------------------------------------------------------------- */
/*  1) AES-encrypt protected_src → protected_out                  */
/* ------------------------------------------------------------- */
$converted = 0;
$srcSets   = ['api', 'inc'];

foreach ($srcSets as $sub) {
    $dir = $srcRoot . '/' . $sub;
    if (!is_dir($dir)) continue;

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        /** @var SplFileInfo $f */
        if (!$f->isFile()) continue;
        $rel     = ltrim(str_replace($srcRoot, '', $f->getPathname()), '/\\'); // e.g. api/get_download.php.aes (we will write same rel)
        $outFile = $outRoot . '/' . $rel;

        $changed = false;
        $ok = encrypt_file_to($f->getPathname(), $outFile, $changed);
        if (!$ok) continue;
        if ($changed) $converted++;
    }
}

/* ------------------------------------------------------------- */
/*  2) Build manifest from protected_out                         */
/*      Client expects paths under /protected/...                */
/* ------------------------------------------------------------- */
$files = [];
$it2 = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($outRoot, FilesystemIterator::SKIP_DOTS)
);
foreach ($it2 as $f) {
    /** @var SplFileInfo $f */
    if (!$f->isFile()) continue;
    $relOut = ltrim(str_replace($outRoot, '', $f->getPathname()), '/\\'); // inc/xxx or api/xxx
    $key    = 'protected/' . $relOut;                                    // manifest key for client
    $files[$key] = b64sha256($f->getPathname());                          // hash of ciphertext
}

$manifest = [
    'domain'    => $domain,
    'license'   => $license,
    'generated' => gmdate('c'),
    'algo'      => 'sha256-base64',
    'files'     => $files,
];

$manifestJson = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($manifestJson === false) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'manifest_json_failed']);
    exit;
}

/* ------------------------------------------------------------- */
/*  3) Sign manifest (RSA-SHA256)                                */
/* ------------------------------------------------------------- */
if (!is_file($privKeyFile) || !is_file($pubKeyFile)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'server_keys_missing']);
    exit;
}
$pkey = openssl_pkey_get_private(@file_get_contents($privKeyFile));
if (!$pkey) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'private_key_invalid']);
    exit;
}
if (!openssl_sign($manifestJson, $sig, $pkey, OPENSSL_ALGO_SHA256)) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'sign_failed']);
    exit;
}
openssl_pkey_free($pkey);
$signatureB64 = base64_encode($sig);

/* ------------------------------------------------------------- */
/*  4) Save integrity snapshot                                   */
/* ------------------------------------------------------------- */
$intDir = $integrityRoot . '/' . preg_replace('~[^a-z0-9\.\-]~i', '_', $domain);
@mkdir($intDir, 0755, true);
@file_put_contents($intDir . '/manifest.json', $manifestJson);
@file_put_contents($intDir . '/manifest.sig',  $signatureB64);

/* ------------------------------------------------------------- */
/*  5) Send to client receiver                                   */
/* ------------------------------------------------------------- */
$multipart = [
    'domain'     => $domain,
    'license'    => $license,
    'manifest'   => new CURLFile($intDir . '/manifest.json', 'application/json', 'manifest.json'),
    'signature'  => new CURLFile($intDir . '/manifest.sig',  'text/plain',        'manifest.sig'),
    'public_key' => new CURLFile($pubKeyFile,                'application/x-pem-file', 'server_public.pem'),
];

$ch = curl_init($receiverUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS     => $multipart,
    CURLOPT_HTTPHEADER     => ['X-FS-Auth: ' . $authSecret, 'Expect:'],
    CURLOPT_SSL_VERIFYPEER => false, // enable true when you have proper CA chain installed
    CURLOPT_TIMEOUT        => 60,
]);
$response = curl_exec($ch);
$http     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err      = curl_error($ch);
curl_close($ch);

/* ------------------------------------------------------------- */
/*  6) Final JSON                                                 */
/* ------------------------------------------------------------- */
if ($response === false) {
    http_response_code(502);
    echo json_encode([
        'ok'    => false,
        'error' => 'curl_error',
        'http'  => $http,
        'curl'  => $err,
        'to'    => $receiverUrl
    ]);
    exit;
}

$decoded = json_decode($response, true);
if ($http !== 200 || !is_array($decoded)) {
    http_response_code($http ?: 500);
    echo json_encode([
        'ok'       => false,
        'error'    => 'receiver_non_json_or_non_200',
        'http'     => $http,
        'to'       => $receiverUrl,
        'response' => is_array($decoded) ? $decoded : $response
    ]);
    exit;
}

echo json_encode([
    'ok'        => (bool)($decoded['ok'] ?? false),
    'domain'    => $domain,
    'converted' => $converted,
    'sealed'    => count($files),
    'receiver'  => $decoded,
    'http'      => $http,
    'to'        => $receiverUrl
], JSON_UNESCAPED_SLASHES);
