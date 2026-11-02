# Partner Promo Codes Manual Deployment

This package adds secure, single-use BRC promo codes for partner campaigns. It extends the existing partner campaign tables and uses the existing wallet reward service.

## Upload and extract

Upload the ZIP to the cPanel home directory, then run:

```bash
cd /home/vxyxdrvh/public_html

cp app/Services/PartnerPromoCodeService.php app/Services/PartnerPromoCodeService.php.before-promo-codes 2>/dev/null || true
cp routes/api.php routes/api.php.before-promo-codes 2>/dev/null || true
cp routes/web.php routes/web.php.before-promo-codes 2>/dev/null || true

unzip -o /home/vxyxdrvh/partner-promo-codes-backend-2026-10-14.zip -d /home/vxyxdrvh/public_html
```

## Apply only the new migration

Do not run the full migration command because this legacy application may try to recreate existing tables such as `users`.

```bash
cd /home/vxyxdrvh/public_html

php artisan migrate \
  --path=database/migrations/2026_10_14_000001_create_partner_promo_code_tables.php \
  --force

php artisan optimize:clear
```

## Verify routes

```bash
php artisan route:list | grep -E 'partner-promo|promo-codes'
```

Expected app routes:

- `POST api/auth/partner-promo-codes/redeem`
- `GET api/auth/partner-promo-codes/redemptions`

Expected admin routes include campaign promo-code management, CSV export, redemption history, and revoke-unused actions.

## Use the admin panel

1. Open Partner Campaigns.
2. Open Edit on the selected campaign.
3. Select Manage promo codes.
4. Generate a small test batch first, such as 10 codes.
5. Download the CSV and test one code with a test Blockrium account.
6. Confirm the wallet credit, notification, campaign claim, and redemption record.
7. Submit the same code again and confirm no second credit is created.

For a large partner distribution, use multiple batches such as 10,000 or 25,000 codes. The maximum is 100,000 codes per batch, but smaller batches reduce request time and make partner delivery easier.

## Storage and safety

- Full promo codes are encrypted in `storage/app/private/partner-promo` and are not stored in the database.
- The CSV export is temporary and is deleted after download.
- Send the CSV and its ZIP password through separate channels.
- Never put a generated CSV in `public_html`.
- Ensure the application can write to `storage/app/private`.
- Do not edit reward amounts inside the CSV after generation.

## Backend API request

```json
{
  "code": "BRC-7K4M-92PX-AL8Q",
  "request_key": "unique-client-redemption-id"
}
```

The app must send the user's existing API authentication token. The reward amount is never accepted from the client.
