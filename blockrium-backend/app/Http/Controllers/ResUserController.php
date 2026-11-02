<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Auth;
use Validator;
use Illuminate\Http\Request;
use App\Models\ResUser;
use App\Models\ResUserModel;
use App\Models\Referral;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Config;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Models\ButtonClick;
use App\Jobs\SendPushNotificationJob;
use App\Notifications\RefferalNotification;
use DateTime;
use Mail;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Services\GamificationService;
use App\Services\WalletRewardService;
use App\Services\VerifiedMemberService;
use App\Services\DodoBoostService;
use App\Services\IntegrationSettingsService;
class ResUserController extends Controller
{
    private $dodoBoostService;
    private $integrationSettings;

    private const MINING_REWARD = 1.5;
    private const ATTENDANCE_REWARD = 1.0;
    private const AD_VIDEO_REWARD = 1.0;
    private const BOOSTER_REWARD = 1.5;
    private const MINING_WINDOW_HOURS = 6;
    private const DAILY_STREAK_MAX_DAYS = 7;
    private const BASE_MINING_RATE = 1.0;
    private const REFERRAL_TEAM_BOOST_PER_ACTIVE_USER = 1.0;
    private const REFERRAL_TEAM_BOOST_CAP = 10.0;
    private const REFERRAL_PING_DAILY_LIMIT = 3;
    private const ONBOARDING_QUEST_REWARD = 5.0;
    private const REWARD_CALENDAR_DAYS = 30;
    private const STREAK_CHEST_DAY = 7;
    private const STREAK_CHEST_REWARD = 5.0;
    private const REFERRAL_PING_TYPES = [
        'mine_reminder' => [
            'label' => 'Start mining again',
            'title' => 'Mining reminder',
            'body' => '%s pinged you to start your Blockrium mining session.',
        ],
        'streak_reminder' => [
            'label' => 'Claim today streak',
            'title' => 'Streak reminder',
            'body' => '%s pinged you to claim today\'s Blockrium streak.',
        ],
        'task_reminder' => [
            'label' => 'Complete a task',
            'title' => 'Task reminder',
            'body' => '%s pinged you to complete a Blockrium reward task.',
        ],
        'team_boost' => [
            'label' => 'Join team boost',
            'title' => 'Team boost reminder',
            'body' => '%s pinged you to help unlock your team mining boost.',
        ],
    ];

    public function __construct(
        ResUserModel $ResUserModel,
        GamificationService $gamificationService,
        WalletRewardService $walletRewardService,
        VerifiedMemberService $verifiedMemberService,
        DodoBoostService $dodoBoostService,
        IntegrationSettingsService $integrationSettings
    ) {
        Config::set('jwt.user', 'App\Models\ResUser'); 
        Config::set('auth.providers.users.model', \App\Models\ResUser::class);
        $this->guard = "api_res_user";
        $this->ResUserModel = $ResUserModel;
        $this->gamificationService = $gamificationService;
        $this->walletRewardService = $walletRewardService;
        $this->verifiedMemberService = $verifiedMemberService;
        $this->dodoBoostService = $dodoBoostService;
        $this->integrationSettings = $integrationSettings;
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
        //$this->middleware('auth:api', ['except' => ['login', 'register', 'logout', 'refresh', 'userProfile', 'addCredit', 'userProfileUpdate']]);
    }

    private function hasClaimedToday($date): bool
    {
        if (!$date) {
            return false;
        }

        return Carbon::parse($date)->isSameDay(Carbon::now());
    }

    private function creditWallet(int $userId, float $amount)
    {
        return $this->walletRewardService->credit($userId, $amount);
    }

    private function withExistingTimestamps(string $table, array $payload, $now = null, bool $includeCreatedAt = false, bool $includeUpdatedAt = true): array
    {
        $timestamp = $now ?: now();

        if ($includeCreatedAt && Schema::hasColumn($table, 'created_at')) {
            $payload['created_at'] = $timestamp;
        }

        if ($includeUpdatedAt && Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = $timestamp;
        }

        return $payload;
    }

    private function miningPayload($record, ?Carbon $now = null, ?int $userId = null): array
    {
        $now = $now ?: Carbon::now();
        $ratePayload = $userId ? $this->currentMiningRatePayload($userId) : [
            'base_mining_reward' => self::MINING_REWARD,
            'base_mining_rate' => self::BASE_MINING_RATE,
            'mining_rate' => self::MINING_REWARD,
            'mining_reward' => self::MINING_REWARD,
            'reward' => self::MINING_REWARD,
            'paid_boost_percent' => 0,
            'kyc_boost_percent' => 0,
            'kyc_boost_active' => false,
            'team_boost_percent' => 0,
            'boost_percent' => 0,
            'active_paid_boost' => null,
            'network_stats' => null,
        ];

        if (!$record) {
            return array_merge([
                'Mining_Details' => null,
                'button_status' => 0,
                'can_mine' => true,
                'seconds_remaining' => 0,
                'nextMining' => null,
                'nextMiningTimestamp' => null,
                'server_time' => $now->toDateTimeString(),
                'serverTimestamp' => $now->timestamp * 1000,
                'mining_window_hours' => self::MINING_WINDOW_HOURS,
            ], $ratePayload);
        }

        $startedAt = $record->rewards_start_time ?? $record->created_at ?? null;
        $startedAtCarbon = $startedAt ? Carbon::parse($startedAt) : $now->copy()->subHours(self::MINING_WINDOW_HOURS);
        $nextMining = $startedAtCarbon->copy()->addHours(self::MINING_WINDOW_HOURS);
        $secondsRemaining = max(0, $now->diffInSeconds($nextMining, false));
        $canMine = $secondsRemaining <= 0;

        return array_merge([
            'Mining_Details' => $record,
            'button_status' => $canMine ? 0 : 1,
            'can_mine' => $canMine,
            'seconds_remaining' => $secondsRemaining,
            'nextMining' => $canMine ? null : $nextMining->toDateTimeString(),
            'nextMiningTimestamp' => $canMine ? null : $nextMining->timestamp * 1000,
            'mining_start_time' => $startedAtCarbon->toDateTimeString(),
            'rewards_start_time' => $startedAtCarbon->toDateTimeString(),
            'server_time' => $now->toDateTimeString(),
            'serverTimestamp' => $now->timestamp * 1000,
            'mining_window_hours' => self::MINING_WINDOW_HOURS,
        ], $ratePayload);
    }

    private function fastMiningRatePayload(int $userId, ?Carbon $now = null): array
    {
        $now = $now ?: Carbon::now();
        $paidBoost = $this->activePaidBoost($userId, $now);
        $paidBoostPercent = $paidBoost ? (float) $paidBoost->boost_percent : 0.0;
        $kycBoostPercent = $this->verifiedMemberService->verifiedMiningBoostPercent($userId);
        $totalBoostPercent = $paidBoostPercent + $kycBoostPercent;
        $reward = round(self::MINING_REWARD * (1 + ($totalBoostPercent / 100)), 4);

        return [
            'base_mining_reward' => self::MINING_REWARD,
            'base_mining_rate' => self::BASE_MINING_RATE,
            'mining_rate' => $reward,
            'mining_reward' => $reward,
            'reward' => $reward,
            'paid_boost_percent' => $paidBoostPercent,
            'kyc_boost_percent' => $kycBoostPercent,
            'kyc_boost_active' => $kycBoostPercent > 0,
            'team_boost_percent' => 0,
            'boost_percent' => $totalBoostPercent,
            'active_paid_boost' => $paidBoost ? [
                'id' => (int) $paidBoost->id,
                'plan_id' => (int) $paidBoost->boost_plan_id,
                'plan_name' => $paidBoost->plan_name,
                'boost_percent' => (float) $paidBoost->boost_percent,
                'starts_at' => $paidBoost->starts_at,
                'ends_at' => $paidBoost->ends_at,
            ] : null,
            'network_stats' => null,
        ];
    }

    private function fastMiningPayload($record, Carbon $now, int $userId): array
    {
        $ratePayload = $this->fastMiningRatePayload($userId, $now);

        if (!$record) {
            return array_merge([
                'Mining_Details' => null,
                'button_status' => 0,
                'can_mine' => true,
                'seconds_remaining' => 0,
                'nextMining' => null,
                'nextMiningTimestamp' => null,
                'server_time' => $now->toDateTimeString(),
                'serverTimestamp' => $now->timestamp * 1000,
                'mining_window_hours' => self::MINING_WINDOW_HOURS,
            ], $ratePayload);
        }

        $startedAt = $record->rewards_start_time ?? $record->created_at ?? null;
        $startedAtCarbon = $startedAt ? Carbon::parse($startedAt) : $now->copy()->subHours(self::MINING_WINDOW_HOURS);
        $nextMining = $startedAtCarbon->copy()->addHours(self::MINING_WINDOW_HOURS);
        $secondsRemaining = max(0, $now->diffInSeconds($nextMining, false));
        $canMine = $secondsRemaining <= 0;

        return array_merge([
            'Mining_Details' => $record,
            'button_status' => $canMine ? 0 : 1,
            'can_mine' => $canMine,
            'seconds_remaining' => $secondsRemaining,
            'nextMining' => $canMine ? null : $nextMining->toDateTimeString(),
            'nextMiningTimestamp' => $canMine ? null : $nextMining->timestamp * 1000,
            'mining_start_time' => $startedAtCarbon->toDateTimeString(),
            'rewards_start_time' => $startedAtCarbon->toDateTimeString(),
            'server_time' => $now->toDateTimeString(),
            'serverTimestamp' => $now->timestamp * 1000,
            'mining_window_hours' => self::MINING_WINDOW_HOURS,
        ], $ratePayload);
    }

    private function rewardStatusPayload($rewardRecord, ?Carbon $now = null): array
    {
        $now = $now ?: Carbon::now();
        $lastClaimedAt = $rewardRecord->updated_at ?? null;
        $claimedToday = $this->hasClaimedToday($lastClaimedAt);
        $streakDay = (int) ($rewardRecord->status ?? 0);

        if ($lastClaimedAt && !$claimedToday && !Carbon::parse($lastClaimedAt)->isSameDay($now->copy()->subDay())) {
            $streakDay = 0;
        }

        $streakDay = max(0, min($streakDay, self::DAILY_STREAK_MAX_DAYS));
        $nextClaimAt = $claimedToday ? $now->copy()->addDay()->startOfDay() : $now;

        return [
            'token' => (float) ($rewardRecord->token ?? 0),
            'status' => $streakDay,
            'streak_day' => $streakDay,
            'max_streak_days' => self::DAILY_STREAK_MAX_DAYS,
            'claimed_today' => $claimedToday,
            'can_claim' => !$claimedToday,
            'last_claimed_at' => $lastClaimedAt,
            'updated_at' => $lastClaimedAt,
            'next_claim_at' => $nextClaimAt->toDateTimeString(),
            'server_time' => $now->toDateTimeString(),
        ];
    }

    private function rewardCalendarDayReward(int $day): float
    {
        if ($day === 30) {
            return 10.0;
        }

        if ($day === 15) {
            return 3.0;
        }

        if ($day % self::STREAK_CHEST_DAY === 0) {
            return self::STREAK_CHEST_REWARD;
        }

        return self::ATTENDANCE_REWARD;
    }

    private function rewardCalendarPayload(int $userId, $rewardRecord = null, ?Carbon $now = null): array
    {
        $now = $now ?: Carbon::now();
        $rewardRecord = $rewardRecord ?: DB::table('tblreward')->where('userid', $userId)->first();
        $statusPayload = $this->rewardStatusPayload($rewardRecord, $now);
        $currentStreak = (int) $statusPayload['streak_day'];
        $claimedToday = (bool) $statusPayload['claimed_today'];
        $today = $now->toDateString();
        $chestClaim = Schema::hasTable('daily_reward_chest_claims')
            ? DB::table('daily_reward_chest_claims')
                ->where('user_id', $userId)
                ->where('claim_date', $today)
                ->first()
            : null;
        $chestUnlocked = $currentStreak >= self::STREAK_CHEST_DAY && $claimedToday;

        $days = [];
        for ($day = 1; $day <= self::REWARD_CALENDAR_DAYS; $day++) {
            $isChestDay = $day % self::STREAK_CHEST_DAY === 0 || $day === 30;
            $isCurrentCycleDay = $day <= self::STREAK_CHEST_DAY;
            $state = 'locked';

            if ($isCurrentCycleDay && $day <= $currentStreak) {
                $state = 'claimed';
            } elseif ($isCurrentCycleDay && $day === $currentStreak + 1 && !$claimedToday) {
                $state = 'ready';
            } elseif ($isCurrentCycleDay && $day === $currentStreak && $claimedToday) {
                $state = 'today';
            }

            $days[] = [
                'day' => $day,
                'title' => 'Day ' . $day,
                'reward_amount' => $this->rewardCalendarDayReward($day),
                'reward_label' => number_format($this->rewardCalendarDayReward($day), 2) . ' BRC',
                'is_chest_day' => $isChestDay,
                'state' => $state,
            ];
        }

        return [
            'status' => $statusPayload,
            'calendar_days' => $days,
            'current_streak_day' => $currentStreak,
            'max_streak_days' => self::STREAK_CHEST_DAY,
            'calendar_total_days' => self::REWARD_CALENDAR_DAYS,
            'chest' => [
                'day' => self::STREAK_CHEST_DAY,
                'reward_amount' => self::STREAK_CHEST_REWARD,
                'reward_label' => number_format(self::STREAK_CHEST_REWARD, 2) . ' BRC',
                'unlocked' => $chestUnlocked,
                'claimed' => (bool) $chestClaim,
                'can_claim' => $chestUnlocked && !$chestClaim,
                'claimed_at' => $chestClaim->claimed_at ?? null,
            ],
        ];
    }

    private function onboardingQuestSteps(int $userId): array
    {
        $user = DB::table('res_users')->where('id', $userId)->first();
        $referralCode = $user ? $this->ensureUserReferralCode($user) : null;
        $hasMined = Schema::hasTable('button_clicks')
            && DB::table('button_clicks')->where('user_id', $userId)->exists();
        $hasStreak = Schema::hasTable('tblreward')
            && DB::table('tblreward')->where('userid', $userId)->exists();
        $hasClaimedTask = Schema::hasTable('task_claimeds')
            && DB::table('task_claimeds')->where('user_id', $userId)->exists();

        return [
            [
                'key' => 'profile_ready',
                'title' => 'Create your miner profile',
                'description' => 'Your Blockrium account is ready.',
                'action' => 'profile',
                'completed' => (bool) $user,
            ],
            [
                'key' => 'start_mining',
                'title' => 'Start first mining cycle',
                'description' => 'Open Mining and start a cycle.',
                'action' => 'mining',
                'completed' => $hasMined,
            ],
            [
                'key' => 'claim_streak',
                'title' => 'Claim a daily streak',
                'description' => 'Collect your first daily streak reward.',
                'action' => 'streak',
                'completed' => $hasStreak,
            ],
            [
                'key' => 'complete_task',
                'title' => 'Complete one reward task',
                'description' => 'Finish any available task and claim it.',
                'action' => 'tasks',
                'completed' => $hasClaimedTask,
            ],
            [
                'key' => 'referral_ready',
                'title' => 'Get referral code ready',
                'description' => 'Open My Network and share your code.',
                'action' => 'referral',
                'completed' => !empty($referralCode),
            ],
        ];
    }

