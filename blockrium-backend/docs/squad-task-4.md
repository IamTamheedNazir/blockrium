# Blockrium Task 4 - Squad / Team Mining Backend

## Product language

Use "Squad Activity", "Community Boost", and "daily participation". Do not call this profit sharing, passive income, guaranteed rewards, or phone crypto mining.

## New authenticated APIs

- `GET /api/auth/squads/me`
- `GET /api/auth/squads/discover`
- `GET /api/auth/squads/leaderboard`
- `POST /api/auth/squads/create`
- `POST /api/auth/squads/join`
- `POST /api/auth/squads/leave`
- `POST /api/auth/squads/chest/claim`

Create request:

```json
{
  "name": "Blockrium Builders",
  "description": "Daily participation squad",
  "max_members": 20,
  "is_public": true
}
```

Join request:

```json
{
  "invite_code": "SQABCDEFGH"
}
```

or:

```json
{
  "squad_id": 1
}
```

## Backend rules

- One active squad membership per user.
- Squad size is 5 to 20 members.
- Weekly points are calculated from existing backend-validated activity:
  - participation sessions
  - daily streak claims
  - task claims
  - Learn & Earn completions
  - referral activity when timestamp data exists
- Weekly squad chest unlocks when the squad reaches `weekly_target_points`.
- Each active member can claim the weekly chest once.
- Chest rewards are XP by default.
- Optional BRC support exists but is null by default and uses `WalletRewardService` if configured.
- Community boost percent is returned for display/status and is not profit sharing.

## New admin pages

- `GET /admin/engagement/squads`
- `GET /admin/engagement/squads/{id}`

Admin can view squads, members, weekly points, leaderboard, and chest claims. Admin can enable/disable a squad.

## New tables

- `squads`
- `squad_members`
- `squad_invites`
- `squad_weekly_chests`

## Deployment

Run only the targeted migration:

```bash
cd ~/public_html
php artisan migrate --force --path=database/migrations/2026_07_12_000001_create_squad_mining_tables.php
php artisan optimize:clear
php artisan route:list | grep squads
```

## React Native next step

Build a Community/Squads screen:

- If user has no squad: show create squad, join by code, and discover list.
- If user has squad: show members, invite code, weekly points, chest status, leaderboard.
- Add "Claim weekly chest" only when `chest_unlocked = true`.
- Use safe wording: "community activity boost", not earning/profit.
