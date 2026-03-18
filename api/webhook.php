<?php
require __DIR__ . '/helpers.php';
$config  = load_config();
$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!verify_signature($payload, $sigHeader, $config['stripe_webhook_secret'])) {
    http_response_code(400); exit('Invalid signature');
}

$event = json_decode($payload, true);
if (($event['type'] ?? '') === 'checkout.session.completed') {
    $session = $event['data']['object'] ?? [];
    $type    = $session['metadata']['type'] ?? 'pack';
    if ($type === 'reading') {
        handle_reading_completed($session);
    } else {
        handle_checkout_completed($config, $session);
    }
}
http_response_code(200);
echo 'ok';

function verify_signature(string $payload, string $header, string $secret): bool {
    if (!$secret) return false;
    $parts = [];
    foreach (explode(',', $header) as $pair) {
        [$k, $v] = array_pad(explode('=', $pair, 2), 2, null);
        if ($k) $parts[$k] = $v;
    }
    if (!isset($parts['t'], $parts['v1'])) return false;
    $expected = hash_hmac('sha256', $parts['t'] . '.' . $payload, $secret);
    return hash_equals($expected, $parts['v1']);
}

function handle_checkout_completed(array $config, array $session): void {
    $tokens = load_tokens($config);
    $token  = generate_token();
    $pack   = latest_pack($config);
    $email  = $session['customer_details']['email'] ?? 'unknown';
    $name   = $session['customer_details']['name']  ?? '';
    $tokens[$token] = ['email' => $email, 'created' => time(), 'pack' => $pack, 'status' => 'active'];
    save_tokens($config, $tokens);
    notify_supabase('funnel', 'Send purchase follow-up to buyer', [
        'email' => $email, 'name' => $name, 'token' => $token, 'product' => 'Card Blueprints Pack'
    ]);
}

function handle_reading_completed(array $session): void {
    $meta  = $session['metadata'] ?? [];
    $email = $session['customer_details']['email'] ?? ($meta['email'] ?? 'unknown');
    $name  = $meta['name']      ?? '';
    $bday  = $meta['birthday']  ?? '';
    $rdate = $meta['read_date'] ?? '';
    $q     = $meta['question']  ?? '';

    // Save to readings table
    $sb_url = 'https://djopinoumymftemtifrn.supabase.co/rest/v1';
    $sb_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImRqb3Bpbm91bXltZnRlbXRpZnJuIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3MzgyNjMxMiwiZXhwIjoyMDg5NDAyMzEyfQ.z_SxCQUdWSwKOkNH4Kht-oYmhqqB9fm5SGdDbvpoPJE';
    $hdrs   = "apikey: $sb_key\r\nAuthorization: Bearer $sb_key\r\nContent-Type: application/json\r\nPrefer: return=minimal\r\n";

    $reading_body = json_encode([
        'email'    => $email,
        'name'     => $name,
        'question' => $q,
        'spread'   => $rdate,
        'status'   => 'pending',
        'result'   => json_encode(['birthday' => $bday, 'read_date' => $rdate]),
    ]);
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'header' => $hdrs, 'content' => $reading_body, 'ignore_errors' => true
    ]]);
    @file_get_contents("$sb_url/readings", false, $ctx);

    // Notify coordinator
    notify_supabase('coordinator', 'New video reading order — needs to be recorded', [
        'email' => $email, 'name' => $name, 'birthday' => $bday,
        'read_date' => $rdate, 'question' => $q
    ]);

    // Confirmation email via funnel agent
    notify_supabase('funnel', 'Send reading confirmation email to buyer', [
        'email' => $email, 'name' => $name, 'product' => 'Personal Video Reading',
        'details' => "Date: $rdate | Question: $q"
    ]);
}

function notify_supabase(string $agent, string $task, array $payload): void {
    $sb_url = 'https://djopinoumymftemtifrn.supabase.co/rest/v1/tasks';
    $sb_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImRqb3Bpbm91bXltZnRlbXRpZnJuIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3MzgyNjMxMiwiZXhwIjoyMDg5NDAyMzEyfQ.z_SxCQUdWSwKOkNH4Kht-oYmhqqB9fm5SGdDbvpoPJE';
    $body   = json_encode(['assigned_to' => $agent, 'task' => $task, 'payload' => $payload]);
    $ctx    = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\napikey: $sb_key\r\nAuthorization: Bearer $sb_key\r\nPrefer: return=minimal\r\n",
        'content'       => $body,
        'ignore_errors' => true,
    ]]);
    @file_get_contents($sb_url, false, $ctx);
}
