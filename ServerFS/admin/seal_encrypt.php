<?php
/**
 * License Server — seal_encrypt.php (CLI or browser run, admin-only)
 * Encrypts source PHP files into /out/<rel>.php.enc using per-domain CEK.
 * Encrypted format: "FSSL" + ver(1) + IV(12) + CIPHERTEXT + TAG(16)
 */

declare(strict_types=1);
error_reporting(0);

// INPUT: domain, src_root, out_root, list of php files (relative to src_root)
$domain  = $_GET['domain']   ?? '2.final-space.com';
$srcRoot = rtrim($_GET['src'] ?? (__DIR__ . '/../../sealed_src'), '/');
$outRoot = rtrim($_GET['out'] ?? (__DIR__ . '/../../sealed_out'), '/');
$files   = $_GET['files']    ?? 'core/auth.php,core/checkout.php';

@mkdir($outRoot, 0755, true);

function db_get_or_create_cek_for_domain(string $domain): string {
    $dir = __DIR__ . '/../../data/ceks';
    @mkdir($dir, 0755, true);
    $fn = $dir . '/' . preg_replace('/[^a-z0-9.-]/i','_', $domain) . '.bin';
    if (is_file($fn)) {
        $k = @file_get_contents($fn);
        if ($k !== false && strlen($k) === 32) return $k;
    }
    $k = random_bytes(32);
    @file_put_contents($fn, $k);
    return $k;
}
function hkdf(string $ikm, string $salt, string $info, int $len=32): string {
    if ($salt==='') $salt=str_repeat("\0",32);
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    $okm='';$t='';$i=0;
    while(strlen($okm)<$len){$i++;$t=hash_hmac('sha256',$t.$info.chr($i),$prk,true);$okm.=$t;}
    return substr($okm,0,$len);
}

$cek = db_get_or_create_cek_for_domain($domain);
$list = array_filter(array_map('trim', explode(',', $files)));

$done = [];
foreach ($list as $rel) {
    $src = $srcRoot . '/' . $rel;
    if (!is_file($src)) { $done[] = ['file'=>$rel,'status'=>'missing_src']; continue; }

    $plain = file_get_contents($src);
    $iv  = random_bytes(12);
    $key = hkdf($cek, $domain.'|'.preg_replace('#\.php$#','',$rel), 'FS:SealedPHP', 32);
    $tag = '';
    $ct  = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ct === false) { $done[]=['file'=>$rel,'status'=>'encrypt_fail']; continue; }

    $blob = 'FSSL' . chr(1) . $iv . $ct . $tag;

    // write to outRoot maintaining subfolders
    $out = $outRoot . '/' . preg_replace('#\.php$#','',$rel) . '.php.enc';
    @mkdir(dirname($out), 0755, true);
    file_put_contents($out, $blob);

    $done[] = ['file'=>$rel,'status'=>'ok','out'=>str_replace(__DIR__.'/','',$out)];
}

header('Content-Type: application/json');
echo json_encode(['ok'=>true,'domain'=>$domain,'count'=>count($done),'items'=>$done], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