    private function onboardingQuestPayload(int $userId): array
    {
        $steps = $this->onboardingQuestSteps($userId);
        $completedCount = collect($steps)->filter(function ($step) {
            return (bool) $step['completed'];
        })->count();
        $totalSteps = count($steps);
        $claim = Schema::hasTable('onboarding_quest_claims')
            ? DB::table('onboarding_quest_claims')->where('user_id', $userId)->first()
            : null;
        $isCompleted = $completedCount >= $totalSteps;

        return [
            'title' => 'Starter Quest',
            'subtitle' => 'Complete the basics and claim your launch reward.',
            'reward_amount' => self::ONBOARDING_QUEST_REWARD,
            'reward_label' => number_format(self::ONBOARDING_QUEST_REWARD, 2) . ' BRC',
            'steps' => $steps,
            'completed_count' => $completedCount,
            'total_steps' => $totalSteps,
            'progress_percent' => $totalSteps > 0 ? round(($completedCount / $totalSteps) * 100) : 0,
            'is_completed' => $isCompleted,
            'claimed' => (bool) $claim,
            'claimed_at' => $claim->claimed_at ?? null,
            'can_claim' => $isCompleted && !$claim,
        ];
    }

    private function nextRewardStatus($rewardRecord, Carbon $now): int
    {
        if (!$rewardRecord || empty($rewardRecord->updated_at)) {
            return 1;
        }

        $lastClaimedAt = Carbon::parse($rewardRecord->updated_at);

        if ($lastClaimedAt->isSameDay($now->copy()->subDay())) {
            $currentStatus = max(0, (int) $rewardRecord->status);
            return $currentStatus >= self::DAILY_STREAK_MAX_DAYS ? 1 : $currentStatus + 1;
        }

        return 1;
    }

