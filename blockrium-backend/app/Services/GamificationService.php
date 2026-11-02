<?php

namespace App\Services;

use App\Models\ResUser;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GamificationService
{
    private const LEVELS = [
        ['level' => 1, 'name' => 'New Explorer', 'xp' => 0],
        ['level' => 2, 'name' => 'Active Miner', 'xp' => 100],
        ['level' => 3, 'name' => 'Network Builder', 'xp' => 500],
        ['level' => 4, 'name' => 'Web3 Learner', 'xp' => 1000],
        ['level' => 5, 'name' => 'Blockrium Elite', 'xp' => 2500],
    ];

    private const DAILY_CAPS = [
        'mining_started' => 10,
        'streak_claimed' => 15,
        'task_claimed' => 100,
        'referral_activated' => 500,
        'lesson_completed' => 500,
        'wallet_safety_completed' => 25,
        'squad_chest_claimed' => 200,
    ];

    private const EVENT_BADGES = [
        'mining_started' => 'first_participation',
        'streak_claimed' => 'streak_starter',
        'starter_quest_claimed' => 'starter_complete',
        'referral_activated' => 'network_builder',
        'lesson_completed' => 'web3_learner',
        'wallet_safety_completed' => 'safety_first',
        'squad_chest_claimed' => 'squad_builder',
    ];

    public function awardXp(
        ResUser $user,
        string $eventKey,
        int $amount,
        ?string $sourceType = null,
        ?int $sourceId = null,
        array $metadata = []
    ): array {
        if ($amount <= 0 || !Schema::hasTable('user_xp_events')) {
            return [
                'awarded' => false,
                'reason' => 'unavailable',
                'xp_awarded' => 0,
            ];
        }

        $eventKey = strtolower(trim($eventKey));
        $dedupeKey = $this->dedupeKey((int) $user->id, $eventKey, $sourceType, $sourceId);

        try {
            return DB::transaction(function () use ($user, $eventKey, $amount, $sourceType, $sourceId, $metadata, $dedupeKey) {
                if ($this->hasXpEvent($user, $eventKey, $sourceType, $sourceId, $dedupeKey)) {
                    return [
                        'awarded' => false,
                        'reason' => 'duplicate',
                        'xp_awarded' => 0,
                        'summary' => $this->getSummary($user),
                    ];
                }

                if (!$this->enforceDailyCap($user, $eventKey, $amount)) {
                    return [
                        'awarded' => false,
                        'reason' => 'daily_cap',
                        'xp_awarded' => 0,
                        'summary' => $this->getSummary($user),
                    ];
                }

                DB::table('user_xp_events')->insert([
                    'user_id' => (int) $user->id,
                    'event_key' => $eventKey,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'dedupe_key' => $dedupeKey,
                    'xp_amount' => $amount,
                    'metadata' => $metadata ? json_encode($metadata) : null,
                    'awarded_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if (isset(self::EVENT_BADGES[$eventKey])) {
                    $this->awardBadge($user, self::EVENT_BADGES[$eventKey], $sourceType, $sourceId);
                }

                return [
                    'awarded' => true,
                    'reason' => null,
                    'xp_awarded' => $amount,
                    'summary' => $this->getSummary($user),
                ];
            }, 3);
        } catch (QueryException $error) {
            if ((string) $error->getCode() === '23000') {
                return [
                    'awarded' => false,
                    'reason' => 'duplicate',
                    'xp_awarded' => 0,
                    'summary' => $this->getSummary($user),
                ];
            }

            throw $error;
        }
    }

    public function getUserXp(ResUser $user): int
    {
        if (!Schema::hasTable('user_xp_events')) {
            return 0;
        }

        return (int) DB::table('user_xp_events')
            ->where('user_id', (int) $user->id)
            ->sum('xp_amount');
    }

    public function getUserLevel(ResUser $user): array
    {
        $xp = $this->getUserXp($user);
        $current = self::LEVELS[0];
        $next = null;

        foreach (self::LEVELS as $index => $level) {
            if ($xp >= $level['xp']) {
                $current = $level;
                $next = self::LEVELS[$index + 1] ?? null;
            }
        }

        $progress = 100;
        if ($next) {
            $range = max(1, $next['xp'] - $current['xp']);
            $progress = (int) min(100, max(0, round((($xp - $current['xp']) / $range) * 100)));
        }

        return [
            'xp' => $xp,
            'level' => $current['level'],
            'level_name' => $current['name'],
            'current_level_xp' => $current['xp'],
            'next_level_xp' => $next['xp'] ?? null,
            'progress_percent' => $progress,
        ];
    }

    public function awardBadge(
        ResUser $user,
        string $badgeKey,
        ?string $sourceType = null,
        ?int $sourceId = null
    ): array {
        if (!Schema::hasTable('badges') || !Schema::hasTable('user_badges')) {
            return ['awarded' => false, 'reason' => 'unavailable'];
        }

        $badge = DB::table('badges')->where('key', $badgeKey)->where('is_active', 1)->first();
        if (!$badge) {
            return ['awarded' => false, 'reason' => 'badge_not_found'];
        }

        $exists = DB::table('user_badges')
            ->where('user_id', (int) $user->id)
            ->where('badge_id', $badge->id)
            ->exists();

        if ($exists) {
            return ['awarded' => false, 'reason' => 'duplicate', 'badge' => $this->badgePayload($badge)];
        }

        try {
            DB::table('user_badges')->insert([
                'user_id' => (int) $user->id,
                'badge_id' => $badge->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'awarded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $error) {
            if ((string) $error->getCode() !== '23000') {
                throw $error;
            }

            return ['awarded' => false, 'reason' => 'duplicate', 'badge' => $this->badgePayload($badge)];
        }

        return ['awarded' => true, 'reason' => null, 'badge' => $this->badgePayload($badge)];
    }

    public function getSummary(ResUser $user): array
    {
        $summary = $this->getUserLevel($user);
        $summary['badges'] = $this->earnedBadges($user);
        $summary['season'] = $this->activeSeason();

        return $summary;
    }

    public function badges(ResUser $user): array
    {
        if (!Schema::hasTable('badges')) {
            return ['earned' => [], 'available' => []];
        }

        $earnedIds = Schema::hasTable('user_badges')
            ? DB::table('user_badges')->where('user_id', (int) $user->id)->pluck('badge_id')->all()
            : [];

        $all = DB::table('badges')->where('is_active', 1)->orderBy('sort_order')->orderBy('id')->get();

        return [
            'earned' => $all->filter(function ($badge) use ($earnedIds) {
                return in_array($badge->id, $earnedIds);
            })->map(function ($badge) {
                return $this->badgePayload($badge);
            })->values()->all(),
            'available' => $all->reject(function ($badge) use ($earnedIds) {
                return in_array($badge->id, $earnedIds);
            })->map(function ($badge) {
                return $this->badgePayload($badge);
            })->values()->all(),
        ];
    }

    public function hasXpEvent(
        ResUser $user,
        string $eventKey,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $dedupeKey = null
    ): bool {
        if (!Schema::hasTable('user_xp_events')) {
            return false;
        }

        $dedupeKey = $dedupeKey ?: $this->dedupeKey((int) $user->id, $eventKey, $sourceType, $sourceId);

        return DB::table('user_xp_events')->where('dedupe_key', $dedupeKey)->exists();
    }

    public function enforceDailyCap(ResUser $user, string $eventKey, int $amount): bool
    {
        if (!isset(self::DAILY_CAPS[$eventKey])) {
            return true;
        }

        $awardedToday = (int) DB::table('user_xp_events')
            ->where('user_id', (int) $user->id)
            ->where('event_key', $eventKey)
            ->where('awarded_at', '>=', Carbon::now()->startOfDay())
            ->sum('xp_amount');

        return $awardedToday + $amount <= self::DAILY_CAPS[$eventKey];
    }

    private function dedupeKey(int $userId, string $eventKey, ?string $sourceType, ?int $sourceId): string
    {
        $parts = [$userId, $eventKey];

        if (in_array($eventKey, ['mining_started', 'streak_claimed'])) {
            $parts[] = Carbon::now()->toDateString();
        } elseif ($sourceType !== null && $sourceId !== null) {
            $parts[] = $sourceType;
            $parts[] = $sourceId;
        }

        return implode(':', $parts);
    }

    private function earnedBadges(ResUser $user): array
    {
        if (!Schema::hasTable('badges') || !Schema::hasTable('user_badges')) {
            return [];
        }

        return DB::table('user_badges')
            ->join('badges', 'user_badges.badge_id', '=', 'badges.id')
            ->where('user_badges.user_id', (int) $user->id)
            ->where('badges.is_active', 1)
            ->orderByDesc('user_badges.awarded_at')
            ->select('badges.*', 'user_badges.awarded_at')
            ->get()
            ->map(function ($badge) {
                $payload = $this->badgePayload($badge);
                $payload['awarded_at'] = $badge->awarded_at;
                return $payload;
            })
            ->all();
    }

    private function activeSeason(): array
    {
        if (!Schema::hasTable('reward_seasons')) {
            return ['key' => 'genesis', 'title' => 'Genesis Season', 'progress_percent' => 0];
        }

        $now = now();
        $season = DB::table('reward_seasons')
            ->where('is_active', 1)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->orderByDesc('starts_at')
            ->first();

        if (!$season) {
            return ['key' => 'genesis', 'title' => 'Genesis Season', 'progress_percent' => 0];
        }

        $start = Carbon::parse($season->starts_at);
        $end = Carbon::parse($season->ends_at);
        $duration = max(1, $start->diffInSeconds($end));
        $elapsed = min($duration, max(0, $start->diffInSeconds($now)));

        return [
            'key' => $season->key,
            'title' => $season->title,
            'progress_percent' => (int) round(($elapsed / $duration) * 100),
            'starts_at' => $season->starts_at,
            'ends_at' => $season->ends_at,
        ];
    }

    private function badgePayload($badge): array
    {
        return [
            'key' => $badge->key,
            'name' => $badge->name,
            'description' => $badge->description,
            'icon' => $badge->icon,
            'category' => $badge->category,
        ];
    }
}
