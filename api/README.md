# Card Blueprints API

Files under `/api` power Stripe checkout + webhooks when deployed to SiteGround.

## Setup
1. Copy `config.example.php` → `config.php` on the server.
2. Fill in:
   - `stripe_secret_key`
   - `stripe_webhook_secret`
   - Success/cancel URLs (use the live domain)
   - `pack_dir` pointing to the secure storage outside `public_html`
3. Deploy `checkout.php`, `webhook.php`, `download.php`, and `helpers.php`.
4. Create a Stripe webhook endpoint pointing to `/api/webhook.php` (events: `checkout.session.completed`).

## Flow
- `checkout.php` creates a Checkout Session (POST JSON `{}` → returns `{id}` for Stripe.js).
- Stripe hosts the payment page.
- `webhook.php` stores a download token once the session completes.
- `download.php?token=xyz` streams the latest PDF from the secure storage.

TODO: add transactional email to send the download link automatically (can reuse Buttondown API or Stripe email receipts).