    private function activePaidBoost(int $userId, ?Carbon $now = null)
    {
        if (!Schema::hasTable('user_boosts')) {
            return null;
        }

        $now = $now ?: Carbon::now();

        return DB::table('user_boosts')
            ->leftJoin('boost_plans', 'user_boosts.boost_plan_id', '=', 'boost_plans.id')
            ->where('user_boosts.user_id', $userId)
            ->where('user_boosts.status', 'active')
            ->where('user_boosts.payment_status', 'paid')
            ->where(function ($query) use ($now) {
                $query->whereNull('user_boosts.starts_at')
                    ->orWhere('user_boosts.starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('user_boosts.ends_at')
                    ->orWhere('user_boosts.ends_at', '>=', $now);
            })
            ->select(
                'user_boosts.*',
                'boost_plans.name as plan_name',
                'boost_plans.duration_days as plan_duration_days'
            )
            ->orderByDesc('user_boosts.boost_percent')
            ->orderByDesc('user_boosts.ends_at')
            ->first();
    }

    private function teamNetworkSnapshot(int $userId, bool $includeMembers = true, string $filter = 'all'): array
    {
        $now = Carbon::now();
        $user = DB::table('res_users')->where('id', $userId)->first();
        $referralCode = $this->ensureUserReferralCode($user);
        $pingDailyLimit = $this->referralPingDailyLimit();

        $query = DB::table('referrals')
            ->leftJoin('res_users', 'referrals.user_id', '=', 'res_users.id')
            ->where(function ($where) use ($userId, $referralCode) {
                $where->where('referrals.referred_by', $userId);

                if (!empty($referralCode)) {
                    $where->orWhere('referrals.referred_by', $referralCode);
                }
            })
            ->select(
                'referrals.id as referral_id',
                'referrals.user_id',
                'referrals.referral_code',
                'referrals.referred_by',
                'referrals.rewards',
                'referrals.created_at as referred_at',
                'res_users.name',
                'res_users.username',
                'res_users.email',
                'res_users.is_active',
                'res_users.device_id'
            );

        $rows = $query->get();
        $referrals = [];
        $seenUserIds = [];

        foreach ($rows as $row) {
            $referralUserId = (int) $row->user_id;
            if ($referralUserId <= 0 || isset($seenUserIds[$referralUserId])) {
                continue;
            }

            $seenUserIds[$referralUserId] = true;
            $referrals[] = $row;
        }

        $referralUserIds = array_keys($seenUserIds);
        $lastMiningByUser = [];
        $pingsByUser = [];

        if (!empty($referralUserIds) && Schema::hasTable('button_clicks')) {
            $miningRows = DB::table('button_clicks')
                ->select('user_id', DB::raw('MAX(created_at) as last_mined_at'))
                ->whereIn('user_id', $referralUserIds)
                ->groupBy('user_id')
                ->get();

            foreach ($miningRows as $miningRow) {
                $lastMiningByUser[(int) $miningRow->user_id] = $miningRow->last_mined_at;
            }
        }

        if (!empty($referralUserIds) && Schema::hasTable('referral_pings')) {
            $pingRows = DB::table('referral_pings')
                ->select(
                    'recipient_user_id',
                    DB::raw('COUNT(*) as ping_count_today'),
                    DB::raw('MAX(created_at) as last_ping_at')
                )
                ->where('sender_user_id', $userId)
                ->whereIn('recipient_user_id', $referralUserIds)
                ->where('created_at', '>=', $now->copy()->startOfDay())
                ->groupBy('recipient_user_id')
                ->get();

            foreach ($pingRows as $pingRow) {
                $pingsByUser[(int) $pingRow->recipient_user_id] = $pingRow;
            }
        }

        $members = [];
        $activeTodayCount = 0;
        $inactiveCount = 0;
        $pingedTodayCount = 0;
        $totalReferralRewards = 0;

        foreach ($referrals as $row) {
            $referralUserId = (int) $row->user_id;
            $lastMinedAt = $lastMiningByUser[$referralUserId] ?? null;
            $lastMinedCarbon = $lastMinedAt ? Carbon::parse($lastMinedAt) : null;
            $activeToday = $lastMinedCarbon && $lastMinedCarbon->greaterThanOrEqualTo($now->copy()->subDay());
            $minedRecently = $lastMinedCarbon && $lastMinedCarbon->greaterThanOrEqualTo($now->copy()->subHours(self::MINING_WINDOW_HOURS));
            $pingStats = $pingsByUser[$referralUserId] ?? null;
            $pingCountToday = (int) ($pingStats->ping_count_today ?? 0);

            if ($activeToday) {
                $activeTodayCount++;
            } else {
                $inactiveCount++;
            }

            if ($pingCountToday > 0) {
                $pingedTodayCount++;
            }

            $totalReferralRewards += (float) ($row->rewards ?? 0);

            if ($includeMembers) {
                $members[] = [
                    'referral_id' => (int) $row->referral_id,
                    'user_id' => $referralUserId,
                    'name' => $row->name,
                    'username' => $row->username,
                    'email' => $row->email,
                    'is_active' => (int) ($row->is_active ?? 0),
                    'referred_at' => $row->referred_at,
                    'last_mined_at' => $lastMinedAt,
                    'active_today' => (bool) $activeToday,
                    'mined_recently' => (bool) $minedRecently,
                    'referral_rewards' => (float) ($row->rewards ?? 0),
                    'ping_count_today' => $pingCountToday,
                    'last_ping_at' => $pingStats->last_ping_at ?? null,
                    'can_ping' => !$minedRecently && $pingCountToday < $pingDailyLimit,
                ];
            }
        }

        $filteredMembers = $this->filterNetworkMembers($members, $filter);

        $teamBoostPercent = $this->teamBoostPercentForActiveCount($activeTodayCount);

        return [
            'stats' => [
                'direct_count' => count($referrals),
                'active_today_count' => $activeTodayCount,
                'inactive_count' => $inactiveCount,
                'pinged_today_count' => $pingedTodayCount,
                'referral_rewards' => $totalReferralRewards,
                'team_boost_percent' => $teamBoostPercent,
                'team_boost_cap' => max(array_column($this->teamBoostTiers(), 'boost_percent') ?: [self::REFERRAL_TEAM_BOOST_CAP]),
                'team_boost_tiers' => $this->teamBoostTiers(),
                'ping_daily_limit' => $pingDailyLimit,
                'visible_count' => count($filteredMembers),
            ],
            'filters' => [
                'all',
                'active',
                'inactive',
                'new',
                'needs_ping',
                'top_earners',
            ],
            'active_filter' => $filter,
            'ping_types' => $this->referralPingTypesForApi(),
            'settings' => $this->referralSettingsPayload(),
            'referrals' => $filteredMembers,
        ];
    }

    private function filterNetworkMembers(array $members, string $filter): array
    {
        $filter = strtolower(trim($filter ?: 'all'));
        $now = Carbon::now();

        if ($filter === 'active') {
            return array_values(array_filter($members, function ($member) {
                return !empty($member['active_today']);
            }));
        }

        if ($filter === 'inactive') {
            return array_values(array_filter($members, function ($member) {
                return empty($member['active_today']);
            }));
        }

        if ($filter === 'new') {
            return array_values(array_filter($members, function ($member) use ($now) {
                if (empty($member['referred_at'])) {
                    return false;
                }

                return Carbon::parse($member['referred_at'])->greaterThanOrEqualTo($now->copy()->subDays(7));
            }));
        }

        if ($filter === 'needs_ping') {
            return array_values(array_filter($members, function ($member) {
                return empty($member['active_today']) && !empty($member['can_ping']);
            }));
        }

        if ($filter === 'top_earners') {
            usort($members, function ($first, $second) {
                return (float) ($second['referral_rewards'] ?? 0) <=> (float) ($first['referral_rewards'] ?? 0);
            });

            return $members;
        }

        return $members;
    }

    private function referralPingTypesForApi(): array
    {
        $types = [];

        foreach (self::REFERRAL_PING_TYPES as $key => $config) {
            $types[] = [
                'type' => $key,
                'label' => $config['label'],
            ];
        }

        return $types;
    }

    private function referralPingTemplate(string $type): array
    {
        return self::REFERRAL_PING_TYPES[$type] ?? self::REFERRAL_PING_TYPES['mine_reminder'];
    }

    private function referralSetting(string $key, $default = null)
    {
        if (!Schema::hasTable('referral_settings')) {
            return $default;
        }

        $value = DB::table('referral_settings')
            ->where('setting_key', $key)
            ->value('setting_value');

        return $value === null ? $default : $value;
    }

    private function referralSettingsPayload(): array
    {
        return [
            'referrer_signup_reward' => (float) $this->referralSetting('referrer_signup_reward', 5),
            'new_user_signup_reward' => (float) $this->referralSetting('new_user_signup_reward', 0),
            'referral_ping_daily_limit' => $this->referralPingDailyLimit(),
            'team_boost_tiers' => $this->teamBoostTiers(),
            'referral_code_prefix' => (string) $this->referralSetting('referral_code_prefix', 'BRC'),
        ];
    }

    private function referralPingDailyLimit(): int
    {
        return max(1, (int) $this->referralSetting('referral_ping_daily_limit', self::REFERRAL_PING_DAILY_LIMIT));
    }

    private function teamBoostTiers(): array
    {
        $tiers = [
            [
                'active_referrals' => (int) $this->referralSetting('team_boost_tier_1_count', 1),
                'boost_percent' => (float) $this->referralSetting('team_boost_tier_1_percent', 2),
            ],
            [
                'active_referrals' => (int) $this->referralSetting('team_boost_tier_2_count', 5),
                'boost_percent' => (float) $this->referralSetting('team_boost_tier_2_percent', 5),
            ],
            [
                'active_referrals' => (int) $this->referralSetting('team_boost_tier_3_count', 10),
                'boost_percent' => (float) $this->referralSetting('team_boost_tier_3_percent', 10),
            ],
        ];

        usort($tiers, function ($first, $second) {
            return (int) $first['active_referrals'] <=> (int) $second['active_referrals'];
        });

        return $tiers;
    }

    private function teamBoostPercentForActiveCount(int $activeTodayCount): float
    {
        $boostPercent = 0.0;

        foreach ($this->teamBoostTiers() as $tier) {
            if ($activeTodayCount >= (int) $tier['active_referrals']) {
                $boostPercent = max($boostPercent, (float) $tier['boost_percent']);
            }
        }

        return $boostPercent;
    }

    private function generateUniqueReferralCode(): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $this->referralSetting('referral_code_prefix', 'BRC')));
        $prefix = $prefix ?: 'BRC';

        do {
            $suffix = strtoupper(Str::random(8));
            $code = $prefix . '-' . $suffix;
        } while (DB::table('res_users')->where('refferal_code', $code)->exists());

        return $code;
    }

    private function ensureUserReferralCode($user): string
    {
        if (!$user) {
            return '';
        }

        $code = trim((string) ($user->refferal_code ?? ''));
        $duplicateExists = $code !== ''
            && DB::table('res_users')
                ->where('refferal_code', $code)
                ->where('id', '!=', $user->id)
                ->exists();

        if ($code === '' || $duplicateExists) {
            $code = $this->generateUniqueReferralCode();
            $updateData = $this->withExistingTimestamps('res_users', [
                'refferal_code' => $code,
            ]);

            DB::table('res_users')->where('id', $user->id)->update($updateData);
            $user->refferal_code = $code;
        }

        $this->ensureReferralRecord((int) $user->id, $code);

        return $code;
    }

    private function ensureReferralRecord(int $userId, string $referralCode)
    {
        $existing = DB::table('referrals')->where('user_id', $userId)->first();

        if ($existing) {
            if (empty($existing->referral_code) && $referralCode !== '') {
                $updateData = $this->withExistingTimestamps('referrals', [
                    'referral_code' => $referralCode,
                ]);

                DB::table('referrals')->where('id', $existing->id)->update($updateData);
            }

            return DB::table('referrals')->where('id', $existing->id)->first();
        }

        $insertData = $this->withExistingTimestamps('referrals', [
            'user_id' => $userId,
            'referral_code' => $referralCode,
            'referred_by' => null,
            'rewards' => 0,
        ], null, true);

        $id = DB::table('referrals')->insertGetId($insertData);

        return DB::table('referrals')->where('id', $id)->first();
    }

    private function awardReferralReward(int $referrerUserId, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $referrer = DB::table('res_users')->where('id', $referrerUserId)->first();
        $referrerCode = $this->ensureUserReferralCode($referrer);
        $referrerReferral = $this->ensureReferralRecord($referrerUserId, $referrerCode);

        DB::table('referrals')->where('id', $referrerReferral->id)->increment('rewards', $amount);
    }

    private function applyReferralCodeForUser(int $userId, string $inputCode): array
    {
        $inputCode = strtoupper(trim($inputCode));
        if ($inputCode === '') {
            return ['status' => 0, 'message' => 'Referral code is required.'];
        }

        $user = DB::table('res_users')->where('id', $userId)->first();
        if (!$user) {
            return ['status' => 0, 'message' => 'User not found.'];
        }

        $ownCode = $this->ensureUserReferralCode($user);
        if (strtoupper($ownCode) === $inputCode) {
            return ['status' => 0, 'message' => 'You cannot use your own referral code.'];
        }

        $referrer = DB::table('res_users')->where('refferal_code', $inputCode)->first();
        if (!$referrer) {
            return ['status' => 0, 'message' => 'Referral code not found.'];
        }

        if ((int) $referrer->id === $userId) {
            return ['status' => 0, 'message' => 'You cannot use your own referral code.'];
        }

        $referral = $this->ensureReferralRecord($userId, $ownCode);
        if (!empty($referral->referred_by)) {
            return [
                'status' => 0,
                'message' => 'Referral code is already applied to this account.',
                'data' => $referral,
            ];
        }

        $updateData = $this->withExistingTimestamps('referrals', [
            'referred_by' => $referrer->refferal_code,
        ]);

        DB::table('referrals')->where('id', $referral->id)->update($updateData);

        $referrerReward = (float) $this->referralSetting('referrer_signup_reward', 5);
        $newUserReward = (float) $this->referralSetting('new_user_signup_reward', 0);

        $this->awardReferralReward((int) $referrer->id, $referrerReward);

        if ($newUserReward > 0) {
            $this->creditWallet($userId, $newUserReward);
        }

        $referrerGamification = $this->gamificationService->awardXp(
            ResUser::findOrFail((int) $referrer->id),
            'referral_activated',
            100,
            'referral_user',
            $userId
        );

        try {
            $userInstance = ResUser::find($referrer->id);
            if ($userInstance) {
                $userInstance->notify(new RefferalNotification(
                    ResUser::find($userId),
                    $user->name,
                    'refferal',
                    $referrer->name
                ));
            }
        } catch (\Throwable $error) {
            Log::warning('Referral notification failed: ' . $error->getMessage());
        }

        return [
            'status' => 1,
            'message' => 'Referral code applied successfully.',
            'data' => [
                'referrer' => [
                    'id' => (int) $referrer->id,
                    'name' => $referrer->name,
                    'username' => $referrer->username,
                ],
                'referrer_reward' => $referrerReward,
                'new_user_reward' => $newUserReward,
                'gamification' => $referrerGamification,
            ],
        ];
    }

    private function referralMissionProgress(array $stats, string $metric)
    {
        $metric = strtolower(trim($metric));

        if ($metric === 'direct_referrals') {
            return (int) ($stats['direct_count'] ?? 0);
        }

        if ($metric === 'active_referrals_today') {
            return (int) ($stats['active_today_count'] ?? 0);
        }

        if ($metric === 'pinged_today') {
            return (int) ($stats['pinged_today_count'] ?? 0);
        }

        if ($metric === 'inactive_referrals') {
            return (int) ($stats['inactive_count'] ?? 0);
        }

        if ($metric === 'referral_rewards') {
            return (float) ($stats['referral_rewards'] ?? 0);
        }

        return 0;
    }

    private function referralMissionClaimed(int $userId, $mission): bool
    {
        if (!Schema::hasTable('user_referral_mission_claims') || !$mission) {
            return false;
        }

        $query = DB::table('user_referral_mission_claims')
            ->where('user_id', $userId)
            ->where('referral_mission_id', $mission->id);

        if (($mission->frequency ?? 'once') === 'daily') {
            $query->where('claim_date', Carbon::now()->toDateString());
        }

        return $query->exists();
    }

    private function referralMissionPayload($mission, array $stats): array
    {
        $progress = $this->referralMissionProgress($stats, (string) $mission->metric);
        $target = max(1, (int) $mission->target_count);

        return [
            'id' => (int) $mission->id,
            'title' => $mission->title,
            'slug' => $mission->slug,
            'description' => $mission->description,
            'metric' => $mission->metric,
            'target_count' => $target,
            'progress' => $progress,
            'reward_amount' => (float) $mission->reward_amount,
            'frequency' => $mission->frequency,
            'status' => (int) $mission->status,
            'is_complete' => $progress >= $target,
        ];
    }

    private function currentMiningRatePayload(int $userId, bool $includeNetwork = false): array
    {
        $now = Carbon::now();
        $paidBoost = $this->activePaidBoost($userId, $now);
        $network = $this->teamNetworkSnapshot($userId, $includeNetwork);

        $baseReward = self::MINING_REWARD;
        $paidBoostPercent = $paidBoost ? (float) $paidBoost->boost_percent : 0.0;
        $kycBoostPercent = $this->verifiedMemberService->verifiedMiningBoostPercent($userId);
        $teamBoostPercent = (float) ($network['stats']['team_boost_percent'] ?? 0);
        $totalBoostPercent = $paidBoostPercent + $teamBoostPercent + $kycBoostPercent;
        $reward = round($baseReward * (1 + ($totalBoostPercent / 100)), 4);

        $payload = [
            'base_mining_reward' => $baseReward,
            'base_mining_rate' => self::BASE_MINING_RATE,
            'mining_rate' => $reward,
            'mining_reward' => $reward,
            'reward' => $reward,
            'paid_boost_percent' => $paidBoostPercent,
            'kyc_boost_percent' => $kycBoostPercent,
            'kyc_boost_active' => $kycBoostPercent > 0,
            'team_boost_percent' => $teamBoostPercent,
            'boost_percent' => $totalBoostPercent,
            'active_paid_boost' => $paidBoost ? [
                'id' => (int) $paidBoost->id,
                'plan_id' => (int) $paidBoost->boost_plan_id,
                'plan_name' => $paidBoost->plan_name,
                'boost_percent' => (float) $paidBoost->boost_percent,
                'starts_at' => $paidBoost->starts_at,
                'ends_at' => $paidBoost->ends_at,
            ] : null,
            'network_stats' => $network['stats'],
        ];

        if ($includeNetwork) {
            $payload['network_referrals'] = $network['referrals'];
        }

        return $payload;
    }

    private function currentMiningReward(int $userId): float
    {
        $rate = $this->currentMiningRatePayload($userId);
        return (float) $rate['reward'];
    }

    private function fastMiningReward(int $userId): float
    {
        $now = Carbon::now();
        return (float) $this->fastMiningRatePayload($userId, $now)['reward'];
    }

    private function insertUserNotification(int $userId, string $type, string $message, array $extra = []): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        $payload = [
            'type' => $type,
            'title' => $extra['title'] ?? 'Blockrium notification',
            'action' => $extra['action'] ?? null,
            'data' => array_merge(['message' => $message], $extra),
        ];

        $insert = [];
        if (Schema::hasColumn('notifications', 'type')) {
            $insert['type'] = $type;
        }
        if (Schema::hasColumn('notifications', 'notifiable_type')) {
            $insert['notifiable_type'] = ResUser::class;
        }
        if (Schema::hasColumn('notifications', 'notifiable_id')) {
            $insert['notifiable_id'] = $userId;
        }
        if (Schema::hasColumn('notifications', 'data')) {
            $insert['data'] = json_encode($payload);
        }
        if (Schema::hasColumn('notifications', 'notification_type')) {
            $insert['notification_type'] = $type;
        }
        if (Schema::hasColumn('notifications', 'status')) {
            $insert['status'] = 1;
        }
        if (Schema::hasColumn('notifications', 'n_type')) {
            $insert['n_type'] = 1;
        }
        if (Schema::hasColumn('notifications', 'read_at')) {
            $insert['read_at'] = null;
        }
        if (Schema::hasColumn('notifications', 'created_at')) {
            $insert['created_at'] = now();
        }
        if (Schema::hasColumn('notifications', 'updated_at')) {
            $insert['updated_at'] = now();
        }

        if (!empty($insert)) {
            DB::table('notifications')->insert($insert);
        }
    }

    private function insertRewardNotification(
        int $userId,
        float $amount,
        string $title,
        string $message,
        array $extra = []
    ): void {
        $this->insertUserNotification($userId, 'BRC_REWARD', $message, array_merge([
            'title' => $title,
            'action' => 'CLAIM_REWARDS',
            'coin' => $amount,
        ], $extra));
    }

    
    /*
    |========================================|
    |=============== User login==============|
    |========================================|
    */
    public function login(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Enter a valid email and password.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = strtolower(trim($request->email));
        $password = (string) $request->password;
        $checkuser = ResUser::whereRaw('LOWER(email) = ?', [$email])->first();

        if (!$checkuser) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
                'message_title' => 'Invalid email or password.',
            ], 401);
        }

        if ((int) ($checkuser->is_active ?? 1) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is blocked. Contact the Blockrium team.',
                'message_title' => 'Your account is blocked. Contact the Blockrium team.',
            ], 403);
        }

        $passwordMatches = Hash::check($password, (string) $checkuser->password);
        $legacyMd5Match = !$passwordMatches && hash_equals((string) $checkuser->password, md5($password));

        if (!$passwordMatches && !$legacyMd5Match) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
                'message_title' => 'Invalid email or password.',
            ], 401);
        }

        if ($legacyMd5Match) {
            $checkuser->password = Hash::make($password);
        }

        $token = JWTAuth::fromUser($checkuser);
        if (Schema::hasColumn('res_users', 'remember_token')) {
            $checkuser->remember_token = $token;
        }
        $checkuser->save();

        $tokenPayload = [
            'message' => 'Authenticated',
            'message_title' => 'success',
            'id' => $checkuser->id,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => null,
            'user' => $checkuser,
        ];

        return response()->json([
            'success' => true,
			'message'=>'Authenticated',
			'message_title'=>'User successfully login',
            'id' => $checkuser->id,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => null,
            'user' => $checkuser,
            'token' => [
                'original' => $tokenPayload,
            ],
        ], 200); 
    }

   
    /*
    |===========================================|
    |=============== User register==============|
    |===========================================|
    */
    
    
    
    
    //  $validator = Validator::make($request->all(), [
    //     'username' => 'required|string|between:2,10|unique:res_users',
    //     'name' => 'required|string|between:2,100',
    //     'email' => 'required|string|email|max:100|unique:res_users',
    //     'phone_number' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|unique:res_users',
    //     'password' => 'required|string|confirmed|min:8',
    // ]);

    // if($validator->fails()){
    //     return response()->json($validator->errors()->toJson(), 400);
    // }

    // // Password strength validation
    // $password = $request->input('password');
    // $passwordScore = Password::min(8)
    //     ->requireUppercase()
    //     ->requireNumeric()
    //     ->requireSpecialCharacter()
    //     ->evaluate($password);

    // if ($passwordScore < 4) {
    //     return response()->json([
    //         'message' => 'Password is too weak.',
    //         'password_score' => $passwordScore
    //     ], 400);
    // }

    // $referral_code = Str::random(12);
    
    
    public function register(Request $request) {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|between:2,50',
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:res_users,email',
            'phone_number' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|unique:res_users,phone_number',
            'password' => 'required|string|confirmed|min:6',
            'country' => 'required|string',
            //'refferal_code' => 'string|between:2,15',
            //'password_confirmation' => 'required|string|confirmed|min:6',
        ]);

        if($validator->fails()){
            return response()->json([
                'success' => false,
                'message' => 'Please correct the signup details and try again.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $username = trim((string) $request->username);
        $name = trim((string) $request->name);
        $email = strtolower(trim((string) $request->email));
        $phoneNumber = trim((string) $request->phone_number);
        $password = (string) $request->password;
        $country = trim((string) $request->country);
        $referredBy = $request->filled('referred_by')
            ? strtoupper(trim((string) $request->referred_by))
            : null;

        return DB::transaction(function () use (
            $username,
            $name,
            $email,
            $phoneNumber,
            $password,
            $country,
            $referredBy
        ) {
            $referralCode = $this->generateUniqueReferralCode();
            $user = ResUser::create([
                'username' => $username,
                'name' => $name,
                'phone_number' => $phoneNumber,
                'refferal_code' => $referralCode,
                'email' => $email,
                'password' => Hash::make($password),
                'user_image' => '',
                'country' => $country,
            ]);

            $referral = new Referral([
                'user_id' => $user->id,
                'referral_code' => $referralCode,
                'referred_by' => null,
                'rewards' => 0,
            ]);
            $user->referral()->save($referral);

            $referralResult = null;
            if ($referredBy) {
                $referralResult = $this->applyReferralCodeForUser((int) $user->id, $referredBy);
            }

            $walletData = $this->withExistingTimestamps('tbluserwallet', [
                'userid' => $user->id,
                'totaltoken' => 0,
            ], now(), true);
            DB::table('tbluserwallet')->insert($walletData);

            $token = JWTAuth::fromUser($user);
            if (Schema::hasColumn('res_users', 'remember_token')) {
                $user->remember_token = $token;
                $user->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Authenticated',
                'message_title' => 'User successfully registered',
                'id' => $user->id,
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => null,
                'user' => $user,
                'referral' => $referralResult,
                'token' => $token,
            ], 200);
        });
    }


    /*
    |=========================================|
    |=============== User Logout==============|
    |=========================================|
    */

    public function logout(Request $request) {
        auth($this->guard)->logout();
        return response()->json(['message'=>'Authenticated','message_title' => 'User successfully signed out']);
    }


    /*
    |==========================================|
    |===============Refresh Token==============|
    |==========================================|
    */ 
    public function refresh(Request $request) {
        $user1 = auth($this->guard)->user();
        return $this->createNewToken(auth($this->guard)->refresh(),'refresh',$user1);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    
    /*
    |=========================================|
    |===============User Profile==============|
    |=========================================|
    */ 
    public function userProfile(Request $request) {
		$user = auth($this->guard)->user();
        $userid= $user['id'];
        $referralCode = $this->ensureUserReferralCode($user);
        $user->refferal_code = $referralCode;
        $userattendance = DB::table('tblattendance')->where('userid',$userid)->get();
        $url = 'https://maticlegend.com';
        $referred_by = DB::table('referrals')->where('user_id', $userid)->first();
        if(!empty($referred_by->referred_by)){
            $is_referred_by =true;
        }else{
            $is_referred_by =false;
        }
        
        $user_image = $user->user_image;
        //print_r($user);die();
        return response()->json([
            'message'=>'Authenticated',
            'message_title'=>'success',
            'user'=>$user,
            'user_image'=>$user_image,
            'attendance'=>$userattendance,
            'is_referred_by'=>$is_referred_by,
            'referral_code'=>$referralCode,
            'referral'=>$referred_by,
            'referral_settings'=>$this->referralSettingsPayload(),
            'verified_member'=>app(\App\Services\VerifiedMemberService::class)->status((int) $userid),
        ]);
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function createNewToken($token,$method='',$user1=''){
		$user = auth($this->guard)->user();
		$user['remember_token'] = $token;
		if(!empty($method) && $method == 'refresh'){
			$data = array(
                'remember_token' => $token
            );
            $user['id'] = $user1['id'];
            $this->ResUserModel->update_row('res_users',$data,array("id"=>$user1['id']));
		}
        return response()->json([
			'message'=>'Authenticated',
			'message_title'=>'success',
			'id' => $user['id'],
            'access_token' => $token,
            'token_type' => 'bearer',
            //'expires_in' => auth($this->guard)->factory()->getTTL() * 60,
            'expires_in' => auth($this->guard)->factory()->setTTL(null),
            'user' => $user
        ]);
		
    }
	
    
    /*
    |================================================|
    |===============Update User Profile==============|
    |================================================|
    */
    // public function updateProfile(Request $request){
    //     $user = ResUser::find($request->post('id'));
    //   // print_r($user); die();
    //     if($user){
    //         if($request->file('user_image')){
    //             //$store_img=$request->file('user_image')->store('image', 'public');
    //             $image_path = $request->file('user_image')->getClientOriginalName();
    //             $file= $request->file('user_image');
    //             $file-> move(public_path('user_image/'), $image_path);
    //         }
    //         else{
    //             $image_path = ''; 
    //         }
    //         $response=array(
    //         //'username'=>$request->post('username'),
    //         'name'=>$request->post('name'),
    //         'phone_number'=>$request->post('phone_number'),		
    //         'password'=>$request->post('password'),
    //         'email'=>$request->post('email'),
    //         'user_image'=> $image_path,
    //         'country' => $request->post('country'),
    //         );
    //         if(!$request->post('email')){
    //             $rules =[
    //             //'username' => ['required', 'string', 'max:255'],
    //             'name' => ['required', 'string', 'max:255'],
    //             'email' => ['required', 'string', 'email', 'max:255', 'unique:res_users'],
    //             'phone_number' => 'required|min:10',
    //             ];
    //         }
    //         else
    //         {
    //             $rules =[
    //             'name' => ['required', 'string', 'max:255'],
    //             'phone_number' => 'required|min:10',
    //             ];		
    //         }
    //         $validator = Validator::make($response, $rules);
    //         if ($validator->passes()) 
    //         {
    //             $user->name=$response['name'];
    //             if($request->post('email') && !empty($request->post('email')))
    //             {
    //                 $user->email=$response['email'];
    //             }
    //             if($image_path)
    //             {
    //                 $user->user_image=$image_path;
    //             }
    //             $user->phone_number = $response['phone_number'];			
    //             $error=0;
    //             if($request->post('email') && !empty($request->post('email')))
    //             {
    //                 $email = ResUser::where('email',$response['email'])->first();
    //                 $user_email = ResUser::find($request->post('id'), ['email']);
    //                 if($email && $user_email->email!=$response['email'])
    //                 {
    //                     $error=1;				
    //                 }
    //             }
    //             if($error==0){
    //                 $user->save();		
    //                 if($user)
    //                 {		
    //                     unset($user->email_verified_at);
    //                     unset($user->settings);
    //                     $response=array('status' =>1,'message'=>'Success!','errors'=>[],'data'=>$user);				
    //                 }
    //                 else
    //                 {
    //                     $response=array('status' =>0,'message'=>'Something went wrong, please try again later!','errors'=>[],'data'=>[]);				
    //                 }
    //             }
    //             else
    //             {
    //                 $response=array('status' =>0,'message'=>'This email already exists, please try using another email!','errors'=>[]);
    //             }			
    //         } 
    //         else
    //         {
    //             $errors=[];
    //             $i=0;
    //             foreach($validator->errors()->all() as $key=>$value)
    //             {
    //                 $errors[$i]['error']= $value;
    //                 $i++;
    //                 }
    //             $response=array(
    //             'status' =>0,
    //             'message'=>'Error!',
    //             'errors'=>$errors);
    //         }
    //     }
    //     else
    //     {
    //         $response=array('status' =>0,'message'=>'Something went wrong, please try again later!','errors'=>[],'data'=>[]);
    //     }
    //     return response()->json($response);
    // }
     public function updateProfile(Request $request){
        // $user = ResUser::find($request->post('id'));
       // print_r($user); die();
       $user = auth($this->guard)->user();
        if($user){
            if($request->file('user_image')){
                //$store_img=$request->file('user_image')->store('image', 'public');
                $image_path = $request->file('user_image')->getClientOriginalName();
                $file= $request->file('user_image');
                $file-> move(public_path('user_image/'), $image_path);
            }
            else{
                $image_path = ''; 
            }
            $response=array(
            //'username'=>$request->post('username'),
            'name'=>$request->post('name'),
            'phone_number'=>$request->post('phone_number'),		
            'password'=>$request->post('password'),
            'email'=>$request->post('email'),
            'user_image'=> $image_path,
            'country' => $request->post('country'),
            );
            // if(!$request->post('email')){
            //     $rules =[
            //     //'username' => ['required', 'string', 'max:255'],
            //   // 'name' => ['required', 'string', 'max:255'],
            //     'email' => ['required', 'string', 'email', 'max:255', 'unique:res_users'],
            //     //'phone_number' => 'required|min:10',
            //     ];
            // }
            // else
            // {
            //     $rules =[
            //     'name' => ['required', 'string', 'max:255'],
            //     'phone_number' => 'required|min:10',
            //     ];		
            // }
            // $validator = Validator::make($response, $rules);
            // if ($validator->passes()) 
            // {
                if($request->post('name')){
                    $user->name=$response['name'];
                }
                
                if($request->post('email') && !empty($request->post('email')))
                {
                    $user->email=$response['email'];
                }
                if($request->file('user_image') && $image_path)
                {
                    $user->user_image=$image_path;
                }
                
                if($request->post('phone_number'))
                {
                   $user->phone_number = $response['phone_number'];	
                }
                		
                $error=0;
                if($user)
                {
                    $email = ResUser::where('email',$user->email)->first();
                    $user_email = $user->email;
                    if($email && $user_email != $user->email)
                    {
                        $error=1;				
                    }
                }
                if($error==0){
                    $user->save();		
                    if($user)
                    {		
                        unset($user->email_verified_at);
                        unset($user->settings);
                        $url = 'https://maticlegend.com';
                        $user_image = $url.'/public/user_image/'.$user['user_image'];
                        $response=array('status' =>1,'message'=>'Success!','errors'=>[],'data'=>$user,'user_image'=>$user_image);				
                    }
                    else
                    {
                        $response=array('status' =>0,'message'=>'Something went wrong, please try again later!','errors'=>[],'data'=>[]);				
                    }
                }
                else
                {
                    $response=array('status' =>0,'message'=>'This email already exists, please try using another email!','errors'=>[]);
                }			
            // } 
            // else
            // {
            //     $errors=[];
            //     $i=0;
            //     foreach($validator->errors()->all() as $key=>$value)
            //     {
            //         $errors[$i]['error']= $value;
            //         $i++;
            //         }
            //     $response=array(
            //     'status' =>0,
            //     'message'=>'Error!',
            //     'errors'=>$errors);
            // }
        }
        else
        {
            $response=array('status' =>0,'message'=>'Something went wrong, please try again later!','errors'=>[],'data'=>[]);
        }
        return response()->json($response);
    }
    
//     public function updateProfile(Request $request)
// {
//     $user = auth($this->guard)->user();

//     if (!$user) {
//         return response()->json(['status' => 0, 'message' => 'Something went wrong, please try again later!', 'errors' => [], 'data' => []]);
//     }

//     // Validation Rules
//     $rules = [
//         'name' => 'required|string|max:255',
//         'email' => 'required|string|email|max:255|unique:res_users,email,' . $user->id,
//         'phone_number' => 'required|min:10',
//         'user_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
//     ];
//     $validator = Validator::make($request->all(), $rules);

//     if ($validator->fails()) {
//         return response()->json([
//             'status' => 0,
//             'message' => 'Validation errors!',
//             'errors' => $validator->errors()
//         ]);
//     }

//     // File Upload
//     $imagePath = $user->user_image;
//     if ($request->hasFile('user_image')) {
//         $file = $request->file('user_image');
//         $imagePath = $file->getClientOriginalName();
//         $file->move(public_path('user_image'), $imagePath);
//     }

//     // Updating User Details
//     $user->update([
//         'name' => $request->post('name'),
//         'email' => $request->post('email'),
//         'phone_number' => $request->post('phone_number'),
//         'user_image' => $imagePath,
//         'country' => $request->post('country')
//     ]);

//     // Prepare Response
//     $userImageUrl = url('user_image/' . $user->user_image);
//     return response()->json([
//         'status' => 1,
//         'message' => 'Success!',
//         'errors' => [],
//         'data' => $user->only(['name', 'email', 'phone_number', 'country']),
//         'user_image' => $userImageUrl
//     ]);
// }


    /*
    |============================================|
    |========== UserAttendance Save==============|
    |============================================|
    */

    public function UserAttendance(Request $request){
        $user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        $userid = (int) $user['id'];

        return DB::transaction(function () use ($userid) {
            $latestAttendance = DB::table('tblattendance')
                ->where('userid', $userid)
                ->latest()
                ->lockForUpdate()
                ->first();

            $attendanceData = DB::table('tblattendance')
                ->where('userid', $userid)
                ->latest()
                ->take(10)
                ->get();

            if ($latestAttendance && $this->hasClaimedToday($latestAttendance->attendance_date)) {
                return response()->json([
                    'status' => 0,
                    'message' => 'User already submitted attendance today',
                    'attendance_data' => $attendanceData,
                ]);
            }

            $postData = $this->withExistingTimestamps('tblattendance', [
                'userid' => $userid,
                'coins' => ($latestAttendance ? (float) $latestAttendance->coins : 0) + self::ATTENDANCE_REWARD,
                'attendance_date' => now(),
            ], null, true);

            DB::table('tblattendance')->insert($postData);

            return response()->json([
                'status' => 1,
                'message' => 'User attendance added successfully',
                'data' => $postData,
                'attendance_data' => DB::table('tblattendance')
                    ->where('userid', $userid)
                    ->latest()
                    ->take(10)
                    ->get(),
            ]);
        });
    }


    /*
    |============================================|
    |========== usermining ======================|
    |============================================|
    */

    public function usermining(Request $request){
        $user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        $userid = (int) $user['id'];
        $rewardAmount = $this->currentMiningReward($userid);

        return DB::transaction(function () use ($user, $userid, $rewardAmount) {
            $now = Carbon::now();
            $record = DB::table('tbl_user_mining')
                ->where('userid', $userid)
                ->lockForUpdate()
                ->first();

            if (!$record) {
                $postData = $this->withExistingTimestamps('tbl_user_mining', [
                    'userid' => $userid,
                    'button_status' => 1,
                    'hour_count' => 1,
                    'totalamount' => $rewardAmount,
                    'mining_start_time' => $now,
                ], $now, true);

                DB::table('tbl_user_mining')->insert($postData);
                $gamification = $this->gamificationService->awardXp(
                    $user,
                    'mining_started',
                    10,
                    'tbl_user_mining',
                    $userid
                );

                return response()->json([
                    'status' => 1,
                    'message' => 'User mining added successfully',
                    'data' => $postData,
                    'button_status' => 1,
                    'reward' => $rewardAmount,
                    'rate' => $this->currentMiningRatePayload($userid),
                    'nextMining' => $now->copy()->addHour()->toDateTimeString(),
                    'nextMiningTimestamp' => $now->copy()->addHour()->timestamp * 1000,
                    'gamification' => $gamification,
                ]);
            }

            $lastRewardAt = ($record->updated_at ?? null) ?: ($record->mining_start_time ?? ($record->created_at ?? null));
            if ($lastRewardAt && Carbon::parse($lastRewardAt)->diffInMinutes($now) < 60) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Please wait before claiming the next mining reward',
                    'button_status' => (int) $record->button_status,
                    'nextMining' => Carbon::parse($lastRewardAt)->addHour()->toDateTimeString(),
                ]);
            }

            $currentHour = (int) $record->hour_count;
            $nextHour = $currentHour >= 24 ? 1 : $currentHour + 1;
            $buttonStatus = $nextHour >= 24 ? 0 : 1;
            $postData = $this->withExistingTimestamps('tbl_user_mining', [
                'button_status' => $buttonStatus,
                'hour_count' => $nextHour,
                'totalamount' => (float) $record->totalamount + $rewardAmount,
                'mining_start_time' => $currentHour >= 24 ? $now : $record->mining_start_time,
            ], $now);

            DB::table('tbl_user_mining')->where('id', $record->id)->update($postData);
            $gamification = $this->gamificationService->awardXp(
                $user,
                'mining_started',
                10,
                'tbl_user_mining',
                (int) $record->id
            );

            return response()->json([
                'status' => 1,
                'message' => 'User mining added successfully',
                'data' => $postData,
                'button_status' => $buttonStatus,
                'reward' => $rewardAmount,
                'rate' => $this->currentMiningRatePayload($userid),
                'nextMining' => $now->copy()->addHour()->toDateTimeString(),
                'nextMiningTimestamp' => $now->copy()->addHour()->timestamp * 1000,
                'gamification' => $gamification,
            ]);
        });
    }
    /*
    |==============================================|
    |========== get_mining_details ================|
    |==============================================|
    */
    public function get_mining_details(Request $request) {
		$user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        $userid = (int) $user['id'];
        $miningDetails = DB::table('button_clicks')
            ->where('user_id', $userid)
            ->orderByDesc('created_at')
            ->first();

        $payload = $this->miningPayload($miningDetails, null, $userid);

        return response()->json(array_merge([
            'status' => 1,
            'message' => 'Authenticated',
            'message_title' => 'success',
            'data' => $payload,
        ], $payload));
    }

    /*
    |==============================================|
    |========== daily Reward ======================|
    |==============================================|
    */
    public function dailyreward(Request $request){
        $user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        $userid = (int) $user['id'];
        $rewardAmount = self::ATTENDANCE_REWARD;

        return DB::transaction(function () use ($user, $userid, $rewardAmount) {
            $now = Carbon::now();
            $rewardRecord = DB::table('tblreward')
                ->where('userid', $userid)
                ->lockForUpdate()
                ->first();

            if ($rewardRecord && $this->hasClaimedToday($rewardRecord->updated_at)) {
                return response()->json([
                    'status' => 0,
                    'message' => 'User already claimed their reward',
                    'data' => $this->rewardStatusPayload($rewardRecord, $now),
                ]);
            }

            $nextStatus = $this->nextRewardStatus($rewardRecord, $now);
            $newTokenTotal = (float) ($rewardRecord->token ?? 0) + $rewardAmount;

            if ($rewardRecord) {
                $updateData = $this->withExistingTimestamps('tblreward', [
                    'token' => $newTokenTotal,
                    'status' => $nextStatus,
                ], $now);

                DB::table('tblreward')->where('id', $rewardRecord->id)->update($updateData);
            } else {
                $insertData = $this->withExistingTimestamps('tblreward', [
                    'userid' => $userid,
                    'token' => $rewardAmount,
                    'status' => $nextStatus,
                ], $now, true);

                DB::table('tblreward')->insert($insertData);
            }

            $wallet = $this->creditWallet($userid, $rewardAmount);
            $freshRewardRecord = DB::table('tblreward')->where('userid', $userid)->first();
            $payload = $this->rewardStatusPayload($freshRewardRecord, $now);
            $payload['reward'] = $rewardAmount;
            $gamification = $this->gamificationService->awardXp($user, 'streak_claimed', 15);
            $this->insertRewardNotification(
                $userid,
                (float) $rewardAmount,
                'Daily reward received',
                "Congratulations! {$rewardAmount} BRC has been added to your wallet."
            );

            return response()->json([
                'status' => 1,
                'message' => 'User reward claimed successfully',
                'reward' => $rewardAmount,
                'data' => $payload,
                'wallet_bal' => $wallet,
                'gamification' => $gamification,
            ]);
        });
    }
    
    public function random_dailyreward(Request $request){
        $user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        $userid = (int) $user['id'];

        return DB::transaction(function () use ($user, $userid) {
            $now = Carbon::now();
            $rewardRecord = DB::table('tblreward')
                ->where('userid', $userid)
                ->lockForUpdate()
                ->first();

            if ($rewardRecord && $this->hasClaimedToday($rewardRecord->updated_at)) {
                $payload = $this->rewardStatusPayload($rewardRecord, $now);
                return response()->json([
                    'status' => 0,
                    'message' => 'User already claimed their reward',
                    'data' => $payload,
                ]);
            }

            $reward = mt_rand(1, 3);
            $nextStatus = $this->nextRewardStatus($rewardRecord, $now);
            $newTokenTotal = (float) ($rewardRecord->token ?? 0) + $reward;

            if ($rewardRecord) {
                $updateData = $this->withExistingTimestamps('tblreward', [
                    'token' => $newTokenTotal,
                    'status' => $nextStatus,
                ], $now);

                DB::table('tblreward')->where('id', $rewardRecord->id)->update($updateData);
            } else {
                $insertData = $this->withExistingTimestamps('tblreward', [
                    'userid' => $userid,
                    'token' => $reward,
                    'status' => $nextStatus,
                ], $now, true);

                DB::table('tblreward')->insert($insertData);
            }

            $wallet = $this->creditWallet($userid, (float) $reward);
            $freshRewardRecord = DB::table('tblreward')->where('userid', $userid)->first();
            $payload = $this->rewardStatusPayload($freshRewardRecord, $now);
            $payload['reward'] = $reward;
            $gamification = $this->gamificationService->awardXp($user, 'streak_claimed', 15);
            $this->insertRewardNotification(
                $userid,
                (float) $reward,
                'Daily streak reward received',
                "Congratulations! {$reward} BRC has been added to your wallet."
            );

            return response()->json([
                'status' => 1,
                'message' => 'Daily reward claimed successfully',
                'reward' => $reward,
                'data' => $payload,
                'wallet_bal' => $wallet,
                'gamification' => $gamification,
            ]);
        });
    }
    
    // public function random_dailyreward(Request $request){
            
    //     $user = auth($this->guard)->user();
    //     $userid= $user['id'];
    //     $getdata= DB::table('tblreward')->where('userid',$userid)->first();
        
    //     if(is_null($getdata)){
    //         $rand_token = mt_rand(1,3);
    //         $postData = [
    //             'userid'=>$userid,
    //             'token'=>$rand_token,
    //             'status' =>1,
    //             'created_at'=>now(),
    //             'updated_at'=>now(),
    //         ];
    //         $postRef = DB::table('tblreward')->insert($postData);
    //         if($postRef){
    //             $response = array('status'=> 1 ,'message'=> 'User daily first reward claimed successfully','data'=>$postData,'reward'=>$rand_token);
    //         }
    //         else {
    //             $response = array('status'=> 1 ,'message'=> 'User daily reward not added ');
    //         }
    //         return $response;
    //     }
        
    //     $id =$getdata->id;
    //     $coin = $getdata->token;
        
    //     if($getdata->status ==1){
    //         $rand_token = mt_rand(1,3);
    //         $postData = [
    //             'token'=>(float)($coin + $rand_token),
    //             'status'=>2,
    //             'updated_at'=>now(),
    //         ];
    //         $postRef = DB::table('tblreward')->where('id',$id)->update($postData);
    //         if($postRef){
    //             $response = array('status'=> 1 ,'message'=> 'User second reward claimed successfully','data'=>$postData,'reward'=>$rand_token);
    //         }
    //         else {
    //             $response = array('status'=> 0 ,'message'=> 'User reward not claimed');
    //         }
    //         return $response;
    //     }
        
    //     if($getdata->status ==2){
    //         $rand_token = mt_rand(1,3);
    //         $postData = [
    //             'token'=>(float)($coin + $rand_token),
    //             'status'=>3,
    //             'updated_at'=>now(),
    //         ];
    //         $postRef = DB::table('tblreward')->where('id',$id)->update($postData);
    //         if($postRef){
    //             $response = array('status'=> 1 ,'message'=> 'User third reward claimed successfully','data'=>$postData,'reward'=>$rand_token);
    //         }
    //         else {
    //             $response = array('status'=> 0 ,'message'=> 'User reward not claimed');
    //         }
    //         return $response;
    //     }
        
    //     if($getdata != '' && $getdata->status ==3){
    //         $dailyreward = $getdata->updated_at;
    //         $today = date('Y-m-d');
    //         if($dailyreward != $today && $userid){
    //             $rand_token = mt_rand(1,3);
    //             $postData = [
    //                 'token'=>(float)($coin + $rand_token),
    //                 'updated_at'=>now(),
    //                 'status'=>1,
    //             ];
    //             $postRef = DB::table('tblreward')->where('id',$id)->update($postData);
    //             if($postRef){
    //                 $response = array('status'=> 1 ,'message'=> 'User daily first reward claimed successfully','data'=>$postData,'reward'=>$rand_token);
    //             }
    //             else {
    //                 $response = array('status'=> 0 ,'message'=> 'User reward not claimed');
    //             }
    //             return $response;
    //         }
    //         else{
    //             $response = array('status'=> 0 ,'message'=> 'User already claimed their reward');
    //         }
    //     }
    //     else {
    //         $response = array('status'=> 0 ,'message'=> 'Invalid request');
    //     }
        
    //     return $response;
    // }

    
    public function reward_status(){
        $user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        $userid = (int) $user['id'];
        $rewardRecord = DB::table('tblreward')->where('userid', $userid)->first();
        $payload = $this->rewardStatusPayload($rewardRecord);

        return response()->json([
            'status' => 1,
            'message' => 'User daily reward status',
            'data' => $payload,
        ]);
    }

    public function rewardCalendar(Request $request)
    {
        $user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        if (!Schema::hasTable('daily_reward_chest_claims')) {
            return response()->json([
                'status' => 0,
                'message' => 'Reward calendar is not installed yet. Run database migrations first.',
            ], 422);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Reward calendar fetched successfully.',
            'data' => $this->rewardCalendarPayload((int) $user->id),
        ]);
    }

    public function claimRewardChest(Request $request)
    {
        $user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        if (!Schema::hasTable('daily_reward_chest_claims')) {
            return response()->json([
                'status' => 0,
                'message' => 'Reward calendar is not installed yet. Run database migrations first.',
            ], 422);
        }

        $userId = (int) $user->id;

        return DB::transaction(function () use ($user, $userId) {
            $now = Carbon::now();
            $today = $now->toDateString();
            $rewardRecord = DB::table('tblreward')
                ->where('userid', $userId)
                ->lockForUpdate()
                ->first();
            $payload = $this->rewardCalendarPayload($userId, $rewardRecord, $now);

            if (!$payload['chest']['can_claim']) {
                return response()->json([
                    'status' => 0,
                    'message' => $payload['chest']['claimed']
                        ? '7-day chest already claimed.'
                        : 'Complete a 7-day streak before claiming the chest.',
                    'data' => $payload,
                ], 422);
            }

            $claimInsertData = $this->withExistingTimestamps('daily_reward_chest_claims', [
                'user_id' => $userId,
                'streak_day' => self::STREAK_CHEST_DAY,
                'reward_amount' => self::STREAK_CHEST_REWARD,
                'claim_date' => $today,
                'claimed_at' => $now,
            ], $now, true);

            DB::table('daily_reward_chest_claims')->insert($claimInsertData);

            $wallet = $this->creditWallet($userId, self::STREAK_CHEST_REWARD);
            $this->insertRewardNotification(
                $userId,
                (float) self::STREAK_CHEST_REWARD,
                '7-day chest reward received',
                'Congratulations! 5 BRC has been added to your wallet from the 7-day chest.'
            );

            return response()->json([
                'status' => 1,
                'message' => '7-day chest claimed successfully.',
                'reward' => self::STREAK_CHEST_REWARD,
                'data' => $this->rewardCalendarPayload($userId, $rewardRecord, $now),
                'wallet_bal' => $wallet,
            ]);
        });
    }

    public function onboardingQuest(Request $request)
    {
        $user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        if (!Schema::hasTable('onboarding_quest_claims')) {
            return response()->json([
                'status' => 0,
                'message' => 'Onboarding quest is not installed yet. Run database migrations first.',
            ], 422);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Onboarding quest fetched successfully.',
            'data' => $this->onboardingQuestPayload((int) $user->id),
        ]);
    }

    public function claimOnboardingQuest(Request $request)
    {
        $user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        if (!Schema::hasTable('onboarding_quest_claims')) {
            return response()->json([
                'status' => 0,
                'message' => 'Onboarding quest is not installed yet. Run database migrations first.',
            ], 422);
        }

        $userId = (int) $user->id;

        return DB::transaction(function () use ($user, $userId) {
            $existingClaim = DB::table('onboarding_quest_claims')
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($existingClaim) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Starter quest reward already claimed.',
                    'data' => $this->onboardingQuestPayload($userId),
                ], 422);
            }

            $payload = $this->onboardingQuestPayload($userId);
            if (!$payload['is_completed']) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Complete every starter quest step before claiming.',
                    'data' => $payload,
                ], 422);
            }

            $questClaimInsertData = $this->withExistingTimestamps('onboarding_quest_claims', [
                'user_id' => $userId,
                'reward_amount' => self::ONBOARDING_QUEST_REWARD,
                'claimed_at' => now(),
            ], now(), true);

            DB::table('onboarding_quest_claims')->insert($questClaimInsertData);

            $wallet = $this->creditWallet($userId, self::ONBOARDING_QUEST_REWARD);
            $gamification = $this->gamificationService->awardXp(
                $user,
                'starter_quest_claimed',
                50,
                'starter_quest',
                1
            );
            $this->insertRewardNotification(
                $userId,
                (float) self::ONBOARDING_QUEST_REWARD,
                'Starter quest reward received',
                'Congratulations! 5 BRC has been added to your wallet from the starter quest.'
            );

            return response()->json([
                'status' => 1,
                'message' => 'Starter quest reward claimed successfully.',
                'data' => $this->onboardingQuestPayload($userId),
                'wallet_bal' => $wallet,
                'reward' => self::ONBOARDING_QUEST_REWARD,
                'gamification' => $gamification,
            ]);
        });
    }

     /*
    |==============================================|
    |========== User Wallet =======================|
    |==============================================|
    */
    public function wallet(Request $request){
        
        $user = auth($this->guard)->user();
        $userid= $user['id'];
        $dailyreward = DB::table('tblreward')->where('userid',$userid)->latest()->first();
        
        $attendance = DB::table('tblattendance')->where('userid',$userid)->latest()->first();
        $user_mining = DB::table('tbl_user_mining')->where('userid',$userid)->latest()->first();
        $wallet_bal = DB::table('tbluserwallet')->where('userid',$userid)->latest()->first();
        $referrals_bal = DB::table('referrals')->where('user_id',$userid)->latest()->first();

        $mining_button =ButtonClick::where('user_id',$userid)->select("id", \DB::raw("SUM(wallet) as total"))->get();
        
        $sum = $mining_button->sum('total');
        //echo "<pre>"; print_r($sum); die();
        if(is_null($dailyreward)){
            $dailyrewardtoken = 0 ;
        }else{
            $dailyrewardtoken   = $dailyreward->token;
        }

        if(is_null($attendance)){
            $attendancetoken = 0 ;
        }else{
            $attendancetoken    = $attendance->coins;
        }

        if(is_null($user_mining)){
            $user_miningtoken = 0 ;
        }else{
            $user_miningtoken   = $user_mining->totalamount;
        }
        
        if(is_null($wallet_bal)){
            $wallet_bal = 0 ;
        }else{
            $wallet_bal   = $wallet_bal->totaltoken;
        }

        if(is_null($referrals_bal)){
            $referrals_bal = 0 ;
        }else{
            $referrals_bal   = $referrals_bal->rewards;
        }
        
        // if(is_null($mining_button)){
        //     $mining_button_bal = 0 ;
        // }else{
        //     $mining_button_bal   = $mining_button->total;
        // }
        
        $verified_bal=0;
        $unverified_bal=(float)($dailyrewardtoken + $attendancetoken + $user_miningtoken + $wallet_bal + $referrals_bal+$sum );
        
        $mining_rate=[
            'miningRateLevelIndex'=>'1'
            // 'index2'=>'1 e/hr',
            // 'index3'=>'2 e/hr'
            ];
        

        $total_bal =(float)($dailyrewardtoken + $attendancetoken + $user_miningtoken + $wallet_bal + $referrals_bal+$sum );
        //echo "<pre>"; print_r($total_bal);die();
        $response = array('status'=> 1 ,'message'=> 'User wallet updated successfully','wallet_balance'=>$total_bal,'verififiedBalance'=>$verified_bal,'unVerifiedBalance'=>$unverified_bal,'miningRateLeveiIndex '=>$mining_rate,'ref_balance'=>$referrals_bal);

        // $getdata= DB::table('tbluserwallet')->where('userid',$userid)->first();
        // if(is_null($getdata)){
        //     $postData = [
        //         'userid'=>$userid,
        //         'totaltoken'=>$totaltoken,
        //         'created_at'=>now(),
                
        //     ];
        //     $postRef = DB::table('tbluserwallet')->insert($postData);
        //     if($postRef){
        //         $response = array('status'=> 1 ,'message'=> 'User wallet added successfully','data'=>$postData);
        //     }
        //     else {
        //         $response = array('status'=> 1 ,'message'=> 'User wallet not added ');
        //     }
        //     return $response;
        // }
        // if($getdata != ''){
        //     $getdata= DB::table('tbluserwallet')->where('userid',$userid)->first();
        //     $id =$getdata->id;
        //     $coin = $getdata->totaltoken;
            
        //         $postData = [
        //             'totaltoken'=>$totaltoken,
                    
        //         ];
        //         $postRef = DB::table('tbluserwallet')->where('id',$id)->update($postData);
        //         // if($postRef){
        //             $response = array('status'=> 1 ,'message'=> 'User wallet updated successfully','data'=>$postData);
        //         // }
        //         // else {
        //         //     $response = array('status'=> 0 ,'message'=> 'User wallet not updated');
        //         // }
        //         return $response;
           
        // }
         
        return $response;
    }
        

    public function click(Request $request)
    {
        $user = auth($this->guard)->user();
        $userid= $user['id'];
        $rewardAmount = $this->currentMiningReward((int) $userid);
        $lastClick =DB::table('tbl_user_mining')->where('userid',$userid)->latest()->first();
        $enable_status=1;
        $disable_status=0;
        // $user->miningButtonClicks()->latest()->first();

        if ($lastClick === null || Carbon::now()->diffInHours($lastClick->mining_start_time) >= 6) {

            $postData = [
                        'userid'=>$userid,
                        'mining_start_time'=>Carbon::now(),
                        'totalamount'=>$rewardAmount,
                        'created_at'=>now(),
                        
                    ];
            $postRef = DB::table('tbl_user_mining')->insert($postData);
            // $user->miningButtonClicks()->create([
            //     'clicked_at' => Carbon::now(),
            // ]);

            $nextMining = Carbon::now()->addHours(self::MINING_WINDOW_HOURS);
            return response()->json([
                'status' => 1,
                'message' => 'Button_enabled',
                'button_status' => $enable_status,
                'reward' => $rewardAmount,
                'rate' => $this->currentMiningRatePayload((int) $userid),
                'nextMining' => $nextMining->toDateTimeString(),
                'nextMiningTimestamp' => $nextMining->timestamp * 1000,
            ]);
        }

        $lastMiningStart = $lastClick->mining_start_time ?: ($lastClick->created_at ?: Carbon::now());
        $nextMining = Carbon::parse($lastMiningStart)->addHours(self::MINING_WINDOW_HOURS);
        return response()->json([
            'status' => 0,
            'message' => 'Button_still_disabled',
            'button_status' => $disable_status,
            'nextMining' => $nextMining->toDateTimeString(),
            'nextMiningTimestamp' => $nextMining->timestamp * 1000,
        ]);
    }


    public function clickButton(Request $request)
    {
        $user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        $userid = (int) $user['id'];

        try {
            DB::statement('SET SESSION innodb_lock_wait_timeout = 3');
        } catch (\Throwable $error) {
            Log::warning('Unable to set mining lock wait timeout', [
                'user_id' => $userid,
                'error' => $error->getMessage(),
            ]);
        }

        $now = Carbon::now();

        try {
            $lastClick = DB::table('button_clicks')
                ->where('user_id', $userid)
                ->orderByDesc('created_at')
                ->first();

            $currentPayload = $this->fastMiningPayload($lastClick, $now, $userid);

            if (!$currentPayload['can_mine']) {
                return response()->json(array_merge([
                    'status' => 0,
                    'message' => 'You need to wait before clicking the button again.',
                    'data' => $currentPayload,
                ], $currentPayload));
            }

            $rewardAmount = (float) $currentPayload['reward'];

            if ($lastClick) {
                $lastClickUpdateData = $this->withExistingTimestamps('button_clicks', [
                    'button_status' => 0,
                ], $now);

                DB::table('button_clicks')
                    ->where('id', $lastClick->id)
                    ->update($lastClickUpdateData);
            }

            $insertData = $this->withExistingTimestamps('button_clicks', [
                'user_id' => $userid,
                'rewards_start_time' => $now,
                'wallet' => $rewardAmount,
                'button_status' => 1,
            ], $now, true);

            $newClickId = DB::table('button_clicks')->insertGetId($insertData);
            $newClick = DB::table('button_clicks')->where('id', $newClickId)->first();
            $payload = $this->fastMiningPayload($newClick, $now, $userid);

            $result = [
                'newClickId' => (int) $newClickId,
                'payload' => $payload,
            ];
        } catch (\Throwable $error) {
            Log::error('Mining start failed', [
                'user_id' => $userid,
                'error' => $error->getMessage(),
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Mining could not start right now. Please try again in a moment.',
            ], 503);
        }

        $gamification = [
            'awarded' => false,
            'reason' => 'skipped',
            'xp_awarded' => 0,
        ];

        try {
            $gamification = $this->gamificationService->awardXp(
                $user,
                'mining_started',
                10,
                'button_click',
                (int) $result['newClickId']
            );
        } catch (\Throwable $error) {
            Log::warning('Mining started but gamification award failed', [
                'user_id' => $userid,
                'button_click_id' => $result['newClickId'],
                'error' => $error->getMessage(),
            ]);
        }

        return response()->json(array_merge([
            'status' => 1,
            'message' => 'Mining started and reward added to your wallet.',
            'data' => $result['payload'],
            'gamification' => $gamification,
        ], $result['payload']));
    }

    public function update_referral(Request $request){
        $user = auth($this->guard)->user();

        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        $result = $this->applyReferralCodeForUser(
            (int) $user->id,
            (string) $request->input('referred_by', $request->input('referral_code', ''))
        );

        return response()->json($result, $result['status'] ? 200 : 422);
    }

    public function team_referral(Request $request) {
		$user = auth($this->guard)->user();
        $userid= $user['id'];
        $referral_code= DB::table('res_users')->where('id',$userid)->first();
        if($referral_code->refferal_code !=''){
            $get_referral_code = $referral_code->refferal_code;
            $all_users = DB::table('res_users')->get();
            $team_referral = DB::table('referrals')->leftJoin('res_users', 'referrals.user_id', '=', 'res_users.id')->where('referred_by',$get_referral_code)->select('referrals.*','res_users.name','res_users.email')->get();
            $team_referral_count = DB::table('referrals')->where('referred_by',$get_referral_code)->count();
            if($team_referral_count > 0){
                $referred_by_user_data =$team_referral;
                return response()->json(['message'=>'Authenticated','message_title'=>'success','referred_by_user_data'=>$referred_by_user_data,'team_referral_count'=>$team_referral_count]);
            }else{
                $referred_by_user_data = [];
                return response()->json(['message'=>'No team found','referred_by_user_data'=>$referred_by_user_data]);
            }
        }else{
            return response()->json(['message'=>'No team found']);
        }
        
    }

    public function myNetwork(Request $request)
    {
        $user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        $filter = strtolower((string) $request->input('filter', 'all'));
        $referralCode = $this->ensureUserReferralCode($user);
        $network = $this->teamNetworkSnapshot((int) $user->id, true, $filter);

        return response()->json([
            'status' => 1,
            'message' => 'Authenticated',
            'message_title' => 'success',
            'referral_code' => $referralCode,
            'data' => $network,
        ]);
    }

    public function validateReferralCode(Request $request)
    {
        $code = strtoupper(trim((string) $request->input('referral_code', $request->input('referred_by', ''))));
        if ($code === '') {
            return response()->json(['status' => 0, 'message' => 'Referral code is required.'], 422);
        }

        $user = auth($this->guard)->user();
        if ($user && strtoupper($this->ensureUserReferralCode($user)) === $code) {
            return response()->json(['status' => 0, 'message' => 'You cannot use your own referral code.'], 422);
        }

        $referrer = DB::table('res_users')->where('refferal_code', $code)->first();
        if (!$referrer) {
            return response()->json(['status' => 0, 'message' => 'Referral code not found.'], 404);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Referral code is valid.',
            'data' => [
                'referral_code' => $code,
                'referrer' => [
                    'id' => (int) $referrer->id,
                    'name' => $referrer->name,
                    'username' => $referrer->username,
                ],
                'settings' => $this->referralSettingsPayload(),
            ],
        ]);
    }

    public function referralMissions(Request $request)
    {
        $user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        if (!Schema::hasTable('referral_missions')) {
            return response()->json([
                'status' => 0,
                'message' => 'Referral missions are not installed yet. Run database migrations first.',
            ], 422);
        }

        $network = $this->teamNetworkSnapshot((int) $user->id, false);
        $stats = $network['stats'] ?? [];
        $missions = DB::table('referral_missions')
            ->where('status', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function ($mission) use ($user, $stats) {
                $payload = $this->referralMissionPayload($mission, $stats);
                $claimed = $this->referralMissionClaimed((int) $user->id, $mission);
                $payload['claimed'] = $claimed;
                $payload['can_claim'] = !$claimed && $payload['is_complete'];

                return $payload;
            })
            ->values();

        return response()->json([
            'status' => 1,
            'message' => 'Authenticated',
            'message_title' => 'success',
            'stats' => $stats,
            'settings' => $this->referralSettingsPayload(),
            'data' => $missions,
        ]);
    }

    public function claimReferralMission(Request $request)
    {
        $user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        if (!Schema::hasTable('referral_missions') || !Schema::hasTable('user_referral_mission_claims')) {
            return response()->json([
                'status' => 0,
                'message' => 'Referral missions are not installed yet. Run database migrations first.',
            ], 422);
        }

        $missionId = (int) $request->input('mission_id');
        $mission = DB::table('referral_missions')
            ->where('id', $missionId)
            ->where('status', 1)
            ->first();

        if (!$mission) {
            return response()->json(['status' => 0, 'message' => 'Referral mission not found.'], 404);
        }

        $network = $this->teamNetworkSnapshot((int) $user->id, false);
        $payload = $this->referralMissionPayload($mission, $network['stats'] ?? []);

        if (!$payload['is_complete']) {
            return response()->json([
                'status' => 0,
                'message' => 'Mission is not complete yet.',
                'data' => $payload,
            ], 422);
        }

        if ($this->referralMissionClaimed((int) $user->id, $mission)) {
            return response()->json([
                'status' => 0,
                'message' => 'Mission reward already claimed.',
                'data' => $payload,
            ], 422);
        }

        $wallet = DB::transaction(function () use ($user, $mission) {
            $claimData = $this->withExistingTimestamps('user_referral_mission_claims', [
                'user_id' => (int) $user->id,
                'referral_mission_id' => (int) $mission->id,
                'reward_amount' => (float) $mission->reward_amount,
                'claim_date' => Carbon::now()->toDateString(),
            ], null, true);

            DB::table('user_referral_mission_claims')->insert($claimData);

            return $this->creditWallet((int) $user->id, (float) $mission->reward_amount);
        });

        $payload['claimed'] = true;
        $payload['can_claim'] = false;

        return response()->json([
            'status' => 1,
            'message' => 'Referral mission reward claimed.',
            'data' => $payload,
            'wallet' => $wallet,
        ]);
    }

    public function pingReferral(Request $request)
    {
        $user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        if (!Schema::hasTable('referral_pings')) {
            return response()->json([
                'status' => 0,
                'message' => 'Referral ping feature is not installed yet. Run database migrations first.',
            ], 422);
        }

        $recipientUserId = (int) ($request->input('referral_user_id') ?: $request->input('user_id'));
        if ($recipientUserId <= 0) {
            return response()->json(['status' => 0, 'message' => 'Referral user is required'], 422);
        }

        $messageType = strtolower((string) $request->input('message_type', 'mine_reminder'));
        if (!array_key_exists($messageType, self::REFERRAL_PING_TYPES)) {
            return response()->json([
                'status' => 0,
                'message' => 'Unsupported referral ping type.',
                'ping_types' => $this->referralPingTypesForApi(),
            ], 422);
        }

        $senderUserId = (int) $user->id;
        $senderReferralCode = $this->ensureUserReferralCode($user);
        $pingDailyLimit = $this->referralPingDailyLimit();
        $recipient = DB::table('res_users')->where('id', $recipientUserId)->first();

        if (!$recipient) {
            return response()->json(['status' => 0, 'message' => 'Referral user not found'], 404);
        }

        $referral = DB::table('referrals')
            ->where('user_id', $recipientUserId)
            ->where(function ($query) use ($senderUserId, $senderReferralCode) {
                $query->where('referred_by', $senderUserId);

                if (!empty($senderReferralCode)) {
                    $query->orWhere('referred_by', $senderReferralCode);
                }
            })
            ->first();

        if (!$referral) {
            return response()->json([
                'status' => 0,
                'message' => 'You can ping only your direct referral users.',
            ], 403);
        }

        $now = Carbon::now();
        $lastMining = DB::table('button_clicks')
            ->where('user_id', $recipientUserId)
            ->orderByDesc('created_at')
            ->first();

        if ($lastMining && Carbon::parse($lastMining->created_at)->greaterThanOrEqualTo($now->copy()->subHours(self::MINING_WINDOW_HOURS))) {
            return response()->json([
                'status' => 0,
                'message' => 'This referral has mined recently. Ping is available when they are inactive.',
                'last_mined_at' => $lastMining->created_at,
            ]);
        }

        $pingCountToday = DB::table('referral_pings')
            ->where('sender_user_id', $senderUserId)
            ->where('recipient_user_id', $recipientUserId)
            ->where('created_at', '>=', $now->copy()->startOfDay())
            ->count();

        if ($pingCountToday >= $pingDailyLimit) {
            return response()->json([
                'status' => 0,
                'message' => 'Daily ping limit reached for this referral.',
                'ping_count_today' => $pingCountToday,
                'ping_daily_limit' => $pingDailyLimit,
            ]);
        }

        $pingData = $this->withExistingTimestamps('referral_pings', [
            'sender_user_id' => $senderUserId,
            'recipient_user_id' => $recipientUserId,
            'referral_id' => $referral->id,
            'message_type' => $messageType,
            'status' => 'sent',
        ], $now, true);

        $pingId = DB::table('referral_pings')->insertGetId($pingData);

        $senderName = $user->username ?: ($user->name ?: 'Your referral sponsor');
        $template = $this->referralPingTemplate($messageType);
        $title = $template['title'];
        $body = sprintf($template['body'], $senderName);
        $notificationData = [
            'ping_id' => (string) $pingId,
            'sender_user_id' => (string) $senderUserId,
            'type' => 'REFERRAL_PING',
            'message_type' => $messageType,
        ];

        $this->insertUserNotification($recipientUserId, 'REFERRAL_PING', $body, $notificationData);

        if (!empty($recipient->device_id)) {
            SendPushNotificationJob::dispatch($recipient->device_id, $recipientUserId, $title, $body, $notificationData);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Referral ping sent successfully.',
            'ping_id' => $pingId,
            'message_type' => $messageType,
            'ping_count_today' => $pingCountToday + 1,
            'ping_daily_limit' => $pingDailyLimit,
        ]);
    }
    
    // public function get_notification(Request $request){
    //     $user = auth($this->guard)->user();
    //     $userid = $user['id'];
    //     $notifications = DB::table('notifications')->where('notifiable_id', $userid)->where('read_at', null)->get();
    //     $data = json_decode($notifications->data, true);
    //     $message = $data['data']['message'];
    //     $message = str_replace(array("\r", "\n"), '', $message);
    //     $message = trim(preg_replace('/\s+/', ' ', $message));
    //     $rewardmessage =$data['data']['message'] = $message;
    //     $notifications->data = json_encode($data);
    //     $notification_date = $notifications->created_at;
    //     $response = array('status'=> 1 ,'message'=> 'Authenticated','notifications'=> $rewardmessage,'Date'=>$notification_date);
    //     return $response;
    // }
    
    public function get_notification(Request $request){
    $user = auth($this->guard)->user();
    $userid = $user['id'];
    $notifications = DB::table('notifications')->where('notifiable_id', $userid)->where('read_at', null)->get();
    
    $rewardmessage = '';
    $notification_date = '';
    $read_at = '';
    $totalcoin = '';
    foreach($notifications as $notification) {
        $data = json_decode($notification->data, true);
        $message = $data['data']['message'];
        $message = str_replace(array("\r", "\n"), '', $message);
        $message = trim(preg_replace('/\s+/', ' ', $message));
        $rewardmessage .= $message . "\n\n";
        $notification_date = $notification->created_at;
        $notification->data = json_encode($data);
        $read_at = $notification->read_at;
        $coin = isset($data['data']['coin']) ? $data['data']['coin'] : null;

        if ($coin) {
            $totalcoin = $coin;
        } else {
            $totalcoin = '';
        }

        
    }
    
    $response = array('status'=> 1 ,'message'=> 'Authenticated','notifications'=> $rewardmessage,'Date'=>$notification_date,'Read_at'=>$read_at,'Coin'=> $totalcoin);
    return $response;
}

    

    public function markAsRead(Request $request){
        $user = auth($this->guard)->user();
        $userid = $user['id'];
        $notifications = $user->unreadNotifications->markAsRead();
        $notifications = DB::table('notifications')->where('notifiable_id', $userid)->where('read_at', null)->update(['read_at'=>now()]);
        $response = array('status'=> 1 ,'message'=> 'You have read the message','notifications'=> $notifications);
        return $response;
    }
    
    public function count_notification(Request $request){
        $user = auth($this->guard)->user();
        $userid = $user['id'];
        $notifications = DB::table('notifications')->where('notifiable_id', $userid)->where('read_at', null)->get();
        $countnotifications=$notifications->count();
        $response = array('status'=> 1 ,'message'=> 'You have read the message','countnotifications'=> $countnotifications);
        return $response;
    }
    
    // public function isActive(Request $request){
    //     $user = auth($this->guard)->user();
    //     $userid = $user['id'];
    //     $is_active = ResUser::where('id',$userid)->first();
    //     print_r($is_active->is_active);
    //     if($is_active){
    //         $response = array('status'=> 1 ,'message'=> 'Hello! We regret to inform you that your account has been suspended due to a violation of our app\'s policies. We take our policies seriously to ensure a safe and fair experience for all of our users.If you believe that this suspension was made in error, please contact us to discuss the matter further. However, if the violation was intentional or due to negligence, we ask that you review our policies and take appropriate steps to rectify the situation.We appreciate your understanding and cooperation in this matter. Thank you for using our app.','is_active'=> $is_active);
        
    //     return $response;
    //     }
    // }
    
    public function isActive(Request $request){
        $user = auth($this->guard)->user();
        $userid = $user['id'];
    
        $suspended_user = ResUser::where('id', $userid)
            ->where('is_active', '=', 0)
            ->first();
    
        if ($suspended_user) {
            $response = array(
                'status' => 1,
                'message' => 'Hello! We regret to inform you that your account has been suspended due to a violation of our app\'s policies. We take our policies seriously to ensure a safe and fair experience for all of our users. If you believe that this suspension was made in error, please contact us to discuss the matter further. However, if the violation was intentional or due to negligence, we ask that you review our policies and take appropriate steps to rectify the situation. We appreciate your understanding and cooperation in this matter. Thank you for using our app.',
                'is_active' =>$suspended_user->is_active
                
            );
    
            return $response;
        } 
        else {
            // Handle case where user is active or not found
            // For example:
            return response()->json(['status' => 0, 'message' => 'Your account is currently active.','is_active' =>'1']);
            
        }
    }
    


        public function refferalNotification(Request $request)
        {
            $user = auth($this->guard)->user();
            $notifiable_id = $user->id;
           
        
            $notifications = DB::table('notifications')
                            ->where('notifiable_id', $notifiable_id)
                            ->where('notification_type','Refferal Add')
                            ->where('read_at', null)
                            ->get();
        
            if (count($notifications) === 0) {
                return ['message' => 'No notifications'];
            }
        
           $formatted_notifications = [];
            foreach ($notifications as $notification) {
                $data = json_decode($notification->data, true);
                $message = $data['data']['message'];
                $message = preg_replace('/\s+/', ' ', $message);
                $notification_date = date('Y-m-d H:i:s', strtotime($notification->created_at));
        
                $formatted_notifications[] = [
                    'message' => $message,
                    'date' => $notification_date
                ];
            }

        
            return $formatted_notifications;
        }
        
    public function refferalNotificationCount(Request $request)
    {
        $user = auth($this->guard)->user();
        $userid = $user['id'];
    
        $refferalNotificationCount =  DB::table('notifications')
                            ->where('notifiable_id', $userid)
                            ->where('notification_type','Refferal Add')
                            ->where('read_at', null)
                            ->get();
        if (count($refferalNotificationCount) === 0) {
            return ['message' => 'No notifications'];
        }
    
        $data = count($refferalNotificationCount);
    
            return [
                'refferalNotificationCount' => $data
            ];
        }
        
    public function refferalNotificationRead($id){
       $user = auth($this->guard)->user();
        $userid = $user['id'];
           $userUnreadNotification = auth()->user()
                                          ->unreadNotifications
                                          ->where('id', $id)
                                          ->first();
            
          if($userUnreadNotification) {
            $userUnreadNotification->markAsRead();
            $response = array('status'=> 1 ,'message'=> 'You have read the message');
          }
        
        return $response;
    }
    
    

     public function allNotification(Request $request)
        {
            $user = auth($this->guard)->user();
            $notifiable_id = $user->id;
           
        
            $notifications = DB::table('notifications')
                            ->where('notifiable_id', $notifiable_id)
                            ->where('read_at', null)
                            ->get();
        
            if (count($notifications) === 0) {
                return ['data'=>[],'message' => 'No notifications'];
            }
        
           $formatted_notifications = [];
            foreach ($notifications as $notification) {
                $data = json_decode($notification->data, true);
                $message = $data['data']['message'] ?? 'You have a new notification.';
                $notification_type =$notification->notification_type;
                $message = preg_replace('/\s+/', ' ', $message);
                $notification_date = date('Y-m-d H:i:s', strtotime($notification->created_at));
                $coin = $data['data']['coin'] ?? 0;
                $formatted_notifications[] = [
                    'title' => $data['title'] ?? ($data['subject'] ?? 'Notification'),
                    'message' => $message,
                    'date' => $notification_date,
                    'notification_type'=>$notification_type,
                    'action' => $data['action'] ?? null,
                    'coin' => $coin,
                ];
            }

        
            return $formatted_notifications;
        }
        
    public function EmailVerify(Request $request) {
        $logeduser = auth($this->guard)->user();
        $email = $request->input('email');
        
        if (!empty($email)) {
            $user = ResUser::where('email', $email)->first();
    
            if ($user) {
                // Check for an existing valid OTP
                $verificationCode = VerificationCode::where('user_id', $user->id)
                    ->latest('expire_at')
                    ->first();
                    
                $now = Carbon::now();
    
                if ($verificationCode && $now->isBefore($verificationCode->expire_at)) {
                    return response()->json(['success' => true, 'verificationCode' => $verificationCode]);
                     $to = $user->email;
                            $data = [
                                'otp' => $verificationCode, 
                            ];
                    
                            $content = view('frontend.email_templates.email-otp', compact('data'))->render();;
                    
                            $subject = "Earnflow Account : Email Address Update Verification";
                            $headers = "MIME-Version: 1.0" . "\r\n";
                            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                            $headers .= 'From: no-replay@blockrium.com' . "\r\n";
                    
                            mail($to, $subject, $content, $headers);
                }
    
                // Create a New OTP
                $newOtp = random_int(100000, 999999);
    
                $insertedData = VerificationCode::create([
                    'user_id' => $user->id,
                    'email_otp' => $newOtp,
                    'expire_at' => Carbon::now()->addMinutes(10)
                ]);
    
                if ($insertedData) {
                    // Session::put('member_username', $user->email);
                    // Session::put('member_userid', $user->userid);
                    $getotp = VerificationCode::where('id', $insertedData->id)->first();
                            $to = $user->email;
                            $data = [
                                'otp' => $getotp->email_otp, 
                                'username'=>$user->email,
                            ];
                    
                            $content = view('frontend.email_templates.email-otp', compact('data'))->render();;
                    
                            $subject = "Earnflow Account : Email Address Update Verification";
                            $headers = "MIME-Version: 1.0" . "\r\n";
                            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                            $headers .= 'From: no-replay@blockrium.com' . "\r\n";
                    
                            mail($to, $subject, $content, $headers);
                    return response()->json(['success' => true, 'message' => 'OTP sent to your email.']);
                }
            } else {
                return response()->json(['success' => false, 'message' => 'User does not exist.']);
            }
        } else {
            return response()->json(['success' => false, 'message' => 'Invalid email provided.']);
        }
    }
    
    

    public function verifyOtp(Request $request) {
        $logeduser = auth($this->guard)->user();
        if ($logeduser) {
            $verificationCode = VerificationCode::where('user_id', $logeduser->id)
                ->where('email_otp', $request->otp)
                ->first();
    
            $now = Carbon::now();
    
            if (!$verificationCode) {
                return response()->json(['success' => 'Your OTP is not correct']);
            } elseif ($now->isAfter($verificationCode->expire_at)) {
                return response()->json(['success' => 'Your OTP has expired']);
            }
    
            $user = ResUser::where('id',$logeduser->id)->first();
    
            if ($user) {
              
                $verificationCode->update([
                    'expire_at' => Carbon::now()
                ]);
                $user->update([
                    'email_verified_at' => Carbon::now()
                ]);
                DB::table('res_users')
                    ->where('id', $logeduser->id)
                    ->update(['email_verified_at' =>  Carbon::now()]);
    
                return response()->json(['success' => 'Your email is verified']);
            } else {
                return response()->json(['success' => 'Something went wrong']);
            }
        } else {
            return response()->json(['success' => 'Something data missing']);
        }
    }
    
    public function TransferBrcToAnotherUser(Request $request){
        $user = auth($this->guard)->user();
        $userid = $user['id'];
        
            
        $response = array('status'=> 1 ,'message'=> 'You have read the message','notifications'=> $notifications);
        return $response;
    }
    
    public function GetFingerPrintStatus(Request $request) {
        $logeduser = auth($this->guard)->user();
        if ($logeduser) {
            $getuser_f_print_data= DB::table('check_fprint')->where('userid',$logeduser->id)->first();
           // print_r($getuser_f_print_data);
            if($getuser_f_print_data != null){
                return response()->json(['success' => 'Success','data'=>$getuser_f_print_data]);
            }else{
                return response()->json(['success' => 'Success','data'=>'User data not exist']);
            }
        } else {
            return response()->json(['error' => 'User not logged in']);
        }
    }
    
    
    public function UpdateFingerPrintStatus(Request $request) {
        $logeduser = auth($this->guard)->user();
        if ($logeduser) {
            $getuser_f_print_data= DB::table('check_fprint')->where('userid',$logeduser->id)->first();
           // print_r($getuser_f_print_data);
            if($getuser_f_print_data != null){
                $fingerprintUpdateData = $this->withExistingTimestamps('check_fprint', [
                    'status' => $request->status,
                ]);

                $update=DB::table('check_fprint')
                    ->where('userid', $logeduser->id)
                    ->update($fingerprintUpdateData);
                if($update){
                    $getuser_f_data= DB::table('check_fprint')->where('userid',$logeduser->id)->first();
                    return response()->json(['success' => 'Success','data'=>$getuser_f_data,'message'=>'Fingerprint data updated successfully updated']);
                }else{
                    return response()->json(['error' => 'Somthing went wrong']);
                }
                
            }else{
                 $postData = [
                    'userid'=>$logeduser->id,
                    'status'=>$request->status,
                    'created_at'=>now(),          
                ];
                $postdata = DB::table('check_fprint')->insert($postData);
                if($postdata){
                    $getuser_f_data= DB::table('check_fprint')->where('userid',$logeduser->id)->first();
                    return response()->json(['success' => 'Success','data'=>$getuser_f_data,'message'=>'Fingerprint data inserted successfully updated']);
                }
                
            }
        } else {
            return response()->json(['error' => 'User not logged in']);
        }
    }

    public function boostPlans(Request $request)
    {
        $storeEnabled = $this->integrationSettings->boolean('boost_store_enabled', false);
        $googlePlay = app(\App\Services\GooglePlayBillingService::class);
        $checkoutEnabled = $this->integrationSettings->boolean('boost_checkout_enabled', false)
            && $googlePlay->isConfigured();
        if (!$storeEnabled) {
            return response()->json([
                'status' => 1,
                'store_enabled' => false,
                'checkout_enabled' => $checkoutEnabled,
                'message' => 'Mining boost purchases are temporarily unavailable.',
                'data' => [],
            ]);
        }

        if (!Schema::hasTable('boost_plans')) {
            return response()->json([
                'status' => 0,
                'message' => 'Boost plans are not installed yet. Run database migrations first.',
                'data' => [],
            ]);
        }

        $plans = DB::table('boost_plans')
            ->where('status', 1)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();

        $plans = $plans->map(function ($plan) use ($googlePlay) {
            $mapping = $googlePlay->boostProductMapping($plan);
            $plan->google_play_product_id = $mapping['product_id'];
            $plan->google_play_base_plan_id = $mapping['base_plan_id'];
            $plan->purchase_ready = $googlePlay->isConfigured()
                && $mapping['product_id'] !== ''
                && $mapping['base_plan_id'] !== '';
            $plan->benefit_summary = 'Adds ' . rtrim(rtrim(number_format((float) $plan->boost_percent, 2, '.', ''), '0'), '.')
                . '% to the configured mining rate for the active subscription period.';
            $plan->earnings_disclaimer = 'A rate increase does not guarantee a token amount, token value, exchange listing, or cash return.';
            return $plan;
        });

        return response()->json([
            'status' => 1,
            'message' => 'Authenticated',
            'store_enabled' => true,
            'checkout_enabled' => $checkoutEnabled,
            'google_play_ready' => $googlePlay->isConfigured(),
            'data' => $plans,
        ]);
    }

    public function myBoost(Request $request)
    {
        $user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Authenticated',
            'data' => $this->currentMiningRatePayload((int) $user->id, true),
        ]);
    }

    public function createBoostPurchase(Request $request)
    {
        $user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        return response()->json([
            'status' => 0,
            'message' => 'Client-reported boost purchases are no longer accepted. Create a Google Play purchase intent and submit its purchase token for server verification.',
            'purchase_intent_endpoint' => '/api/auth/google-play/purchase-intents',
        ], 410);
    }

    public function boostPurchaseStatus(Request $request, int $id)
    {
        $user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        if (!Schema::hasTable('user_boosts')) {
            return response()->json(['status' => 0, 'message' => 'Boost purchases are not installed.'], 422);
        }

        $purchase = DB::table('user_boosts as b')
            ->leftJoin('boost_plans as p', 'b.boost_plan_id', '=', 'p.id')
            ->where('b.id', $id)
            ->where('b.user_id', (int) $user->id)
            ->select('b.*', 'p.name as plan_name', 'p.provider as plan_provider')
            ->first();

        if (!$purchase) {
            return response()->json(['status' => 0, 'message' => 'Boost purchase not found.'], 404);
        }

        return response()->json([
            'status' => 1,
            'message' => 'Boost purchase status retrieved.',
            'data' => [
                'purchase_id' => (int) $purchase->id,
                'plan_name' => $purchase->plan_name,
                'provider' => $purchase->provider,
                'status' => $purchase->status,
                'payment_status' => $purchase->payment_status,
                'starts_at' => $purchase->starts_at,
                'ends_at' => $purchase->ends_at,
                'current_period_ends_at' => property_exists($purchase, 'current_period_ends_at') ? $purchase->current_period_ends_at : null,
                'active_rate' => $this->currentMiningRatePayload((int) $user->id, false),
            ],
        ]);
    }

    private function createDodoBoostPurchase($user, $plan, Request $request)
    {
        if (strtolower((string) ($plan->provider ?? '')) !== 'dodo' || empty($plan->provider_product_id)) {
            return response()->json([
                'status' => 0,
                'message' => 'This boost plan is not configured for Dodo Payments yet.',
            ], 422);
        }

        $recent = DB::table('user_boosts')
            ->where('user_id', (int) $user->id)
            ->where('boost_plan_id', $plan->id)
            ->where('provider', 'dodo')
            ->where('payment_status', 'pending')
            ->where('created_at', '>=', Carbon::now()->subMinutes(15))
            ->orderByDesc('id')
            ->first();

        if ($recent && !empty($recent->checkout_session_id)) {
            return response()->json([
                'status' => 1,
                'message' => 'A Dodo checkout is already open for this boost plan.',
                'data' => [
                    'purchase_id' => (int) $recent->id,
                    'checkout_url' => $recent->metadata ? (json_decode($recent->metadata, true)['checkout_url'] ?? null) : null,
                    'payment_status' => $recent->payment_status,
                    'status' => $recent->status,
                ],
            ]);
        }

        $now = Carbon::now();
        $purchaseData = $this->withExistingTimestamps('user_boosts', [
            'user_id' => (int) $user->id,
            'boost_plan_id' => $plan->id,
            'provider' => 'dodo',
            'purchase_reference' => 'dodo:pending:' . (string) Str::uuid(),
            'amount_paid' => $plan->price,
            'currency' => $plan->currency,
            'boost_percent' => $plan->boost_percent,
            'starts_at' => null,
            'ends_at' => null,
            'status' => 'pending',
            'payment_status' => 'pending',
            'metadata' => json_encode([
                'client_platform' => $request->input('platform'),
                'requested_at' => $now->toDateTimeString(),
            ]),
        ], $now, true);

        $purchaseId = DB::table('user_boosts')->insertGetId($purchaseData);

        try {
            $checkout = $this->dodoBoostService->createCheckoutSession($user, $plan, (int) $purchaseId);
        } catch (\Throwable $exception) {
            DB::table('user_boosts')->where('id', $purchaseId)->update([
                'status' => 'failed',
                'payment_status' => 'failed',
                'metadata' => json_encode(['error' => 'checkout_creation_failed', 'requested_at' => $now->toDateTimeString()]),
                'updated_at' => now(),
            ]);
            throw $exception;
        }

        $metadata = [
            'checkout_url' => $checkout['checkout_url'],
            'checkout_session_id' => $checkout['session_id'],
            'client_secret_available' => !empty($checkout['client_secret']),
            'requested_at' => $now->toDateTimeString(),
        ];
        DB::table('user_boosts')->where('id', $purchaseId)->update(array_filter([
            'checkout_session_id' => $checkout['session_id'],
            'metadata' => json_encode($metadata),
            'updated_at' => now(),
        ], function ($value) {
            return $value !== null;
        }));

        return response()->json([
            'status' => 1,
            'message' => 'Dodo mining boost checkout created. The boost activates after webhook confirmation.',
            'data' => [
                'purchase_id' => (int) $purchaseId,
                'plan' => $plan,
                'provider' => 'dodo',
                'checkout_url' => $checkout['checkout_url'],
                'session_id' => $checkout['session_id'],
                'client_secret' => $checkout['client_secret'],
                'payment_status' => 'pending',
                'status' => 'pending',
            ],
        ]);
    }

    public function GetBoosterMining(Request $request) {
      	$user = auth($this->guard)->user();
        if ($user) {
            $userid = (int) $user['id'];
            $rate = $this->currentMiningRatePayload($userid, false);
            $network = $rate['network_stats'] ?? [];
            $teamReferralCount = (int) ($network['direct_count'] ?? 0);
            $booster = (float) ($rate['boost_percent'] ?? 0);
            $miningRate = (float) ($rate['mining_rate'] ?? self::MINING_REWARD);
            $dailyTotal = round($miningRate * (24 / self::MINING_WINDOW_HOURS), 4);

            return response()->json([
                'status' => 1,
                'message' => 'Authenticated',
                'message_title' => 'success',
                'reffera' => $teamReferralCount,
                'booster' => $booster,
                'mining' => $miningRate,
                'total' => $dailyTotal,
                'rate' => $rate,
            ]);
        } else {
            return response()->json(['error' => 'User not logged in']);
        }
    }
    
