<?php
require __DIR__ . '/helpers.php';
$config = load_config();
$token = $_GET['token'] ?? '';
if (!$token) {
    exit('Missing token');
}
$tokens = load_tokens($config);
if (!isset($tokens[$token])) {
    exit('Invalid or expired token');
}
$entry = $tokens[$token];
$pack = $entry['pack'] ?? latest_pack($config);
if (!$pack || !file_exists($pack)) {
    exit('Pack unavailable');
}
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($pack) . '"');
readfile($pack);
