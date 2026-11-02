# Blockrium Squad Sharing Task 5

## What this adds

- Public squad invite URLs: `/squad/{invite_code}`
- Public invite resolver API: `GET /api/squads/invite/{invite_code}`
- Authenticated share-card API: `GET /api/auth/squads/share-card`
- Authenticated share tracking API: `POST /api/auth/squads/share`
- Owner/co-leader invite refresh: `POST /api/auth/squads/invite/regenerate`
- Country and language fields on squads.
- Unlimited squad capacity by default. Optional numeric limits are still supported if you later want capped squads.
- Share analytics table: `squad_share_events`.
- Admin squad list/detail visibility for share URL, country, language, share count, and share channels.
- White share-poster metadata and public SVG preview URL: `/squad/{invite_code}/poster.svg`.

## App integration notes

The app should render a native share sheet from `GET /api/auth/squads/share-card`.

Important fields:

- `data.squad.invite_code`
- `data.squad.share_url`
- `data.squad.share_text`
- `data.squad.country_code`
- `data.squad.language_code`
- `data.squad.member_count`
- `data.squad.max_members`
- `data.squad.is_unlimited`
- `data.squad.capacity_label`
- `data.poster.qr_text`
- `data.poster.poster_svg_url`
- `data.poster.safe_note`
- `data.share_options.channels`

After a user shares, call:

```http
POST /api/auth/squads/share
{
  "channel": "whatsapp"
}
```

Supported channels:

- `copy_link`
- `whatsapp`
- `telegram`
- `instagram`
- `facebook`
- `x`
- `sms`
- `email`
- `other`

## Safe wording

Use language like:

> Join my Blockrium squad and help unlock weekly activity chests.

Avoid wording that implies investment returns, passive income, or on-device crypto mining.

## Deployment

Run the Task 4 migration first if it has not been run:

```bash
php artisan migrate --force --path=database/migrations/2026_07_12_000001_create_squad_mining_tables.php
```

Then run Task 5:

```bash
php artisan migrate --force --path=database/migrations/2026_07_14_000001_enhance_squad_sharing_country.php
php artisan migrate --force --path=database/migrations/2026_07_15_000001_make_squad_capacity_unlimited_and_posters.php
php artisan optimize:clear
```
