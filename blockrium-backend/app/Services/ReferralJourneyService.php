<?php

namespace App\Services;

use App\Models\ResUser;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReferralJourneyService
{
    private $walletRewards;
    private $gamification;

    public function __construct(WalletRewardService $walletRewards, GamificationService $gamification)
    {
        $this->walletRewards = $walletRewards;
        $this->gamification = $gamification;
    }

    public function getJourney(ResUser $user): array
    {
        $code = $this->ensureReferralCode($user);
        $stats = $this->networkStats($user, $code);
        $milestones = $this->milestonesForUser($user, $stats);
        $stats['claimable_rewards'] = collect($milestones)
            ->where('can_claim', true)
            ->sum(function ($milestone) {
                return (float) $milestone['reward_amount'];
            });
        $stats['claimable_reward_label'] = number_format((float) $stats['claimable_rewards'], 2, '.', '') . ' BRC';

        return [
            'referral_code' => $code,
            'share_message' => 'Join Blockrium with my referral code ' . $code . ' and mine with my network squad.',
            'stats' => $stats,
            'quality' => [
                'score' => (int) $stats['quality_score'],
                'level' => $stats['quality_level'],
                'summary' => $stats['quality_summary'],
                'next_action' => $stats['next_best_action'],
            ],
            'journey_steps' => $this->journeySteps($stats),
            'milestones' => $milestones,
        ];
    }

    public function claimMilestone(ResUser $user, int $milestoneId): array
    {
        if (!Schema::hasTable('referral_milestones') || !Schema::hasTable('referral_milestone_claims')) {
            return ['success' => false, 'status' => 503, 'message' => 'Referral milestones are temporarily unavailable.'];
        }

        return DB::transaction(function () use ($user, $milestoneId) {
            $milestone = DB::table('referral_milestones')
                ->where('id', $milestoneId)
                ->where('is_active', 1)
                ->lockForUpdate()
                ->first();

            if (!$milestone) {
                return ['success' => false, 'status' => 404, 'message' => 'Referral milestone was not found.'];
            }

            $existing = DB::table('referral_milestone_claims')
                ->where('user_id', (int) $user->id)
                ->where('referral_milestone_id', (int) $milestone->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return [
                    'success' => true,
                    'status' => 200,
                    'message' => 'Referral milestone was already claimed.',
                    'already_claimed' => true,
                    'data' => $this->getJourney($user),
                ];
            }

            $code = $this->ensureReferralCode($user);
            $stats = $this->networkStats($user, $code);
            $eligibility = $this->milestoneEligibility($milestone, $stats);

            if (!$eligibility['eligible']) {
                return [
                    'success' => false,
                    'status' => 422,
                    'message' => 'This referral milestone is not ready yet.',
                    'errors' => [],
                    'data' => [
                        'required_active_referrals' => (int) $milestone->required_active_referrals,
                        'active_referrals' => $stats['active_referrals'],
                        'missing_requirements' => $eligibility['missing'],
                    ],
                ];
            }

            try {
                $claimData = [
                    'user_id' => (int) $user->id,
                    'referral_milestone_id' => (int) $milestone->id,
                    'milestone_key' => $milestone->key,
                    'reward_amount' => (float) $milestone->reward_amount,
                    'claimed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('referral_milestone_claims', 'reward_label')) {
                    $claimData['reward_label'] = $milestone->reward_label ?? 'BRC';
                }
                DB::table('referral_milestone_claims')->insert($claimData);
            } catch (QueryException $error) {
                if ((string) $error->getCode() !== '23000') {
                    throw $error;
                }

                return [
                    'success' => true,
                    'status' => 200,
                    'message' => 'Referral milestone was already claimed.',
                    'already_claimed' => true,
                    'data' => $this->getJourney($user),
                ];
            }

            $wallet = null;
            if ((float) $milestone->reward_amount > 0) {
                $wallet = $this->walletRewards->credit((int) $user->id, (float) $milestone->reward_amount);
            }

            $xp = null;
            if ((int) $milestone->reward_xp > 0) {
                $xp = $this->gamification->awardXp(
                    $user,
                    'referral_milestone_claimed',
                    (int) $milestone->reward_xp,
                    'referral_milestone',
                    (int) $milestone->id
                );
            }

            return [
                'success' => true,
                'status' => 200,
                'message' => 'Referral milestone claimed successfully.',
                'already_claimed' => false,
                'reward' => [
                    'amount' => number_format((float) $milestone->reward_amount, 4, '.', ''),
                    'label' => $milestone->reward_label ?? 'BRC',
                ],
                'wallet' => $wallet,
                'gamification' => $xp,
                'data' => $this->getJourney($user),
            ];
        }, 3);
    }

    public function networkStats(ResUser $user, ?string $code = null): array
    {
        if (!Schema::hasTable('referrals')) {
            return $this->emptyStats();
        }

        $code = $code ?: trim((string) $user->refferal_code);
        $userIds = DB::table('referrals')
            ->where(function ($query) use ($user, $code) {
                $query->where('referred_by', (int) $user->id);
                if ($code !== '') {
                    $query->orWhere('referred_by', $code);
                }
            })
            ->pluck('user_id')
            ->unique()
            ->values();

        $total = $userIds->count();
        $active = 0;
        $minedThreeDays = 0;
        $taskActive = 0;
        $streakActive = 0;
        $verified = 0;
        $newThisWeek = 0;

        if ($userIds->isNotEmpty() && Schema::hasTable('button_clicks')) {
            $activeQuery = DB::table('button_clicks')->whereIn('user_id', $userIds);
            if (Schema::hasColumn('button_clicks', 'rewards_start_time')) {
                $activeQuery->where('rewards_start_time', '>=', Carbon::now()->subDay());
            } elseif (Schema::hasColumn('button_clicks', 'created_at')) {
                $activeQuery->where('created_at', '>=', Carbon::now()->subDay());
            }

            $active = $activeQuery->distinct()->count('user_id');

            $miningDateColumn = Schema::hasColumn('button_clicks', 'rewards_start_time')
                ? 'rewards_start_time'
                : (Schema::hasColumn('button_clicks', 'created_at') ? 'created_at' : null);

            if ($miningDateColumn) {
                $minedThreeDays = DB::table('button_clicks')
                    ->whereIn('user_id', $userIds)
                    ->select('user_id')
                    ->groupBy('user_id')
                    ->havingRaw('COUNT(DISTINCT DATE(' . $miningDateColumn . ')) >= 3')
                    ->pluck('user_id')
                    ->count();
            }
        }

        if ($userIds->isNotEmpty() && Schema::hasTable('task_claimeds')) {
            $taskActive = DB::table('task_claimeds')
                ->whereIn('user_id', $userIds)
                ->distinct()
                ->count('user_id');
        }

        if ($userIds->isNotEmpty() && Schema::hasTable('tblattendance')) {
            $attendanceUserColumn = Schema::hasColumn('tblattendance', 'userid') ? 'userid' : 'user_id';
            if (Schema::hasColumn('tblattendance', $attendanceUserColumn)) {
                $streakActive = DB::table('tblattendance')
                    ->whereIn($attendanceUserColumn, $userIds)
                    ->distinct()
                    ->count($attendanceUserColumn);
            }
        }

        if ($userIds->isNotEmpty() && Schema::hasTable('res_users')) {
            $hasVerificationSignal = Schema::hasColumn('res_users', 'email_verified_at')
                || Schema::hasColumn('res_users', 'device_id')
                || Schema::hasColumn('res_users', 'is_active');

            if ($hasVerificationSignal) {
                $verifiedQuery = DB::table('res_users')->whereIn('id', $userIds);
                $verifiedQuery->where(function ($query) {
                if (Schema::hasColumn('res_users', 'email_verified_at')) {
                    $query->whereNotNull('email_verified_at');
                }
                if (Schema::hasColumn('res_users', 'device_id')) {
                    $query->orWhereNotNull('device_id');
                }
                if (Schema::hasColumn('res_users', 'is_active')) {
                    $query->orWhere('is_active', 1);
                }
                });
                $verified = $verifiedQuery->count();
            }

            if (Schema::hasColumn('res_users', 'created_at')) {
                $newThisWeek = DB::table('res_users')
                    ->whereIn('id', $userIds)
                    ->where('created_at', '>=', Carbon::now()->subDays(7))
                    ->count();
            }
        }

        $quality = $this->qualityScore($total, $active, $minedThreeDays, $taskActive, $streakActive, $verified);

        return [
            'total_joined' => $total,
            'active_referrals' => $active,
            'pending_referrals' => max(0, $total - $active),
            'new_this_week' => $newThisWeek,
            'verified_referrals' => $verified,
            'three_day_miners' => $minedThreeDays,
            'task_referrals' => $taskActive,
            'streak_referrals' => $streakActive,
            'inactive_referrals' => max(0, $total - $active),
            'quality_score' => $quality['score'],
            'quality_level' => $quality['level'],
            'quality_summary' => $quality['summary'],
            'next_best_action' => $this->nextBestAction($total, $active, $minedThreeDays, $taskActive, $streakActive),
            'claimable_rewards' => 0,
            'claimable_reward_label' => '0.00 BRC',
        ];
    }

    private function milestonesForUser(ResUser $user, array $stats): array
    {
        if (!Schema::hasTable('referral_milestones')) {
            return [];
        }

        $claimedIds = Schema::hasTable('referral_milestone_claims')
            ? DB::table('referral_milestone_claims')
                ->where('user_id', (int) $user->id)
                ->pluck('referral_milestone_id')
                ->all()
            : [];

        return DB::table('referral_milestones')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function ($milestone) use ($stats, $claimedIds) {
                $claimed = in_array($milestone->id, $claimedIds);
                $eligibility = $this->milestoneEligibility($milestone, $stats);
                $eligible = $eligibility['eligible'];
                $requiredQualityScore = $this->columnValue($milestone, 'required_quality_score', 0);
                $requiredVerified = $this->columnValue($milestone, 'required_verified_referrals', 0);
                $requiredTasks = $this->columnValue($milestone, 'required_task_referrals', 0);
                $requiredStreaks = $this->columnValue($milestone, 'required_streak_referrals', 0);

                return [
                    'id' => (int) $milestone->id,
                    'key' => $milestone->key,
                    'title' => $milestone->title,
                    'description' => $milestone->description,
                    'reward_category' => $this->columnValue($milestone, 'reward_category', 'active_referral'),
                    'required_active_referrals' => (int) $milestone->required_active_referrals,
                    'required_verified_referrals' => $requiredVerified,
                    'required_task_referrals' => $requiredTasks,
                    'required_streak_referrals' => $requiredStreaks,
                    'required_quality_score' => $requiredQualityScore,
                    'current_active_referrals' => (int) $stats['active_referrals'],
                    'current_verified_referrals' => (int) $stats['verified_referrals'],
                    'current_task_referrals' => (int) $stats['task_referrals'],
                    'current_streak_referrals' => (int) $stats['streak_referrals'],
                    'current_quality_score' => (int) $stats['quality_score'],
                    'reward_amount' => number_format((float) $milestone->reward_amount, 2, '.', ''),
                    'reward_label' => $milestone->reward_label ?? 'BRC',
                    'reward_xp' => (int) $milestone->reward_xp,
                    'status' => $claimed ? 'claimed' : ($eligible ? 'ready' : 'locked'),
                    'can_claim' => !$claimed && $eligible,
                    'claimed' => $claimed,
                    'missing_requirements' => $eligibility['missing'],
                ];
            })
            ->all();
    }

    private function milestoneEligibility($milestone, array $stats): array
    {
        $requirements = [
            'active_referrals' => (int) $milestone->required_active_referrals,
            'verified_referrals' => $this->columnValue($milestone, 'required_verified_referrals', 0),
            'task_referrals' => $this->columnValue($milestone, 'required_task_referrals', 0),
            'streak_referrals' => $this->columnValue($milestone, 'required_streak_referrals', 0),
            'quality_score' => $this->columnValue($milestone, 'required_quality_score', 0),
        ];
        $missing = [];

        foreach ($requirements as $key => $required) {
            $current = (int) ($stats[$key] ?? 0);
            if ($required > 0 && $current < $required) {
                $missing[] = [
                    'metric' => $key,
                    'required' => $required,
                    'current' => $current,
                ];
            }
        }

        return [
            'eligible' => empty($missing),
            'missing' => $missing,
        ];
    }

    private function journeySteps(array $stats): array
    {
        return [
            $this->step('invite_one', 'Invite 1 friend', 'Share your code and get one signup.', $stats['total_joined'], 1),
            $this->step('first_miner', 'Friend starts mining', 'At least one referral mines in the last 24 hours.', $stats['active_referrals'], 1),
            $this->step('three_day_miner', '3-day real miner', 'One referral mines on three different days.', $stats['three_day_miners'], 1),
            $this->step('task_active', 'Task active team', 'Three referrals complete at least one task.', $stats['task_referrals'], 3),
            $this->step('streak_active', 'Streak active team', 'Three referrals claim daily streak rewards.', $stats['streak_referrals'], 3),
            $this->step('quality_network', 'Quality network', 'Reach a 65 referral quality score.', $stats['quality_score'], 65),
        ];
    }

    private function step(string $key, string $title, string $description, int $current, int $target): array
    {
        $progress = $target > 0 ? min(100, (int) floor(($current / $target) * 100)) : 0;

        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'current' => $current,
            'target' => $target,
            'progress_percent' => $progress,
            'status' => $current >= $target ? 'complete' : ($current > 0 ? 'active' : 'locked'),
        ];
    }

    private function qualityScore(int $total, int $active, int $threeDay, int $tasks, int $streaks, int $verified): array
    {
        if ($total <= 0) {
            return [
                'score' => 0,
                'level' => 'Starter',
                'summary' => 'Invite your first real user to start building quality.',
            ];
        }

        $score = 0;
        $score += min(20, $total * 4);
        $score += min(20, (int) floor(($active / max(1, $total)) * 20));
        $score += min(20, (int) floor(($threeDay / max(1, $total)) * 20));
        $score += min(15, (int) floor(($tasks / max(1, $total)) * 15));
        $score += min(15, (int) floor(($streaks / max(1, $total)) * 15));
        $score += min(10, (int) floor(($verified / max(1, $total)) * 10));
        $score = min(100, $score);

        if ($score >= 90) {
            $level = 'Diamond';
        } elseif ($score >= 75) {
            $level = 'Gold';
        } elseif ($score >= 50) {
            $level = 'Silver';
        } elseif ($score >= 25) {
            $level = 'Bronze';
        } else {
            $level = 'Starter';
        }

        return [
            'score' => $score,
            'level' => $level,
            'summary' => $level . ' network quality based on active mining, tasks, streaks, and verification.',
        ];
    }

    private function nextBestAction(int $total, int $active, int $threeDay, int $tasks, int $streaks): array
    {
        if ($total <= 0) {
            return ['title' => 'Invite your first friend', 'description' => 'Share your referral code to start earning quality rewards.', 'action' => 'share'];
        }
        if ($active <= 0) {
            return ['title' => 'Help referrals start mining', 'description' => 'Ping inactive referrals and ask them to start a mining cycle.', 'action' => 'ping'];
        }
        if ($threeDay <= 0) {
            return ['title' => 'Build 3-day miners', 'description' => 'Bring one referral back for three mining days.', 'action' => 'ping'];
        }
        if ($tasks < 3) {
            return ['title' => 'Ask referrals to complete tasks', 'description' => 'Task activity makes your network rewards stronger.', 'action' => 'tasks'];
        }
        if ($streaks < 3) {
            return ['title' => 'Grow streak activity', 'description' => 'Encourage referrals to claim daily streak rewards.', 'action' => 'streak'];
        }

        return ['title' => 'Keep your network active', 'description' => 'Maintain mining, streak, and task activity to unlock higher rewards.', 'action' => 'maintain'];
    }

    private function emptyStats(): array
    {
        return [
            'total_joined' => 0,
            'active_referrals' => 0,
            'pending_referrals' => 0,
            'new_this_week' => 0,
            'verified_referrals' => 0,
            'three_day_miners' => 0,
            'task_referrals' => 0,
            'streak_referrals' => 0,
            'inactive_referrals' => 0,
            'quality_score' => 0,
            'quality_level' => 'Starter',
            'quality_summary' => 'Invite your first real user to start building quality.',
            'next_best_action' => ['title' => 'Invite your first friend', 'description' => 'Share your referral code to start earning quality rewards.', 'action' => 'share'],
            'claimable_rewards' => 0,
            'claimable_reward_label' => '0.00 BRC',
        ];
    }

    private function columnValue($row, string $column, $fallback)
    {
        return property_exists($row, $column) ? $row->{$column} : $fallback;
    }

    private function ensureReferralCode(ResUser $user): string
    {
        $code = trim((string) $user->refferal_code);
        if ($code !== '') {
            return $code;
        }

        $prefix = 'BRC';
        if (Schema::hasTable('referral_settings')) {
            $configured = DB::table('referral_settings')->where('setting_key', 'referral_code_prefix')->value('setting_value');
            $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $configured)) ?: 'BRC';
        }

        do {
            $code = $prefix . '-' . strtoupper(Str::random(8));
        } while (DB::table('res_users')->where('refferal_code', $code)->exists());

        $update = ['refferal_code' => $code];
        if (Schema::hasColumn('res_users', 'updated_at')) {
            $update['updated_at'] = now();
        }
        DB::table('res_users')->where('id', (int) $user->id)->update($update);
        $user->refferal_code = $code;

        if (Schema::hasTable('referrals') && !DB::table('referrals')->where('user_id', (int) $user->id)->exists()) {
            $insert = [
                'user_id' => (int) $user->id,
                'referral_code' => $code,
                'referred_by' => null,
                'rewards' => 0,
            ];
            if (Schema::hasColumn('referrals', 'created_at')) {
                $insert['created_at'] = now();
            }
            if (Schema::hasColumn('referrals', 'updated_at')) {
                $insert['updated_at'] = now();
            }
            DB::table('referrals')->insert($insert);
        }

        return $code;
    }
}
