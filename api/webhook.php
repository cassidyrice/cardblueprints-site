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
    $meta    = $session['metadata'] ?? [];
    $type    = $meta['type'] ?? 'unknown';

    if ($type === 'letter') {
        handle_letter_subscription($config, $session, $meta);
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

function handle_letter_subscription(array $config, array $session, array $meta): void {
    $email    = $session['customer_details']['email'] ?? ($meta['email'] ?? '');
    $name     = $meta['name']     ?? '';
    $birthday = $meta['birthday'] ?? '';
    $address  = $meta['address']  ?? '';
    $plan     = $meta['plan']     ?? 'monthly';
    $date     = date('Y-m-d H:i:s');

    // 1. Append to CSV
    $csv_dir = __DIR__ . '/../storage';
    if (!is_dir($csv_dir)) mkdir($csv_dir, 0700, true);
    $csv_path = $csv_dir . '/subscribers.csv';
    $is_new   = !file_exists($csv_path);
    $fp = fopen($csv_path, 'a');
    if ($fp) {
        if ($is_new) fputcsv($fp, ['date', 'name', 'email', 'birthday', 'address', 'plan']);
        fputcsv($fp, [$date, $name, $email, $birthday, $address, $plan]);
        fclose($fp);
    }

    // 2. Email notification via SMTP
    $subject = "New subscriber: $name ($plan)";
    $body = "New Card Blueprints subscriber!\n\n"
          . "Name:     $name\n"
          . "Email:    $email\n"
          . "Birthday: $birthday\n"
          . "Address:  $address\n"
          . "Plan:     $plan\n"
          . "Date:     $date\n";

    send_smtp(
        $config['smtp_host'] ?? 'mail.cardblueprints.com',
        $config['smtp_port'] ?? 465,
        $config['smtp_user'] ?? 'contact@cardblueprints.com',
        $config['smtp_pass'] ?? '',
        'contact@cardblueprints.com',
        'cassricemail@gmail.com',
        $subject,
        $body
    );
}

function send_smtp(string $host, int $port, string $user, string $pass, string $from, string $to, string $subject, string $body): bool {
    $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $sock = stream_socket_client("ssl://$host:$port", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
    if (!$sock) return false;

    // Read full multi-line SMTP response, return last line
    $read = function() use ($sock) {
        $last = '';
        do {
            $line = fgets($sock, 512);
            if ($line === false) break;
            $last = $line;
        } while (isset($line[3]) && $line[3] === '-');
        return $last;
    };
    $write = function(string $cmd) use ($sock) { fwrite($sock, "$cmd\r\n"); };

    $read();                                    // 220 greeting
    $write("EHLO cardblueprints.com"); $read(); // 250 capabilities

    $write("AUTH LOGIN");         $read();      // 334
    $write(base64_encode($user)); $read();      // 334
    $write(base64_encode($pass)); $resp = $read(); // 235 or 535
    if (!str_starts_with(trim($resp), '235')) { fclose($sock); return false; }

    $write("MAIL FROM:<$from>");  $read();      // 250
    $write("RCPT TO:<$to>");      $read();      // 250
    $write("DATA");               $read();      // 354

    $msg = "From: Card Blueprints <$from>\r\n"
         . "To: $to\r\n"
         . "Subject: $subject\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n"
         . "\r\n"
         . $body;
    $write($msg);
    $write(".");                  $resp = $read(); // 250
    $write("QUIT");

    fclose($sock);
    return str_starts_with(trim($resp), '250');
}
