<?php
function load_config(): array {
    $path = __DIR__ . '/config.php';
    if (!file_exists($path)) {
        throw new RuntimeException('Missing config.php. Copy config.example.php and set your keys.');
    }
    return require $path;
}

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function tokens_path(array $config): string {
    $storage = $config['storage_dir'];
    if (!is_dir($storage)) {
        mkdir($storage, 0700, true);
    }
    return rtrim($storage, '/') . '/download_tokens.json';
}

function load_tokens(array $config): array {
    $path = tokens_path($config);
    if (!file_exists($path)) {
        return [];
    }
    $decoded = json_decode(file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function save_tokens(array $config, array $tokens): void {
    file_put_contents(tokens_path($config), json_encode($tokens, JSON_PRETTY_PRINT));
}

function latest_pack(array $config): ?string {
    $files = glob(rtrim($config['pack_dir'], '/') . '/card_pack_*.pdf');
    if (!$files) {
        return null;
    }
    sort($files);
    return end($files);
}

function generate_token(): string {
    return bin2hex(random_bytes(16));
}
