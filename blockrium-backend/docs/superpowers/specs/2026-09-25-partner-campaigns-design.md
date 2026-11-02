# Partner Campaigns and Rewards Design

## Status

Approved for backend implementation on 2026-09-25.

## Goal

Allow administrators to publish time-bounded collaboration campaigns from the backend. Active campaigns can appear in the mobile app home response, send users through tracked partner links, and award BRC using an administrator-selected completion mode. Partners receive a read-only analytics view for their own campaigns.

The backend owns campaign rules, tracking, eligibility, reward accounting, and partner reporting. The frontend team owns campaign cards, home placement, claim controls, and partner dashboard presentation.

## Product Rules

- A campaign is visible to app users only when it is active, within its start and end dates, and within its configured budget and participant limit.
- If no campaign is eligible, the home response contains no campaign section or an empty campaign collection that the frontend can omit without reserving layout space.
- Every campaign has one reward mode selected by an administrator:
  - `click_claim`: a signed-in user records a click and may claim the configured BRC reward once.
  - `admin_approval`: a signed-in user records a click and creates a pending completion; an administrator approves or rejects the completion before the user can claim.
- A user can claim a campaign reward at most once.
- A campaign cannot distribute more than its participant limit or total BRC budget.
- Pausing or expiring a campaign stops new clicks, approvals, and claims while preserving historical analytics.
- Reward crediting and claim creation are one atomic operation. A retry must not create a second reward.
- Campaign destination URLs must be validated as HTTP or HTTPS URLs. JavaScript, local file, and other unsafe schemes are rejected.

## Components

### Partner and campaign records

Use a partner record separate from a campaign so one collaborator can own multiple campaigns. A campaign contains its display content, destination, schedule, reward configuration, status, limits, and public slug.

### Click tracking

Provide an authenticated app click endpoint that records the user click and returns the destination URL for the frontend to open. Also provide a public smart redirect route for campaign links shared outside the app. Public clicks are counted as anonymous visitors unless an attribution token can be safely associated with a signed-in user.

Click records store campaign, nullable user, visitor hash, request metadata that is safe to retain, and timestamps. Unique-user analytics use user ID when available and a privacy-preserving visitor hash otherwise.

### Completion and claims

Completion records represent eligibility separately from claims. This allows click mode and admin-approval mode to share the same claim endpoint and prevents the admin approval flow from crediting the wallet early.

Claims use a unique campaign/user key. The claim transaction locks the campaign and the user's claim state, rechecks dates, status, participant limit, budget, and completion state, credits the existing wallet service, inserts the claim, and creates the existing BRC notification.

### Partner dashboard access

Partner dashboard access uses a revocable, read-only token. Store only a hash of the token. The dashboard token is scoped to one partner, supports rotation, and never exposes other partners' campaigns or user private data. The dashboard reports aggregate data by default.

## Data Model

The implementation should add migrations for the following tables. Exact column types should follow the existing database conventions.

### `partners`

- `id`
- `name`
- `contact_email` nullable
- `status` (`active`, `paused`)
- timestamps

### `partner_dashboard_tokens`

- `id`
- `partner_id`
- `token_hash`
- `label` nullable
- `last_used_at` nullable
- `revoked_at` nullable
- timestamps

### `partner_campaigns`

- `id`
- `partner_id`
- `public_id` or UUID
- `slug` unique
- `title`
- `description` nullable
- `image_url` or stored image path nullable
- `destination_url`
- `status` (`draft`, `active`, `paused`, `expired`)
- `reward_mode` (`click_claim`, `admin_approval`)
- `reward_amount` decimal
- `reward_label` default `BRC`
- `participant_limit` nullable
- `budget_amount` nullable
- `starts_at`
- `ends_at`
- `sort_order`
- `created_by_admin_id` nullable
- timestamps

### `partner_campaign_clicks`

- `id`
- `campaign_id`
- `user_id` nullable
- `visitor_hash` nullable
- `source` (`app`, `public_link`, `unknown`)
- `redirected_at` nullable
- timestamps

### `partner_campaign_completions`

- `id`
- `campaign_id`
- `user_id`
- `status` (`pending`, `approved`, `rejected`)
- `approved_by_admin_id` nullable
- `approved_at` nullable
- `rejected_at` nullable
- `admin_note` nullable
- timestamps

### `partner_campaign_claims`

