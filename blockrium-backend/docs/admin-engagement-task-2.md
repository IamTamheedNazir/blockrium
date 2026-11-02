# Blockrium Engagement Admin - Task 2

## What this release adds

The existing authenticated admin panel gains an **Engagement** menu with:

1. Dashboard
2. XP Events
3. Badges
4. User Badges
5. Seasons
6. Referral Milestones
7. Referral Claims
8. Daily Missions Monitor
9. Production Health

The reporting pages are read-only. Badge, season, and referral milestone configuration changes are validated, transaction-safe, and recorded in `engagement_admin_audits`. There is no manual XP or user badge award action.

## Production deployment

Upload and extract the deployment ZIP into the Laravel application root (`~/public_html`). Do not replace `.env` and do not upload a `vendor` directory from another machine.

Run these commands one at a time:

```bash
cd ~/public_html
php artisan migrate --force --path=./database/migrations/2026_07_05_000001_add_engagement_admin_reporting_fields.php
php artisan optimize:clear
php artisan route:list | grep admin/engagement
php artisan migrate:status | grep 2026_07_05
```

Expected migration result:

```text
Migrated: 2026_07_05_000001_add_engagement_admin_reporting_fields
```

If it says `Nothing to migrate`, confirm the migration is listed as `Yes` by the final command. Do not run the full migration set on this legacy installation unless its old migration history has first been reconciled.

## Production checks

1. Sign in as an existing admin and open `/admin/engagement`.
2. Confirm all nine Engagement menu pages open.
3. Create, edit, and toggle a temporary badge, then remove or deactivate it.
4. Open the Genesis Season and confirm its dates/data remain unchanged unless intentionally edited.
5. Create, edit, and toggle a test referral milestone with a zero BRC reward.
6. Confirm XP Events, User Badges, and Referral Claims paginate without exposing request payloads or credentials.
7. Confirm Production Health reports all four mobile API routes and the engagement tables.
8. Test the released Play app: mining participation, streak, task, Starter Quest, referral journey, and wallet balance.
9. Check `storage/logs/laravel.log` for new errors after those tests.

## Security and compatibility

- Existing `checklogin` admin middleware protects every Engagement route.
- No wallet secret, private key, recovery phrase, passcode, webhook secret, or production credential is displayed.
- Log output is bounded to a small tail sample, strips structured request context, and redacts sensitive values.
- XP remains separate from BRC and has no withdrawal or cash-value path.
- Existing mobile endpoint paths and response contracts are unchanged.
- Referral claim `reward_label` defaults to `BRC`; the mobile service has a fallback during deployment so old and new schemas remain compatible.
- Existing historical referral claims are never rewritten or duplicated.

## Rollback

Restore the replaced application files from the server backup. The new audit table and `reward_label` columns are safe to retain because they preserve operational history. Avoid rolling the migration down on a live system unless the application code has already been restored.

## Known production risk

The legacy admin authentication grants the same access to every signed-in admin and has no fine-grained role/permission layer. Task 2 preserves that existing behavior. A later hardening task should add admin roles, permission checks, stronger session controls, and a visible audit-history page.