//     public function GetBoosterMining(Request $request) {
//     $user = auth($this->guard)->user();
//     if (!$user) {
//         return response()->json(['error' => 'User not logged in']);
//     }
    
//     $userId = $user['id'];
    
//     // Get the total balance for each user
//     $users = DB::table('res_users')
//         ->leftJoin('tblreward', 'res_users.id', '=', 'tblreward.userid')
//         ->leftJoin('tblattendance', 'res_users.id', '=', 'tblattendance.userid')
//         ->leftJoin('tbl_user_mining', 'res_users.id', '=', 'tbl_user_mining.userid')
//         ->leftJoin('tbluserwallet', 'res_users.id', '=', 'tbluserwallet.userid')
//         ->leftJoin('referrals', 'res_users.id', '=', 'referrals.user_id')
//         ->leftJoin('button_clicks as bc', 'res_users.id', '=', 'bc.user_id')
//         ->leftJoin(DB::raw('(SELECT user_id, SUM(wallet) as total FROM button_clicks GROUP BY user_id) AS button_clicks_total'), function ($join) {
//             $join->on('res_users.id', '=', 'button_clicks_total.user_id');
//         })
//         ->where('res_users.is_active', 1)
//         ->select('res_users.id', 'res_users.name', 'res_users.user_image', 
//             DB::raw('IFNULL(tblreward.token, 0) + IFNULL(tblattendance.coins, 0) + 
//                 IFNULL(tbl_user_mining.totalamount, 0) + IFNULL(tbluserwallet.totaltoken, 0) + 
//                 IFNULL(referrals.rewards, 0) + IFNULL(button_clicks_total.total, 0) as total_balance')
//         )
//         ->orderByDesc('total_balance')
//         ->get();

