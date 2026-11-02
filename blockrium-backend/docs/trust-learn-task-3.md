# Blockrium Task 3 - Trust Center and Learn & Earn

## Backend scope

This release adds backend and admin support for Trust Center and Learn & Earn without changing the React Native app.

## New public API

- `GET /api/trust-center`

Returns active Trust Center sections, safe links, and `updated_at`.

## New authenticated APIs

- `GET /api/auth/learn/modules`
- `GET /api/auth/learn/modules/{id}`
- `POST /api/auth/learn/modules/{id}/complete`

The module detail API does not expose `correct_option`. Quiz completion validates answers on the backend, stores user progress, prevents duplicate lesson rewards, and awards XP through `GamificationService` with:

- `event_key`: `lesson_completed`
- `source_type`: `learn_module`
- `source_id`: module ID

Optional BRC rewards are disabled by default because seeded modules use `reward_brc = null`. If an admin later configures a positive BRC amount, rewards use the existing `WalletRewardService`.

## New admin pages

- `GET /admin/trust-center`
- `GET /admin/trust-center/sections/create`
- `GET /admin/trust-center/sections/{id}/edit`
- `GET /admin/engagement/learn`
- `GET /admin/engagement/learn/create`
- `GET /admin/engagement/learn/{id}/edit`
- `GET /admin/engagement/learn/{id}/questions`
- `GET /admin/engagement/learn/{id}/questions/{questionId}/edit`
- `GET /admin/engagement/learn-progress`

## Tables

- `trust_center_sections`
- `learn_modules`
- `learn_questions`
- `user_learn_progress`

The migration seeds required Trust Center sections and six starter Learn modules with three questions each.

## App integration notes

The app can add screens later using:

- Trust Center screen: `GET /api/trust-center`
- Learn list screen: `GET /api/auth/learn/modules`
- Learn detail/quiz screen: `GET /api/auth/learn/modules/{id}`
- Quiz submit: `POST /api/auth/learn/modules/{id}/complete`

Treat XP as status/progress only. Do not describe XP or BRC as income, profit, passive return, or guaranteed reward. Blockrium mining should be described as a backend-controlled daily participation session, not phone hardware mining.

## Deployment

Run only the targeted migration:

```bash
cd ~/public_html
php artisan migrate --force --path=./database/migrations/2026_07_08_000001_create_trust_center_and_learn_earn_tables.php
php artisan optimize:clear
```

Then check:

```bash
php artisan route:list | grep trust-center
php artisan route:list | grep learn
```
