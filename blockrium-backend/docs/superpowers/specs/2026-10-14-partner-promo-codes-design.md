# Partner Promo Codes Design

## Status

Approved for backend implementation on 2026-10-14.

## Goal

Allow Blockrium to generate secure, single-use BRC promo codes for a partner campaign, export them as a CSV for Interstellar to distribute through winning Crates, and let Blockrium users redeem the codes in the app without a direct partner API integration.

The existing partner campaign system remains the campaign owner and reporting source. Promo codes are an additional reward delivery method, not a second campaign engine.

## Product Decisions

- Blockrium generates the codes and reward allocations.
- Interstellar receives a CSV and distributes codes to winning Crate users.
- Blockrium does not require Interstellar API callbacks for the first release.
- Promo redemption requires a valid Blockrium account with email or phone verification.
- Promo redemption does not require paid KYC by default.
- KYC remains required for verified-member benefits and BRC-to-BRC transfers.
- A user can redeem at most one promo code from the same campaign unless an administrator explicitly changes that policy for a future campaign.
- The reward amount is always read from the server-side code record; the client cannot choose or change it.
- A successful reward credit and code claim are one atomic operation.
- Plain promo codes are never stored in the database.
- If the partner gives a code only after a winning Crate, Blockrium treats that code as partner-issued proof of eligibility.

## Existing Components To Reuse

- `partner_campaigns` stores the campaign, partner, schedule, status, budget, and public identity.
- `partner_campaign_claims` stores the final campaign reward claim and aggregate reporting data.
- `WalletRewardService::credit` credits the existing BRC wallet and must remain the only wallet credit path for promo rewards.
- The existing admin audit mechanism records batch creation, export, revocation, and manual corrections.
- Existing campaign notification behavior is reused for successful promo claims.

## End-to-End Lifecycle

1. An administrator creates or selects an active partner campaign.
2. The administrator creates a promo batch with a fixed reward or a row-level reward allocation.
3. The backend validates the requested count, total BRC allocation, campaign window, and expiry.
4. The backend generates cryptographically random codes and stores only HMAC-SHA256 hashes plus safe display metadata.
5. The backend creates an immutable export manifest containing batch ID, campaign, count, total reward, expiry, and CSV SHA-256.
6. The administrator downloads a password-protected ZIP containing the CSV and manifest, preferably through a one-time or authenticated admin download.
7. The administrator sends the ZIP and password through separate channels to Interstellar.
8. Interstellar assigns a code to a winning Crate without modifying the code or reward amount.
9. The user opens Blockrium, signs in or creates an account, and enters the code in the promo redemption screen.
10. The API normalizes the submitted code, applies rate limits, and finds the matching code hash.
11. A database transaction locks the code and campaign, rechecks every eligibility rule, credits BRC, creates the campaign claim and redemption record, marks the code claimed, and creates a notification.
12. The app shows the confirmed reward and the transaction reference.
13. The admin and partner dashboards report generated, claimed, rejected, and distributed amounts.

## Data Model

### `partner_promo_batches`

- `id`
- `campaign_id`
- `batch_reference` unique, human-readable and non-secret
- `status`: `draft`, `generated`, `exported`, `paused`, `completed`, `revoked`
- `code_count`
- `generated_count`
- `claimed_count` default 0
- `total_reward_amount` decimal(12,4)
- `reward_label` default `BRC`
- `expires_at`
- `exported_at` nullable
- `export_sha256` nullable
- `export_downloaded_by_admin_id` nullable
- `export_downloaded_at` nullable
- `created_by_admin_id`
- timestamps

Indexes: campaign/status, status/expiry, batch reference.

### `partner_promo_codes`

- `id`
- `batch_id`
- `campaign_id`
- `code_hash` unique
- `code_suffix` containing only the final four characters for safe admin lookup
- `reward_amount` decimal(12,4)
- `reward_label` default `BRC`
- `status`: `available`, `claimed`, `revoked`, `expired`
- `claimed_by_user_id` nullable
- `claimed_at` nullable
- `redemption_reference` nullable
- timestamps

Indexes: batch/status, campaign/status, claimed user, expiry through the batch relation.

The plaintext code is generated only for the export stream and is never inserted into the database or application logs.

### `partner_promo_redemptions`

- `id`
- `promo_code_id`
- `batch_id`
- `campaign_id`
- `user_id`
- `request_key` unique per user and campaign request flow
- `status`: `succeeded`, `rejected`, `held`
- `failure_reason` nullable
- `wallet_reference` nullable
- `reward_amount` decimal(12,4)
- `device_hash` nullable
- `visitor_hash` nullable
- `created_at`

Indexes: user/campaign, code/status, campaign/date, request key.

### Existing `partner_campaign_claims` extension

Add nullable `promo_code_id` and `claim_source` with `promo_code` as the value for this flow. Keep the existing unique campaign/user rule so one account cannot claim multiple codes from the same campaign.

## Code Generation

- Use the framework cryptographically secure random generator.
- Use an uppercase alphabet that removes visually ambiguous characters such as `O`, `0`, `I`, and `1`.
- Format codes with a fixed prefix and grouped characters, for example `BRC-7K4M-92PX-AL8Q`.
- Normalize input by trimming whitespace and uppercasing before hashing.
- Hash with an application-secret HMAC, not a plain unsalted hash.
- Reject duplicate generated hashes and retry generation safely.
- Do not encode the reward amount, user ID, or database ID into the code.

## CSV and Delivery Contract

The CSV is UTF-8 with a header and no internal database identifiers:

