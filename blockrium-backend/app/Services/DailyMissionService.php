<?php

namespace App\Services;

use App\Models\ResUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DailyMissionService
{
    private const MINING_WINDOW_HOURS = 6;

    public function getDashboard(ResUser $user): array
    {
        $builders = [
            'mining' => 'miningMission',
            'daily_streak' => 'streakMission',
            'reward_task' => 'taskMission',
            'starter_quest' => 'starterQuestMission',
            'referral' => 'referralMission',
            'squad' => 'squadMission',
            'learn' => 'learnMission',
            'wallet_safety' => 'walletSafetyMission',
        ];

        $missions = [];
        foreach ($builders as $key => $method) {
            try {
                $missions[] = $this->{$method}($user);
            } catch (Throwable $error) {
                Log::warning('Daily mission module failed', [
                    'mission' => $key,
                    'user_id' => (int) $user->id,
                    'error' => $error->getMessage(),
                ]);

                $missions[] = $this->mission(
                    $key,
                    $this->titleFor($key),
                    'This mission is temporarily unavailable.',
                    'error',
                    $key,
                    0
                );
            }
        }

        $completed = collect($missions)->filter(function ($mission) {
            return in_array($mission['status'], ['completed', 'active']);
        })->count();
        $total = count($missions);

        return [
            'summary' => [
                'completed_count' => $completed,
                'total_count' => $total,
                'progress_percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            ],
            'missions' => $missions,
        ];
    }

    private function miningMission(ResUser $user): array
    {
        if (!Schema::hasTable('button_clicks')) {
            return $this->mission('mining', 'Start Mining', 'Daily participation is unavailable.', 'unavailable', 'mining');
        }

        $latest = DB::table('button_clicks')
            ->where('user_id', (int) $user->id)
            ->orderByDesc('id')
            ->first();

        if (!$latest) {
            return $this->mission(
                'mining',
                'Start Mining',
                'Start today\'s participation session',
                'ready',
                'mining',
                0,
                ['can_start' => true]
            );
        }

        $startedAt = $latest->rewards_start_time ?? $latest->created_at ?? null;
        $endsAt = $startedAt ? Carbon::parse($startedAt)->addHours(self::MINING_WINDOW_HOURS) : null;
        $active = $endsAt && $endsAt->isFuture();

        return $this->mission(
            'mining',
            'Start Mining',
            $active ? 'Today\'s participation session is active' : 'Start today\'s participation session',
            $active ? 'active' : 'ready',
            'mining',
            $active ? 100 : 0,
            [
                'can_start' => !$active,
                'started_at' => $startedAt,
                'ends_at' => $endsAt ? $endsAt->toDateTimeString() : null,
                'seconds_remaining' => $active ? max(0, now()->diffInSeconds($endsAt)) : 0,
            ]
        );
    }

    private function streakMission(ResUser $user): array
    {
        if (!Schema::hasTable('tblreward')) {
            return $this->mission('daily_streak', 'Daily Streak', 'Daily reward is unavailable.', 'unavailable', 'daily_streak');
        }

        $record = DB::table('tblreward')->where('userid', (int) $user->id)->orderByDesc('id')->first();
        $lastClaimed = $record->updated_at ?? null;
        $claimedToday = $lastClaimed && Carbon::parse($lastClaimed)->isToday();
        $streak = (int) ($record->status ?? 0);

        return $this->mission(
            'daily_streak',
            'Daily Streak',
            $claimedToday ? 'Today\'s streak is complete' : 'Claim today\'s validated streak reward',
            $claimedToday ? 'completed' : 'ready',
            'daily_streak',
            $claimedToday ? 100 : 0,
            [
                'claimed_today' => (bool) $claimedToday,
                'streak_day' => max(0, min(7, $streak)),
                'last_claimed_at' => $lastClaimed,
            ]
        );
    }

    private function taskMission(ResUser $user): array
    {
        if (!Schema::hasTable('tasks') || !Schema::hasTable('task_claimeds')) {
            return $this->mission('reward_task', 'Reward Task', 'Reward tasks are unavailable.', 'unavailable', 'tasks');
        }

        $claimQuery = DB::table('task_claimeds')->where('user_id', (int) $user->id);
        if (Schema::hasColumn('task_claimeds', 'created_at')) {
            $claimQuery->where('created_at', '>=', now()->startOfDay());
        }

        $claimedToday = $claimQuery->exists();
        $claimedTaskIds = DB::table('task_claimeds')
            ->where('user_id', (int) $user->id)
            ->pluck('task_id');
        $available = DB::table('tasks')->whereNotIn('id', $claimedTaskIds)->count();

        $status = $claimedToday ? 'completed' : ($available > 0 ? 'ready' : 'locked');

        return $this->mission(
            'reward_task',
            'Reward Task',
            $claimedToday ? 'A validated task was completed today' : 'Complete an available reward task',
            $status,
            'tasks',
            $claimedToday ? 100 : 0,
            ['available_count' => $available, 'claimed_today' => $claimedToday]
        );
    }

    private function starterQuestMission(ResUser $user): array
    {
        if (!Schema::hasTable('onboarding_quest_claims')) {
            return $this->mission('starter_quest', 'Starter Quest', 'Starter Quest is unavailable.', 'unavailable', 'starter_quest');
        }

        $claimed = DB::table('onboarding_quest_claims')->where('user_id', (int) $user->id)->first();
        if ($claimed) {
            return $this->mission(
                'starter_quest',
                'Starter Quest',
                'Starter Quest completed',
                'completed',
                'starter_quest',
                100,
                ['claimed_at' => $claimed->claimed_at ?? null]
            );
        }

        $steps = [
            true,
            Schema::hasTable('button_clicks') && DB::table('button_clicks')->where('user_id', (int) $user->id)->exists(),
            Schema::hasTable('tblreward') && DB::table('tblreward')->where('userid', (int) $user->id)->exists(),
            Schema::hasTable('task_claimeds') && DB::table('task_claimeds')->where('user_id', (int) $user->id)->exists(),
            !empty($user->refferal_code),
        ];
        $completed = count(array_filter($steps));
        $progress = (int) round(($completed / count($steps)) * 100);

        return $this->mission(
            'starter_quest',
            'Starter Quest',
            $completed === count($steps) ? 'Starter Quest reward is ready' : 'Complete the Blockrium basics',
            $completed === count($steps) ? 'ready' : 'active',
            'starter_quest',
            $progress,
            ['completed_steps' => $completed, 'total_steps' => count($steps)]
        );
    }

    private function referralMission(ResUser $user): array
    {
        if (!Schema::hasTable('referrals')) {
            return $this->mission('referral', 'Build Your Network', 'Referral progress is unavailable.', 'unavailable', 'referral');
        }

        $code = trim((string) $user->refferal_code);
        $referralIds = DB::table('referrals')
            ->where(function ($query) use ($user, $code) {
                $query->where('referred_by', (int) $user->id);
                if ($code !== '') {
                    $query->orWhere('referred_by', $code);
                }
            })
            ->pluck('user_id');

        $active = 0;
        if ($referralIds->isNotEmpty() && Schema::hasTable('button_clicks')) {
            $active = DB::table('button_clicks')
                ->whereIn('user_id', $referralIds)
                ->where(function ($query) {
                    if (Schema::hasColumn('button_clicks', 'rewards_start_time')) {
                        $query->where('rewards_start_time', '>=', now()->subDay());
                    } else {
                        $query->where('created_at', '>=', now()->subDay());
                    }
                })
                ->distinct()
                ->count('user_id');
        }

        $status = $active > 0 ? 'completed' : ($code !== '' ? 'active' : 'ready');

        return $this->mission(
            'referral',
            'Build Your Network',
            $active > 0 ? 'Your network has active participants today' : 'Share your referral code and help your network participate',
            $status,
            'referral',
            $active > 0 ? 100 : 0,
            [
                'referral_code_ready' => $code !== '',
                'total_joined' => $referralIds->count(),
                'active_referrals' => $active,
            ]
        );
    }

    private function learnMission(ResUser $user): array
    {
        if (!Schema::hasTable('learn_modules') || !Schema::hasTable('user_learn_progress')) {
            return $this->mission(
                'learn',
                'Learn & Earn',
                'Learning modules are unavailable.',
                'unavailable',
                'learn'
            );
        }

        $activeModuleIds = DB::table('learn_modules')
            ->where('is_active', 1)
            ->pluck('id');

        if ($activeModuleIds->isEmpty()) {
            return $this->mission(
                'learn',
                'Learn & Earn',
                'No Web3 safety lessons are active yet.',
                'unavailable',
                'learn'
            );
        }

        $completed = DB::table('user_learn_progress')
            ->where('user_id', (int) $user->id)
            ->whereIn('module_id', $activeModuleIds)
            ->where('status', 'completed')
            ->distinct()
            ->count('module_id');

        $total = $activeModuleIds->count();
        $allDone = $completed >= $total;

        return $this->mission(
            'learn',
            'Learn & Earn',
            $allDone ? 'All active Web3 safety lessons are complete.' : 'Complete a Web3 safety lesson.',
            $allDone ? 'completed' : 'ready',
            'learn',
            $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            [
                'active_modules' => $total,
                'completed_modules' => $completed,
                'incomplete_modules' => max(0, $total - $completed),
            ]
        );
    }

    private function squadMission(ResUser $user): array
    {
        if (!Schema::hasTable('squads') || !Schema::hasTable('squad_members')) {
            return $this->mission(
                'squad',
                'Squad Activity',
                'Squad activity is unavailable.',
                'unavailable',
                'squad'
            );
        }

        $membership = DB::table('squad_members')
            ->where('user_id', (int) $user->id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        if (!$membership) {
            return $this->mission(
                'squad',
                'Squad Activity',
                'Create or join a squad for weekly community progress.',
                'ready',
                'squad',
                0,
                ['has_squad' => false]
            );
        }

        $squad = DB::table('squads')->where('id', $membership->squad_id)->where('is_active', 1)->first();
        if (!$squad) {
            return $this->mission(
                'squad',
                'Squad Activity',
                'Your squad is temporarily unavailable.',
                'unavailable',
                'squad'
            );
        }

        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();
        $memberIds = DB::table('squad_members')
            ->where('squad_id', (int) $squad->id)
            ->where('status', 'active')
            ->pluck('user_id');
        $points = 0;

        if ($memberIds->isNotEmpty() && Schema::hasTable('button_clicks') && Schema::hasColumn('button_clicks', 'rewards_start_time')) {
            $points += DB::table('button_clicks')
                ->whereIn('user_id', $memberIds)
                ->whereBetween('rewards_start_time', [$weekStart, $weekEnd])
                ->count() * 10;
        }

        if ($memberIds->isNotEmpty() && Schema::hasTable('tblreward') && Schema::hasColumn('tblreward', 'updated_at')) {
            $points += DB::table('tblreward')
                ->whereIn('userid', $memberIds)
                ->whereBetween('updated_at', [$weekStart, $weekEnd])
                ->count() * 10;
        }

        $target = max(1, (int) ($squad->weekly_target_points ?? 500));
        $progress = (int) min(100, round(($points / $target) * 100));

        return $this->mission(
            'squad',
            'Squad Activity',
            $points >= $target ? 'Your squad chest is ready.' : 'Help your squad build weekly activity.',
            $points >= $target ? 'completed' : 'active',
            'squad',
            $progress,
            [
                'has_squad' => true,
                'squad_id' => (int) $squad->id,
                'squad_name' => $squad->name,
                'weekly_points' => $points,
                'weekly_target_points' => $target,
                'chest_unlocked' => $points >= $target,
            ]
        );
    }

    private function walletSafetyMission(ResUser $user): array
    {
        return $this->mission(
            'wallet_safety',
            'Wallet Safety',
            'Review wallet safety guidance. Never share a recovery phrase.',
            'ready',
            'wallet_safety',
            0,
            ['stores_wallet_secrets' => false]
        );
    }

    private function mission(
        string $key,
        string $title,
        string $subtitle,
        string $status,
        string $action,
        int $progress = 0,
        array $meta = []
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'subtitle' => $subtitle,
            'status' => $status,
            'reward_label' => $key === 'learn' ? 'XP' : 'BRC',
            'action' => $action,
            'progress_percent' => max(0, min(100, $progress)),
            'meta' => $meta,
        ];
    }

    private function titleFor(string $key): string
    {
        $titles = [
            'mining' => 'Start Mining',
            'daily_streak' => 'Daily Streak',
            'reward_task' => 'Reward Task',
            'starter_quest' => 'Starter Quest',
            'referral' => 'Build Your Network',
            'squad' => 'Squad Activity',
            'learn' => 'Learn & Earn',
            'wallet_safety' => 'Wallet Safety',
        ];

        return $titles[$key] ?? 'Daily Mission';
    }
}