- `id`
- `campaign_id`
- `user_id`
- `reward_amount`
- `reward_label`
- `wallet_reference` nullable
- `claimed_at`
- timestamps
- unique index on `campaign_id`, `user_id`

An optional audit table or the existing admin audit mechanism should record campaign creation, updates, pause/expire actions, approvals, rejections, token rotations, and manual corrections.

## API Contract

### App endpoints

- `GET /api/auth/partner-campaigns`
  - Returns active campaigns eligible for the current user.
  - Returns an empty collection when none are available.
  - Includes campaign identity, presentation data, schedule, reward data, current user status, click URL, and claim URL.
- `POST /api/auth/partner-campaigns/{campaign}/click`
  - Authenticated endpoint for app attribution.
  - Records a click idempotently enough for analytics and returns the validated destination URL.
- `POST /api/auth/partner-campaigns/{campaign}/claim`
  - Applies the campaign reward after all rules pass.
  - Returns reward amount, claim state, and wallet balance data consistent with existing reward endpoints.
- `GET /partner/campaign/{slug}`
  - Public smart redirect.
  - Records an anonymous click when possible and redirects only to the validated destination URL.

### Admin endpoints and pages

Add a dedicated admin area under `/admin/partner-campaigns`, protected by the existing `checklogin` middleware:

- list campaigns and aggregate performance
- create campaign
- edit campaign
- pause, resume, or expire campaign
- view campaign details and user completion records
- approve or reject pending completions
- create a partner
- issue, rotate, and revoke partner dashboard links

All write actions validate input and record admin audit data.

### Partner dashboard API

- `GET /partner/dashboard/{token}/overview`
- `GET /partner/dashboard/{token}/campaigns`
- `GET /partner/dashboard/{token}/campaigns/{campaign}/metrics`

Responses contain aggregate metrics only unless a future, explicitly approved requirement adds identifiable user data. Metrics include impressions when available, total clicks, unique visitors, signed-in users, pending completions, approved completions, claims, BRC distributed, budget remaining, participant slots remaining, and conversion rate.

## Admin and Budget Behavior

- Draft campaigns are not visible or clickable.
- Active campaigns must have valid dates and a positive reward amount unless the campaign is explicitly configured as non-rewarding.
- A campaign reaches `expired` automatically when its end time passes, while a scheduled job is optional because every read and write must also enforce the time window.
- Budget checks use the configured total budget and the sum of successful claims, not raw clicks.
- Participant limits count successful claims or approved participants according to the selected business rule; the first implementation should count successful claims for click mode and approved completions for approval mode.
- If the budget or participant limit is reached, new claims are rejected without changing the wallet.

## Security and Abuse Controls

- Require app authentication for user click and claim endpoints.
- Use authorization checks so users can claim only for themselves.
- Use database uniqueness and transactions for idempotent claims.
- Rate-limit click, claim, public redirect, and partner dashboard requests.
- Hash visitor identifiers and avoid storing raw IP addresses unless an explicit retention policy is approved.
- Do not expose dashboard tokens in logs or API errors; allow token rotation and revocation.
- Restrict destination URLs to HTTP and HTTPS and reject redirects to internal or local protocols.
- Do not allow partner dashboard tokens to mutate campaigns or award rewards.

## Frontend Integration Contract

The frontend should request the active campaign collection as part of the authenticated home loading flow. It should render the campaign component only when the returned collection is non-empty. It should open the returned click URL or call the click endpoint before opening the destination, and it should use the claim status returned by the backend rather than assuming a reward was issued.

The backend implementation will not modify the frontend project.

## Testing and Verification

Backend tests should cover:

- active date filtering and empty campaign results
- draft, paused, and expired visibility
- click tracking and validated redirects
- click-and-claim success and duplicate claim rejection
- pending, approved, and rejected admin-approval flows
- participant limit and budget enforcement under retries
- wallet credit and notification creation
- partner dashboard token scoping, rotation, and revocation
- destination URL validation
- rate-limit and authorization behavior

Before deployment, run PHP syntax checks, Laravel tests, migration status checks, and a staging or controlled production smoke test. Apply migrations before enabling a campaign.

## Out of Scope For First Implementation

- Partner login accounts and role management
- Automatic partner callbacks or webhook verification
- Automatic fraud scoring beyond rate limits, uniqueness, and campaign caps
- Multi-currency rewards
- Frontend UI implementation