```csv
code,reward_amount,reward_asset,expires_at
BRC-7K4M-92PX-AL8Q,500,BRC,2027-01-31
BRC-2R8D-PL91-XM5T,1000,BRC,2027-01-31
```

The ZIP contains:

- `interstellar-promo-codes.csv`
- `manifest.json`

The manifest contains campaign name, batch reference, generated count, total BRC, expiry, creation timestamp, and CSV SHA-256. It must not contain code hashes, admin secrets, or user information.

The admin download must be authenticated, audited, rate-limited, and streamed without writing the CSV into a public web directory. If repeat download is needed, store only an encrypted private export or regenerate the same batch from a protected encrypted source; never generate a second batch accidentally.

## API Contract

### App redemption

`POST /api/auth/partner-promo-codes/redeem`

Request:

```json
{
  "code": "BRC-7K4M-92PX-AL8Q",
  "request_key": "client-generated-idempotency-key"
}
```

The server derives the campaign from the code. The client must not send a reward amount.

Success response contains campaign title, reward amount, reward label, wallet reference, redemption reference, and claimed timestamp.

`GET /api/auth/partner-promo-codes/redemptions`

Returns the signed-in user's successful and held promo redemptions without exposing code hashes or other users.

### Admin

Admin routes are protected by the existing admin middleware:

- `POST /admin/partner-campaigns/{campaign}/promo-batches`
- `GET /admin/partner-campaigns/{campaign}/promo-batches`
- `GET /admin/partner-promo-batches/{batch}`
- `GET /admin/partner-promo-batches/{batch}/export`
- `POST /admin/partner-promo-batches/{batch}/pause`
- `POST /admin/partner-promo-batches/{batch}/revoke-unused`
- `GET /admin/partner-promo-batches/{batch}/redemptions`

Admin responses must show aggregate totals and safe code suffixes only.

## Redemption Transaction

The redemption service must execute the following inside one database transaction:

1. Lock the code row by its HMAC hash.
2. Lock the parent campaign and confirm it is active and within its dates.
3. Confirm the batch is generated/exported and not paused or revoked.
4. Confirm the code is available and not expired.
5. Confirm the user is active and satisfies account verification requirements.
6. Confirm no existing campaign claim exists for the user.
7. Confirm the campaign budget and participant limit remain available.
8. Call `WalletRewardService::credit` with the stored reward amount.
9. Insert `partner_campaign_claims` with `claim_source = promo_code`.
10. Insert `partner_promo_redemptions` with a unique request key.
11. Mark the code claimed and update batch counters.
12. Create the existing BRC reward notification.
13. Commit only after all records succeed.

If a request is retried with the same request key, return the original successful result without another wallet credit. If a different user submits an already claimed code, return a generic unavailable response and record the attempt without revealing the claimant.

## Error and Hold States

User-facing responses must distinguish actionable states without exposing security-sensitive details:

- Invalid or unavailable code
- Campaign expired or paused
- Account verification required
- Reward allocation exhausted
- Code already redeemed
- Redemption temporarily held for review
- Temporary server failure with safe retry guidance

Suspicious attempts should create a `held` redemption only when the risk policy requires it. A held code remains unavailable until an administrator resolves it; it must not credit the wallet automatically.

## Abuse Controls

- Rate-limit redemption by account, device hash, and visitor hash.
- Add a stricter limit for repeated invalid codes.
- Use request idempotency keys.
- Enforce one successful promo claim per user per campaign.
- Detect multiple accounts sharing a device or IP hash without storing raw IP by default.
- Flag rapid claims, high invalid-attempt rates, disabled accounts, and unusual country/device patterns.
- Do not delete suspicious claims or balances automatically; hold them for admin review.
- Audit every batch generation, export, download, pause, revoke, redemption, hold, release, and manual correction.

## Admin Reporting

The campaign dashboard should show:

- Codes generated
- Codes exported
- Codes available
- Codes claimed
- Codes revoked or expired
- Unique Blockrium users
- BRC distributed
- Remaining BRC allocation
- Redemption conversion rate
- Held and rejected attempts
- Daily redemption trend

Partner-facing reporting must remain aggregate-only and must not expose user email, IP, device identifiers, or internal code hashes.

## Testing Requirements

Backend tests must cover:

- Fixed-reward and variable-reward CSV generation
- Secure export manifest and CSV hash
- Plain codes never being logged or persisted
- Duplicate code prevention during generation
- Active, paused, expired, and revoked batch behavior
- Successful redemption and wallet credit
- Same request retry without duplicate credit
- Same-user second-code rejection
- Different-user claimed-code rejection
- Campaign budget and participant limits under concurrent requests
- Invalid-code rate limiting
- Account verification requirements
- Held redemption behavior
- Admin authorization and audit records
- Export download not being publicly accessible
- Partner reports containing aggregates only

## Rollout Plan

1. Implement migration and back-end services.
2. Add admin batch creation and export.
3. Add the redemption API and notification response.
4. Run automated tests and PHP syntax checks.
5. Create a ten-code staging batch.
6. Redeem one test code from a test account.
7. Confirm wallet credit, notification, claim record, and dashboard metrics.
8. Confirm a second attempt is rejected without an extra credit.
9. Generate the production batch only after the staging test passes.
10. Add the mobile redemption screen and deep link after the backend contract is stable.

## Out of Scope For First Release

- Direct Interstellar API or webhook integration
- Partner login accounts
- Automatic proof-of-Crate validation
- Multi-currency rewards
- User-to-user transfer of promo codes
- Editing a generated code's reward amount
- Reusing a code after revocation or expiry
