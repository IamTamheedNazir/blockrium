# Blockrium PWA deployment

This standalone frontend is intended for `mine.blockrium.com`. It does not replace or modify the React Native app or the existing `blockrium.com`, `wallet.blockrium.com`, or `shield.blockrium.com` sites.

## Local development

```powershell
cd web-pwa
npm install
npm run dev
```

The Vite development server proxies `/api/*` to `https://maticlegend.com/api/*`.

## Production routing

Build with `npm run build` and publish the contents of `web-pwa/dist` as the static document root for `mine.blockrium.com`. Configure HTTPS and use `nginx.mine.blockrium.com.conf.example` as the starting point for the isolated virtual host. Its `/api/` route proxies to the existing cPanel API so browser calls are same-origin and do not require broad cross-origin access. The proxy must pass the Authorization header and multipart request bodies.

Do not proxy admin paths or expose server credentials in the frontend. Do not cache API responses in the service worker: authenticated balances, KYC, support, and wallet data must remain live and private.

## Feature coverage and platform adapters

- Shared backend: login, registration, profile, wallet balance, mining session, tasks, community data, KYC status/start, payment support submission, and promo redemption.
- Web-only install: web manifest, icons, offline shell, iOS Add to Home Screen guidance, mobile tab navigation.
- Native Appodeal mediation and Google Play Billing are not browser APIs. They require separately configured web ad inventory and a web-compatible payment flow; the PWA must not claim that a native ad was served or a purchase was confirmed when it was not.
- Non-custodial Web3 wallet secrets must not be copied into web storage. Use a browser wallet connector or omit key signing until explicitly integrated.
