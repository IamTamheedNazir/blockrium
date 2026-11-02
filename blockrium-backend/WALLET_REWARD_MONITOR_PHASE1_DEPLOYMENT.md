# Wallet and Reward Monitor - Phase 1

This package adds a read-only admin monitor at `/admin/wallet-monitor`.

It reports:

- Recorded aggregate wallet balances.
- Mining, daily reward, task, activity, and referral source totals when the legacy tables are available.
- Referral code and referrer identifiers.
- KYC and payment status.
- High-balance and activity review signals.
- Whether the canonical BRC ledger is available.

This phase does **not** change balances, burn tokens, place holds, or run a migration. Legacy source totals can overlap, so the reconciliation delta is an investigation indicator only and must not be used for automatic enforcement.

## Upload and deploy

Upload `wallet-reward-monitor-phase1-2026-10-14-r2.zip` to `/home/vxyxdrvh/` in cPanel Terminal, then run:

```bash
cd /home/vxyxdrvh/public_html

cp app/Services/WalletMonitorService.php app/Services/WalletMonitorService.php.before-wallet-monitor
cp app/Http/Controllers/WalletMonitorAdminController.php app/Http/Controllers/WalletMonitorAdminController.php.before-wallet-monitor 2>/dev/null || true
cp resources/views/wallet-monitor/index.blade.php resources/views/wallet-monitor/index.blade.php.before-wallet-monitor 2>/dev/null || true
cp resources/views/admin/includes/sidebar.blade.php resources/views/admin/includes/sidebar.blade.php.before-wallet-monitor
cp routes/web.php routes/web.php.before-wallet-monitor

unzip -o /home/vxyxdrvh/wallet-reward-monitor-phase1-2026-10-14-r2.zip -d /home/vxyxdrvh/public_html

php artisan optimize:clear
php artisan route:list | grep -E 'admin/wallet-monitor'
```

Expected route:

```text
GET|HEAD  admin/wallet-monitor  admin.wallet-monitor.index  App\\Http\\Controllers\\WalletMonitorAdminController@index
```

Open `https://maticlegend.com/admin/wallet-monitor` while signed in as an admin.

## Important safety note

Do not use the displayed reconciliation delta to burn or release tokens. The next phase should add an immutable BRC ledger and per-user evidence/cases before any balance adjustment or restriction workflow is enabled.