//     // Find the position and total balance of the authenticated user
//     $authUserPosition = null;
//     $authUserTotalBalance = null;
//     foreach ($users as $index => $u) {
//         if ($u->id == $userId) {
//             $authUserPosition = $index + 1;
//             $authUserTotalBalance = $u->total_balance;
//             break;
//         }
//     }
    
//     if ($authUserPosition === null) {
//         // Authenticated user not found in the list
//         return response()->json(['error' => 'Authenticated user not found']);
//     }

//     // Retrieve referral code
//     $referral_code = DB::table('res_users')->where('id', $userId)->value('refferal_code');

//     if (!$referral_code) {
//         return response()->json(['message' => 'No data found']);
//     }

//     // Count team referrals
//     $team_referral_count = DB::table('referrals')->where('referred_by', $referral_code)->count();
    
//     // Calculate total
//     $mining_ret = 0.5;
//     $booster = 0.0;
//     $total = ($team_referral_count > 0) ? ($team_referral_count * 0.1 + $booster * 24 + $mining_ret * 24) : ($booster * 24 + $mining_ret * 24);

//     return response()->json([
//         'message' => 'Authenticated',
//         'message_title' => 'success',
//         'reffera' => $team_referral_count,
//         'booster' => $booster,
//         'mining' => $mining_ret,
//         'total' => $total,
//         'authUserPosition' => $authUserPosition,
//         'authUserTotalBalance' => $authUserTotalBalance
//     ]);
// }

    
//     public function GetBoosterMining(Request $request) {
//     $user = auth($this->guard)->user();
//     if (!$user) {
//         return response()->json(['error' => 'User not logged in']);
//     }
    
