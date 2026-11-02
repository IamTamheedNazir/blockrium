# Dodo Mining Boost Deployment

This update connects the existing `boost_plans` and `user_boosts` workflow to the three Dodo subscription products. A boost is active only after a verified Dodo webhook.

## Product mapping

| Plan | Boost | Dodo Product ID |
| --- | ---: | --- |
| Blockrium Basic Mining Boost | 10% | `pdt_0NpjcrZyiWgvx1oUlI5dA` |
| Blockrium Pro Mining Boost | 25% | `pdt_0NpjdNVSSu10tZgrHtLRB` |
| Blockrium Elite Mining Boost | 50% | `pdt_0Npjds36e2gZHc0wiW5j0` |

## cPanel deployment

Run from the Laravel project directory after extracting this ZIP:

```bash
cd /home/vxyxdrvh/public_html

php artisan migrate \
  --path=database/migrations/2026_10_14_000002_add_dodo_mining_boost_support.php \
  --force

php artisan optimize:clear
php artisan config:clear
```

If the project uses a custom PHP binary, use that binary instead of `php`.

## Dodo webhook endpoint

Create a separate Dodo webhook endpoint:

```text
https://maticlegend.com/api/webhooks/dodo/boost
```

Use the same Dodo webhook secret saved in the admin integration settings. Subscribe to payment and subscription lifecycle events, including:

- `payment.succeeded`
- `payment.processing`
- `payment.failed`
- `subscription.created`
- `subscription.active`
- `subscription.renewed`
- `subscription.updated`
- `subscription.cancelled` or `subscription.canceled`
- `subscription.expired`
- refund or dispute events

Keep the existing KYC payment webhook endpoint separate. Do not replace the existing `/api/webhooks/dodo` endpoint used by verification payments.

## Admin configuration

Open `/admin/verified-members/settings` and confirm the Dodo API key and webhook secret are configured. Then open `/admin/manage/boost-plans` and verify that the three plans show `Dodo` and the Product IDs above.

## App flow

1. Call `GET /api/auth/boost-plans`.
2. Call `POST /api/auth/boost-purchase` with `{ "plan_id": <id>, "platform": "android" }`.
3. Open the returned `checkout_url`.
4. After checkout, poll `GET /api/auth/boost-purchase/<purchase_id>`.
5. Treat the boost as active only when `payment_status` is `paid` and `status` is `active`.

The existing `GET /api/auth/my-boost` and mining-rate responses will include the active paid boost automatically.

## Rollback

Before deployment, copy the changed application files. If rollback is needed, restore the application files but do not drop the migration tables while Dodo events may still be retrying. Disable the three Dodo boost plans in the admin panel first.
