<?php
/**
 * subscribe-checkout.php
 * Creates a Stripe Checkout session for the monthly letter subscription.
 * Supports monthly ($25/mo) and yearly ($250/yr) billing.
 */
require __DIR__ . '/helpers.php';
$config = load_config();

header('Content-Type: application/json');

$payload = json_decode(file_get_contents('php://input'), true) ?? [];

$plan     = $payload['plan']     ?? 'monthly';
$name     = $payload['name']     ?? '';
$email    = $payload['email']    ?? '';
$birthday = $payload['birthday'] ?? '';
$address  = $payload['address']  ?? '';

// Validate required fields
if (!$name || !$email || !$birthday || !$address) {
    json_response(['error' => 'All fields are required.'], 400);
}

// Live Stripe Price IDs for "The Analog Algorithm — Monthly Letter"
$price_ids = [
    'monthly' => $config['letter_monthly_price_id'] ?? 'price_1TCsLLDgoKThmC0IiFoOkAF8',
    'yearly'  => $config['letter_yearly_price_id']  ?? 'price_1TCsLLDgoKThmC0ILe04dHLC',
];

$price_id = $price_ids[$plan] ?? $price_ids['monthly'];

$line_item_data = http_build_query([
    'line_items[0][price]'    => $price_id,
    'line_items[0][quantity]' => 1,
]);

$success_url = $config['letter_success_url'] ?? 'https://cardblueprints.com/thank-you.html';
$cancel_url  = $config['letter_cancel_url']  ?? 'https://cardblueprints.com/';

$checkout_data = http_build_query([
    'mode'                          => 'subscription',
    'success_url'                   => $success_url,
    'cancel_url'                    => $cancel_url,
    'customer_email'                => $email,
    'payment_method_types[0]'       => 'card',
    'metadata[type]'                => 'letter',
    'metadata[name]'                => $name,
    'metadata[birthday]'            => $birthday,
    'metadata[address]'             => $address,
    'metadata[plan]'                => $plan,
    'subscription_data[metadata][type]'     => 'letter',
    'subscription_data[metadata][name]'     => $name,
    'subscription_data[metadata][birthday]' => $birthday,
    'subscription_data[metadata][address]'  => $address,
]);

$post_data = $line_item_data . '&' . $checkout_data;

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD        => $config['stripe_secret_key'] . ':',
    CURLOPT_POSTFIELDS     => $post_data,
]);
$response = curl_exec($ch);
$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    json_response(['error' => curl_error($ch)], 500);
}

$decoded = json_decode($response, true);
curl_close($ch);

if ($status >= 400 || !isset($decoded['url'])) {
    $error_msg = $decoded['error']['message'] ?? 'Unable to create checkout session.';
    json_response(['error' => $error_msg], 500);
}

json_response(['url' => $decoded['url']]);
