<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$email  = trim($input['email'] ?? '');
$source = trim($input['source'] ?? 'website');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email']);
    exit;
}

$bd_key = '4e83b7a8-9aec-442d-8b5e-ded5ad22ff96';

// 1. Add to Buttondown
$bd_body = json_encode(['email' => $email, 'tags' => [$source]]);
$bd_ctx  = stream_context_create(['http' => [
    'method'        => 'POST',
    'header'        => "Authorization: Token {$bd_key}\r\nContent-Type: application/json\r\n",
    'content'       => $bd_body,
    'ignore_errors' => true,
]]);
$bd_resp = @file_get_contents('https://api.buttondown.email/v1/subscribers', false, $bd_ctx);
$bd_data = json_decode($bd_resp, true);
$already_exists = isset($bd_data['code']) && $bd_data['code'] === 'email_already_exists';

// 2. Notify Supabase — add subscriber + create funnel task
$sb_url = 'https://djopinoumymftemtifrn.supabase.co/rest/v1';
$sb_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImRqb3Bpbm91bXltZnRlbXRpZnJuIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3MzgyNjMxMiwiZXhwIjoyMDg5NDAyMzEyfQ.z_SxCQUdWSwKOkNH4Kht-oYmhqqB9fm5SGdDbvpoPJE';
$sb_headers = "apikey: {$sb_key}\r\nAuthorization: Bearer {$sb_key}\r\nContent-Type: application/json\r\nPrefer: return=minimal\r\n";

// Insert subscriber (ignore duplicate)
$sub_body = json_encode(['email' => $email, 'source' => $source, 'tags' => [$source]]);
$sub_ctx  = stream_context_create(['http' => [
    'method'        => 'POST',
    'header'        => $sb_headers . "Prefer: resolution=ignore-duplicates\r\n",
    'content'       => $sub_body,
    'ignore_errors' => true,
]]);
@file_get_contents("$sb_url/subscribers", false, $sub_ctx);

// Create welcome task for funnel agent (only for new subscribers)
if (!$already_exists) {
    $task_body = json_encode([
        'assigned_to' => 'funnel',
        'task'        => 'Send welcome email to new subscriber',
        'payload'     => ['email' => $email, 'source' => $source],
    ]);
    $task_ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => $sb_headers,
        'content'       => $task_body,
        'ignore_errors' => true,
    ]]);
    @file_get_contents("$sb_url/tasks", false, $task_ctx);
}

echo json_encode(['success' => true, 'new' => !$already_exists]);
