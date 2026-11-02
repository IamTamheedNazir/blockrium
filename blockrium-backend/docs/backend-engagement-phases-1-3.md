# Blockrium Engagement Backend: Phases 1-3

This backend treats mining as a server-controlled daily participation session. XP is a non-withdrawable engagement score with no cash value and is stored separately from BRC balances.

## Endpoints

All endpoints below require the existing `api_res_user` JWT except where noted.

### `GET /api/auth/daily-missions`

```json
{
  "success": true,
  "data": {
    "summary": {
      "completed_count": 2,
      "total_count": 7,
      "progress_percent": 29
    },
    "missions": [
      {
        "key": "mining",
        "title": "Start Mining",
        "subtitle": "Today's participation session is active",
        "status": "active",
        "reward_label": "BRC",
        "action": "mining",
        "progress_percent": 100,
        "meta": {
          "can_start": false,
          "started_at": "2026-07-03 10:00:00",
          "ends_at": "2026-07-03 16:00:00",
          "seconds_remaining": 3600
        }
      }
    ]
  }
}
```

Mission modules are fault-isolated. A failed or missing module returns `error` or `unavailable` without failing the full dashboard.

### `GET /api/auth/gamification/summary`

```json
{
  "success": true,
  "data": {
    "xp": 120,
    "level": 2,
    "level_name": "Active Miner",
    "current_level_xp": 100,
    "next_level_xp": 500,
    "progress_percent": 5,
    "badges": [],
    "season": {
      "key": "genesis",
      "title": "Genesis Season",
      "progress_percent": 50,
      "starts_at": "2026-01-01 00:00:00",
      "ends_at": "2026-12-31 23:59:59"
    }
  }
}
```

### `GET /api/auth/gamification/badges`

```json
{
  "success": true,
  "data": {
    "earned": [
      {
        "key": "first_participation",
        "name": "First Participant",
        "description": "Started the first backend-validated participation session.",
        "icon": "pickaxe",
        "category": "participation"
      }
    ],
    "available": []
  }
}
```

### `GET /api/auth/referrals/journey`

```json
{
  "success": true,
  "data": {
    "referral_code": "BRC-ABC12345",
    "share_message": "Join Blockrium with my referral code BRC-ABC12345 and take part in the network.",
    "stats": {
      "total_joined": 3,
      "active_referrals": 1,
      "pending_referrals": 2,
      "claimable_rewards": 1
    },
    "milestones": [
      {
        "id": 1,
        "key": "first-active-referral",
        "title": "First Active Referral",
        "required_active_referrals": 1,
        "reward_amount": "1.00",
        "reward_label": "BRC",
        "reward_xp": 25,
        "status": "ready",
        "can_claim": true,
        "claimed": false
      }
    ]
  }
}
```

### `POST /api/auth/referrals/milestone/{id}/claim`

```json
{
  "success": true,
  "message": "Referral milestone claimed successfully.",
  "already_claimed": false,
  "reward": {
    "amount": "1.0000",
    "label": "BRC"
  },
  "gamification": {
    "awarded": true,
    "xp_awarded": 25
  },
  "data": {}
}
```

Repeated claims return HTTP 200 with `already_claimed: true` and do not credit BRC or XP again.

## XP Hooks

- `mining_started`: 10 XP, once per user per day.
- `streak_claimed`: 15 XP, once per user per day.
- `task_claimed`: 10 XP per backend-confirmed task, capped at 100 XP per day.
- `starter_quest_claimed`: 50 XP once.
- `referral_activated`: 100 XP per valid referred user, capped at 500 XP per day.
- `referral_milestone_claimed`: configured per milestone and awarded once.

Existing reward endpoint fields are preserved. Successful responses may include an additional `gamification` object.

## Duplicate Protection

`user_xp_events.dedupe_key` is unique and generated only by the backend from user, event, date, and validated source. `user_badges` has a unique `(user_id, badge_id)` constraint. `referral_milestone_claims` has a unique `(user_id, referral_milestone_id)` constraint. Milestone claim creation, BRC credit, and XP award run inside a database transaction.

## Deployment

Run only these new migrations on the legacy production database:

```bash
php artisan migrate --force --path=./database/migrations/2026_07_03_000001_create_gamification_foundation_tables.php
php artisan migrate --force --path=./database/migrations/2026_07_03_000002_create_referral_journey_tables.php
php artisan optimize:clear
```

The migrations seed the starter badges, Genesis Season, and referral milestones. The equivalent idempotent seeders are also available:

```bash
php artisan db:seed --class=Database\\Seeders\\GamificationSeeder --force
php artisan db:seed --class=Database\\Seeders\\ReferralMilestoneSeeder --force
```

Do not run unscoped `php artisan migrate --force` on the legacy live database until its old migration history has been reconciled.
