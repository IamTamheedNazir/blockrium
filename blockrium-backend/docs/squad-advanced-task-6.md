# Blockrium Squad Advanced Features

Backend/admin update for squad engagement.

## Added

- Unlimited squad capacity by default (`max_members = 0` means unlimited).
- Squad roles: `owner`, `co_leader`, `moderator`, `member`.
- Squad country leaderboard in `GET /api/auth/squads/me`.
- Social poster templates: `white`, `dark`, `country`, `weekly_winner`.
- Public poster SVG: `GET /squad/{invite_code}/poster.svg`.
- Weekly squad missions with server-side progress and rewards.
- Invite streak rewards for users whose invited squad members are active today.
- Safe squad announcements using predefined admin templates.
- Admin squad settings page for status, discoverability, promotion tier, country, poster template, weekly target, rewards, and admin note.

## App Endpoints

All `/api/auth/*` endpoints require the normal app auth token.

### GET `/api/auth/squads/me`

Now includes:

- `squad.country_rank`
- `country_leaderboard`
- `missions`
- `invite_streak`
- `announcements`
- `share_card.poster.poster_svg_url`
- `share_card.poster.template`
- `share_card.poster.capacity_label`

### GET `/api/auth/squads/missions`

Returns active weekly missions for the user's current squad.

### POST `/api/auth/squads/missions/{id}/claim`

Claims a completed squad mission once per week.

### POST `/api/auth/squads/invite-streak/claim`

Claims the user's daily invite streak if at least one invited member was active today.

### POST `/api/auth/squads/member/role`

Body:

```json
{
  "user_id": 123,
  "role": "moderator"
}
```

Allowed by owner/co-leader. Owners cannot be changed through this endpoint.

### POST `/api/auth/squads/member/remove`

Body:

```json
{
  "user_id": 123
}
```

Allowed by owner/co-leader. Owners cannot be removed.

### GET `/api/auth/squads/announcements`

Returns active global and squad-specific safe announcements.

## App UI Suggestions

- Show squad role badge on member cards.
- Show role management buttons only for `owner` and `co_leader`.
- Add country leaderboard tab beside global leaderboard.
- Add poster template selector in share flow if the API returns multiple templates later; current template is admin-controlled.
- Use `poster_svg_url` for quick social sharing preview, or recreate a native image with the same payload fields.
- Show weekly missions as progress cards with claim buttons.
- Show invite streak near share/invite UI.
- Show announcements as a small squad banner, not a blocking popup.

## Admin Pages

- `/admin/engagement/squads/{id}/settings`
- Squad detail page now includes role controls and remove-member controls.
- Production Health includes the new squad mission, announcement, and invite-streak tables.

