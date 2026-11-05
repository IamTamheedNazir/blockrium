<?php

namespace App\Services;

use App\Jobs\SendPushNotificationJob;
use App\Support\BrcAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VerifiedMemberService
{
    private const IN_PROGRESS_KYC_STATUSES = ['started', 'submitted', 'in_review', 'in_progress'];

    private const RESERVED_USERNAMES = ['admin', 'support', 'blockrium', 'official', 'system', 'moderator', 'root'];

    private const MIN_BRC_TRANSFER_UNITS = 50;

    private $wallets;
    private $didit;
    private $settings;

    public function __construct(WalletRewardService $wallets, DiditService $didit, IntegrationSettingsService $settings)
    {
        $this->wallets = $wallets;
        $this->didit = $didit;
        $this->settings = $settings;
    }

    public function status(int $userId): array
    {
        $membership = $this->membership($userId);
        $payment = $this->latestVerificationPayment($userId);
        $username = Schema::hasTable('brc_usernames')
            ? DB::table('brc_usernames')->where('user_id', $userId)->first()
            : null;
        $verified = $this->isVerifiedFromMembership($membership);
        $kycBoostPercent = $this->verifiedMiningBoostPercent($userId);
        $nextAction = $this->nextAction($membership, $payment, $userId);

        return [
            'is_verified' => $verified,
            'badge' => [
                'visible' => $verified,
                'label' => 'Verified Blockrium Member',
                'icon' => 'verified_tick',
            ],
            'kyc_status' => $membership ? $membership->kyc_status : 'not_started',
            'didit_status' => $membership ? $membership->didit_status : null,
            'kyc_country' => $membership ? $membership->kyc_country : null,
            'payment_status' => $membership ? $membership->payment_status : 'pending',
            'access_suspended' => $membership ? !empty($membership->suspended_at) : false,
            'verified_at' => $membership ? $membership->verified_at : null,
            'brc_transfer_balance' => $verified ? $this->brcTransferBalance($userId) : '0.00000000',
            'next_action' => $nextAction['key'],
            'next_action_label' => $nextAction['label'],
            'next_action_url' => $nextAction['url'],
            'payment' => $this->paymentPayload($payment, $nextAction),
            'payment_support' => $this->paymentSupportSummary($userId),
            'username' => $username ? $username->username : null,
            'waitlist' => $this->waitlistPayload($userId),
            'benefits' => [
                'brc_username_transfers' => $verified,
                'wallet_early_access' => $verified,
                'mining_boost_eligibility' => $verified,
                'presale_access' => $verified,
                'mining_boost_percent' => $kycBoostPercent,
                'mining_boost_active' => $verified && $kycBoostPercent > 0,
            ],
            'benefits_details' => $this->benefitDetails($verified, $kycBoostPercent),
        ];
    }

    public function waitlist(int $userId): array
    {
        return $this->waitlistPayload($userId);
    }

    public function joinWaitlist(int $userId, string $countryCode, string $countryName): array
    {
        if (!$this->waitlistEnabled()) {
            throw new HttpException(503, 'The KYC waiting list is temporarily closed.');
        }

        $countryCode = $this->normalizeCountryCode($countryCode);
        $countryName = trim($countryName);
        if ($countryName === '' || strlen($countryName) > 100) {
            throw new HttpException(422, 'A valid country name is required.');
        }

        $membership = $this->membership($userId);
        if ($membership && $this->isVerifiedFromMembership($membership)) {
            throw new HttpException(409, 'This account is already a verified Blockrium member.');
        }

        $existing = $this->waitlistRow($userId);
        if ($existing && in_array($existing->status, ['invited', 'started'], true)) {
            return $this->waitlistPayload($userId);
        }

        if ($existing && $existing->status === 'converted' && $membership && in_array($membership->kyc_status, ['started', 'submitted', 'in_review', 'approved'], true)) {
            return $this->waitlistPayload($userId);
        }

        $now = now();
        $payload = [
            'country_code' => $countryCode,
            'country_name' => $countryName,
            'status' => 'waiting',
            'joined_at' => $existing && $existing->status === 'waiting' && $existing->country_code === $countryCode
                ? $existing->joined_at
                : $now,
            'invited_at' => null,
            'started_at' => null,
            'converted_at' => null,
            'cancelled_at' => null,
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('kyc_waitlist_entries')->where('id', $existing->id)->update($payload);
        } else {
            $payload['user_id'] = $userId;
            $payload['created_at'] = $now;
            DB::table('kyc_waitlist_entries')->insert($payload);
        }

        return $this->waitlistPayload($userId);
    }

    public function leaveWaitlist(int $userId): array
    {
        $entry = $this->waitlistRow($userId);
        if (!$entry) {
            return $this->waitlistPayload($userId);
        }

        if (in_array($entry->status, ['invited', 'started', 'converted'], true)) {
            throw new HttpException(409, 'This KYC request is already in progress and cannot be removed from the queue.');
        }

        DB::table('kyc_waitlist_entries')->where('id', $entry->id)->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->waitlistPayload($userId);
    }

    public function waitlistCountrySummary()
    {
        if (!Schema::hasTable('kyc_waitlist_entries')) {
            return collect();
        }

        return DB::table('kyc_waitlist_entries')
            ->select('country_code', 'country_name', 'status', 'joined_at')
            ->whereIn('status', ['waiting', 'invited', 'started', 'converted'])
            ->orderBy('country_name')
            ->orderBy('joined_at')
            ->get()
            ->groupBy('country_code')
            ->map(function ($entries) {
                $countryCode = (string) $entries->first()->country_code;
                $waitingCount = $entries->where('status', 'waiting')->count();
                $invitedCount = $entries->where('status', 'invited')->count();
                $startedCount = $entries->where('status', 'started')->count();
                $convertedCount = $entries->where('status', 'converted')->count();
                $memberLimit = $this->countryMemberLimit($countryCode);
                $activeCount = $invitedCount + $startedCount + $convertedCount;

                return (object) [
                    'country_code' => $countryCode,
                    'country_name' => $entries->first()->country_name,
                    'waiting_count' => $waitingCount,
                    'invited_count' => $invitedCount,
                    'started_count' => $startedCount,
                    'converted_count' => $convertedCount,
                    'total_count' => $entries->count(),
                    'member_limit' => $memberLimit,
                    'limit' => $memberLimit,
                    'capacity_limit' => $memberLimit,
                    'country_limit' => $memberLimit,
                    'max_members' => $memberLimit,
                    'active_count' => $activeCount,
                    'active_members' => $activeCount,
                    'active_or_converted_count' => $activeCount,
                    'available_slots' => max(0, $memberLimit - $activeCount),
                    'remaining_slots' => max(0, $memberLimit - $activeCount),
                    'slots_remaining' => max(0, $memberLimit - $activeCount),
                ];
            })
            ->values();
    }

    public function resendWaitlistInvitation(int $userId): array
    {
        if (!Schema::hasTable('kyc_waitlist_entries')) {
            throw new HttpException(503, 'KYC waitlist is not available yet.');
        }

        $entry = DB::table('kyc_waitlist_entries')->where('user_id', $userId)->first();
        if (!$entry) {
            throw new HttpException(404, 'KYC waitlist entry was not found.');
        }
        if (!in_array($entry->status, ['invited', 'started'], true)) {
            throw new HttpException(409, 'A KYC invitation can only be resent to an invited user.');
        }

        $membership = $this->membership($userId);
        if ($membership && in_array($membership->kyc_status, ['approved', 'submitted', 'in_review'], true)) {
            throw new HttpException(409, 'This user already has an active or approved KYC case.');
        }
        if (!DB::table('res_users')->where('id', $userId)->exists()) {
            throw new HttpException(404, 'User was not found.');
        }
        if (!Schema::hasTable('notifications')) {
            throw new HttpException(503, 'KYC notifications are not available yet.');
        }

        $this->notifyWaitlistInvitation($userId);

        return ['notifications_created' => 1];
    }

    public function inviteWaitlist(string $countryCode, int $limit, ?int $adminId = null): int
    {
        $countryCode = $this->normalizeCountryCode($countryCode);
        $limit = max(1, min($limit, 500));

        $userIds = DB::transaction(function () use ($countryCode, $limit, $adminId) {
            $entries = DB::table('kyc_waitlist_entries')
                ->where('country_code', $countryCode)
                ->where('status', 'waiting')
                ->orderBy('joined_at')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get(['id', 'user_id']);

            if ($entries->isEmpty()) {
                return [];
            }

            DB::table('kyc_waitlist_entries')
                ->whereIn('id', $entries->pluck('id')->all())
                ->update([
                    'status' => 'invited',
                    'invited_at' => now(),
                    'invited_by_admin_id' => $adminId,
                    'updated_at' => now(),
                ]);

            return $entries->pluck('user_id')->map(function ($id) {
                return (int) $id;
            })->all();
        });

        foreach ($userIds as $userId) {
            $this->notifyWaitlistInvitation($userId);
        }

        return count($userIds);
    }

    public function inviteSelectedWaitlist(array $userIds, ?int $adminId = null): int
    {
        $userIds = collect($userIds)
            ->map(function ($userId) {
                return (int) $userId;
            })
            ->filter(function ($userId) {
                return $userId > 0;
            })
            ->unique()
            ->values()
            ->all();

        if (!$userIds) {
            return 0;
        }

        $invitedUserIds = DB::transaction(function () use ($userIds, $adminId) {
            $entries = DB::table('kyc_waitlist_entries')
                ->whereIn('user_id', $userIds)
                ->where('status', 'waiting')
                ->lockForUpdate()
                ->get(['id', 'user_id']);

            if ($entries->isEmpty()) {
                return [];
            }

            DB::table('kyc_waitlist_entries')
                ->whereIn('id', $entries->pluck('id')->all())
                ->where('status', 'waiting')
                ->update([
                    'status' => 'invited',
                    'invited_at' => now(),
                    'invited_by_admin_id' => $adminId,
                    'updated_at' => now(),
                ]);

            return $entries->pluck('user_id')->map(function ($userId) {
                return (int) $userId;
            })->all();
        });

        foreach ($invitedUserIds as $userId) {
            $this->notifyWaitlistInvitation($userId);
        }

        return count($invitedUserIds);
    }

    public function saveCountryLimit(string $countryCode, int $memberLimit, ?int $adminId = null): void
    {
        $countryCode = $this->normalizeCountryCode($countryCode);
        $memberLimit = max(1, min($memberLimit, 1000000));

        if (!Schema::hasTable('kyc_country_limits')) {
            throw new RuntimeException('Country limit table is not available.');
        }

        $columns = Schema::getColumnListing('kyc_country_limits');
        $countryColumn = in_array('country_code', $columns, true) ? 'country_code' : (in_array('country', $columns, true) ? 'country' : null);
        $limitColumn = null;
        foreach (['member_limit', 'maximum_members', 'max_members', 'limit'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                $limitColumn = $candidate;
                break;
            }
        }
        if (!$countryColumn || !$limitColumn) {
            throw new RuntimeException('Country limit table has an unsupported schema.');
        }

        $query = DB::table('kyc_country_limits')->where($countryColumn, $countryCode);
        $payload = [$countryColumn => $countryCode, $limitColumn => $memberLimit];
        foreach (['updated_by_admin_id', 'admin_id'] as $adminColumn) {
            if (in_array($adminColumn, $columns, true)) {
                $payload[$adminColumn] = $adminId;
                break;
            }
        }
        if (in_array('updated_at', $columns, true)) {
            $payload['updated_at'] = now();
        }

        if ($query->exists()) {
            $query->update($payload);
            return;
        }

        if (in_array('created_at', $columns, true)) {
            $payload['created_at'] = now();
        }
        DB::table('kyc_country_limits')->insert($payload);
    }

    public function verifiedMiningBoostPercent(int $userId): float
    {
        if (!$this->isVerifiedFromMembership($this->membership($userId))) {
            return 0.0;
        }

        return max(0.0, min(100.0, (float) $this->setting('kyc_verified_mining_boost_percent', 10)));
    }

    public function startKyc(int $userId): array
    {
        $membership = $this->membership($userId);
        if ($membership && $membership->kyc_status === 'approved') {
            return $this->status($userId);
        }

        $activeStatuses = ['Not Started', 'In Progress', 'Resubmitted', 'Awaiting User'];
        if ($membership && $membership->didit_session_id && in_array($membership->didit_status, $activeStatuses, true) && $membership->didit_session_url) {
            return [
                'verification_url' => $membership->didit_session_url,
                'session_id' => $membership->didit_session_id,
                'status' => $this->status($userId),
            ];
        }

        $waitlist = $this->waitlistRow($userId);
        if (!$waitlist) {
            throw new HttpException(403, 'Join the KYC waiting list before starting verification.');
        }
        if ($waitlist->status === 'waiting') {
            return [
                'waitlisted' => true,
                'message' => 'You are on the KYC waiting list. We will invite you when your country batch opens.',
                'status' => $this->status($userId),
            ];
        }
        if (!in_array($waitlist->status, ['invited', 'started'], true)) {
            throw new HttpException(403, 'Your KYC waiting-list invitation is not active.');
        }

        $membership = $membership ?: $this->ensureMembership($userId);

        $monthlyStarted = DB::table('verified_memberships')
            ->where('kyc_provider', 'didit')
            ->whereNotNull('didit_session_id')
            ->where('kyc_started_at', '>=', now()->startOfMonth())
            ->count();
        if ($monthlyStarted >= (int) $this->setting('didit_monthly_limit', config('services.didit.monthly_limit', 500))) {
            throw new HttpException(429, 'This month\'s verification capacity has been reached.');
        }

        $user = DB::table('res_users')->where('id', $userId)->first();
        if (!$user) {
            throw new HttpException(404, 'User was not found.');
        }

        $session = $this->didit->createSession($user, 'user-' . $userId);
        DB::table('verified_memberships')->where('id', $membership->id)->update([
            'kyc_status' => 'started',
            'kyc_provider' => 'didit',
            'kyc_reference' => $session['session_id'],
            'didit_session_id' => $session['session_id'],
            'didit_session_url' => $session['url'],
            'didit_status' => $session['status'],
            'kyc_started_at' => now(),
            'rejection_reason' => null,
            'updated_at' => now(),
        ]);
        DB::table('kyc_waitlist_entries')->where('id', $waitlist->id)->update([
            'status' => 'started',
            'started_at' => $waitlist->started_at ?: now(),
            'updated_at' => now(),
        ]);

        return [
            'verification_url' => $session['url'],
            'session_id' => $session['session_id'],
            'status' => $this->status($userId),
        ];
    }

    public function submitKyc(int $userId): array
    {
        return $this->status($userId);
    }

    public function handleDiditWebhook(array $payload): array
    {
        $eventId = (string) ($payload['event_id'] ?? '');
        $eventType = (string) ($payload['webhook_type'] ?? '');
        $sessionId = (string) ($payload['session_id'] ?? '');
        $diditStatus = (string) ($payload['status'] ?? '');
        if ($eventId === '' || $sessionId === '' || !in_array($eventType, ['status.updated', 'data.updated'], true)) {
            throw new HttpException(422, 'Unsupported Didit webhook payload.');
        }

        $environment = (string) ($payload['environment'] ?? '');
        if ($environment !== '' && $environment !== $this->setting('didit_environment', config('services.didit.environment', 'live'))) {
            throw new HttpException(422, 'Didit webhook environment does not match this application.');
        }

        $result = DB::transaction(function () use ($payload, $eventId, $eventType, $sessionId, $diditStatus) {
            $membership = DB::table('verified_memberships')
                ->where('didit_session_id', $sessionId)
                ->lockForUpdate()
                ->first();
            if (!$membership) {
                throw new HttpException(404, 'Didit session is not linked to a Blockrium user.');
            }

            $inserted = DB::table('didit_webhook_events')->insertOrIgnore([
                'event_id' => $eventId,
                'webhook_type' => $eventType,
                'session_id' => $sessionId,
                'status' => $diditStatus,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if (!$inserted) {
                return ['ignored' => true, 'reason' => 'duplicate_event'];
            }

            $updates = [
                'didit_status' => $diditStatus,
                'didit_event_id' => $eventId,
                'kyc_reference' => $sessionId,
                'updated_at' => now(),
            ];

            if ($diditStatus === 'Approved') {
                $country = $this->diditCountry($payload);
                if (!$country) {
                    $updates['kyc_status'] = 'in_review';
                    $updates['rejection_reason'] = 'Didit approved the session but did not return a document country.';
                } elseif ($this->countryLimitReached($country, (int) $membership->id)) {
                    $updates['kyc_status'] = 'country_limit';
                    $updates['kyc_country'] = $country;
                    $updates['rejection_reason'] = 'The pilot allocation for this document country is full.';
                } else {
                    $updates['kyc_status'] = 'approved';
                    $updates['kyc_country'] = $country;
                    $updates['rejection_reason'] = null;
                    $updates['kyc_reviewed_at'] = now();
                }
            } elseif ($diditStatus === 'In Review') {
                $updates['kyc_status'] = 'in_review';
            } elseif (in_array($diditStatus, ['Declined', 'Expired', 'Abandoned', 'Kyc Expired'], true)) {
                $updates['kyc_status'] = 'rejected';
                $updates['rejection_reason'] = 'Didit returned: ' . $diditStatus;
            } elseif (in_array($diditStatus, ['In Progress', 'Resubmitted', 'Awaiting User', 'Not Started'], true)) {
                $updates['kyc_status'] = 'submitted';
            }

            DB::table('verified_memberships')->where('id', $membership->id)->update($updates);
            $this->syncWaitlistAfterKyc($membership->user_id, $updates['kyc_status'] ?? $membership->kyc_status);
            DB::table('didit_webhook_events')->where('event_id', $eventId)->update(['processed_at' => now(), 'updated_at' => now()]);

            return [
                'ignored' => false,
                'status' => $diditStatus,
                'kyc_status' => $updates['kyc_status'] ?? $membership->kyc_status,
                'payment_reminder_user_id' => ($updates['kyc_status'] ?? $membership->kyc_status) === 'approved'
                    && $membership->kyc_status !== 'approved'
                    && ($membership->payment_status ?? 'pending') !== 'paid'
                    ? (int) $membership->user_id
                    : null,
            ];
        });

        $reminderUserId = $result['payment_reminder_user_id'] ?? null;
        if ($reminderUserId) {
            try {
                $this->sendAdminReminder((int) $reminderUserId);
            } catch (\Throwable $error) {
                Log::warning('Didit webhook could not queue the KYC payment reminder.', [
                    'user_id' => (int) $reminderUserId,
                    'error' => $error->getMessage(),
                ]);
            }
        }

        unset($result['payment_reminder_user_id']);
        return $result;
    }

    public function reconcileDiditSessions(int $offset = 0): array
    {
        if (!Schema::hasTable('verified_memberships') || !Schema::hasTable('res_users')) {
            throw new HttpException(503, 'KYC storage is not available yet.');
        }

        $providerSessions = $this->didit->listRecentUserSessions(100, max(0, $offset));
        $stats = [
            'provider_count' => $providerSessions['count'],
            'inspected' => count($providerSessions['results']),
            'offset' => $providerSessions['offset'],
            'next_offset' => $providerSessions['next_offset'],
            'linked' => 0,
            'updated' => 0,
            'unlinked' => 0,
            'protected' => 0,
            'approved_payment_pending' => 0,
            'has_more' => $providerSessions['has_more'],
        ];
        $seenUsers = [];
        $reminderUserIds = [];
        $sessions = $providerSessions['results'];
        usort($sessions, function ($left, $right) {
            $leftCreated = strtotime((string) ($left['created_at'] ?? '')) ?: 0;
            $rightCreated = strtotime((string) ($right['created_at'] ?? '')) ?: 0;
            return $rightCreated <=> $leftCreated;
        });

        foreach ($sessions as $session) {
            $vendorData = (string) ($session['vendor_data'] ?? '');
            if (!preg_match('/^user-([1-9][0-9]*)$/', $vendorData, $matches)) {
                $stats['unlinked']++;
                continue;
            }

            $userId = (int) $matches[1];
            if (isset($seenUsers[$userId])) {
                continue;
            }
            $seenUsers[$userId] = true;

            if (!DB::table('res_users')->where('id', $userId)->exists()) {
                $stats['unlinked']++;
                continue;
            }

            $diditStatus = (string) $session['status'];
            $mappedStatus = $this->mapDiditSessionStatus($diditStatus);
            if ($mappedStatus === null) {
                continue;
            }

            $sessionId = (string) $session['session_id'];
            $sessionStartedAt = $this->validDiditTimestamp($session['created_at'] ?? null);
            $country = strtoupper(trim((string) ($session['country'] ?? '')));
            if (!preg_match('/^[A-Z]{3}$/', $country)) {
                $country = null;
            }

            $outcome = DB::transaction(function () use ($userId, $sessionId, $sessionStartedAt, $diditStatus, $mappedStatus, $country) {
                $membership = DB::table('verified_memberships')->where('user_id', $userId)->lockForUpdate()->first();

                if ($membership && $membership->kyc_status === 'approved'
                    && ($membership->payment_status === 'paid' || !$membership->didit_session_id || $membership->didit_session_id !== $sessionId)) {
                    return 'protected';
                }

                if ($membership && $membership->didit_session_id && $membership->didit_session_id !== $sessionId) {
                    $currentStartedAt = $this->validDiditTimestamp($membership->kyc_started_at ?? null);
                    if (!$sessionStartedAt || ($currentStartedAt && $sessionStartedAt <= $currentStartedAt)) {
                        return 'protected';
                    }
                }

                if (!$membership) {
                    $membershipId = DB::table('verified_memberships')->insertGetId([
                        'user_id' => $userId,
                        'payment_amount' => (float) $this->setting('dodo_verification_amount', config('services.dodo.verification_amount', 5)),
                        'payment_currency' => (string) $this->setting('dodo_verification_currency', config('services.dodo.verification_currency', 'USD')),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $membership = DB::table('verified_memberships')->where('id', $membershipId)->first();
                }

                $nextStatus = $mappedStatus;
                $rejectionReason = null;
                if ($diditStatus === 'Approved') {
                    if (!$country) {
                        $nextStatus = 'in_review';
                        $rejectionReason = 'Didit approved the session but did not return a document country.';
                    } elseif ($this->countryLimitReached($country, (int) $membership->id)) {
                        $nextStatus = 'country_limit';
                        $rejectionReason = 'The pilot allocation for this document country is full.';
                    }
                } elseif ($nextStatus === 'rejected') {
                    $rejectionReason = 'Didit returned: ' . $diditStatus;
                }

                $updates = [
                    'kyc_status' => $nextStatus,
                    'kyc_provider' => 'didit',
                    'kyc_reference' => $sessionId,
                    'didit_session_id' => $sessionId,
                    'didit_status' => $diditStatus,
                    'kyc_started_at' => $sessionStartedAt ?: ($membership->kyc_started_at ?: now()),
                    'kyc_country' => $country,
                    'rejection_reason' => $rejectionReason,
                    'updated_at' => now(),
                ];
                if ($nextStatus === 'approved') {
                    $updates['kyc_reviewed_at'] = $membership->kyc_reviewed_at ?: now();
                }

                $changed = false;
                foreach ($updates as $column => $value) {
                    if ($column === 'updated_at') {
                        continue;
                    }
                    if ((string) ($membership->{$column} ?? '') !== (string) ($value ?? '')) {
                        $changed = true;
                        break;
                    }
                }
                if (!$changed) {
                    return 'unchanged';
                }

                DB::table('verified_memberships')->where('id', $membership->id)->update($updates);
                $this->syncWaitlistAfterKyc($userId, $nextStatus);
                $newlyApproved = $membership->kyc_status !== 'approved' && $nextStatus === 'approved'
                    && ($membership->payment_status ?? 'pending') !== 'paid';

                return $newlyApproved ? 'approved_payment_pending' : 'updated';
            });

            $stats['linked']++;
            if ($outcome === 'protected') {
                $stats['protected']++;
            } elseif ($outcome === 'updated' || $outcome === 'approved_payment_pending') {
                $stats['updated']++;
                if ($outcome === 'approved_payment_pending') {
                    $stats['approved_payment_pending']++;
                    $reminderUserIds[] = $userId;
                }
            }
        }

        foreach ($reminderUserIds as $userId) {
            try {
                $this->sendAdminReminder($userId);
            } catch (\Throwable $error) {
                Log::warning('Didit sync could not queue the KYC payment reminder.', [
                    'user_id' => $userId,
                    'exception' => get_class($error),
                ]);
            }
        }

        return $stats;
    }

    private function mapDiditSessionStatus(string $status): ?string
    {
        return [
            'Not Started' => 'started',
            'In Progress' => 'submitted',
            'Resubmitted' => 'submitted',
            'Awaiting User' => 'submitted',
            'In Review' => 'in_review',
            'Approved' => 'approved',
            'Declined' => 'rejected',
            'Expired' => 'rejected',
            'Abandoned' => 'rejected',
            'Kyc Expired' => 'rejected',
        ][$status] ?? null;
    }

    private function validDiditTimestamp($value): ?string
    {
        if (!$value || !is_string($value)) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable $error) {
            return null;
        }
    }

    public function startPayment(int $userId): array
    {
        if ($this->settings->boolean('google_play_kyc_enabled', false)) {
            throw new HttpException(410, 'Blockrium Verified membership activation now uses Google Play Billing. Update the app to continue.');
        }

        $membership = $this->ensureMembership($userId);
        if ($membership->kyc_status !== 'approved') {
            throw new HttpException(403, 'KYC approval is required before payment.');
        }
        if ($membership->payment_status === 'paid') {
            return $this->status($userId);
        }

        $url = $this->setting('dodo_verification_payment_url', config('services.dodo.verification_payment_url'));
        if (!$url) {
            throw new HttpException(503, 'Blockrium Verified membership payment is not configured yet.');
        }

        $paymentId = DB::table('verification_payments')->insertGetId([
            'user_id' => $userId,
            'provider' => 'dodo',
            'status' => 'pending',
            'amount' => (float) $this->setting('dodo_verification_amount', config('services.dodo.verification_amount', 5)),
            'currency' => $this->setting('dodo_verification_currency', config('services.dodo.verification_currency', 'USD')),
            'checkout_url' => $url,
            'metadata' => json_encode(['user_id' => $userId]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'payment_id' => $paymentId,
            'checkout_url' => $url,
            'amount' => (float) $this->setting('dodo_verification_amount', config('services.dodo.verification_amount', 5)),
            'currency' => $this->setting('dodo_verification_currency', config('services.dodo.verification_currency', 'USD')),
            'status' => 'pending',
        ];
    }

    public function createPaymentSupportCase(int $userId, ?int $paymentId, ?string $providerReference, ?string $message): array
    {
        if (!Schema::hasTable('verification_support_cases')) {
            throw new HttpException(503, 'Payment support is not available yet.');
        }

        $membership = $this->ensureMembership($userId);
        $payment = null;
        if ($paymentId) {
            $payment = DB::table('verification_payments')->where('id', $paymentId)->where('user_id', $userId)->first();
            if (!$payment) {
                throw new HttpException(404, 'The payment record was not found.');
            }
        } else {
            $payment = $this->latestVerificationPayment($userId);
        }

        if (!$payment && $membership->payment_status !== 'paid') {
            throw new HttpException(422, 'Start a Blockrium Verified membership payment before opening a payment support case.');
        }

        $openCase = DB::table('verification_support_cases')
            ->where('user_id', $userId)
            ->whereIn('status', ['open', 'in_review'])
            ->orderByDesc('id')
            ->first();
        if ($openCase) {
            return $this->supportCasePayload($openCase);
        }

        $reference = trim((string) ($providerReference ?: ($payment ? $payment->provider_payment_id : $membership->payment_reference)));
        $caseId = DB::table('verification_support_cases')->insertGetId([
            'user_id' => $userId,
            'verification_payment_id' => $payment ? $payment->id : null,
            'type' => 'payment_issue',
            'status' => 'open',
            'provider' => $payment ? $payment->provider : ($membership->payment_provider ?: null),
            'provider_reference' => $reference !== '' ? $reference : null,
            'amount' => $payment ? $payment->amount : $membership->payment_amount,
            'currency' => $payment ? $payment->currency : $membership->payment_currency,
            'user_message' => $message ? trim($message) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->supportCasePayload(DB::table('verification_support_cases')->where('id', $caseId)->first());
    }

    public function paymentSupportCases(int $userId)
    {
        if (!Schema::hasTable('verification_support_cases')) {
            return collect();
        }

        return DB::table('verification_support_cases')->where('user_id', $userId)->orderByDesc('id')->limit(25)->get()
            ->map(function ($case) {
                return $this->supportCasePayload($case);
            });
    }

    public function adminPaymentSupportCases(?string $status = null)
    {
        if (!Schema::hasTable('verification_support_cases')) {
            return collect();
        }

        $query = DB::table('verification_support_cases as s')
            ->leftJoin('res_users as u', 'u.id', '=', 's.user_id')
            ->leftJoin('verified_memberships as m', 'm.user_id', '=', 's.user_id')
            ->leftJoin('verification_payments as p', 'p.id', '=', 's.verification_payment_id')
            ->select(['s.*', 'u.name', 'u.email', 'u.username as legacy_username', 'm.kyc_status', 'm.payment_status', 'm.didit_status', 'p.status as payment_record_status', 'p.provider_payment_id'])
            ->orderByRaw("CASE s.status WHEN 'open' THEN 0 WHEN 'in_review' THEN 1 WHEN 'resolved' THEN 2 WHEN 'rejected' THEN 3 ELSE 4 END")
            ->orderByDesc('s.id');

        if ($status && in_array($status, ['open', 'in_review', 'resolved', 'rejected'], true)) {
            $query->where('s.status', $status);
        }

        return $query->limit(500)->get();
    }

    public function updatePaymentSupportCase(int $caseId, string $status, ?string $adminNotes, ?int $adminId): array
    {
        if (!Schema::hasTable('verification_support_cases')) {
            throw new HttpException(503, 'Payment support is not available yet.');
        }
        if (!in_array($status, ['open', 'in_review', 'resolved', 'rejected'], true)) {
            throw new HttpException(422, 'Invalid payment support status.');
        }

        $case = DB::table('verification_support_cases')->where('id', $caseId)->first();
        if (!$case) {
            throw new HttpException(404, 'Payment support case was not found.');
        }
        if ($status === 'resolved') {
            $membership = $this->membership((int) $case->user_id);
            $payment = $case->verification_payment_id
                ? DB::table('verification_payments')->where('id', $case->verification_payment_id)->first()
                : $this->latestVerificationPayment((int) $case->user_id);
            if (!$membership || $membership->payment_status !== 'paid' || !$payment || $payment->status !== 'paid') {
                throw new HttpException(422, 'Resolve this case only after the payment is verified and recorded.');
            }
        }

        DB::table('verification_support_cases')->where('id', $caseId)->update([
            'status' => $status,
            'admin_notes' => $adminNotes !== null ? trim($adminNotes) : $case->admin_notes,
            'resolved_by_admin_id' => in_array($status, ['resolved', 'rejected'], true) ? $adminId : null,
            'resolved_at' => in_array($status, ['resolved', 'rejected'], true) ? now() : null,
            'updated_at' => now(),
        ]);

        return $this->supportCasePayload(DB::table('verification_support_cases')->where('id', $caseId)->first());
    }

    public function approveKyc(int $userId, ?int $adminId, bool $confirmedDiditDashboardApproval = false): array
    {
        $this->ensureMembership($userId);
        $approval = DB::transaction(function () use ($userId, $adminId, $confirmedDiditDashboardApproval) {
            $membership = DB::table('verified_memberships')
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$membership) {
                throw new HttpException(404, 'Verification case was not found.');
            }

            $providerApproved = $membership->kyc_provider === 'didit'
                && $membership->didit_status === 'Approved'
                && $membership->kyc_country;
            $terminalDiditStatuses = ['declined', 'expired', 'abandoned', 'kyc expired'];
            $diditStatusIsTerminal = in_array(strtolower(trim((string) $membership->didit_status)), $terminalDiditStatuses, true);
            $adminConfirmed = $confirmedDiditDashboardApproval
                && $membership->kyc_provider === 'didit'
                && in_array($membership->kyc_status, ['submitted', 'in_review'], true)
                && !$diditStatusIsTerminal
                && !empty($membership->didit_session_id)
                && $membership->kyc_country;

            $hasReviewableDiditSession = $membership->kyc_provider === 'didit'
                && in_array($membership->kyc_status, ['submitted', 'in_review'], true)
                && !empty($membership->didit_session_id);
            if (!$membership->kyc_country
                && ($membership->didit_status === 'Approved' || ($confirmedDiditDashboardApproval && $hasReviewableDiditSession))) {
                throw new HttpException(409, 'The Didit session has no document country in Blockrium yet. Sync Didit sessions before approving this user.');
            }

            if (!$providerApproved && !$adminConfirmed) {
                throw new HttpException(403, 'KYC can only be approved after a valid Didit result.');
            }

            $wasApproved = $membership->kyc_status === 'approved';
            DB::table('verified_memberships')->where('id', $membership->id)->update([
                'kyc_status' => 'approved',
                'didit_status' => $adminConfirmed ? 'Approved' : $membership->didit_status,
                'kyc_reviewed_by_admin_id' => $adminId,
                'kyc_reviewed_at' => now(),
                'rejection_reason' => null,
                'updated_at' => now(),
            ]);

            return [
                'was_approved' => $wasApproved,
                'payment_status' => $membership->payment_status,
            ];
        });

        if (!$approval['was_approved'] && $approval['payment_status'] !== 'paid') {
            try {
                $this->sendAdminReminder($userId);
            } catch (\Throwable $error) {
                Log::warning('KYC approval reminder could not be queued; app status will still prompt for payment.', [
                    'user_id' => $userId,
                    'error' => $error->getMessage(),
                ]);
            }
        }

        return $this->status($userId);
    }

    public function rejectKyc(int $userId, ?int $adminId, ?string $reason): array
    {
        $membership = $this->ensureMembership($userId);
        DB::table('verified_memberships')->where('id', $membership->id)->update([
            'kyc_status' => 'rejected',
            'kyc_reviewed_by_admin_id' => $adminId,
            'kyc_reviewed_at' => now(),
            'rejection_reason' => $reason,
            'updated_at' => now(),
        ]);

        return $this->status($userId);
    }

    public function revokeAdminAccess(int $userId, string $scope, string $reason, ?int $adminId): array
    {
        if (!in_array($scope, ['member_access', 'kyc_and_member_access'], true)) {
            throw new HttpException(422, 'Choose a valid access revocation scope.');
        }

        return DB::transaction(function () use ($userId, $scope, $reason, $adminId) {
            $membership = DB::table('verified_memberships')
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$membership) {
                throw new HttpException(404, 'Verification case was not found.');
            }

            $revokeKyc = $scope === 'kyc_and_member_access';
            $alreadyRevoked = $membership->suspended_at
                && (!$revokeKyc || $membership->kyc_status === 'revoked');
            if ($alreadyRevoked) {
                return ['changed' => false, 'kyc_status' => $membership->kyc_status, 'payment_status' => $membership->payment_status];
            }

            if (!$revokeKyc && ($membership->kyc_status !== 'approved' || $membership->payment_status !== 'paid')) {
                throw new HttpException(409, 'Member access can only be revoked for a KYC-approved, paid member.');
            }
            if ($revokeKyc && !in_array($membership->kyc_status, ['approved', 'revoked'], true)) {
                throw new HttpException(409, 'KYC access can only be revoked for an approved KYC record.');
            }

            $updates = [
                'suspended_at' => $membership->suspended_at ?: now(),
                'verified_at' => null,
                'updated_at' => now(),
            ];
            if ($revokeKyc) {
                $updates['kyc_status'] = 'revoked';
                if (Schema::hasColumn('verified_memberships', 'kyc_reviewed_at')) {
                    $updates['kyc_reviewed_at'] = now();
                }
                if (Schema::hasColumn('verified_memberships', 'kyc_reviewed_by_admin_id')) {
                    $updates['kyc_reviewed_by_admin_id'] = $adminId;
                }
                if (Schema::hasColumn('verified_memberships', 'rejection_reason')) {
                    $updates['rejection_reason'] = $reason;
                }
            }

            DB::table('verified_memberships')->where('id', $membership->id)->update($updates);

            return [
                'changed' => true,
                'kyc_status' => $revokeKyc ? 'revoked' : $membership->kyc_status,
                'payment_status' => $membership->payment_status,
            ];
        });
    }

    public function sendAdminReminder(int $userId): array
    {
        $membership = $this->membership($userId);
        if (!$membership) {
            throw new HttpException(404, 'Verification case was not found.');
        }

        if ($membership->kyc_status === 'approved' && $membership->payment_status !== 'paid') {
            $title = 'KYC approved · membership payment pending';
            $message = 'Your KYC is complete. Complete the one-time Blockrium Verified membership payment in Blockrium to unlock member benefits.';
            $type = 'KYC_PAYMENT_REMINDER';
        } elseif ($membership->kyc_status === 'rejected') {
            $title = 'Continue your identity verification';
            $message = 'Your previous identity verification could not be completed. Open Blockrium to review the status and request verification again.';
            $type = 'KYC_RETRY_REQUEST';
        } else {
            throw new HttpException(409, 'A reminder is available only for rejected KYC or approved KYC with payment pending.');
        }

        $user = DB::table('res_users')->select('id', 'device_id')->where('id', $userId)->first();
        if (!$user) {
            throw new HttpException(404, 'User was not found.');
        }

        if (Schema::hasTable('notifications')) {
            $payload = [
                'type' => $type,
                'title' => $title,
                'action' => 'verification',
                'data' => json_encode([
                    'message' => $message,
                    'action' => 'verification',
                    'route' => 'VerifiedMember',
                ]),
                'notification_type' => $type,
                'status' => '1',
                'n_type' => '1',
                'notifiable_type' => 'App\\Models\\ResUser',
                'notifiable_id' => $userId,
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            DB::table('notifications')->insert(array_intersect_key(
                $payload,
                array_flip(Schema::getColumnListing('notifications'))
            ));
        }

        $pushQueued = !empty($user->device_id);
        if ($pushQueued) {
            SendPushNotificationJob::dispatch(
                $user->device_id,
                $userId,
                $title,
                $message,
                [
                    'type' => $type,
                    'notification_type' => $type,
                    'action' => 'verification',
                    'route' => 'VerifiedMember',
                ]
            );
        }

        return ['type' => $type, 'push_queued' => $pushQueued];
    }

    public function confirmPayment(int $userId, string $provider, string $reference, float $amount, string $currency): array
    {
        $currency = strtoupper(trim($currency));
        if (!is_finite($amount) || $amount <= 0 || abs($amount - round($amount, 2)) > 0.00000001 || !preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw new HttpException(422, 'Payment amount or currency is invalid.');
        }

        $this->ensureMembership($userId);

        DB::transaction(function () use ($userId, $provider, $reference, $amount, $currency) {
            $membership = DB::table('verified_memberships')
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();
            if (!$membership) {
                throw new HttpException(404, 'Verification case was not found.');
            }
            if ($membership->kyc_status !== 'approved') {
                throw new HttpException(403, 'KYC must be approved before payment can activate verification.');
            }

            $paymentKey = hash('sha256', $provider . '|' . $reference);
            $existing = DB::table('verification_payments')
                ->where('provider_payment_key', $paymentKey)
                ->lockForUpdate()
                ->first();
            if ($existing && (int) $existing->user_id !== $userId) {
                throw new HttpException(409, 'This payment reference is already linked to another user.');
            }
            if ($existing && (
                abs((float) $existing->amount - $amount) > 0.0001
                || strtoupper((string) $existing->currency) !== $currency
            )) {
                throw new HttpException(409, 'This payment reference was already recorded with different payment details.');
            }
            if ($membership->payment_status === 'paid') {
                return;
            }

            $payment = [
                'user_id' => $userId,
                'provider' => $provider,
                'provider_payment_id' => $reference,
                'provider_payment_key' => $paymentKey,
                'status' => 'paid',
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'paid_at' => now(),
                'updated_at' => now(),
            ];
            if ($existing && $existing->status === 'paid') {
                // Replayed provider callbacks must not rewrite the confirmed ledger row.
            } elseif ($existing) {
                DB::table('verification_payments')->where('id', $existing->id)->update($payment);
            } else {
                $payment['created_at'] = now();
                DB::table('verification_payments')->insert($payment);
            }

            DB::table('verified_memberships')->where('id', $membership->id)->update([
                'payment_status' => 'paid',
                'payment_provider' => $provider,
                'payment_reference' => $reference,
                'payment_amount' => $amount,
                'payment_currency' => strtoupper($currency),
                'verified_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return $this->status($userId);
    }

    public function createUsername(int $userId, string $username): array
    {
        $this->requireVerified($userId);
        $normalized = strtolower(trim($username));
        if (!preg_match('/^[a-z0-9_]{4,20}$/', $normalized) || in_array($normalized, self::RESERVED_USERNAMES, true)) {
            throw new HttpException(422, 'Username must be 4-20 characters using lowercase letters, numbers, or underscore.');
        }

        if (DB::table('brc_usernames')->where('normalized_username', $normalized)->where('user_id', '!=', $userId)->exists()) {
            throw new HttpException(409, 'That BRC username is already taken.');
        }

        $existing = DB::table('brc_usernames')->where('user_id', $userId)->first();
        if ($existing) {
            throw new HttpException(409, 'A BRC username is already assigned to this account.');
        }

        DB::table('brc_usernames')->insert([
            'user_id' => $userId,
            'username' => $normalized,
            'normalized_username' => $normalized,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->status($userId);
    }

    public function usernameAvailable(string $username): bool
    {
        $normalized = strtolower(trim($username));
        return (bool) preg_match('/^[a-z0-9_]{4,20}$/', $normalized)
            && !in_array($normalized, self::RESERVED_USERNAMES, true)
            && !DB::table('brc_usernames')->where('normalized_username', $normalized)->exists();
    }

    public function transferByUsername(int $senderId, string $recipientUsername, string $amount, string $idempotencyKey, ?string $note = null): array
    {
        $normalized = strtolower(trim($recipientUsername));
        $value = $this->decimalAmount($amount);
        $key = trim($idempotencyKey);
        if ($key === '' || strlen($key) > 120) {
            throw new HttpException(422, 'A valid idempotency key is required.');
        }
        $normalizedNote = trim((string) $note);
        $normalizedNote = $normalizedNote === '' ? null : $normalizedNote;

        return DB::transaction(function () use ($senderId, $normalized, $value, $key, $normalizedNote) {
            $existing = DB::table('brc_transfers')->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->sender_user_id !== $senderId) {
                    throw new HttpException(409, 'This transfer request key has already been used.');
                }

                $recipient = DB::table('brc_usernames')->where('normalized_username', $normalized)->first();
                if (!$recipient
                    || (int) $existing->recipient_user_id !== (int) $recipient->user_id
                    || BrcAmount::toUnits((string) $existing->amount) !== BrcAmount::toUnits($value)
                    || trim((string) ($existing->note ?? '')) !== (string) ($normalizedNote ?? '')) {
                    throw new HttpException(409, 'This transfer request key was already used for a different transfer.');
                }

                return $this->transferPayload($existing);
            }

            $recipient = DB::table('brc_usernames')->where('normalized_username', $normalized)->first();
            if (!$recipient) {
                throw new HttpException(404, 'Recipient BRC username was not found.');
            }
            if ((int) $recipient->user_id === $senderId) {
                throw new HttpException(422, 'You cannot transfer BRC to yourself.');
            }

            $valueUnits = BrcAmount::toUnits($value);
            if ($valueUnits < self::MIN_BRC_TRANSFER_UNITS) {
                throw new HttpException(422, 'Minimum BRC transfer is 0.00000050. Amounts must use 0.00000050 BRC steps for an exact 2% fee.');
            }
            if ($valueUnits % 50 !== 0) {
                throw new HttpException(422, 'BRC transfer amounts must use 0.00000050 BRC steps for an exact 2% fee.');
            }
            $feeUnits = intdiv($valueUnits, 50);
            $totalDebitUnits = $valueUnits + $feeUnits;

            $treasuryUsername = strtolower(trim((string) $this->settings->get('brc_transfer_treasury_username', '')));
            $treasury = $treasuryUsername !== ''
                ? DB::table('brc_usernames')->where('normalized_username', $treasuryUsername)->first()
                : null;
            $treasuryUserId = $treasury ? (int) $treasury->user_id : null;
            $partyIds = array_values(array_unique(array_filter([
                $senderId,
                (int) $recipient->user_id,
                $treasuryUserId,
            ], function ($userId) {
                return $userId !== null && $userId > 0;
            })));
            sort($partyIds, SORT_NUMERIC);

            $accounts = collect();
            if (Schema::hasTable('res_users')) {
                $accountQuery = DB::table('res_users')
                    ->whereIn('id', $partyIds)
                    ->orderBy('id')
                    ->lockForUpdate();
                $accountColumns = Schema::hasColumn('res_users', 'is_active')
                    ? ['id', 'is_active']
                    : ['id'];
                $accounts = $accountQuery->get($accountColumns)->keyBy('id');
            }

            if (Schema::hasColumn('res_users', 'is_active')) {
                foreach ([$senderId, (int) $recipient->user_id] as $participantId) {
                    $account = $accounts->get($participantId);
                    if (!$account || (int) $account->is_active !== 1) {
                        throw new HttpException(403, 'Active Blockrium accounts are required to send or receive BRC.');
                    }
                }
            }

            $memberships = DB::table('verified_memberships')
                ->whereIn('user_id', $partyIds)
                ->orderBy('user_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            if (!$this->isVerifiedFromMembership($memberships->get($senderId))) {
                throw new HttpException(403, 'Only verified Blockrium users can use this feature.');
            }
            if (!$this->isVerifiedFromMembership($memberships->get((int) $recipient->user_id))) {
                throw new HttpException(403, 'Only verified Blockrium users can use this feature.');
            }

            $treasuryMembership = $treasuryUserId !== null ? $memberships->get($treasuryUserId) : null;
            if (!$treasuryMembership
                || $treasuryMembership->kyc_status !== 'approved'
                || $treasuryMembership->payment_status !== 'paid'
                || $treasuryMembership->suspended_at !== null) {
                throw new HttpException(503, 'BRC transfer fee wallet is not configured as an active verified account.');
            }

            if ($treasuryUserId === $senderId || $treasuryUserId === (int) $recipient->user_id) {
                throw new HttpException(503, 'BRC transfer fee wallet must be different from the sender and recipient.');
            }

            $senderWallet = DB::table('tbluserwallet')
                ->where('userid', $senderId)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            if (!$senderWallet || BrcAmount::toUnits($senderWallet->totaltoken) < $totalDebitUnits) {
                throw new HttpException(422, 'Insufficient BRC balance.');
            }

            $fee = BrcAmount::fromUnits($feeUnits);
            $totalDebit = BrcAmount::fromUnits($totalDebitUnits);
            $newBalance = BrcAmount::fromUnits(BrcAmount::toUnits($senderWallet->totaltoken) - $totalDebitUnits);
            $walletUpdate = ['totaltoken' => $newBalance];
            if (Schema::hasColumn('tbluserwallet', 'updated_at')) {
                $walletUpdate['updated_at'] = now();
            }
            DB::table('tbluserwallet')->where('id', $senderWallet->id)->update($walletUpdate);
            $this->wallets->credit((int) $recipient->user_id, $value);
            if ($treasuryUserId !== null) {
                $this->wallets->credit($treasuryUserId, BrcAmount::fromUnits($feeUnits));
            }

            $transfer = [
                'sender_user_id' => $senderId,
                'recipient_user_id' => (int) $recipient->user_id,
                'amount' => $value,
                'fee_amount' => $fee,
                'total_debit' => $totalDebit,
                'fee_rate_basis_points' => 200,
                'status' => 'completed',
                'reference' => 'BRC-' . strtoupper(Str::random(20)),
                'idempotency_key' => $key,
                'note' => $normalizedNote,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('brc_transfers', 'fee_wallet_user_id')) {
                $transfer['fee_wallet_user_id'] = $treasuryUserId;
            } elseif ($treasuryUserId !== null) {
                throw new HttpException(503, 'BRC transfer fee accounting storage is not installed.');
            }
            $id = DB::table('brc_transfers')->insertGetId($transfer);
            $transfer['id'] = $id;

            $this->notifyTransfer($senderId, (int) $recipient->user_id, $value);

            return $this->transferPayload((object) $transfer, $newBalance);
        });
    }

    public function transfers(int $userId, int $limit = 25)
    {
        return DB::table('brc_transfers as t')
            ->leftJoin('brc_usernames as sender', 'sender.user_id', '=', 't.sender_user_id')
            ->leftJoin('brc_usernames as recipient', 'recipient.user_id', '=', 't.recipient_user_id')
            ->where(function ($query) use ($userId) {
                $query->where('t.sender_user_id', $userId)->orWhere('t.recipient_user_id', $userId);
            })
            ->orderByDesc('t.id')
            ->limit($limit)
            ->get(['t.id', 't.amount', 't.fee_amount', 't.total_debit', 't.fee_rate_basis_points', 't.status', 't.reference', 't.note', 't.created_at', 't.sender_user_id', 't.recipient_user_id', 'sender.username as sender_username', 'recipient.username as recipient_username'])
            ->map(function ($transfer) use ($userId) {
                $transfer->direction = (int) $transfer->sender_user_id === $userId ? 'sent' : 'received';
                return $transfer;
            });
    }

    public function pendingForAdmin(array $filters = [])
    {
        $query = DB::table('verified_memberships as m')
            ->leftJoin('res_users as u', 'u.id', '=', 'm.user_id')
            ->select(['m.*', 'u.name', 'u.email', 'u.username as legacy_username']);

        $hasPlayPurchases = Schema::hasTable('google_play_purchases');
        if ($hasPlayPurchases) {
            $latestKycPurchase = DB::table('google_play_purchases')
                ->selectRaw('user_id, MAX(id) as latest_purchase_id')
                ->where('purpose', 'kyc')
                ->whereNotNull('google_order_id')
                ->groupBy('user_id');
            $query->leftJoinSub($latestKycPurchase, 'latest_kyc_play', function ($join) {
                $join->on('latest_kyc_play.user_id', '=', 'm.user_id');
            })->leftJoin('google_play_purchases as kyc_play', 'kyc_play.id', '=', 'latest_kyc_play.latest_purchase_id')
                ->addSelect([
                    'kyc_play.google_order_id as google_play_order_id',
                    'kyc_play.status as google_play_purchase_status',
                    'kyc_play.acknowledgement_state as google_play_acknowledgement_state',
                ]);
        } else {
            $query->addSelect(DB::raw('NULL as google_play_order_id'))
                ->addSelect(DB::raw('NULL as google_play_purchase_status'))
                ->addSelect(DB::raw('NULL as google_play_acknowledgement_state'));
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'payment_pending') {
                $query->where('m.kyc_status', 'approved')->where(function ($payment) {
                    $payment->where('m.payment_status', '!=', 'paid')->orWhereNull('m.payment_status');
                });
            } elseif ($filters['status'] === 'access_revoked') {
                $query->whereNotNull('m.suspended_at');
            } elseif ($filters['status'] === 'verified') {
                $query->where('m.kyc_status', 'approved')->where('m.payment_status', 'paid')->whereNull('m.suspended_at');
            } elseif ($filters['status'] === 'in_progress') {
                $this->whereInProgressKyc($query, 'm.kyc_status');
            } else {
                $query->where('m.kyc_status', $filters['status']);
            }
        }

        if (!empty($filters['payment_status'])) {
            $query->where('m.payment_status', $filters['payment_status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($builder) use ($search, $hasPlayPurchases) {
                $builder->where('u.name', 'like', $search)
                    ->orWhere('u.email', 'like', $search)
                    ->orWhere('u.username', 'like', $search)
                    ->orWhere('m.didit_session_id', 'like', $search)
                    ->orWhere('m.payment_reference', 'like', $search);
                if ($hasPlayPurchases) {
                    $builder->orWhere('kyc_play.google_order_id', 'like', $search);
                }
            });
        }

        return $query
            ->orderByRaw("CASE m.kyc_status WHEN 'approved' THEN 1 WHEN 'in_review' THEN 2 WHEN 'submitted' THEN 3 WHEN 'started' THEN 4 WHEN 'rejected' THEN 5 WHEN 'revoked' THEN 6 WHEN 'country_limit' THEN 7 WHEN 'not_started' THEN 8 ELSE 9 END")
            ->orderByDesc('m.updated_at')
            ->paginate(50)
            ->withQueryString();
    }

    public function adminSummary(): array
    {
        $base = DB::table('verified_memberships');

        return [
            'total' => (clone $base)->count(),
            'not_started' => (clone $base)->where('kyc_status', 'not_started')->count(),
            'in_progress' => $this->whereInProgressKyc(clone $base, 'kyc_status')->count(),
            'kyc_approved' => (clone $base)->where('kyc_status', 'approved')->count(),
            'payment_pending' => (clone $base)->where('kyc_status', 'approved')->where(function ($payment) {
                $payment->where('payment_status', '!=', 'paid')->orWhereNull('payment_status');
            })->count(),
            'verified' => (clone $base)->where('kyc_status', 'approved')->where('payment_status', 'paid')->whereNull('suspended_at')->count(),
            'rejected' => (clone $base)->whereIn('kyc_status', ['rejected', 'country_limit'])->count(),
            'waitlisted' => Schema::hasTable('kyc_waitlist_entries')
                ? DB::table('kyc_waitlist_entries')->where('status', 'waiting')->count()
                : 0,
        ];
    }

    private function whereInProgressKyc($query, string $column)
    {
        return $query->whereIn(DB::raw('LOWER(TRIM(' . $column . '))'), self::IN_PROGRESS_KYC_STATUSES);
    }

    private function setting(string $key, $fallback = null)
    {
        return $this->settings->get($key, $fallback);
    }

    private function waitlistEnabled(): bool
    {
        return filter_var($this->setting('kyc_waitlist_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function waitlistPayload(int $userId): array
    {
        if (!Schema::hasTable('kyc_waitlist_entries')) {
            return [
                'enabled' => false,
                'status' => null,
                'country_code' => null,
                'country_name' => null,
                'position' => null,
                'country_waiting_count' => 0,
                'country_active_count' => 0,
                'joined_at' => null,
                'message' => null,
            ];
        }

        $entry = $this->waitlistRow($userId);
        $position = null;
        $waitingCount = 0;
        $activeCount = 0;
        if ($entry) {
            $waitingCount = DB::table('kyc_waitlist_entries')
                ->where('country_code', $entry->country_code)
                ->where('status', 'waiting')
                ->count();
            $activeCount = DB::table('kyc_waitlist_entries')
                ->where('country_code', $entry->country_code)
                ->whereIn('status', ['waiting', 'invited'])
                ->count();

            if ($entry->status === 'waiting') {
                $position = DB::table('kyc_waitlist_entries')
                    ->where('country_code', $entry->country_code)
                    ->where('status', 'waiting')
                    ->where(function ($query) use ($entry) {
                        $query->where('joined_at', '<', $entry->joined_at)
                            ->orWhere(function ($sameTime) use ($entry) {
                                $sameTime->where('joined_at', $entry->joined_at)
                                    ->where('id', '<=', $entry->id);
                            });
                    })
                    ->count();
            }
        }

        return [
            'enabled' => $this->waitlistEnabled(),
            'status' => $entry ? $entry->status : null,
            'country_code' => $entry ? $entry->country_code : null,
            'country_name' => $entry ? $entry->country_name : null,
            'position' => $position,
            'country_waiting_count' => $waitingCount,
            'country_active_count' => $activeCount,
            'joined_at' => $entry ? $entry->joined_at : null,
            'invited_at' => $entry ? $entry->invited_at : null,
            'started_at' => $entry ? $entry->started_at : null,
            'message' => $this->setting('kyc_waitlist_message', 'Join the KYC waiting list to reserve your place for the next country batch.'),
        ];
    }

    private function benefitDetails(bool $verified, float $kycBoostPercent): array
    {
        $availability = $verified ? 'active' : 'after_kyc_and_payment';

        return [
            [
                'key' => 'verified_badge',
                'label' => 'Verified Blockrium member badge',
                'status' => $verified ? 'active' : 'locked',
                'available_after' => $availability,
            ],
            [
                'key' => 'mining_boost',
                'label' => $kycBoostPercent . '% mining boost',
                'boost_percent' => $kycBoostPercent,
                'status' => $verified && $kycBoostPercent > 0 ? 'active' : 'locked',
                'available_after' => $availability,
            ],
            [
                'key' => 'brc_username_transfers',
                'label' => 'BRC username transfers',
                'status' => $verified ? 'active' : 'locked',
                'available_after' => $availability,
            ],
            [
                'key' => 'wallet_early_access',
                'label' => 'Blockrium wallet early access',
                'status' => $verified ? 'active' : 'locked',
                'available_after' => $availability,
            ],
            [
                'key' => 'presale_access',
                'label' => 'Presale access and discounts when available',
                'status' => $verified ? 'active' : 'locked',
                'available_after' => $availability,
            ],
        ];
    }

    private function waitlistRow(int $userId)
    {
        if (!Schema::hasTable('kyc_waitlist_entries')) {
            return null;
        }

        return DB::table('kyc_waitlist_entries')->where('user_id', $userId)->first();
    }

    private function countryMemberLimit(string $countryCode): int
    {
        $default = max(1, (int) $this->setting('didit_country_limit', config('services.didit.country_limit', 100)));
        if (!Schema::hasTable('kyc_country_limits')) {
            return $default;
        }

        try {
            $columns = Schema::getColumnListing('kyc_country_limits');
            if (!in_array('country_code', $columns, true)) {
                return $default;
            }

            $limitColumn = null;
            foreach (['member_limit', 'maximum_members', 'max_members', 'limit'] as $candidate) {
                if (in_array($candidate, $columns, true)) {
                    $limitColumn = $candidate;
                    break;
                }
            }
            if (!$limitColumn) {
                return $default;
            }

            $value = DB::table('kyc_country_limits')
                ->where('country_code', strtoupper($countryCode))
                ->value($limitColumn);

            return $value !== null ? max(1, (int) $value) : $default;
        } catch (\Throwable $error) {
            return $default;
        }
    }

    private function normalizeCountryCode(string $countryCode): string
    {
        $countryCode = strtoupper(trim($countryCode));
        if (!preg_match('/^[A-Z]{3}$/', $countryCode)) {
            throw new HttpException(422, 'Country code must be a three-letter ISO code.');
        }

        return $countryCode;
    }

    private function syncWaitlistAfterKyc(int $userId, string $kycStatus): void
    {
        if (!Schema::hasTable('kyc_waitlist_entries')) {
            return;
        }

        $entry = $this->waitlistRow($userId);
        if (!$entry) {
            return;
        }

        if ($kycStatus === 'approved') {
            DB::table('kyc_waitlist_entries')->where('id', $entry->id)->update([
                'status' => 'converted',
                'converted_at' => $entry->converted_at ?: now(),
                'updated_at' => now(),
            ]);
        } elseif (in_array($kycStatus, ['rejected', 'country_limit'], true)) {
            DB::table('kyc_waitlist_entries')->where('id', $entry->id)->update([
                'status' => 'waiting',
                'invited_at' => null,
                'started_at' => null,
                'converted_at' => null,
                'updated_at' => now(),
            ]);
        }
    }

    private function notifyWaitlistInvitation(int $userId): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        $payload = [
            'type' => 'kyc_waitlist_invitation',
            'title' => 'KYC verification is available',
            'action' => 'verification',
            'data' => json_encode([
                'message' => 'Your country KYC batch is open. Start verification from the Blockrium app.',
                'action' => 'verification',
            ]),
            'notification_type' => 'KYC_WAITLIST_INVITATION',
            'status' => '1',
            'n_type' => '1',
            'notifiable_type' => 'App\\Models\\ResUser',
            'notifiable_id' => $userId,
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('notifications')->insert(array_intersect_key($payload, array_flip(Schema::getColumnListing('notifications'))));

        // Keep the in-app inbox entry, but also notify invited users who have
        // already registered a push token from the mobile app.
        if (Schema::hasTable('res_users') && Schema::hasColumn('res_users', 'device_id')) {
            $deviceToken = DB::table('res_users')
                ->where('id', $userId)
                ->value('device_id');

            if (!empty($deviceToken)) {
                SendPushNotificationJob::dispatch(
                    $deviceToken,
                    $userId,
                    'KYC verification is available',
                    'Your country KYC batch is open. Start identity verification from the Blockrium app.',
                    [
                        'type' => 'KYC_WAITLIST_INVITATION',
                        'notification_type' => 'KYC_WAITLIST_INVITATION',
                        'action' => 'verification',
                        'route' => 'VerifiedMember',
                    ]
                );
            }
        }
    }

    private function diditCountry(array $payload): ?string
    {
        $verifications = $payload['decision']['id_verifications'] ?? [];
        foreach ((array) $verifications as $verification) {
            $country = strtoupper(trim((string) ($verification['issuing_state'] ?? '')));
            if (preg_match('/^[A-Z]{3}$/', $country)) {
                return $country;
            }

            $walletCountry = strtoupper(trim((string) ($verification['wallet_verification']['issuing_country'] ?? '')));
            if (preg_match('/^[A-Z]{3}$/', $walletCountry)) {
                return $walletCountry;
            }
        }

        return null;
    }

    private function countryLimitReached(string $country, int $currentMembershipId): bool
    {
        return DB::table('verified_memberships')
            ->where('kyc_status', 'approved')
            ->where('kyc_country', $country)
            ->where('id', '!=', $currentMembershipId)
            ->count() >= (int) $this->setting('didit_country_limit', config('services.didit.country_limit', 100));
    }

    private function latestVerificationPayment(int $userId)
    {
        if (!Schema::hasTable('verification_payments')) {
            return null;
        }

        return DB::table('verification_payments')->where('user_id', $userId)->orderByDesc('id')->first();
    }

    private function nextAction($membership, $payment, int $userId): array
    {
        if ($membership && $membership->suspended_at) {
            return ['key' => 'access_revoked', 'label' => 'Access is disabled. Contact Blockrium support.', 'url' => null];
        }
        if ($this->isVerifiedFromMembership($membership)) {
            return ['key' => 'verified', 'label' => 'Verification complete', 'url' => null];
        }
        if (!$membership) {
            return ['key' => 'join_waitlist', 'label' => 'Join the KYC waiting list', 'url' => null];
        }
        if ($membership->payment_status === 'paid' && $membership->kyc_status !== 'approved') {
            return ['key' => 'kyc_review_pending', 'label' => 'KYC review is pending', 'url' => null];
        }
        if ($membership->kyc_status === 'approved') {
            if ($payment && in_array($payment->status, ['pending', 'processing'], true)) {
                $timeout = max(5, (int) $this->setting('verification_support_timeout_minutes', 30));
                $aged = $payment->created_at && strtotime((string) $payment->created_at) <= time() - ($timeout * 60);
                return [
                    'key' => $aged ? 'payment_support' : 'payment_processing',
                    'label' => $aged ? 'Payment support is available' : 'Payment confirmation is processing',
                    'url' => $payment->checkout_url ?: null,
                ];
            }
            return ['key' => 'payment_required', 'label' => 'Activate Blockrium Verified membership', 'url' => null];
        }

        $waitlist = $this->waitlistRow($userId);
        if (in_array($membership->kyc_status, ['started', 'submitted', 'in_review'], true) || ($waitlist && in_array($waitlist->status, ['invited', 'started'], true))) {
            return ['key' => 'complete_kyc', 'label' => 'Complete identity verification', 'url' => $membership->didit_session_url ?: null];
        }
        if ($waitlist && $waitlist->status === 'waiting') {
            return ['key' => 'waitlist', 'label' => 'Waiting for a KYC invitation', 'url' => null];
        }
        return ['key' => 'join_waitlist', 'label' => 'Join the KYC waiting list', 'url' => null];
    }

    private function paymentPayload($payment, array $nextAction): ?array
    {
        if (!$payment) {
            return null;
        }

        $timeout = max(5, (int) $this->setting('verification_support_timeout_minutes', 30));
        $supportEligible = in_array($payment->status, ['pending', 'processing'], true)
            && $payment->created_at
            && strtotime((string) $payment->created_at) <= time() - ($timeout * 60);

        return [
            'id' => (int) $payment->id,
            'provider' => $payment->provider,
            'status' => $payment->status,
            'amount' => $payment->amount !== null ? (float) $payment->amount : null,
            'currency' => $payment->currency,
            'checkout_url' => $payment->status === 'paid' ? null : $payment->checkout_url,
            'provider_payment_id' => $payment->provider_payment_id,
            'created_at' => $payment->created_at,
            'paid_at' => $payment->paid_at,
            'support_eligible' => (bool) $supportEligible,
            'next_action' => $nextAction['key'],
        ];
    }

    private function paymentSupportSummary(int $userId): array
    {
        if (!Schema::hasTable('verification_support_cases')) {
            return ['available' => false, 'open_count' => 0, 'latest' => null];
        }

        $latest = DB::table('verification_support_cases')->where('user_id', $userId)->orderByDesc('id')->first();
        return [
            'available' => true,
            'open_count' => DB::table('verification_support_cases')->where('user_id', $userId)->whereIn('status', ['open', 'in_review'])->count(),
            'latest' => $latest ? $this->supportCasePayload($latest) : null,
        ];
    }

    private function supportCasePayload($case): array
    {
        return [
            'id' => (int) $case->id,
            'user_id' => (int) $case->user_id,
            'type' => $case->type,
            'status' => $case->status,
            'provider' => $case->provider,
            'provider_reference' => $case->provider_reference,
            'amount' => $case->amount !== null ? (float) $case->amount : null,
            'currency' => $case->currency,
            'message' => $case->user_message,
            'created_at' => $case->created_at,
            'resolved_at' => $case->resolved_at,
        ];
    }

    private function membership(int $userId)
    {
        return DB::table('verified_memberships')->where('user_id', $userId)->first();
    }

    private function ensureMembership(int $userId)
    {
        if (!Schema::hasTable('verified_memberships')) {
            throw new RuntimeException('Verified membership tables are not installed.');
        }
        $membership = $this->membership($userId);
        if ($membership) {
            return $membership;
        }

        $id = DB::table('verified_memberships')->insertGetId([
            'user_id' => $userId,
            'payment_amount' => (float) $this->setting('dodo_verification_amount', config('services.dodo.verification_amount', 5)),
            'payment_currency' => $this->setting('dodo_verification_currency', config('services.dodo.verification_currency', 'USD')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return DB::table('verified_memberships')->where('id', $id)->first();
    }

    private function isVerifiedFromMembership($membership): bool
    {
        return $membership && $membership->kyc_status === 'approved'
            && $membership->payment_status === 'paid'
            && !$membership->suspended_at;
    }

    private function requireVerified(int $userId): void
    {
        if (!$this->isVerifiedFromMembership($this->membership($userId))) {
            throw new HttpException(403, 'Only verified Blockrium users can use this feature.');
        }
    }

    private function decimalAmount(string $amount): string
    {
        try {
            $units = BrcAmount::toUnits(trim($amount));
        } catch (\InvalidArgumentException $exception) {
            throw new HttpException(422, 'Transfer amount must be a positive BRC amount with up to 8 decimals.');
        }
        if ($units <= 0 || $units > BrcAmount::toUnits('1000000')) {
            throw new HttpException(422, 'Transfer amount must be greater than zero and no more than 1,000,000 BRC.');
        }
        return BrcAmount::fromUnits($units);
    }

    private function transferPayload($transfer, ?string $balance = null): array
    {
        $amount = BrcAmount::fromUnits(BrcAmount::toUnits($transfer->amount));
        $fee = isset($transfer->fee_amount)
            ? BrcAmount::fromUnits(BrcAmount::toUnits($transfer->fee_amount))
            : '0.00000000';
        return [
            'id' => (int) $transfer->id,
            'reference' => $transfer->reference,
            'amount' => $amount,
            'fee_amount' => $fee,
            'total_debit' => isset($transfer->total_debit)
                ? BrcAmount::fromUnits(BrcAmount::toUnits($transfer->total_debit))
                : BrcAmount::fromUnits(BrcAmount::toUnits($amount) + BrcAmount::toUnits($fee)),
            'fee_rate_basis_points' => isset($transfer->fee_rate_basis_points) ? (int) $transfer->fee_rate_basis_points : 200,
            'status' => $transfer->status,
            'balance' => $balance,
            'created_at' => $transfer->created_at,
        ];
    }

    private function brcTransferBalance(int $userId): string
    {
        if (!Schema::hasTable('tbluserwallet')) {
            return '0.00000000';
        }

        $balance = DB::table('tbluserwallet')
            ->where('userid', $userId)
            ->orderByDesc('id')
            ->value('totaltoken');

        return BrcAmount::fromUnits(BrcAmount::toUnits($balance ?? '0'));
    }

    private function notifyTransfer(int $senderId, int $recipientId, string $amount): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        $base = [
            'type' => 'brc_transfer',
            'notifiable_type' => 'App\\Models\\ResUser',
            'data' => json_encode(['amount' => $amount, 'sender_id' => $senderId, 'recipient_id' => $recipientId]),
            'notification_type' => 'brc_transfer',
            'status' => '1',
            'n_type' => '1',
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        foreach ([$recipientId, $senderId] as $userId) {
            $payload = $base;
            $payload['notifiable_id'] = $userId;
            $payload['data'] = json_encode(['amount' => $amount, 'sender_id' => $senderId, 'recipient_id' => $recipientId, 'message' => $userId === $recipientId ? 'BRC received' : 'BRC sent']);
            $payload = array_intersect_key($payload, array_flip(Schema::getColumnListing('notifications')));
            DB::table('notifications')->insert($payload);
        }
    }
}
