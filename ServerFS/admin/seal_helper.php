<?php
/**
 * Final Space License Server — Seal Helper (FTP uploader)
 */

$domain = '2.final-space.com';
$license = 'FS-DEMO-000000';

echo "Running reseal for {$domain}...\n";
passthru("php " . __DIR__ . "/reseal_and_verify.php domain={$domain} license={$license}");

$path = __DIR__ . "/../data/integrity/{$domain}/";
$files = ['manifest.json','manifest.sig','server_public.pem'];
$ftp_host = "ftp.2.final-space.com";
$ftp_user = "your_ftp_username";
$ftp_pass = "your_ftp_password";
$ftp_dest = "/public_html/protected/";

$conn = ftp_connect($ftp_host);
$login = ftp_login($conn, $ftp_user, $ftp_pass);
ftp_pasv($conn, true);

foreach ($files as $f) {
    $local = $path . $f;
    if (is_file($local)) {
        echo "Uploading $f ... ";
        echo ftp_put($conn, $ftp_dest . $f, $local, FTP_BINARY) ? "✅\n" : "❌\n";
    }
}
ftp_close($conn);
echo "Done.\n";
