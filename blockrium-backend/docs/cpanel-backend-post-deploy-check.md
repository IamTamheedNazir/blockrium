# cPanel Backend Post-Deploy Check

Run this from Terminal after extracting one complete backend release into `public_html`:

```sh
cd /home/vxyxdrvh/public_html
php artisan optimize:clear
php artisan blockrium:preflight
```

The preflight is read-only. It verifies the controller targets for the critical KYC, support, app-health, email-campaign, Google Play, and BRC-transfer routes; scans active Blade views for missing literal named routes; checks that 11 feature tables and their required columns exist; and confirms that the branded email logo file is included in the web root. Five known legacy views from retired campaign screens are excluded because they are not referenced by current routes or controllers and may remain on servers after overlay deployments.

- If it reports a missing controller action or route, stop before using the admin panel. The extracted files are incomplete or from incompatible revisions; upload one complete backend package rather than adding individual route snippets.
- If it reports a named route referenced by a Blade view, do not dismiss the warning by skipping the view check; that screen will fail at render time. Upload one matching backend revision with both the route and view files.
- If it reports a missing table, back up the database and run the migration command specified for that release. Do not delete or recreate existing tables.
- If the schema check reports a database error, confirm the cPanel `.env` database settings and the database user's table permissions.
- A passing check proves the installed route/controller and table structure only. It does not log in as an admin or verify live Didit, Google Play, SMTP, Firebase, or mobile-device behavior.

Expected success output:

```text
19 critical route/controller actions, 11 feature tables, and 48 required columns verified.
Active Blade views checked for missing named routes; known retired views excluded.
Branded email logo asset is present.
Blockrium deployment preflight passed.
```
