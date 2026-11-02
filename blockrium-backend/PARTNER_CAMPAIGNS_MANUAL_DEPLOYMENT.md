# Partner Campaigns Manual Deployment

This package contains only the backend files for Partner Campaigns. Upload each file to the matching path under the Laravel project root. Do not upload `vendor/` from this package.

## Before Uploading

Back up the existing copies of:

- `routes/api.php`
- `routes/web.php`
- `resources/views/admin/includes/sidebar.blade.php`
- `database/migrations/2026_10_01_000001_create_partner_campaign_task_tables.php`

The other files in this package are new files.

## Upload

Preserve the directory structure in the ZIP. The project root is the directory containing `artisan`.

## After Uploading

From the Laravel project root in cPanel Terminal, run:

```bash
php artisan migrate --force
php artisan optimize:clear
```

If `php artisan` reports that `vendor/autoload.php` is missing, do not delete the existing `vendor` directory. Ask hosting support to restore/install the Composer dependencies first.

## Admin Usage

Open:

```text
/admin/partner-campaigns
```

Create a partner first, then create a campaign. The campaign builder accepts a banner upload or HTTPS image URL, a total BRC allocation, and multiple tasks. Website tasks are click-and-claim; social and custom tasks appear in the review queue before they can be claimed.

The partner dashboard is a private, read-only link. Use **New Link** to issue it and rotate it if the existing link should no longer work.

## API Endpoints

- `GET /api/auth/partner-campaigns`
- `POST /api/auth/partner-campaigns/{id}/click`
- `POST /api/auth/partner-campaigns/{id}/claim`
- `POST /api/auth/partner-campaigns/{id}/tasks/{taskId}/click`
- `POST /api/auth/partner-campaigns/{id}/tasks/{taskId}/submit`
- `POST /api/auth/partner-campaigns/{id}/tasks/{taskId}/claim`
- `GET /partner/campaign/{slug}`
- `GET /partner/dashboard/{token}`

## First Deployment Checklist

1. Upload and extract this package into `/home/vxyxdrvh/public_html`.
2. From the directory containing `artisan`, run `php artisan migrate --force`.
3. Run `php artisan optimize:clear`.
4. Open `/admin/partner-campaigns`, create a partner, then create a draft campaign with at least one task.
5. Create the campaign link, test one website task, and submit one social task for review before sharing the dashboard link.
