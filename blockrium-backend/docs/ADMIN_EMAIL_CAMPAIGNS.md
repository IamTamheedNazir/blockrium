# Blockrium admin account-update email campaigns

## What the feature does

- `/admin/email-campaigns` is available only inside the existing `checklogin` admin session.
- Audiences are active accounts or accounts with no KYC membership / `not_started` / `started` KYC. Invalid email addresses and accounts opted out of account-update email are excluded. Accounts with no telemetry are not assumed inactive.
- Admins save a draft, review the current audience count, then explicitly queue it. Saving a draft never sends email.
- The admin chooses 100–1000 recipients per batch, a 100–1000 daily cap, and a 12- or 24-hour repeat interval. Recipient preparation and every send run in the `email-campaigns` queue, not in a web request.
- A unique `(campaign_id, user_id)` database key prevents a recipient from being added twice to one campaign. Each batch atomically reserves recipients and counts reservations against that campaign's daily cap before dispatching one delayed job per recipient.
- Recipients are rechecked for account activity, audience eligibility, email changes, and opt-out immediately before delivery. Delivery status means the configured transport accepted/rejected the send; it does not prove inbox placement.
- The shared email layout includes the Blockrium mark, website, app, saved Telegram/social links, optional support email, and unsubscribe link. Footer links are saved once under the email campaign admin page. Tests can be sent to an admin-provided address. CTA clicks and approximate image opens are tracked with random per-recipient tokens; open counts can be incomplete or inflated by privacy proxies.
- The signed email-preferences link requires a confirmation POST. Opening a link alone does not change preferences. KYC documents must be submitted through the app, never by replying to email.
- The former unsegmented `/admin/add/notification` all-user email form now redirects to the campaign center; its broadcast POST route is removed.

## Deployment checklist

1. Back up the database and apply migrations with the normal release process: `php artisan migrate --force`.
2. Configure an asynchronous Laravel queue connection. `sync` and `null` queues are deliberately blocked. The app already has the standard `jobs` and `failed_jobs` migrations.
3. Add the cPanel cron entry `* * * * * cd /home/ACCOUNT/public_html && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1` so the due-batch dispatcher runs each minute. Use the actual cPanel account path.
4. Run a persistent worker for the named queue, for example `php artisan queue:work database --queue=email-campaigns --stop-when-empty --max-time=50 --max-jobs=30 --timeout=60 --tries=3`. Confirm the hosting cron or process manager starts it frequently enough. Do not run overlapping workers on a shared host.
5. Configure the Laravel mailer, sender, SPF, DKIM and DMARC in the deployment environment. Send an internal test first and verify headers, logo, unsubscribe and links. Inbox placement cannot be guaranteed by this application.
6. Create a factual account-update draft, choose the audience and caps, send a test, then explicitly queue it with the service-message confirmation. Monitor the campaign detail page and mail/queue logs.

No campaign has been created or sent as part of this code change. Apply the new additive migration before enabling the updated admin screens, then verify cron, worker, HTTPS `APP_URL`, and mail-provider configuration in the deployment environment.