//     $userId = $user['id'];
//     $subquery = DB::table('button_clicks')
//         ->selectRaw('user_id, SUM(wallet) as total')
//         ->groupBy('user_id');

//     $users = DB::table('res_users')
//         ->leftJoinSub($subquery, 'button_clicks', 'res_users.id', '=', 'button_clicks.user_id')
//         ->leftJoin('tblreward', 'res_users.id', '=', 'tblreward.userid')
//         ->leftJoin('tblattendance', 'res_users.id', '=', 'tblattendance.userid')
//         ->leftJoin('tbl_user_mining', 'res_users.id', '=', 'tbl_user_mining.userid')
//         ->leftJoin('tbluserwallet', 'res_users.id', '=', 'tbluserwallet.userid')
//         ->leftJoin('referrals', 'res_users.id', '=', 'referrals.user_id')
//         ->leftJoin('button_clicks as bc', 'res_users.id', '=', 'bc.user_id')
//         ->where('res_users.is_active', 1)
//         ->select('res_users.id', 'res_users.name', 'res_users.user_image', 
//             DB::raw('IFNULL(tblreward.token, 0) + IFNULL(tblattendance.coins, 0) + 
//                 IFNULL(tbl_user_mining.totalamount, 0) + IFNULL(tbluserwallet.totaltoken, 0) + 
//                 IFNULL(referrals.rewards, 0) + IFNULL(SUM(bc.wallet), 0) as total_balance')
//         )
//         ->groupBy('res_users.id')
//         ->orderByDesc('total_balance')
//         ->get();

