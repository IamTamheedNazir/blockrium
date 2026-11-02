# Verified Member Backend Deployment

This package adds the backend foundation for KYC review, the paid verified-member badge, BRC usernames, and verified-user-only internal BRC transfers.

## Upload

Extract the ZIP into `/home/vxyxdrvh/public_html` and allow cPanel to merge the `app`, `config`, `database`, `resources`, and `routes` folders. Do not replace `.env`.

## Configure Didit

Create a published KYC workflow in Didit and configure its public webhook destination as:

```text
https://YOUR_PUBLIC_API_HOST/api/webhooks/didit
```

Subscribe the destination to `status.updated` and choose webhook version `v3`. Add the Didit application API key and the destination signing secret to the server `.env`. The API key is read only from `DIDIT_API_KEY`; never put it in the mobile app or source code.

```env
DIDIT_API_KEY=your_didit_application_key
DIDIT_WEBHOOK_SECRET=your_destination_secret_shared_key
DIDIT_WORKFLOW_ID=your_published_workflow_uuid
DIDIT_BASE_URL=https://verification.didit.me/v3
DIDIT_CALLBACK_URL=https://YOUR_APP_OR_DEEP_LINK/verification-complete
DIDIT_ENVIRONMENT=live
DIDIT_MONTHLY_LIMIT=500
DIDIT_COUNTRY_LIMIT=100
```

`DIDIT_CALLBACK_URL` is the user-facing redirect after the hosted flow. The webhook destination is configured separately at `/api/webhooks/didit`. The backend verifies Didit HMAC signatures, rejects stale webhooks older than five minutes, deduplicates event IDs, and reads the document issuing country from the verified Didit result. It does not store document images or raw decision payloads.

## Configure payment link

In cPanel Terminal, edit the existing `.env` and add a Dodo checkout URL for the $5 verification product:

```bash
cd /home/vxyxdrvh/public_html
printf '\nDODO_VERIFICATION_PAYMENT_URL=https://your-dodo-checkout-url\nDODO_VERIFICATION_AMOUNT=5\nDODO_VERIFICATION_CURRENCY=USD\n' >> .env
```

Use the real Dodo checkout URL only. Do not put the Dodo API key in this package or in the mobile app.

## Run only this migration

The old project has a users-table migration that is already reflected in the live database, so do not run the full migration list on this server. Run the new migration by path:

```bash
cd /home/vxyxdrvh/public_html
php artisan migrate --path=database/migrations/2026_10_10_000001_create_verified_member_tables.php --force
php artisan migrate --path=database/migrations/2026_10_10_000002_create_integration_settings_table.php --force
php artisan migrate --path=database/migrations/2026_10_10_000003_create_kyc_waitlist_tables.php --force
php artisan optimize:clear
```

After deployment, open `/admin/verified-members/settings` in the admin panel. Enter the Didit and Dodo values there and save them. Secret values are encrypted in the `integration_settings` table and are never returned to the app or displayed in full.

The member page at `/admin/verified-members` shows separate KYC, payment, and badge states. A user with approved KYC but no confirmed payment is shown as `Payment pending`; the verified badge and verified-only features remain inactive until payment is confirmed.

## KYC waiting list

Open `/admin/verified-members/waitlist` to review country demand and invite users in controlled batches. Configure the waiting-list message and the verified-user mining boost percentage in `/admin/verified-members/settings`.

The waiting list API is available under `/api/auth`:

- `GET /verification/waitlist`
- `POST /verification/waitlist` with `country_code` (three-letter ISO code) and `country_name`
- `DELETE /verification/waitlist`

Joining the list does not consume a Didit session. Only users invited by an admin can start Didit. The KYC mining boost activates only after both KYC approval and successful payment.

## Admin review

Open `/admin/verified-members` after logging into the admin panel. The flow is:

1. User joins the KYC waiting list and selects a country.
2. Admin reviews country demand and invites a controlled batch.
3. Invited user starts and submits KYC from the app.
4. Admin approves or rejects the KYC case.
5. Only after KYC approval does the app receive the payment URL.
6. Confirm the successful Dodo payment from the admin page until the provider webhook is connected.
7. The API then returns `badge.visible: true`, activates the configured mining boost, and allows one BRC username.

## App API endpoints

All endpoints use the existing JWT user token under `/api/auth`:

- `GET /api/auth/verification/status`
- `POST /api/auth/verification/start`
- `POST /api/auth/verification/submit` to refresh the current status after the hosted flow
- `POST /api/auth/verification/payment/start`
- `POST /api/auth/verification/username/availability` with `username`
- `POST /api/auth/verification/username` with `username`
- `POST /api/auth/brc/transfers` with `recipient_username`, `amount`, `idempotency_key`, and optional `note`
- `GET /api/auth/brc/transfers`

The existing `POST /api/auth/transfer-brc-to-another-user` path is also redirected to the verified transfer implementation for frontend compatibility.

## Important payment note

The current package intentionally does not trust a browser return URL as payment proof. Until the Dodo webhook payload and signing secret are configured, an admin must confirm the payment using the provider reference. This prevents a user from activating their own verification by changing a frontend response.
