<?php
return [
    'stripe_secret_key' => 'sk_live_xxx',
    'stripe_webhook_secret' => 'whsec_xxx',
    'success_url' => 'https://cardblueprints.com/signal-sprints/thank-you.html?token={token}',
    'cancel_url' => 'https://cardblueprints.com/signal-sprints/',
    'price_cents' => 900,
    'currency' => 'usd',
    'product_name' => 'Card Blueprints Pack',
    'storage_dir' => __DIR__ . '/../../storage',
    'pack_dir' => '/home/customer/www/cardblueprints.com/signal-packs',
];