//     $authUserPosition = $users->pluck('id')->search($userId) + 1;
//     $referral_code = DB::table('res_users')->where('id', $userId)->value('refferal_code');

//     if (!$referral_code) {
//         return response()->json(['message' => 'No data found']);
//     }

//     $team_referral_count = DB::table('referrals')->where('referred_by', $referral_code)->count();
//     $mining_ret = 0.5;
//     $booster = 0.0;

//     $total = (($team_referral_count > 0) ? ($team_referral_count * 0.1 + $booster * 24 + $mining_ret * 24) : ($booster * 24 + $mining_ret * 24));

//     return response()->json([
//         'message' => 'Authenticated',
//         'message_title' => 'success',
//         'reffera' => $team_referral_count,
//         'booster' => $booster,
//         'mining' => $mining_ret,
//         'total' => $total,
//         'authUserPosition' => $authUserPosition
//     ]);
// }

    public function PostBoosterMining(Request $request) {
        $logeduser = auth($this->guard)->user();
        if (!$logeduser) {
            return response()->json(['error' => 'User not logged in']);
        }

        return response()->json([
            'status' => 0,
            'message' => 'Mining boosts are paid plans now. Choose a boost plan and complete payment before activation.',
            'requires_payment' => true,
            'plans_endpoint' => url('api/auth/boost-plans'),
            'purchase_endpoint' => url('api/auth/boost-purchase'),
        ], 402);
    }
    
    public function AdsvideosaddCoin(Request $request){
        $user = auth($this->guard)->user();
        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'User not logged in'], 401);
        }

        $userid = (int) $user['id'];

        return DB::transaction(function () use ($userid) {
            $updatedWallet = $this->creditWallet($userid, self::AD_VIDEO_REWARD);
            $this->insertRewardNotification(
                $userid,
                (float) self::AD_VIDEO_REWARD,
                'Ad reward received',
                'Congratulations! 1 BRC has been added to your wallet for watching the ad.'
            );

            return response()->json([
                'status' => 1,
                'message' => 'Success',
                'wallet_bal' => $updatedWallet,
                'current_wallet_bal' => $updatedWallet->totaltoken,
                'added_coin' => self::AD_VIDEO_REWARD,
            ]);
        });
    }
    
    public function GetTaskList(Request $request) {
        $logeduser = auth($this->guard)->user();
    
        if ($logeduser) {
            // Get the tasks, left joining with the 'task_claimeds' table
            $gettasks = DB::table('tasks')
                ->leftJoin('task_claimeds', function ($join) use ($logeduser) {
                    $join->on('tasks.id', '=', 'task_claimeds.task_id')
                        ->where('task_claimeds.user_id', '=', $logeduser->id);
                })
                ->select('tasks.*', DB::raw('IF(task_claimeds.id IS NULL, 0, 1) as is_claimed'))
                ->get();
    
            // Iterate over each task and modify the task_icon field to include the base URL
            foreach ($gettasks as $task) {
                if ($task->task_icon) {
                    // Dynamically generate the full URL for the task_icon
                    $task->task_icon = asset('taskicon/' . $task->task_icon);
                } else {
                    // If no image exists, set task_icon to null or a default image URL
                    $task->task_icon = null; // Or use a placeholder image URL
                }
            }
    
            // Return response with modified task list
            return response()->json([
                'status' => '200',
                'message' => 'Task Fetched Successfully',
                'data' => $gettasks
            ]);
    
        } else {
            return response()->json(['error' => 'User not logged in']);
        }
    }

    
    public function ClaimedTaskReward(Request $request) {
        // Get the logged-in user only once
        $user = auth($this->guard)->user();
    
        // Check if the user is logged in
        if (!$user) {
            return response()->json(['error' => 'User not logged in'], 401);
        }
    
        // Validate the input
        $validator = Validator::make($request->all(), [
            'task_id' => 'required|numeric',
        ]);
    
        // Handle validation errors
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
    
        return DB::transaction(function () use ($request, $user) {
            $task = DB::table('tasks')
                ->where('id', $request->task_id)
                ->lockForUpdate()
                ->first();

            if (!$task) {
                return response()->json(['error' => 'Task not found'], 404);
            }

            $claimedTask = DB::table('task_claimeds')
                ->where('user_id', $user->id)
                ->where('task_id', $request->task_id)
                ->lockForUpdate()
                ->exists();

            if ($claimedTask) {
                return response()->json(['error' => 'You have already claimed this task'], 400);
            }

            $reward = (float) $task->coin_reward;

            $claimData = $this->withExistingTimestamps('task_claimeds', [
                'task_id' => $request->task_id,
                'user_id' => $user->id,
                'coin_reward' => $reward,
            ], null, true);

            DB::table('task_claimeds')->insert($claimData);

            $wallet = $this->creditWallet((int) $user->id, $reward);
            $gamification = $this->gamificationService->awardXp(
                $user,
                'task_claimed',
                10,
                'task',
                (int) $request->task_id
            );
            $this->insertRewardNotification(
                (int) $user->id,
                $reward,
                'Task reward received',
                "Congratulations! {$reward} BRC has been added to your wallet.",
                ['task_id' => (int) $request->task_id]
            );

            return response()->json([
                'status' => 200,
                'message' => 'Task Claimed Successfully.',
                'reward' => $reward,
                'wallet_bal' => $wallet,
                'gamification' => $gamification,
            ]);
        });
    }
    
    public function updateDeviceId(Request $request) {
        $user = auth($this->guard)->user();
        // Log::info("Device id :::: " . json_encode($user));
        if (!$user) {
            return response()->json(['error' => 'User not logged in'], 401);
        }
       
        $validator = Validator::make($request->all(), [
            'device_id' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        
        $data=ResUser::where('id',$user->id)->update(['device_id' => $request->device_id]);
        // Log::info("Device id data:::: " . json_encode($data));
        if($data){
            return response()->json(['status' => 200, 'message' => '']);
        }
        
    }

    
    
    
}
