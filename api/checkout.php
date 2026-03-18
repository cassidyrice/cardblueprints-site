<?php
require __DIR__ . '/helpers.php';
$config = load_config();

$payload = json_decode(file_get_contents('php://input'), true) ?? [];
$successUrl = str_replace('{token}', 'PENDING', $config['success_url']);
$cancelUrl = $config['cancel_url'];

$data = http_build_query([
    'mode' => 'payment',
    'success_url' => $successUrl,
    'cancel_url' => $cancelUrl,
    'payment_method_types[0]' => 'card',
    'line_items[0][price_data][currency]' => $config['currency'],
    'line_items[0][price_data][unit_amount]' => $config['price_cents'],
    'line_items[0][price_data][product_data][name]' => $config['product_name'],
    'line_items[0][quantity]' => 1,
]);

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $config['stripe_secret_key'] . ':',
    CURLOPT_POSTFIELDS => $data,
]);
$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($response === false) {
    json_response(['error' => curl_error($ch)], 500);
}
$decoded = json_decode($response, true);
if ($status >= 400 || !isset($decoded['id'])) {
    json_response(['error' => $decoded['error']['message'] ?? 'Unable to create session'], 500);
}
json_response(['id' => $decoded['id']]);
