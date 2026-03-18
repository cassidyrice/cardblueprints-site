<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { http_response_code(400); echo json_encode(['error' => 'Invalid input']); exit; }

$required = ['name','email','birth_month','birth_day','birth_year','read_month','read_day','read_year','question'];
foreach ($required as $f) {
    if (empty($input[$f])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing field: $f"]);
        exit;
    }
}

$config      = require __DIR__ . '/config.php';
$stripe_key  = $config['stripe_secret_key'];
$success_url = 'https://www.cardblueprints.com/reading-confirm.html?session_id={CHECKOUT_SESSION_ID}';
$cancel_url  = 'https://www.cardblueprints.com/reading.html';

$birthday   = "{$input['birth_month']}/{$input['birth_day']}/{$input['birth_year']}";
$read_date  = "{$input['read_month']}/{$input['read_day']}/{$input['read_year']}";
$question   = substr($input['question'], 0, 500);

$data = http_build_query([
    'mode'                                              => 'payment',
    'success_url'                                       => $success_url,
    'cancel_url'                                        => $cancel_url,
    'payment_method_types[0]'                           => 'card',
    'line_items[0][price_data][currency]'               => 'usd',
    'line_items[0][price_data][unit_amount]'            => 9900,
    'line_items[0][price_data][product_data][name]'     => 'Personal Card Reading — 1 Question',
    'line_items[0][price_data][product_data][description]' => 'Personally recorded video reading delivered within 7 business days.',
    'line_items[0][quantity]'                           => 1,
    'customer_email'                                    => $input['email'],
    'metadata[type]'                                    => 'reading',
    'metadata[name]'                                    => $input['name'],
    'metadata[birthday]'                                => $birthday,
    'metadata[read_date]'                               => $read_date,
    'metadata[question]'                                => $question,
]);

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD        => $stripe_key . ':',
    CURLOPT_POSTFIELDS     => $data,
]);
$response = curl_exec($ch);
$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$decoded = json_decode($response, true);
if ($status >= 400 || !isset($decoded['id'])) {
    http_response_code(500);
    echo json_encode(['error' => $decoded['error']['message'] ?? 'Unable to create session']);
    exit;
}
echo json_encode(['id' => $decoded['id']]);
