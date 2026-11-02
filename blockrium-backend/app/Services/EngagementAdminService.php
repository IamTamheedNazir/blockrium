<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EngagementAdminService
{
    private const HEALTH_TABLES = [
        'user_xp_events',
        'badges',
        'user_badges',
        'reward_seasons',
        'referral_milestones',
        'referral_milestone_claims',
        'trust_center_sections',
        'learn_modules',
        'learn_questions',
        'user_learn_progress',
        'squads',
        'squad_members',
        'squad_invites',
        'squad_weekly_chests',
        'squad_share_events',
        'squad_missions',
        'squad_mission_claims',
        'squad_invite_streak_claims',
        'squad_announcement_templates',
        'squad_announcements',
    ];

    private const API_ROUTES = [
        '/api/trust-center',
        '/api/auth/daily-missions',
        '/api/auth/gamification/summary',
        '/api/auth/gamification/badges',
        '/api/auth/referrals/journey',
        '/api/auth/learn/modules',
        '/api/auth/squads/me',
        '/api/auth/squads/leaderboard',
        '/api/auth/squads/share-card',
        '/api/auth/squads/missions',
        '/api/auth/squads/announcements',
        '/api/squads/invite/{code}',
    ];

    public function dashboard(string $period = '7days'): array
    {
        $periodStart = $this->periodStart($period);
        $xpAvailable = Schema::hasTable('user_xp_events');
        $badgesAvailable = Schema::hasTable('badges') && Schema::hasTable('user_badges');
        $claimsAvailable = Schema::hasTable('referral_milestone_claims');

        $stats = [
            'total_xp' => $xpAvailable ? (int) DB::table('user_xp_events')->sum('xp_amount') : 0,
            'xp_today' => $xpAvailable ? (int) DB::table('user_xp_events')->where('awarded_at', '>=', now()->startOfDay())->sum('xp_amount') : 0,
            'xp_7_days' => $xpAvailable ? (int) DB::table('user_xp_events')->where('awarded_at', '>=', now()->subDays(7))->sum('xp_amount') : 0,
            'users_with_xp' => $xpAvailable ? DB::table('user_xp_events')->distinct()->count('user_id') : 0,
            'badges_earned' => $badgesAvailable ? DB::table('user_badges')->count() : 0,
            'active_badges' => Schema::hasTable('badges') ? DB::table('badges')->where('is_active', 1)->count() : 0,
            'milestone_claims' => $claimsAvailable ? DB::table('referral_milestone_claims')->count() : 0,
            'milestone_brc' => $claimsAvailable ? $this->milestoneBrcTotal() : 0,
            'period_xp' => $xpAvailable ? (int) DB::table('user_xp_events')->where('awarded_at', '>=', $periodStart)->sum('xp_amount') : 0,
        ];

        $season = null;
        if (Schema::hasTable('reward_seasons')) {
            $season = DB::table('reward_seasons')
                ->where('is_active', 1)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->orderByDesc('starts_at')
                ->first();
        }

        return [
            'period' => $period,
            'period_start' => $periodStart,
            'stats' => $stats,
            'season' => $season,
            'top_xp_users' => $this->topXpUsers($periodStart),
            'top_referrers' => $this->topActiveReferrers($periodStart),
            'recent_xp' => $this->recentXpEvents(10),
            'recent_badges' => $this->recentBadgeAwards(10),
            'recent_claims' => $this->recentReferralClaims(10),
        ];
    }

    public function xpEvents(Request $request)
    {
        if (!Schema::hasTable('user_xp_events')) {
            return $this->emptyPaginator($request);
        }

        $query = DB::table('user_xp_events')
            ->leftJoin('res_users', 'user_xp_events.user_id', '=', 'res_users.id')
            ->select(
                'user_xp_events.*',
                'res_users.name as user_name',
                'res_users.username',
                'res_users.email'
            )
            ->orderByDesc('user_xp_events.awarded_at');

        $this->applyUserSearch($query, $request->query('user'), 'user_xp_events.user_id');
        $this->applyDateRange($query, $request, 'user_xp_events.awarded_at');

        if ($request->filled('event_key')) {
            $query->where('user_xp_events.event_key', $request->query('event_key'));
        }

        if ($request->filled('source_type')) {
            $query->where('user_xp_events.source_type', $request->query('source_type'));
        }

        return $query->paginate(25)->appends($request->query());
    }

    public function xpFilterOptions(): array
    {
        if (!Schema::hasTable('user_xp_events')) {
            return ['event_keys' => [], 'source_types' => []];
        }

        return [
            'event_keys' => DB::table('user_xp_events')->whereNotNull('event_key')->distinct()->orderBy('event_key')->pluck('event_key'),
            'source_types' => DB::table('user_xp_events')->whereNotNull('source_type')->distinct()->orderBy('source_type')->pluck('source_type'),
        ];
    }

    public function userBadges(Request $request)
    {
        if (!Schema::hasTable('user_badges') || !Schema::hasTable('badges')) {
            return $this->emptyPaginator($request);
        }

        $query = DB::table('user_badges')
            ->join('badges', 'user_badges.badge_id', '=', 'badges.id')
            ->leftJoin('res_users', 'user_badges.user_id', '=', 'res_users.id')
            ->select(
                'user_badges.*',
                'badges.key as badge_key',
                'badges.name as badge_name',
                'res_users.name as user_name',
                'res_users.username',
                'res_users.email'
            )
            ->orderByDesc('user_badges.awarded_at');

        $this->applyUserSearch($query, $request->query('user'), 'user_badges.user_id');
        $this->applyDateRange($query, $request, 'user_badges.awarded_at');

        if ($request->filled('badge_id')) {
            $query->where('user_badges.badge_id', (int) $request->query('badge_id'));
        }

        return $query->paginate(25)->appends($request->query());
    }

    public function referralClaims(Request $request)
    {
        if (!Schema::hasTable('referral_milestone_claims')) {
            return $this->emptyPaginator($request);
        }

        $query = DB::table('referral_milestone_claims')
            ->leftJoin('referral_milestones', 'referral_milestone_claims.referral_milestone_id', '=', 'referral_milestones.id')
            ->leftJoin('res_users', 'referral_milestone_claims.user_id', '=', 'res_users.id')
            ->select(
                'referral_milestone_claims.*',
                'referral_milestones.title as milestone_title',
                'res_users.name as user_name',
                'res_users.username',
                'res_users.email'
            )
            ->orderByDesc('referral_milestone_claims.claimed_at');

        $this->applyUserSearch($query, $request->query('user'), 'referral_milestone_claims.user_id');
        $this->applyDateRange($query, $request, 'referral_milestone_claims.claimed_at');

        if ($request->filled('milestone_id')) {
            $query->where('referral_milestone_claims.referral_milestone_id', (int) $request->query('milestone_id'));
        }

        return $query->paginate(25)->appends($request->query());
    }

    public function referralClaimTotal(Request $request): float
    {
        if (!Schema::hasTable('referral_milestone_claims')) {
            return 0;
        }

        $query = DB::table('referral_milestone_claims')
            ->leftJoin('res_users', 'referral_milestone_claims.user_id', '=', 'res_users.id');
        $this->applyUserSearch($query, $request->query('user'), 'referral_milestone_claims.user_id');
        $this->applyDateRange($query, $request, 'referral_milestone_claims.claimed_at');

        if ($request->filled('milestone_id')) {
            $query->where('referral_milestone_claims.referral_milestone_id', (int) $request->query('milestone_id'));
        }

        if (Schema::hasColumn('referral_milestone_claims', 'reward_label')) {
            $query->where('referral_milestone_claims.reward_label', 'BRC');
        }

        return (float) $query->sum('referral_milestone_claims.reward_amount');
    }

    public function dailyMissionMonitor(): array
    {
        $start = now()->startOfDay();
        $rows = [
            $this->missionHealth('mining', 'button_clicks', 'Participation sessions', 'user_id', 'rewards_start_time', $start),
            $this->missionHealth('daily_streak', 'tblreward', 'Daily streak rewards', 'userid', 'updated_at', $start),
            $this->missionHealth('reward_task', 'task_claimeds', 'Validated reward tasks', 'user_id', 'created_at', $start),
            $this->missionHealth('starter_quest', 'onboarding_quest_claims', 'Starter Quest claims', 'user_id', 'claimed_at', $start),
            $this->referralMissionHealth($start),
            $this->squadMissionHealth($start),
            $this->learnMissionHealth($start),
            [
                'key' => 'wallet_safety',
                'module' => 'Wallet safety guidance',
                'status' => 'ready',
                'completed_today' => null,
                'ready_active' => null,
                'warning' => 'Sensitive wallet checks remain local to the device.',
            ],
        ];

        return ['missions' => $rows, 'refreshed_at' => now()];
    }

    public function productionHealth(): array
    {
        $tables = [];
        foreach (self::HEALTH_TABLES as $table) {
            $exists = Schema::hasTable($table);
            $tables[] = [
                'table' => $table,
                'exists' => $exists,
                'rows' => $exists ? DB::table($table)->count() : null,
            ];
        }

        $registeredUris = collect(Route::getRoutes())->map(function ($route) {
            return '/' . ltrim($route->uri(), '/');
        });
        $routes = collect(self::API_ROUTES)->map(function ($uri) use ($registeredUris) {
            return ['uri' => $uri, 'available' => $registeredUris->contains($uri)];
        })->all();

        try {
            $log = $this->safeLogSummary();
        } catch (\Throwable $error) {
            $log = ['errors' => [], 'counts' => ['401' => null, '500' => null, 'milestone' => null, 'xp' => null]];
        }

        return [
            'tables' => $tables,
            'routes' => $routes,
            'metrics' => $this->learnTrustMetrics(),
            'squad_metrics' => $this->squadMetrics(),
            'errors' => $log['errors'],
            'counts' => $log['counts'],
            'checks' => $this->postReleaseChecks(),
            'checked_at' => now(),
        ];
    }

    public function metadataPreview($metadata): string
    {
        if (!$metadata) {
            return '';
        }

        $decoded = is_array($metadata) ? $metadata : json_decode((string) $metadata, true);
        if (!is_array($decoded)) {
            return Str::limit($this->redact((string) $metadata), 180);
        }

        $safe = $this->removeSensitiveKeys($decoded);
        return Str::limit((string) json_encode($safe, JSON_UNESCAPED_SLASHES), 180);
    }

    public function recordAdminAudit(?int $adminId, string $action, string $entityType, ?int $entityId, array $changes = []): void
    {
        if (!Schema::hasTable('engagement_admin_audits')) {
            return;
        }

        DB::table('engagement_admin_audits')->insert([
            'admin_id' => $adminId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'changes' => json_encode($this->removeSensitiveKeys($changes)),
            'created_at' => now(),
        ]);
    }

    private function topXpUsers(Carbon $since)
    {
        if (!Schema::hasTable('user_xp_events')) {
            return collect();
        }

        return DB::table('user_xp_events')
            ->leftJoin('res_users', 'user_xp_events.user_id', '=', 'res_users.id')
            ->where('user_xp_events.awarded_at', '>=', $since)
            ->groupBy('user_xp_events.user_id', 'res_users.name', 'res_users.username', 'res_users.email')
            ->select(
                'user_xp_events.user_id',
                'res_users.name',
                'res_users.username',
                'res_users.email',
                DB::raw('SUM(user_xp_events.xp_amount) as xp')
            )
            ->orderByDesc('xp')
            ->limit(10)
            ->get();
    }

    private function topActiveReferrers(Carbon $since)
    {
        if (!Schema::hasTable('referrals') || !Schema::hasTable('button_clicks')) {
            return collect();
        }

        if (!Schema::hasColumn('button_clicks', 'rewards_start_time') && !Schema::hasColumn('button_clicks', 'created_at')) {
            return collect();
        }

        $activityColumn = Schema::hasColumn('button_clicks', 'rewards_start_time')
            ? 'button_clicks.rewards_start_time'
            : 'button_clicks.created_at';

        $groups = DB::table('referrals')
            ->join('button_clicks', 'referrals.user_id', '=', 'button_clicks.user_id')
            ->where($activityColumn, '>=', $since)
            ->whereNotNull('referrals.referred_by')
            ->groupBy('referrals.referred_by')
            ->select('referrals.referred_by', DB::raw('COUNT(DISTINCT referrals.user_id) as active_referrals'))
            ->orderByDesc('active_referrals')
            ->limit(30)
            ->get();

        return $groups->map(function ($group) {
            $user = DB::table('res_users')
                ->where('id', $group->referred_by)
                ->orWhere('refferal_code', $group->referred_by)
                ->first();

            return (object) [
                'user_id' => $user->id ?? null,
                'name' => $user->name ?? null,
                'username' => $user->username ?? null,
                'email' => $user->email ?? null,
                'referral_key' => $group->referred_by,
                'active_referrals' => (int) $group->active_referrals,
            ];
        })->sortByDesc('active_referrals')->take(10)->values();
    }

    private function recentXpEvents(int $limit)
    {
        if (!Schema::hasTable('user_xp_events')) {
            return collect();
        }

        return DB::table('user_xp_events')
            ->leftJoin('res_users', 'user_xp_events.user_id', '=', 'res_users.id')
            ->select('user_xp_events.*', 'res_users.name as user_name', 'res_users.username')
            ->orderByDesc('user_xp_events.awarded_at')
            ->limit($limit)
            ->get();
    }

    private function recentBadgeAwards(int $limit)
    {
        if (!Schema::hasTable('user_badges') || !Schema::hasTable('badges')) {
            return collect();
        }

        return DB::table('user_badges')
            ->join('badges', 'user_badges.badge_id', '=', 'badges.id')
            ->leftJoin('res_users', 'user_badges.user_id', '=', 'res_users.id')
            ->select('user_badges.*', 'badges.name as badge_name', 'badges.key as badge_key', 'res_users.name as user_name', 'res_users.username')
            ->orderByDesc('user_badges.awarded_at')
            ->limit($limit)
            ->get();
    }

    private function recentReferralClaims(int $limit)
    {
        if (!Schema::hasTable('referral_milestone_claims')) {
            return collect();
        }

        return DB::table('referral_milestone_claims')
            ->leftJoin('referral_milestones', 'referral_milestone_claims.referral_milestone_id', '=', 'referral_milestones.id')
            ->leftJoin('res_users', 'referral_milestone_claims.user_id', '=', 'res_users.id')
            ->select('referral_milestone_claims.*', 'referral_milestones.title as milestone_title', 'res_users.name as user_name', 'res_users.username')
            ->orderByDesc('referral_milestone_claims.claimed_at')
            ->limit($limit)
            ->get();
    }

    private function missionHealth(string $key, string $table, string $module, string $userColumn, string $dateColumn, Carbon $start): array
    {
        if (!Schema::hasTable($table)) {
            return [
                'key' => $key,
                'module' => $module,
                'status' => 'error',
                'completed_today' => null,
                'ready_active' => null,
                'warning' => 'Required table is missing: ' . $table,
            ];
        }

        if (!Schema::hasColumn($table, $dateColumn) || !Schema::hasColumn($table, $userColumn)) {
            return [
                'key' => $key,
                'module' => $module,
                'status' => 'unavailable',
                'completed_today' => null,
                'ready_active' => null,
                'warning' => 'Required monitoring columns are unavailable for this legacy table.',
            ];
        }

        $completed = DB::table($table)->where($dateColumn, '>=', $start)->distinct()->count($userColumn);

        return [
            'key' => $key,
            'module' => $module,
            'status' => 'healthy',
            'completed_today' => $completed,
            'ready_active' => null,
            'warning' => null,
        ];
    }

    private function referralMissionHealth(Carbon $start): array
    {
        if (!Schema::hasTable('referrals') || !Schema::hasTable('button_clicks')) {
            return [
                'key' => 'referral',
                'module' => 'Referral network activity',
                'status' => 'error',
                'completed_today' => null,
                'ready_active' => null,
                'warning' => 'Referral or participation activity table is missing.',
            ];
        }

        if (!Schema::hasColumn('button_clicks', 'rewards_start_time') && !Schema::hasColumn('button_clicks', 'created_at')) {
            return [
                'key' => 'referral',
                'module' => 'Referral network activity',
                'status' => 'unavailable',
                'completed_today' => null,
                'ready_active' => null,
                'warning' => 'Participation timestamps are unavailable.',
            ];
        }

        $dateColumn = Schema::hasColumn('button_clicks', 'rewards_start_time')
            ? 'button_clicks.rewards_start_time'
            : 'button_clicks.created_at';
        $completed = DB::table('referrals')
            ->join('button_clicks', 'referrals.user_id', '=', 'button_clicks.user_id')
            ->whereNotNull('referrals.referred_by')
            ->where($dateColumn, '>=', $start)
            ->distinct()
            ->count('referrals.referred_by');

        return [
            'key' => 'referral',
            'module' => 'Referral network activity',
            'status' => 'healthy',
            'completed_today' => $completed,
            'ready_active' => null,
            'warning' => null,
        ];
    }

    private function learnMissionHealth(Carbon $start): array
    {
        if (!Schema::hasTable('learn_modules') || !Schema::hasTable('user_learn_progress')) {
            return [
                'key' => 'learn',
                'module' => 'Learn & Earn lessons',
                'status' => 'error',
                'completed_today' => null,
                'ready_active' => null,
                'warning' => 'Learn module or progress table is missing.',
            ];
        }

        $completedToday = DB::table('user_learn_progress')
            ->where('status', 'completed')
            ->where('completed_at', '>=', $start)
            ->distinct()
            ->count('user_id');

        $activeModules = DB::table('learn_modules')->where('is_active', 1)->count();

        return [
            'key' => 'learn',
            'module' => 'Learn & Earn lessons',
            'status' => $activeModules > 0 ? 'healthy' : 'unavailable',
            'completed_today' => $completedToday,
            'ready_active' => $activeModules,
            'warning' => $activeModules > 0 ? null : 'No active lessons are currently available.',
        ];
    }

    private function squadMissionHealth(Carbon $start): array
    {
        if (!Schema::hasTable('squads') || !Schema::hasTable('squad_members')) {
            return [
                'key' => 'squad',
                'module' => 'Squad activity',
                'status' => 'error',
                'completed_today' => null,
                'ready_active' => null,
                'warning' => 'Squad tables are missing.',
            ];
        }

        return [
            'key' => 'squad',
            'module' => 'Squad activity',
            'status' => DB::table('squads')->where('is_active', 1)->exists() ? 'healthy' : 'unavailable',
            'completed_today' => Schema::hasTable('squad_weekly_chests')
                ? DB::table('squad_weekly_chests')->where('claimed_at', '>=', $start)->count()
                : null,
            'ready_active' => DB::table('squads')->where('is_active', 1)->count(),
            'warning' => null,
        ];
    }

    private function postReleaseChecks(): array
    {
        $checks = [];
        $checks[] = $this->check('XP events recorded in last 24h', Schema::hasTable('user_xp_events') && DB::table('user_xp_events')->where('awarded_at', '>=', now()->subDay())->exists());
        $checks[] = $this->check('No duplicate XP dedupe keys', Schema::hasTable('user_xp_events') && !$this->hasDuplicateGroup('user_xp_events', 'dedupe_key'));
        $checks[] = $this->check('No duplicate user badges', Schema::hasTable('user_badges') && !$this->hasDuplicatePair('user_badges', 'user_id', 'badge_id'));
        $checks[] = $this->check('No duplicate milestone claims', Schema::hasTable('referral_milestone_claims') && !$this->hasDuplicatePair('referral_milestone_claims', 'user_id', 'referral_milestone_id'));
        $checks[] = $this->check('No duplicate lesson completions', Schema::hasTable('user_learn_progress') && !$this->hasDuplicatePair('user_learn_progress', 'user_id', 'module_id'));
        $checks[] = $this->check('Trust Center active sections available', Schema::hasTable('trust_center_sections') && DB::table('trust_center_sections')->where('is_active', 1)->count() >= 10);
        $checks[] = $this->check('Learn starter lessons available', Schema::hasTable('learn_modules') && DB::table('learn_modules')->where('is_active', 1)->count() >= 6);
        $checks[] = $this->check('Squad tables available', Schema::hasTable('squads') && Schema::hasTable('squad_members') && Schema::hasTable('squad_weekly_chests'));
        $checks[] = $this->check('Squad sharing ready', Schema::hasTable('squad_share_events') && Schema::hasColumn('squads', 'country_code') && Schema::hasColumn('squads', 'share_count'));
        $checks[] = $this->check('Squad missions available', Schema::hasTable('squad_missions') && DB::table('squad_missions')->where('is_active', 1)->exists());
        $checks[] = $this->check('Squad announcements ready', Schema::hasTable('squad_announcement_templates') && Schema::hasTable('squad_announcements'));
        $checks[] = $this->check('Reward wallet table available', Schema::hasTable('tbluserwallet'));
        $checks[] = $this->check('API authentication handler available', class_exists(\App\Exceptions\Handler::class));

        return $checks;
    }

    private function learnTrustMetrics(): array
    {
        return [
            'active_trust_sections' => Schema::hasTable('trust_center_sections')
                ? DB::table('trust_center_sections')->where('is_active', 1)->count()
                : null,
            'active_learn_modules' => Schema::hasTable('learn_modules')
                ? DB::table('learn_modules')->where('is_active', 1)->count()
                : null,
            'completed_lessons' => Schema::hasTable('user_learn_progress')
                ? DB::table('user_learn_progress')->where('status', 'completed')->count()
                : null,
            'lesson_completed_xp' => Schema::hasTable('user_xp_events')
                ? (int) DB::table('user_xp_events')->where('event_key', 'lesson_completed')->sum('xp_amount')
                : null,
        ];
    }

    private function squadMetrics(): array
    {
        return [
            'active_squads' => Schema::hasTable('squads')
                ? DB::table('squads')->where('is_active', 1)->count()
                : null,
            'active_squad_members' => Schema::hasTable('squad_members')
                ? DB::table('squad_members')->where('status', 'active')->count()
                : null,
            'weekly_chests_claimed' => Schema::hasTable('squad_weekly_chests')
                ? DB::table('squad_weekly_chests')->count()
                : null,
            'squad_chest_xp' => Schema::hasTable('user_xp_events')
                ? (int) DB::table('user_xp_events')->where('event_key', 'squad_chest_claimed')->sum('xp_amount')
                : null,
            'squad_shares' => Schema::hasTable('squad_share_events')
                ? DB::table('squad_share_events')->count()
                : null,
            'active_squad_missions' => Schema::hasTable('squad_missions')
                ? DB::table('squad_missions')->where('is_active', 1)->count()
                : null,
            'squad_mission_claims' => Schema::hasTable('squad_mission_claims')
                ? DB::table('squad_mission_claims')->count()
                : null,
            'invite_streak_claims' => Schema::hasTable('squad_invite_streak_claims')
                ? DB::table('squad_invite_streak_claims')->count()
                : null,
            'active_squad_announcements' => Schema::hasTable('squad_announcements')
                ? DB::table('squad_announcements')->where('status', 'active')->count()
                : null,
        ];
    }

    private function milestoneBrcTotal(): float
    {
        $query = DB::table('referral_milestone_claims');
        if (Schema::hasColumn('referral_milestone_claims', 'reward_label')) {
            $query->where('reward_label', 'BRC');
        }

        return (float) $query->sum('reward_amount');
    }

    private function safeLogSummary(): array
    {
        $path = storage_path('logs/laravel.log');
        if (!File::exists($path) || !is_readable($path)) {
            return ['errors' => [], 'counts' => ['401' => null, '500' => null, 'milestone' => null, 'xp' => null]];
        }

        $size = File::size($path);
        $handle = fopen($path, 'rb');
        if (!$handle) {
            return ['errors' => [], 'counts' => ['401' => null, '500' => null, 'milestone' => null, 'xp' => null]];
        }

        $readLength = min($size, 262144);
        if ($size > $readLength) {
            fseek($handle, -$readLength, SEEK_END);
        }
        $content = (string) fread($handle, $readLength);
        fclose($handle);

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $errorLines = collect($lines)->filter(function ($line) {
            return strpos($line, '.ERROR:') !== false;
        });

        $safeErrors = $errorLines->take(-20)->map(function ($line) {
            $line = preg_replace('/\s+\{.*$/', '', $line);
            return Str::limit($this->redact((string) $line), 500);
        })->values()->all();

        $sample = implode("\n", $errorLines->all());

        return [
            'errors' => $safeErrors,
            'counts' => [
                '401' => substr_count($sample, '401'),
                '500' => substr_count($sample, '500'),
                'milestone' => preg_match_all('/milestone/i', $sample),
                'xp' => preg_match_all('/\bXP\b|user_xp_events/i', $sample),
            ],
        ];
    }

    private function applyUserSearch(Builder $query, $search, string $userIdColumn): void
    {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        $query->where(function ($where) use ($search, $userIdColumn) {
            if (ctype_digit($search)) {
                $where->orWhere($userIdColumn, (int) $search);
            }
            $where->orWhere('res_users.name', 'like', '%' . $search . '%')
                ->orWhere('res_users.username', 'like', '%' . $search . '%')
                ->orWhere('res_users.email', 'like', '%' . $search . '%');
        });
    }

    private function applyDateRange(Builder $query, Request $request, string $column): void
    {
        if ($request->filled('from')) {
            try {
                $query->where($column, '>=', Carbon::parse($request->query('from'))->startOfDay());
            } catch (\Throwable $error) {
            }
        }

        if ($request->filled('to')) {
            try {
                $query->where($column, '<=', Carbon::parse($request->query('to'))->endOfDay());
            } catch (\Throwable $error) {
            }
        }
    }

    private function periodStart(string $period): Carbon
    {
        if ($period === 'today') {
            return now()->startOfDay();
        }
        if ($period === '30days') {
            return now()->subDays(30);
        }
        return now()->subDays(7);
    }

    private function hasDuplicateGroup(string $table, string $column): bool
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return false;
        }

        return DB::table($table)
            ->select($column)
            ->whereNotNull($column)
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }

    private function hasDuplicatePair(string $table, string $first, string $second): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        return DB::table($table)
            ->select($first, $second)
            ->groupBy($first, $second)
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }

    private function check(string $label, bool $passed): array
    {
        return ['label' => $label, 'passed' => $passed];
    }

    private function removeSensitiveKeys(array $data): array
    {
        $safe = [];
        foreach ($data as $key => $value) {
            if (preg_match('/password|passcode|secret|token|authorization|private.?key|recovery|seed.?phrase|keystore/i', (string) $key)) {
                $safe[$key] = '[REDACTED]';
                continue;
            }
            $safe[$key] = is_array($value) ? $this->removeSensitiveKeys($value) : $value;
        }
        return $safe;
    }

    private function redact(string $value): string
    {
        $patterns = [
            '/Bearer\s+[A-Za-z0-9._\-]+/i',
            '/(password|passcode|secret|token|authorization|private.?key|recovery.?phrase|seed.?phrase|keystore)\s*[:=]\s*[^\s,;]+/i',
        ];

        return preg_replace($patterns, '[REDACTED]', $value);
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 25, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}
