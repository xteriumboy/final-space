<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>PHP OK\n";

$base = realpath(__DIR__ . '/../protected');
echo "Protected folder: $base\n";

$manifest = $base . '/manifest.json';
if (is_file($manifest)) {
    echo "Manifest found\n";
    $data = json_decode(file_get_contents($manifest), true);
    echo "Decoded entries: " . count($data['files'] ?? []) . "\n";
} else {
    echo "Manifest not found\n";
}
