<?php
return [
    // Stripe keys
    'stripe_secret_key'     => 'sk_live_xxx',
    'stripe_webhook_secret' => 'whsec_xxx',

    // Letter subscription — create these Price IDs in Stripe Dashboard
    // Products > Add Product > "The Analog Algorithm — Monthly Letter"
    // Add two prices: $25/month (recurring) and $250/year (recurring)
    'letter_monthly_price_id' => 'price_1TCsLLDgoKThmC0IiFoOkAF8',   // $25/month
    'letter_yearly_price_id'  => 'price_1TCsLLDgoKThmC0ILe04dHLC',    // $250/year

    // Letter checkout URLs
    'letter_success_url' => 'https://cardblueprints.com/thank-you.html',
    'letter_cancel_url'  => 'https://cardblueprints.com/',

    // Legacy one-time pack settings (can be removed if not needed)
    'success_url'    => 'https://cardblueprints.com/signal-sprints/thank-you.html?token={token}',
    'cancel_url'     => 'https://cardblueprints.com/signal-sprints/',
    'price_cents'    => 900,
    'currency'       => 'usd',
    'product_name'   => 'Card Blueprints Pack',
    'storage_dir'    => __DIR__ . '/../../storage',
    'pack_dir'       => '/home/customer/www/cardblueprints.com/signal-packs',
];
