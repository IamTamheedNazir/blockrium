# Blockrium source monorepo

This private repository contains the Blockrium client and backend source:

- **PWA**: this repository root (`src/`, `public/`). Configure `VITE_API_PREFIX` at build time; it defaults to `https://maticlegend.com/api/`.
- **Android React Native app**: `android-react-native/`.
- **Laravel API and admin backend**: `blockrium-backend/`.

## iOS web app scope

The PWA is intended for onboarding iOS users and using features supported by the existing Blockrium API: account registration/sign-in/password recovery, mining sessions, server-backed rewards and tasks, campaigns, promo codes, referrals, squads, learning, profile, notifications, and support issue reports.

Google Play Billing, native Appodeal ads, on-chain Web3 wallet features, KYC/AML initiation, and Verified Member payments are intentionally unavailable in this PWA. The in-app Blockrium balance is read-only here; BRC transfers remain gated to the Android verified-member flow.

## Build the PWA

```sh
npm ci
npm run build
```

Deploy the generated `dist/` files to the PWA host after configuring the Laravel API's CORS allowlist to include `https://mine.blockrium.com`. The existing API remains on `https://maticlegend.com/api/` until a different API host is configured.

## Backend source

`blockrium-backend/` is the Blockrium Laravel source, without `vendor/`, deployment ZIPs, old staging snapshots, runtime logs, or credentials. Install Composer dependencies on the target environment and configure its private `.env` and service credentials there. Do not overwrite production `.env`, user uploads, database, or server-only credentials when deploying.

## Android source

`android-react-native/` contains the React Native app source and native project files. Release keystores, Firebase service-account files, generated bundles, local Gradle/build output, APK/AAB files, and release logs are intentionally excluded. Provide authorized local signing/Firebase configuration before making a signed Android release.

## Repository hygiene

This repository is private. Keep production credentials and user data out of commits. Use the example environment files and maintain secrets in the hosting/provider secret store.
