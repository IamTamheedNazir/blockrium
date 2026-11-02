<?php

namespace App\Services;

use App\Models\ResUser;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SquadService
{
    private const DEFAULT_MAX_MEMBERS = 0;
    private const DEFAULT_WEEKLY_TARGET = 500;
    private const ALLOWED_SHARE_CHANNELS = [
        'copy_link',
        'whatsapp',
        'telegram',
        'instagram',
        'facebook',
        'x',
        'sms',
        'email',
        'other',
    ];
    private const MANAGER_ROLES = ['owner', 'co_leader', 'moderator'];
    private const ROLE_ORDER = ['owner' => 0, 'co_leader' => 1, 'moderator' => 2, 'member' => 3];
    private const POSTER_TEMPLATES = ['white', 'dark', 'country', 'weekly_winner'];
    private const CACHE_SECONDS = 45;

    private $gamification;
    private $walletRewards;

    public function __construct(GamificationService $gamification, WalletRewardService $walletRewards)
    {
        $this->gamification = $gamification;
        $this->walletRewards = $walletRewards;
    }

    public function mySquad(ResUser $user, ?int $memberLimit = null, bool $includeMemberGroups = true): array
    {
        $membership = $this->activeMembership((int) $user->id);
        if (!$membership) {
            return [
                'has_squad' => false,
                'squad' => null,
                'discover' => $this->discoverSquads(5, ['country_code' => $user->country ?? null]),
                'recommended' => $this->recommendedSquads($user, 5),
                'social_feed_preview' => $this->feedPreview(null),
            ];
        }

        $squad = DB::table('squads')->where('id', $membership->squad_id)->first();
        if (!$squad || !$squad->is_active) {
            return ['has_squad' => false, 'squad' => null, 'discover' => $this->discoverSquads(5)];
        }

        return [
            'has_squad' => true,
            'membership' => [
                'role' => $membership->role,
                'joined_at' => $membership->joined_at,
            ],
            'squad' => $this->squadPayload($squad, true, $memberLimit, $includeMemberGroups),
            'leaderboard' => $this->leaderboard(10),
            'country_leaderboard' => $this->leaderboard(10, ['country_code' => $this->columnValue($squad, 'country_code')]),
            'missions' => $this->missionsForUser($user),
            'invite_streak' => $this->inviteStreak($user),
            'announcements' => $this->announcements((int) $squad->id),
            'member_tabs' => $includeMemberGroups
                ? $this->memberTabs((int) $squad->id)
                : $this->lightMemberTabs((int) $squad->id),
            'followers' => $this->followerSummary((int) $squad->id, (int) $user->id),
            'social_feed_preview' => $this->feedPreview((int) $squad->id),
        ];
    }

    public function mySquadProfile(ResUser $user): array
    {
        $membership = $this->activeMembership((int) $user->id);
        if (!$membership) {
            return ['has_squad' => false, 'squad' => null];
        }

        $squad = DB::table('squads')->where('id', $membership->squad_id)->first();
        if (!$squad || !$squad->is_active) {
            return ['has_squad' => false, 'squad' => null];
        }

        return [
            'has_squad' => true,
            'membership' => [
                'role' => $membership->role,
                'joined_at' => $membership->joined_at,
            ],
            // The app already has these fields from appHome; this request exists to hydrate weekly stats.
            'squad' => $this->squadPayload($squad, false, null, false, false, true),
        ];
    }

    public function homeSummary(ResUser $user): array
    {
        $data = $this->mySquad($user, 8, false);

        if (!empty($data['squad'])) {
            $data['squad']['members'] = array_slice($data['squad']['members'] ?? [], 0, 8);
            $data['squad']['member_groups'] = $data['squad']['member_groups'] ?? [
                'leaders' => [],
                'active_today' => [],
                'new_joiners' => [],
                'top_miners' => [],
                'top_referrers' => [],
            ];
        }

        $data['fast_loaded'] = true;
        $data['cache_ttl_seconds'] = self::CACHE_SECONDS;

        return $data;
    }

    public function appHome(ResUser $user): array
    {
        $membership = $this->activeMembership((int) $user->id);

        if (!$membership) {
            return [
                'has_squad' => false,
                'squad' => null,
                'discover' => $this->discoverSquads(4, ['country_code' => $user->country ?? null]),
                'recommended' => $this->recommendedSquads($user, 4),
                'leaderboard' => [],
                'country_leaderboard' => [],
                'weekly_missions' => [],
                'announcements' => [],
                'social_feed_preview' => $this->feedPreview(null),
                'unread_announcements_count' => 0,
                'fast_loaded' => true,
                'cache_ttl_seconds' => self::CACHE_SECONDS,
            ];
        }

        $squad = DB::table('squads')->where('id', $membership->squad_id)->where('is_active', 1)->first();
        if (!$squad) {
            return [
                'has_squad' => false,
                'squad' => null,
                'discover' => $this->discoverSquads(4),
                'recommended' => [],
                'leaderboard' => [],
                'country_leaderboard' => [],
                'weekly_missions' => [],
                'announcements' => [],
                'social_feed_preview' => $this->feedPreview(null),
                'unread_announcements_count' => 0,
                'fast_loaded' => true,
                'cache_ttl_seconds' => self::CACHE_SECONDS,
            ];
        }

        $announcements = $this->announcements((int) $squad->id);
        $missions = array_slice($this->missionsForUser($user), 0, 3);
        $squadPayload = $this->squadPayload($squad, true, 6, false, false, false);
        $squadPayload['members'] = array_slice($squadPayload['members'] ?? [], 0, 6);
        $squadPayload['member_groups'] = $this->lightMemberGroups((int) $squad->id, 6);
        $squadPayload['weekly_missions'] = $missions;
        $squadPayload['invite_streak'] = $this->inviteStreak($user);
        $squadPayload['announcements'] = array_slice($announcements, 0, 3);
        $squadPayload['social_feed_preview'] = $this->feedPreview((int) $squad->id);
        $squadPayload['chest_claimed'] = $this->weeklyChestClaimed((int) $squad->id, (int) $user->id);
        unset($squadPayload['activity_breakdown']);

        $countryCode = $this->columnValue($squad, 'country_code');

        return [
            'has_squad' => true,
            'membership' => [
                'role' => $membership->role,
                'joined_at' => $membership->joined_at,
            ],
            'squad' => $squadPayload,
            'member_tabs' => $this->lightMemberTabs((int) $squad->id),
            'weekly_missions' => $missions,
            'missions' => $missions,
            'invite_streak' => $squadPayload['invite_streak'],
            'announcements' => array_slice($announcements, 0, 3),
            'unread_announcements_count' => count($announcements),
            'followers' => $this->followerSummary((int) $squad->id, (int) $user->id),
            'social_feed_preview' => $this->feedPreview((int) $squad->id),
            'leaderboard' => [],
            'country_leaderboard' => $countryCode ? $this->fastCountryLeaderboard((string) $countryCode, 5) : [],
            'fast_loaded' => true,
            'cache_ttl_seconds' => self::CACHE_SECONDS,
        ];
    }

    public function createSquad(ResUser $user, array $data): array
    {
        if (!Schema::hasTable('squads') || !Schema::hasTable('squad_members')) {
            return $this->failure('Squads are temporarily unavailable.', 503);
        }

        if ($this->activeMembership((int) $user->id)) {
            return $this->failure('You are already in a squad.', 422);
        }

        $name = trim((string) $data['name']);
        $slug = $this->uniqueSlug($name);
        $inviteCode = $this->uniqueInviteCode();
        $countryCode = $this->normalizeCountryCode($data['country_code'] ?? ($user->country ?? null));
        $languageCode = $this->normalizeLanguageCode($data['language_code'] ?? null);
        $maxMembers = $this->normalizeMaxMembers($data['max_members'] ?? self::DEFAULT_MAX_MEMBERS);

        $squad = DB::transaction(function () use ($user, $data, $name, $slug, $inviteCode, $countryCode, $languageCode, $maxMembers) {
            $squadData = [
                'name' => $name,
                'slug' => $slug,
                'invite_code' => $inviteCode,
                'owner_user_id' => (int) $user->id,
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'max_members' => $maxMembers,
                'weekly_target_points' => self::DEFAULT_WEEKLY_TARGET,
                'weekly_chest_reward_xp' => 50,
                'weekly_chest_reward_brc' => null,
                'is_public' => !empty($data['is_public']) ? 1 : 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('squads', 'country_code')) {
                $squadData['country_code'] = $countryCode;
            }
            if (Schema::hasColumn('squads', 'language_code')) {
                $squadData['language_code'] = $languageCode;
            }
            if (Schema::hasColumn('squads', 'poster_template')) {
                $squadData['poster_template'] = $this->posterTemplate($data['poster_template'] ?? 'white');
            }
            if (Schema::hasColumn('squads', 'join_policy')) {
                $squadData['join_policy'] = 'open';
            }

            $squadId = DB::table('squads')->insertGetId($squadData);

            DB::table('squad_members')->insert([
                'squad_id' => $squadId,
                'user_id' => (int) $user->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (Schema::hasTable('squad_invites')) {
                DB::table('squad_invites')->insert([
                    'squad_id' => $squadId,
                    'inviter_user_id' => (int) $user->id,
                    'invite_code' => $inviteCode,
                    'uses_count' => 0,
                    'max_uses' => null,
                    'expires_at' => null,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->gamification->awardBadge($user, 'squad_builder', 'squad', $squadId);

            return DB::table('squads')->where('id', $squadId)->first();
        }, 3);

        return [
            'success' => true,
            'status' => 201,
            'message' => 'Squad created.',
            'data' => ['squad' => $this->squadPayload($squad, true)],
        ];
    }

    public function joinSquad(ResUser $user, array $data): array
    {
        if (!Schema::hasTable('squads') || !Schema::hasTable('squad_members')) {
            return $this->failure('Squads are temporarily unavailable.', 503);
        }

        if ($this->activeMembership((int) $user->id)) {
            return $this->failure('You are already in a squad.', 422);
        }

        $squad = null;
        $invite = null;
        $inviteCode = strtoupper(trim((string) ($data['invite_code'] ?? '')));

        if ($inviteCode !== '' && Schema::hasTable('squad_invites')) {
            $invite = DB::table('squad_invites')
                ->where('invite_code', $inviteCode)
                ->where('is_active', 1)
                ->first();

            if (!$invite || ($invite->expires_at && Carbon::parse($invite->expires_at)->isPast())) {
                return $this->failure('Invite code is invalid or expired.', 404);
            }

            if ($invite->max_uses !== null && (int) $invite->uses_count >= (int) $invite->max_uses) {
                return $this->failure('Invite code has reached its use limit.', 422);
            }

            $squad = DB::table('squads')->where('id', $invite->squad_id)->where('is_active', 1)->first();
        } elseif (!empty($data['squad_id'])) {
            $squad = DB::table('squads')
                ->where('id', (int) $data['squad_id'])
                ->where('is_active', 1)
                ->where('is_public', 1)
                ->first();
        }

        if (!$squad) {
            return $this->failure('Squad not found.', 404);
        }

        $memberCount = $this->activeMemberCount((int) $squad->id);
        $maxMembers = (int) $squad->max_members;
        if ($maxMembers > 0 && $memberCount >= $maxMembers) {
            return $this->failure('This squad is full.', 422);
        }

        try {
            DB::transaction(function () use ($user, $squad, $invite) {
                $memberData = [
                    'role' => 'member',
                    'status' => 'active',
                    'joined_at' => now(),
                    'left_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('squad_members', 'invited_by_user_id')) {
                    $memberData['invited_by_user_id'] = $invite ? (int) $invite->inviter_user_id : null;
                }
                if (Schema::hasColumn('squad_members', 'join_source')) {
                    $memberData['join_source'] = $invite ? 'invite_link' : 'discover';
                }

                DB::table('squad_members')->updateOrInsert(
                    ['squad_id' => (int) $squad->id, 'user_id' => (int) $user->id],
                    $memberData
                );

                if ($invite) {
                    DB::table('squad_invites')->where('id', $invite->id)->increment('uses_count');
                }

                $this->gamification->awardBadge($user, 'squad_builder', 'squad', (int) $squad->id);
            }, 3);
        } catch (QueryException $error) {
            if ((string) $error->getCode() !== '23000') {
                throw $error;
            }
            return $this->failure('You are already a member of this squad.', 422);
        }

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Joined squad.',
            'data' => ['squad' => $this->squadPayload($squad, true)],
        ];
    }

    public function leaveSquad(ResUser $user): array
    {
        $membership = $this->activeMembership((int) $user->id);
        if (!$membership) {
            return $this->failure('You are not in a squad.', 404);
        }

        if ($membership->role === 'owner' && $this->activeMemberCount((int) $membership->squad_id) > 1) {
            return $this->failure('Squad owner cannot leave while other members are active.', 422);
        }

        DB::transaction(function () use ($membership) {
            DB::table('squad_members')->where('id', $membership->id)->update([
                'status' => 'left',
                'left_at' => now(),
                'updated_at' => now(),
            ]);

            if ($membership->role === 'owner') {
                DB::table('squads')->where('id', $membership->squad_id)->update([
                    'is_active' => 0,
                    'updated_at' => now(),
                ]);
            }
        }, 3);

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Left squad.',
            'data' => ['has_squad' => false],
        ];
    }

    public function discoverSquads(int $limit = 20, array $filters = [])
    {
        if (!Schema::hasTable('squads')) {
            return collect();
        }

        $limit = min(50, max(1, $limit));
        $page = min(200, max(1, (int) ($filters['page'] ?? 1)));
        $cacheKey = 'squads:discover:' . md5(json_encode([
            'limit' => $limit,
            'page' => $page,
            'country' => $filters['country_code'] ?? $filters['country'] ?? null,
            'language' => $filters['language_code'] ?? $filters['language'] ?? null,
        ]));

        return Cache::remember($cacheKey, self::CACHE_SECONDS, function () use ($limit, $page, $filters) {
            return $this->discoverSquadsFresh($limit, $page, $filters);
        });
    }

    private function discoverSquadsFresh(int $limit, int $page, array $filters)
    {
        $query = DB::table('squads')
            ->where('is_active', 1)
            ->where('is_public', 1);

        $countryCode = $this->normalizeCountryCode($filters['country_code'] ?? $filters['country'] ?? null);
        if ($countryCode && Schema::hasColumn('squads', 'country_code')) {
            $query->where(function ($where) use ($countryCode) {
                $where->where('country_code', $countryCode)->orWhereNull('country_code');
            });
        }

        $languageCode = $this->normalizeLanguageCode($filters['language_code'] ?? $filters['language'] ?? null);
        if ($languageCode && Schema::hasColumn('squads', 'language_code')) {
            $query->where(function ($where) use ($languageCode) {
                $where->where('language_code', $languageCode)->orWhereNull('language_code');
            });
        }

        if (Schema::hasColumn('squads', 'featured_until')) {
            $query->orderByRaw('CASE WHEN featured_until IS NOT NULL AND featured_until >= NOW() THEN 0 ELSE 1 END');
        }
        if (Schema::hasColumn('squads', 'share_count')) {
            $query->orderByDesc('share_count');
        }

        return $query
            ->orderByDesc('id')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function ($squad) {
                return $this->squadPayload($squad, false, null, true, false, false);
            });
    }

    public function leaderboard(int $limit = 20, array $filters = [])
    {
        if (!Schema::hasTable('squads')) {
            return [];
        }

        $query = DB::table('squads')->where('is_active', 1);

        $countryCode = $this->normalizeCountryCode($filters['country_code'] ?? $filters['country'] ?? null);
        if ($countryCode && Schema::hasColumn('squads', 'country_code')) {
            $query->where('country_code', $countryCode);
        }

        $limit = min(50, max(1, $limit));
        $countryCode = $this->normalizeCountryCode($filters['country_code'] ?? $filters['country'] ?? null);
        $cacheKey = 'squads:leaderboard:' . md5(json_encode(['limit' => $limit, 'country' => $countryCode]));

        return Cache::remember($cacheKey, self::CACHE_SECONDS, function () use ($query, $limit) {
            return $query
                ->get()
                ->map(function ($squad) {
                $stats = $this->weeklyStats((int) $squad->id);
                return [
                    'squad_id' => (int) $squad->id,
                    'name' => $squad->name,
                    'slug' => $squad->slug,
                    'country_code' => $this->columnValue($squad, 'country_code'),
                    'member_count' => $this->activeMemberCount((int) $squad->id),
                    'weekly_points' => $stats['points'],
                    'community_boost_percent' => $stats['community_boost_percent'],
                    'chest_unlocked' => $stats['chest_unlocked'],
                    'share_url' => $this->shareUrl($squad->invite_code),
                ];
                })
                ->sortByDesc('weekly_points')
                ->take($limit)
                ->values()
                ->all();
        });
    }

    public function claimWeeklyChest(ResUser $user): array
    {
        $membership = $this->activeMembership((int) $user->id);
        if (!$membership) {
            return $this->failure('Join a squad before claiming a squad chest.', 404);
        }

        $squad = DB::table('squads')->where('id', $membership->squad_id)->where('is_active', 1)->first();
        if (!$squad) {
            return $this->failure('Squad not found.', 404);
        }

        $stats = $this->weeklyStats((int) $squad->id);
        if (!$stats['chest_unlocked']) {
            return $this->failure('Squad chest is not unlocked yet.', 422, [
                'weekly_points' => $stats['points'],
                'weekly_target_points' => (int) $squad->weekly_target_points,
            ]);
        }

        $week = $this->currentWeek();
        try {
            $result = DB::transaction(function () use ($user, $squad, $week, $stats) {
                $exists = DB::table('squad_weekly_chests')
                    ->where('squad_id', (int) $squad->id)
                    ->where('claimed_by', (int) $user->id)
                    ->where('week_start', $week['start']->toDateString())
                    ->lockForUpdate()
                    ->first();

                if ($exists) {
                    return ['duplicate' => true, 'gamification' => $this->gamification->getSummary($user)];
                }

                $rewardXp = (int) $squad->weekly_chest_reward_xp;
                $rewardBrc = $squad->weekly_chest_reward_brc !== null ? (float) $squad->weekly_chest_reward_brc : null;

                DB::table('squad_weekly_chests')->insert([
                    'squad_id' => (int) $squad->id,
                    'week_start' => $week['start']->toDateString(),
                    'week_end' => $week['end']->toDateString(),
                    'status' => 'claimed',
                    'total_points' => $stats['points'],
                    'reward_xp' => $rewardXp,
                    'reward_brc' => $rewardBrc,
                    'claimed_by' => (int) $user->id,
                    'claimed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $xp = $this->gamification->awardXp($user, 'squad_chest_claimed', $rewardXp, 'squad', (int) $squad->id);

                $wallet = null;
                if ($rewardBrc !== null && $rewardBrc > 0) {
                    $wallet = $this->walletRewards->credit((int) $user->id, $rewardBrc);
                }

                return [
                    'duplicate' => false,
                    'gamification' => $xp['summary'] ?? $this->gamification->getSummary($user),
                    'wallet' => $wallet,
                    'reward_xp' => $rewardXp,
                    'reward_brc' => $rewardBrc,
                ];
            }, 3);
        } catch (QueryException $error) {
            if ((string) $error->getCode() !== '23000') {
                throw $error;
            }
            $result = ['duplicate' => true, 'gamification' => $this->gamification->getSummary($user)];
        }

        return [
            'success' => true,
            'status' => 200,
            'message' => !empty($result['duplicate']) ? 'Squad chest was already claimed this week.' : 'Squad chest claimed.',
            'data' => [
                'claimed' => empty($result['duplicate']),
                'reward_xp' => (int) ($result['reward_xp'] ?? 0),
                'reward_brc' => $result['reward_brc'] ?? null,
                'gamification' => $result['gamification'],
            ],
        ];
    }

    public function missionsForUser(ResUser $user): array
    {
        $membership = $this->activeMembership((int) $user->id);
        if (!$membership || !Schema::hasTable('squad_missions')) {
            return [];
        }

        $week = $this->currentWeek();
        return DB::table('squad_missions')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function ($mission) use ($membership, $user, $week) {
                $progress = $this->missionProgress($mission, (int) $membership->squad_id, (int) $user->id, $week);
                $target = max(1, (int) $mission->target_value);
                $claimed = Schema::hasTable('squad_mission_claims')
                    ? DB::table('squad_mission_claims')
                        ->where('squad_id', (int) $membership->squad_id)
                        ->where('user_id', (int) $user->id)
                        ->where('mission_id', (int) $mission->id)
                        ->where('week_start', $week['start']->toDateString())
                        ->exists()
                    : false;

                return [
                    'id' => (int) $mission->id,
                    'key' => $mission->key,
                    'title' => $mission->title,
                    'description' => $mission->description,
                    'metric' => $mission->metric,
                    'progress' => $progress,
                    'target' => $target,
                    'progress_percent' => (int) min(100, round(($progress / $target) * 100)),
                    'completed' => $progress >= $target,
                    'claimed' => $claimed,
                    'can_claim' => $progress >= $target && !$claimed,
                    'reward_xp' => (int) $mission->reward_xp,
                    'reward_brc' => $mission->reward_brc === null ? null : (float) $mission->reward_brc,
                    'reward_label' => $this->rewardLabel((int) $mission->reward_xp, $mission->reward_brc === null ? null : (float) $mission->reward_brc),
                ];
            })
            ->all();
    }

    public function claimMission(ResUser $user, int $missionId): array
    {
        $membership = $this->activeMembership((int) $user->id);
        if (!$membership) {
            return $this->failure('Join a squad before claiming squad missions.', 404);
        }
        if (!Schema::hasTable('squad_missions') || !Schema::hasTable('squad_mission_claims')) {
            return $this->failure('Squad missions are temporarily unavailable.', 503);
        }

        $mission = DB::table('squad_missions')->where('id', $missionId)->where('is_active', 1)->first();
        if (!$mission) {
            return $this->failure('Squad mission not found.', 404);
        }

        $week = $this->currentWeek();
        $progress = $this->missionProgress($mission, (int) $membership->squad_id, (int) $user->id, $week);
        if ($progress < (int) $mission->target_value) {
            return $this->failure('Squad mission is not complete yet.', 422, [
                'progress' => $progress,
                'target' => (int) $mission->target_value,
            ]);
        }

        try {
            $result = DB::transaction(function () use ($user, $membership, $mission, $week, $progress) {
                $exists = DB::table('squad_mission_claims')
                    ->where('squad_id', (int) $membership->squad_id)
                    ->where('user_id', (int) $user->id)
                    ->where('mission_id', (int) $mission->id)
                    ->where('week_start', $week['start']->toDateString())
                    ->lockForUpdate()
                    ->first();

                if ($exists) {
                    return ['duplicate' => true, 'gamification' => $this->gamification->getSummary($user)];
                }

                $rewardXp = (int) $mission->reward_xp;
                $rewardBrc = $mission->reward_brc === null ? null : (float) $mission->reward_brc;

                DB::table('squad_mission_claims')->insert([
                    'squad_id' => (int) $membership->squad_id,
                    'user_id' => (int) $user->id,
                    'mission_id' => (int) $mission->id,
                    'week_start' => $week['start']->toDateString(),
                    'progress_value' => $progress,
                    'reward_xp' => $rewardXp,
                    'reward_brc' => $rewardBrc,
                    'claimed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $xp = $rewardXp > 0
                    ? $this->gamification->awardXp($user, 'squad_mission_claimed', $rewardXp, 'squad_mission', (int) $mission->id)
                    : ['summary' => $this->gamification->getSummary($user)];

                $wallet = null;
                if ($rewardBrc !== null && $rewardBrc > 0) {
                    $wallet = $this->walletRewards->credit((int) $user->id, $rewardBrc);
                }

                return [
                    'duplicate' => false,
                    'reward_xp' => $rewardXp,
                    'reward_brc' => $rewardBrc,
                    'gamification' => $xp['summary'] ?? $this->gamification->getSummary($user),
                    'wallet' => $wallet,
                ];
            }, 3);
        } catch (QueryException $error) {
            if ((string) $error->getCode() !== '23000') {
                throw $error;
            }
            $result = ['duplicate' => true, 'gamification' => $this->gamification->getSummary($user)];
        }

        return [
            'success' => true,
            'status' => 200,
            'message' => !empty($result['duplicate']) ? 'Squad mission was already claimed this week.' : 'Squad mission claimed.',
            'data' => [
                'claimed' => empty($result['duplicate']),
                'reward_xp' => (int) ($result['reward_xp'] ?? 0),
                'reward_brc' => $result['reward_brc'] ?? null,
                'missions' => $this->missionsForUser($user),
                'gamification' => $result['gamification'],
            ],
        ];
    }

    public function inviteStreak(ResUser $user): array
    {
        $membership = $this->activeMembership((int) $user->id);
        if (!$membership) {
            return [
                'active_invites_today' => 0,
                'streak_days' => 0,
                'target' => 1,
                'claimable' => false,
                'can_claim' => false,
                'claimed_today' => false,
                'claimed' => false,
                'reward_xp' => 0,
                'reward_label' => null,
            ];
        }

        $today = Carbon::today();
        $activeInvites = $this->activeInviteCount((int) $user->id, (int) $membership->squad_id, $today->copy()->startOfDay(), $today->copy()->endOfDay());
        $claimed = Schema::hasTable('squad_invite_streak_claims')
            ? DB::table('squad_invite_streak_claims')->where('user_id', (int) $user->id)->where('streak_date', $today->toDateString())->first()
            : null;
        $yesterday = Schema::hasTable('squad_invite_streak_claims')
            ? DB::table('squad_invite_streak_claims')->where('user_id', (int) $user->id)->where('streak_date', $today->copy()->subDay()->toDateString())->first()
            : null;
        $streakDays = $claimed ? (int) $claimed->streak_days : ($activeInvites > 0 ? ((int) ($yesterday->streak_days ?? 0) + 1) : 0);

        return [
            'active_invites_today' => $activeInvites,
            'streak_days' => $streakDays,
            'target' => 1,
            'claimable' => $activeInvites > 0 && !$claimed,
            'can_claim' => $activeInvites > 0 && !$claimed,
            'claimed_today' => (bool) $claimed,
            'claimed' => (bool) $claimed,
            'reward_xp' => min(100, 30 + ($activeInvites * 10)),
            'reward_label' => $this->rewardLabel(min(100, 30 + ($activeInvites * 10)), null),
        ];
    }

    public function claimInviteStreak(ResUser $user): array
    {
        $membership = $this->activeMembership((int) $user->id);
        if (!$membership) {
            return $this->failure('Join a squad before claiming invite streaks.', 404);
        }
        if (!Schema::hasTable('squad_invite_streak_claims')) {
            return $this->failure('Invite streak rewards are temporarily unavailable.', 503);
        }

        $today = Carbon::today();
        $activeInvites = $this->activeInviteCount((int) $user->id, (int) $membership->squad_id, $today->copy()->startOfDay(), $today->copy()->endOfDay());
        if ($activeInvites <= 0) {
            return $this->failure('Invite streak is not ready. Bring an active invited member today.', 422);
        }

        try {
            $result = DB::transaction(function () use ($user, $membership, $today, $activeInvites) {
                $exists = DB::table('squad_invite_streak_claims')
                    ->where('user_id', (int) $user->id)
                    ->where('streak_date', $today->toDateString())
                    ->lockForUpdate()
                    ->first();
                if ($exists) {
                    return ['duplicate' => true, 'gamification' => $this->gamification->getSummary($user)];
                }

                $yesterday = DB::table('squad_invite_streak_claims')
                    ->where('user_id', (int) $user->id)
                    ->where('streak_date', $today->copy()->subDay()->toDateString())
                    ->first();
                $streakDays = (int) ($yesterday->streak_days ?? 0) + 1;
                $rewardXp = min(100, 30 + ($activeInvites * 10));

                DB::table('squad_invite_streak_claims')->insert([
                    'squad_id' => (int) $membership->squad_id,
                    'user_id' => (int) $user->id,
                    'streak_date' => $today->toDateString(),
                    'active_invites' => $activeInvites,
                    'streak_days' => $streakDays,
                    'reward_xp' => $rewardXp,
                    'reward_brc' => null,
                    'claimed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $xp = $this->gamification->awardXp($user, 'squad_invite_streak', $rewardXp, 'squad', (int) $membership->squad_id);

                return [
                    'duplicate' => false,
                    'active_invites' => $activeInvites,
                    'streak_days' => $streakDays,
                    'reward_xp' => $rewardXp,
                    'gamification' => $xp['summary'] ?? $this->gamification->getSummary($user),
                ];
            }, 3);
        } catch (QueryException $error) {
            if ((string) $error->getCode() !== '23000') {
                throw $error;
            }
            $result = ['duplicate' => true, 'gamification' => $this->gamification->getSummary($user)];
        }

        return [
            'success' => true,
            'status' => 200,
            'message' => !empty($result['duplicate']) ? 'Invite streak was already claimed today.' : 'Invite streak claimed.',
            'data' => [
                'claimed' => empty($result['duplicate']),
                'invite_streak' => $this->inviteStreak($user),
                'gamification' => $result['gamification'],
            ],
        ];
    }

    public function shareCard(ResUser $user): array
    {
        $membership = $this->activeMembership((int) $user->id);
        if (!$membership) {
            return $this->failure('Join or create a squad before sharing.', 404);
        }

        $squad = DB::table('squads')->where('id', $membership->squad_id)->where('is_active', 1)->first();
        if (!$squad) {
            return $this->failure('Squad not found.', 404);
        }

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Squad share card ready.',
            'data' => [
                'squad' => $this->squadPayload($squad, false, null, true, false, false),
                'poster' => $this->posterPayload($squad),
                'share_options' => $this->shareOptions($squad),
            ],
        ];
    }

    public function recordShare(ResUser $user, array $data, ?string $ip = null, ?string $userAgent = null): array
    {
        $membership = $this->activeMembership((int) $user->id);
        if (!$membership) {
            return $this->failure('Join or create a squad before sharing.', 404);
        }

        $squad = DB::table('squads')->where('id', $membership->squad_id)->where('is_active', 1)->first();
        if (!$squad) {
            return $this->failure('Squad not found.', 404);
        }

        $channel = strtolower(trim((string) ($data['channel'] ?? 'copy_link')));
        if (!in_array($channel, self::ALLOWED_SHARE_CHANNELS, true)) {
            $channel = 'other';
        }

        DB::transaction(function () use ($squad, $user, $channel, $ip, $userAgent) {
            if (Schema::hasTable('squad_share_events')) {
                DB::table('squad_share_events')->insert([
                    'squad_id' => (int) $squad->id,
                    'user_id' => (int) $user->id,
                    'invite_code' => $squad->invite_code,
                    'channel' => $channel,
                    'share_url' => $this->shareUrl($squad->invite_code),
                    'ip_hash' => $ip ? hash('sha256', $ip . '|' . config('app.key')) : null,
                    'user_agent' => $userAgent ? Str::limit($userAgent, 255, '') : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (Schema::hasColumn('squads', 'share_count')) {
                DB::table('squads')->where('id', (int) $squad->id)->increment('share_count');
            }
        }, 3);

        $fresh = DB::table('squads')->where('id', (int) $squad->id)->first();

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Squad share tracked.',
            'data' => [
                'squad' => $this->squadPayload($fresh ?: $squad, false, null, true, false, false),
                'poster' => $this->posterPayload($fresh ?: $squad),
                'share_options' => $this->shareOptions($fresh ?: $squad),
            ],
        ];
    }

    public function regenerateInvite(ResUser $user): array
    {
        $membership = $this->activeMembership((int) $user->id);
        if (!$membership || !in_array($membership->role, ['owner', 'co_leader'], true)) {
            return $this->failure('Only the squad owner or co-leader can refresh the invite code.', 403);
        }

        $squad = DB::table('squads')->where('id', $membership->squad_id)->where('is_active', 1)->first();
        if (!$squad) {
            return $this->failure('Squad not found.', 404);
        }

        $inviteCode = $this->uniqueInviteCode();
        DB::transaction(function () use ($squad, $user, $inviteCode) {
            DB::table('squads')->where('id', (int) $squad->id)->update([
                'invite_code' => $inviteCode,
                'updated_at' => now(),
            ]);

            if (Schema::hasTable('squad_invites')) {
                DB::table('squad_invites')->where('squad_id', (int) $squad->id)->update([
                    'is_active' => 0,
                    'updated_at' => now(),
                ]);

                DB::table('squad_invites')->insert([
                    'squad_id' => (int) $squad->id,
                    'inviter_user_id' => (int) $user->id,
                    'invite_code' => $inviteCode,
                    'uses_count' => 0,
                    'max_uses' => null,
                    'expires_at' => null,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }, 3);

        $fresh = DB::table('squads')->where('id', (int) $squad->id)->first();

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Squad invite refreshed.',
            'data' => [
                'squad' => $this->squadPayload($fresh, true),
                'poster' => $this->posterPayload($fresh),
                'share_options' => $this->shareOptions($fresh),
            ],
        ];
    }

    public function updateMemberRole(ResUser $user, int $memberUserId, string $role): array
    {
        $membership = $this->activeMembership((int) $user->id);
        if (!$membership || !in_array($membership->role, ['owner', 'co_leader'], true)) {
            return $this->failure('Only squad owners and co-leaders can update roles.', 403);
        }

        $role = $this->normalizeRole($role);
        if (!$role || $role === 'owner') {
            return $this->failure('Invalid squad role.', 422);
        }
        if ($membership->role === 'co_leader' && $role === 'co_leader') {
            return $this->failure('Only the squad owner can assign co-leaders.', 403);
        }

        $target = DB::table('squad_members')
            ->where('squad_id', (int) $membership->squad_id)
            ->where('user_id', $memberUserId)
            ->where('status', 'active')
            ->first();

        if (!$target) {
            return $this->failure('Squad member not found.', 404);
        }
        if ($target->role === 'owner') {
            return $this->failure('The squad owner role cannot be changed here.', 422);
        }

        DB::table('squad_members')->where('id', $target->id)->update([
            'role' => $role,
            'updated_at' => now(),
        ]);

        $squad = DB::table('squads')->where('id', (int) $membership->squad_id)->first();

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Squad role updated.',
            'data' => ['squad' => $this->squadPayload($squad, true)],
        ];
    }

    public function removeMember(ResUser $user, int $memberUserId): array
    {
        $membership = $this->activeMembership((int) $user->id);
        if (!$membership || !in_array($membership->role, ['owner', 'co_leader'], true)) {
            return $this->failure('Only squad owners and co-leaders can remove members.', 403);
        }
        if ((int) $user->id === $memberUserId) {
            return $this->failure('Use leave squad for your own membership.', 422);
        }

        $target = DB::table('squad_members')
            ->where('squad_id', (int) $membership->squad_id)
            ->where('user_id', $memberUserId)
            ->where('status', 'active')
            ->first();

        if (!$target) {
            return $this->failure('Squad member not found.', 404);
        }
        if ($target->role === 'owner') {
            return $this->failure('The squad owner cannot be removed.', 422);
        }

        DB::table('squad_members')->where('id', $target->id)->update([
            'status' => 'removed',
            'left_at' => now(),
            'updated_at' => now(),
        ]);

        $squad = DB::table('squads')->where('id', (int) $membership->squad_id)->first();

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Squad member removed.',
            'data' => ['squad' => $this->squadPayload($squad, true)],
        ];
    }

    public function announcements(int $squadId): array
    {
        if (!Schema::hasTable('squad_announcements')) {
            return [];
        }

        $now = now();
        return DB::table('squad_announcements')
            ->where('status', 'active')
            ->where(function ($query) use ($squadId) {
                $query->whereNull('squad_id')->orWhere('squad_id', $squadId);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function ($announcement) {
                return [
                    'id' => (int) $announcement->id,
                    'template_key' => $announcement->template_key,
                    'title' => $announcement->title,
                    'body' => $announcement->body,
                    'cta_label' => $announcement->cta_label,
                    'starts_at' => $announcement->starts_at,
                    'ends_at' => $announcement->ends_at,
                ];
            })
            ->all();
    }

    public function publicInvitePayload(string $inviteCode): ?array
    {
        if (!Schema::hasTable('squads')) {
            return null;
        }

        $code = strtoupper(trim($inviteCode));
        $squad = DB::table('squads')
            ->where('invite_code', $code)
            ->where('is_active', 1)
            ->first();

        if (!$squad) {
            return null;
        }

        return [
            'squad' => $this->squadPayload($squad, false, null, true, false, false),
            'poster' => $this->posterPayload($squad),
            'share_options' => $this->shareOptions($squad),
        ];
    }

    public function squadPayload($squad, bool $includeMembers = false, ?int $memberLimit = null, bool $includeMemberGroups = true, bool $includeRank = true, bool $includeStats = true): array
    {
        $target = max(1, (int) $this->columnValue($squad, 'weekly_target_points', self::DEFAULT_WEEKLY_TARGET));
        $week = $this->currentWeek();
        $stats = $includeStats
            ? $this->weeklyStats((int) $squad->id)
            : [
                'points' => 0,
                'breakdown' => [],
                'progress_percent' => 0,
                'community_boost_percent' => 0,
                'chest_unlocked' => false,
                'week_start' => $week['start']->toDateString(),
                'week_end' => $week['end']->toDateString(),
                'deferred' => true,
            ];
        $memberCount = $this->activeMemberCount((int) $squad->id);
        $rawMaxMembers = (int) $this->columnValue($squad, 'max_members', self::DEFAULT_MAX_MEMBERS);
        $isUnlimited = $rawMaxMembers <= 0;
        $countryCode = $this->columnValue($squad, 'country_code');
        $promoted = $this->isPromotedSquad($squad);
        $payload = [
            'id' => (int) $squad->id,
            'name' => $squad->name,
            'slug' => $squad->slug,
            'invite_code' => $squad->invite_code,
            'description' => $squad->description,
            'country_code' => $countryCode,
            'country_name' => $this->countryName($countryCode),
            'language_code' => $this->columnValue($squad, 'language_code'),
            'poster_template' => $this->columnValue($squad, 'poster_template', 'white'),
            'join_policy' => $this->columnValue($squad, 'join_policy', 'open'),
            'share_count' => (int) $this->columnValue($squad, 'share_count', 0),
            'share_url' => $this->shareUrl($squad->invite_code),
            'share_text' => $this->shareText($squad),
            'poster_url' => $this->posterSvgUrl($squad->invite_code),
            'qr_url' => $this->qrUrl($squad->invite_code),
            'member_count' => $memberCount,
            'max_members' => $isUnlimited ? null : $rawMaxMembers,
            'max_members_raw' => $rawMaxMembers,
            'is_unlimited' => $isUnlimited,
            'capacity_label' => $isUnlimited ? 'Unlimited' : number_format($rawMaxMembers),
            'spots_left' => $isUnlimited ? null : max(0, $rawMaxMembers - $memberCount),
            'is_public' => (bool) $squad->is_public,
            'featured' => $promoted,
            'promoted' => $promoted,
            'weekly_target_points' => $target,
            'weekly_points' => $stats['points'],
            'weekly_progress_percent' => $stats['progress_percent'],
            'community_boost_percent' => $stats['community_boost_percent'],
            'boost_percent' => $stats['community_boost_percent'],
            'chest_unlocked' => $stats['chest_unlocked'],
            'week_start' => $stats['week_start'],
            'week_end' => $stats['week_end'],
            'stats_deferred' => !empty($stats['deferred']),
            'country_rank' => $includeRank ? $this->countryRank($squad) : null,
            'social' => $this->socialSummary((int) $squad->id),
        ];

        if ($includeMembers) {
            $payload['members'] = $this->members((int) $squad->id, $memberLimit);
            $payload['member_groups'] = $includeMemberGroups
                ? $this->memberGroups((int) $squad->id)
                : [
                    'leaders' => [],
                    'active_today' => [],
                    'new_joiners' => [],
                    'top_miners' => [],
                    'top_referrers' => [],
                ];
            $payload['activity_breakdown'] = $stats['breakdown'];
        }

        return $payload;
    }

    public function activeMembership(int $userId)
    {
        if (!Schema::hasTable('squad_members')) {
            return null;
        }

        return DB::table('squad_members')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();
    }

    public function weeklyStats(int $squadId): array
    {
        $week = $this->currentWeek();
        return Cache::remember(
            'squads:weekly-stats:' . $squadId . ':' . $week['start']->toDateString(),
            self::CACHE_SECONDS,
            function () use ($squadId, $week) {
                return $this->weeklyStatsFresh($squadId, $week);
            }
        );
    }

    private function weeklyStatsFresh(int $squadId, array $week): array
    {
        $memberIds = $this->activeMemberIds($squadId);
        $squad = Schema::hasTable('squads') ? DB::table('squads')->where('id', $squadId)->first() : null;
        $target = max(1, (int) ($squad->weekly_target_points ?? self::DEFAULT_WEEKLY_TARGET));

        $breakdown = [
            'participation_sessions' => $this->activityCount('button_clicks', 'user_id', $memberIds, ['rewards_start_time', 'created_at'], $week) * 10,
            'streak_claims' => $this->activityCount('tblreward', 'userid', $memberIds, ['updated_at'], $week) * 10,
            'task_claims' => $this->activityCount('task_claimeds', 'user_id', $memberIds, ['created_at', 'updated_at'], $week) * 15,
            'learn_lessons' => $this->activityCount('user_learn_progress', 'user_id', $memberIds, ['completed_at'], $week, ['status' => 'completed']) * 20,
            'referrals' => $this->referralActivityPoints($memberIds, $week),
        ];

        $points = array_sum($breakdown);

        return [
            'points' => $points,
            'breakdown' => $breakdown,
            'progress_percent' => (int) min(100, round(($points / $target) * 100)),
            'community_boost_percent' => $this->communityBoostPercent($points, $target),
            'chest_unlocked' => $points >= $target,
            'week_start' => $week['start']->toDateString(),
            'week_end' => $week['end']->toDateString(),
        ];
    }

    private function members(int $squadId, ?int $limit = null): array
    {
        if (!Schema::hasTable('squad_members')) {
            return [];
        }

        $query = DB::table('squad_members')
            ->leftJoin('res_users', 'squad_members.user_id', '=', 'res_users.id')
            ->where('squad_members.squad_id', $squadId)
            ->where('squad_members.status', 'active')
            ->orderByRaw("CASE squad_members.role WHEN 'owner' THEN 0 WHEN 'co_leader' THEN 1 WHEN 'moderator' THEN 2 ELSE 3 END")
            ->orderBy('squad_members.joined_at')
            ->select('squad_members.*', 'res_users.name', 'res_users.username', 'res_users.uid');

        if ($limit !== null) {
            $query->limit(max(1, min(100, $limit)));
        }

        return $query->get()
            ->map(function ($member) {
                return [
                    'user_id' => (int) $member->user_id,
                    'name' => $member->name ?: $member->username ?: 'Member #' . $member->user_id,
                    'username' => $member->username,
                    'uid' => $member->uid,
                    'role' => $member->role,
                    'join_source' => $this->columnValue($member, 'join_source'),
                    'invited_by_user_id' => $this->columnValue($member, 'invited_by_user_id'),
                    'joined_at' => $member->joined_at,
                ];
            })
            ->all();
    }

    public function membersForTab(ResUser $user, string $tab = 'members', int $limit = 25): array
    {
        $membership = $this->activeMembership((int) $user->id);
        if (!$membership) {
            return [];
        }

        $groups = $this->memberGroups((int) $membership->squad_id);
        $key = strtolower(trim($tab));
        if ($key === 'top_referrers') {
            return array_slice($groups['top_referrers'], 0, $limit);
        }
        if ($key === 'top_miners') {
            return array_slice($groups['top_miners'], 0, $limit);
        }
        if ($key === 'active_today') {
            return array_slice($groups['active_today'], 0, $limit);
        }
        if ($key === 'new_joiners') {
            return array_slice($groups['new_joiners'], 0, $limit);
        }
        if ($key === 'leaders') {
            return array_slice($groups['leaders'], 0, $limit);
        }

        return array_slice($this->members((int) $membership->squad_id), 0, $limit);
    }

    private function memberTabs(int $squadId): array
    {
        $groups = $this->memberGroups($squadId);

        return [
            ['key' => 'members', 'label' => 'Members', 'count' => $this->activeMemberCount($squadId)],
            ['key' => 'active_today', 'label' => 'Active Today', 'count' => count($groups['active_today'])],
            ['key' => 'new_joiners', 'label' => 'New Joiners', 'count' => count($groups['new_joiners'])],
            ['key' => 'top_miners', 'label' => 'Top Miners', 'count' => count($groups['top_miners'])],
            ['key' => 'top_referrers', 'label' => 'Top Referrers', 'count' => count($groups['top_referrers'])],
        ];
    }

    private function lightMemberTabs(int $squadId): array
    {
        $today = [
            'start' => Carbon::today()->startOfDay(),
            'end' => Carbon::today()->endOfDay(),
        ];
        $week = $this->currentWeek();

        return [
            ['key' => 'members', 'label' => 'Members', 'count' => $this->activeMemberCount($squadId)],
            ['key' => 'active_today', 'label' => 'Active Today', 'count' => count($this->activeMemberIdsBetween($squadId, $today['start'], $today['end']))],
            ['key' => 'new_joiners', 'label' => 'New Joiners', 'count' => $this->newJoinerCount($squadId)],
            ['key' => 'top_miners', 'label' => 'Top Miners', 'count' => count($this->topMiningMemberIds($squadId, $week, 24))],
            ['key' => 'top_referrers', 'label' => 'Top Referrers', 'count' => count($this->topReferralMemberIds($squadId, $week, 24))],
        ];
    }

    private function lightMemberGroups(int $squadId, int $limit = 6): array
    {
        $limit = max(1, min(24, $limit));
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();
        $week = $this->currentWeek();

        return Cache::remember('squads:light-member-groups:' . $squadId . ':' . $limit, self::CACHE_SECONDS, function () use ($squadId, $limit, $todayStart, $todayEnd, $week) {
            $leaderIds = DB::table('squad_members')
                ->where('squad_id', $squadId)
                ->where('status', 'active')
                ->whereIn('role', self::MANAGER_ROLES)
                ->orderByRaw("CASE role WHEN 'owner' THEN 0 WHEN 'co_leader' THEN 1 WHEN 'moderator' THEN 2 ELSE 3 END")
                ->orderBy('joined_at')
                ->limit($limit)
                ->pluck('user_id')
                ->map(function ($id) {
                    return (int) $id;
                })
                ->all();

            $newJoinerIds = DB::table('squad_members')
                ->where('squad_id', $squadId)
                ->where('status', 'active')
                ->where('joined_at', '>=', Carbon::now()->subDays(7))
                ->orderByDesc('joined_at')
                ->limit($limit)
                ->pluck('user_id')
                ->map(function ($id) {
                    return (int) $id;
                })
                ->all();

            return [
                'leaders' => $this->membersFromIds($squadId, $leaderIds),
                'active_today' => $this->membersFromIds($squadId, $this->activeMemberIdsBetween($squadId, $todayStart, $todayEnd, $limit)),
                'new_joiners' => $this->membersFromIds($squadId, $newJoinerIds),
                'top_miners' => $this->membersFromIds($squadId, $this->topMiningMemberIds($squadId, $week, $limit), 'mining'),
                'top_referrers' => $this->membersFromIds($squadId, $this->topReferralMemberIds($squadId, $week, $limit), 'referrals'),
            ];
        });
    }

    private function memberGroups(int $squadId): array
    {
        return Cache::remember('squads:member-groups:' . $squadId, self::CACHE_SECONDS, function () use ($squadId) {
            $members = $this->members($squadId);
            $todayStart = Carbon::today()->startOfDay();
            $todayEnd = Carbon::today()->endOfDay();
            $week = $this->currentWeek();

            $leaders = array_values(array_filter($members, function ($member) {
                return in_array($member['role'], self::MANAGER_ROLES, true);
            }));

            $activeToday = array_values(array_filter($members, function ($member) use ($todayStart, $todayEnd) {
                return $this->userHasActivityBetween((int) $member['user_id'], $todayStart, $todayEnd);
            }));

            $newJoiners = array_values(array_filter($members, function ($member) {
                return !empty($member['joined_at']) && Carbon::parse($member['joined_at'])->greaterThanOrEqualTo(Carbon::now()->subDays(7));
            }));

            $topMiners = $this->rankMembersByActivity($members, $week, 'mining');
            $topReferrers = $this->rankMembersByActivity($members, $week, 'referrals');

            return [
                'leaders' => array_slice($leaders, 0, 12),
                'active_today' => array_slice($activeToday, 0, 24),
                'new_joiners' => array_slice($newJoiners, 0, 24),
                'top_miners' => array_slice($topMiners, 0, 24),
                'top_referrers' => array_slice($topReferrers, 0, 24),
            ];
        });
    }

    private function membersFromIds(int $squadId, array $userIds, ?string $scoreMetric = null): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (!$userIds) {
            return [];
        }

        $members = DB::table('squad_members')
            ->leftJoin('res_users', 'squad_members.user_id', '=', 'res_users.id')
            ->where('squad_members.squad_id', $squadId)
            ->where('squad_members.status', 'active')
            ->whereIn('squad_members.user_id', $userIds)
            ->select('squad_members.*', 'res_users.name', 'res_users.username', 'res_users.uid')
            ->get()
            ->keyBy('user_id');

        $scores = [];
        if ($scoreMetric === 'mining') {
            $scores = $this->memberActivityScores($userIds, $this->currentWeek());
        } elseif ($scoreMetric === 'referrals') {
            $scores = $this->memberReferralScores($userIds, $this->currentWeek());
        }

        $ordered = [];
        foreach ($userIds as $userId) {
            if (!$members->has($userId)) {
                continue;
            }
            $member = $members->get($userId);
            $payload = [
                'user_id' => (int) $member->user_id,
                'name' => $member->name ?: $member->username ?: 'Member #' . $member->user_id,
                'username' => $member->username,
                'uid' => $member->uid,
                'role' => $member->role,
                'join_source' => $this->columnValue($member, 'join_source'),
                'invited_by_user_id' => $this->columnValue($member, 'invited_by_user_id'),
                'joined_at' => $member->joined_at,
            ];
            if (isset($scores[$userId])) {
                $payload['score'] = (int) $scores[$userId];
            }
            $ordered[] = $payload;
        }

        return $ordered;
    }

    private function activeMemberIdsBetween(int $squadId, Carbon $start, Carbon $end, int $limit = 0): array
    {
        $memberIds = $this->activeMemberIds($squadId);
        if (!$memberIds) {
            return [];
        }

        $ids = [];
        foreach ($this->activitySources() as $source) {
            $dateColumn = $this->firstAvailableColumn($source['table'], $source['date_columns']);
            if (!$dateColumn || !Schema::hasColumn($source['table'], $source['user_column'])) {
                continue;
            }

            $query = DB::table($source['table'])
                ->whereIn($source['user_column'], $memberIds)
                ->whereBetween($dateColumn, [$start, $end])
                ->select($source['user_column'])
                ->distinct();

            foreach ($source['where'] ?? [] as $column => $value) {
                if (Schema::hasColumn($source['table'], $column)) {
                    $query->where($column, $value);
                }
            }

            foreach ($query->pluck($source['user_column']) as $id) {
                $ids[(int) $id] = (int) $id;
                if ($limit > 0 && count($ids) >= $limit) {
                    break 2;
                }
            }
        }

        return array_values($ids);
    }

    private function newJoinerCount(int $squadId): int
    {
        if (!Schema::hasTable('squad_members') || !Schema::hasColumn('squad_members', 'joined_at')) {
            return 0;
        }

        return (int) DB::table('squad_members')
            ->where('squad_id', $squadId)
            ->where('status', 'active')
            ->where('joined_at', '>=', Carbon::now()->subDays(7))
            ->count();
    }

    private function topMiningMemberIds(int $squadId, array $week, int $limit = 10): array
    {
        $scores = $this->memberActivityScores($this->activeMemberIds($squadId), $week);
        arsort($scores);

        return array_slice(array_keys($scores), 0, max(1, $limit));
    }

    private function memberActivityScores(array $memberIds, array $week): array
    {
        if (!$memberIds || !Schema::hasTable('button_clicks') || !Schema::hasColumn('button_clicks', 'user_id')) {
            return [];
        }

        $dateColumn = $this->firstAvailableColumn('button_clicks', ['rewards_start_time', 'created_at']);
        if (!$dateColumn) {
            return [];
        }

        return DB::table('button_clicks')
            ->whereIn('user_id', $memberIds)
            ->whereBetween($dateColumn, [$week['start'], $week['end']])
            ->select('user_id', DB::raw('COUNT(*) as score'))
            ->groupBy('user_id')
            ->orderByDesc('score')
            ->pluck('score', 'user_id')
            ->mapWithKeys(function ($score, $userId) {
                return [(int) $userId => (int) $score];
            })
            ->all();
    }

    private function topReferralMemberIds(int $squadId, array $week, int $limit = 10): array
    {
        $scores = $this->memberReferralScores($this->activeMemberIds($squadId), $week);
        arsort($scores);

        return array_slice(array_keys($scores), 0, max(1, $limit));
    }

    private function memberReferralScores(array $memberIds, array $week): array
    {
        if (!$memberIds || !Schema::hasTable('referrals') || !Schema::hasTable('res_users')) {
            return [];
        }

        $dateColumn = $this->firstAvailableColumn('referrals', ['created_at', 'updated_at']);
        if (!$dateColumn) {
            return [];
        }

        $users = DB::table('res_users')
            ->whereIn('id', $memberIds)
            ->select('id', 'refferal_code')
            ->get();
        $codeToUser = [];
        foreach ($users as $user) {
            $codeToUser[(string) $user->id] = (int) $user->id;
            if (!empty($user->refferal_code)) {
                $codeToUser[(string) $user->refferal_code] = (int) $user->id;
            }
        }

        if (!$codeToUser) {
            return [];
        }

        $scores = array_fill_keys(array_values($codeToUser), 0);
        $rows = DB::table('referrals')
            ->whereIn('referred_by', array_keys($codeToUser))
            ->whereBetween($dateColumn, [$week['start'], $week['end']])
            ->select('referred_by', DB::raw('COUNT(*) as score'))
            ->groupBy('referred_by')
            ->get();

        foreach ($rows as $row) {
            $userId = $codeToUser[(string) $row->referred_by] ?? null;
            if ($userId) {
                $scores[$userId] = (int) ($scores[$userId] ?? 0) + (int) $row->score;
            }
        }

        return array_filter($scores, function ($score) {
            return (int) $score > 0;
        });
    }

    private function rankMembersByActivity(array $members, array $week, string $metric): array
    {
        $ranked = [];
        foreach ($members as $member) {
            $userId = (int) $member['user_id'];
            $score = $metric === 'referrals'
                ? $this->memberReferralCount($userId, $week)
                : $this->activityCount('button_clicks', 'user_id', [$userId], ['rewards_start_time', 'created_at'], $week);
            if ($score <= 0) {
                continue;
            }
            $member['score'] = $score;
            $ranked[] = $member;
        }

        usort($ranked, function ($a, $b) {
            return ((int) $b['score']) <=> ((int) $a['score']);
        });

        return $ranked;
    }

    private function memberReferralCount(int $userId, array $week): int
    {
        if (!Schema::hasTable('referrals') || !Schema::hasTable('res_users')) {
            return 0;
        }

        $user = DB::table('res_users')->where('id', $userId)->first();
        $codes = array_filter([$userId, $user->refferal_code ?? null]);
        $dateColumn = Schema::hasColumn('referrals', 'created_at') ? 'created_at' : null;
        if (!$dateColumn) {
            return 0;
        }

        return (int) DB::table('referrals')
            ->whereIn('referred_by', $codes)
            ->whereBetween($dateColumn, [$week['start'], $week['end']])
            ->count();
    }

    private function socialSummary(int $squadId): array
    {
        $feedStatus = $this->settingValue('squad_feed_status', 'coming_soon');
        if (!in_array($feedStatus, ['disabled', 'coming_soon', 'enabled'], true)) {
            $feedStatus = 'coming_soon';
        }

        return [
            'followers_count' => $this->followersCount($squadId),
            'feed_enabled' => Schema::hasTable('squad_feed_events') && $feedStatus === 'enabled',
            'post_feed_status' => $feedStatus,
            'post_feed_note' => $feedStatus === 'enabled'
                ? 'Squad feed uses safe templates and verified activity posts.'
                : 'Squad feed will start with safe templates and verified activity posts.',
        ];
    }

    private function followerSummary(int $squadId, int $userId): array
    {
        return [
            'count' => $this->followersCount($squadId),
            'following' => $this->isFollowing($squadId, $userId),
        ];
    }

    private function followersCount(int $squadId): int
    {
        if (!Schema::hasTable('squad_followers')) {
            return 0;
        }

        return (int) DB::table('squad_followers')
            ->where('squad_id', $squadId)
            ->where('status', 'active')
            ->count();
    }

    private function isFollowing(int $squadId, int $userId): bool
    {
        if (!Schema::hasTable('squad_followers')) {
            return false;
        }

        return DB::table('squad_followers')
            ->where('squad_id', $squadId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    public function followSquad(ResUser $user, int $squadId, bool $follow = true): array
    {
        if (!Schema::hasTable('squads') || !Schema::hasTable('squad_followers')) {
            return $this->failure('Squad follows are temporarily unavailable.', 503);
        }

        $squad = DB::table('squads')->where('id', $squadId)->where('is_active', 1)->first();
        if (!$squad) {
            return $this->failure('Squad not found.', 404);
        }
        if ($follow && Schema::hasColumn('squads', 'allow_followers') && !$squad->allow_followers) {
            return $this->failure('This squad is not accepting followers right now.', 422);
        }

        DB::table('squad_followers')->updateOrInsert(
            ['squad_id' => $squadId, 'user_id' => (int) $user->id],
            [
                'status' => $follow ? 'active' : 'muted',
                'followed_at' => $follow ? now() : null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return [
            'success' => true,
            'status' => 200,
            'message' => $follow ? 'Squad followed.' : 'Squad unfollowed.',
            'data' => [
                'squad' => $this->squadPayload($squad, false, null, true, false, false),
                'followers' => $this->followerSummary($squadId, (int) $user->id),
            ],
        ];
    }

    private function feedPreview(?int $squadId): array
    {
        $items = [];

        if ($squadId && Schema::hasTable('squad_announcements')) {
            foreach ($this->announcements($squadId) as $announcement) {
                $items[] = [
                    'type' => 'announcement',
                    'title' => $announcement['title'],
                    'body' => $announcement['body'],
                    'status' => 'live',
                ];
            }
        }

        return array_slice(array_merge($items, [
            [
                'type' => 'weekly_mission',
                'title' => 'Weekly mission posts',
                'body' => 'Completed squad missions will appear in the feed automatically.',
                'status' => 'coming_soon',
            ],
            [
                'type' => 'new_member',
                'title' => 'New joiner posts',
                'body' => 'Welcome posts for new squad members are planned with safe templates.',
                'status' => 'coming_soon',
            ],
            [
                'type' => 'weekly_winner',
                'title' => 'Winner poster posts',
                'body' => 'Top squads will be able to share weekly winner posters.',
                'status' => 'coming_soon',
            ],
        ]), 0, 5);
    }

    private function recommendedSquads(ResUser $user, int $limit = 5)
    {
        $country = $this->normalizeCountryCode($user->country ?? null);
        $recommended = $this->discoverSquads($limit, ['country_code' => $country]);
        if (count($recommended) >= $limit) {
            return $recommended;
        }

        return $this->discoverSquads($limit);
    }

    private function activeMemberIds(int $squadId): array
    {
        if (!Schema::hasTable('squad_members')) {
            return [];
        }

        return DB::table('squad_members')
            ->where('squad_id', $squadId)
            ->where('status', 'active')
            ->pluck('user_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();
    }

    private function activeMemberCount(int $squadId): int
    {
        if (!Schema::hasTable('squad_members')) {
            return 0;
        }

        return DB::table('squad_members')
            ->where('squad_id', $squadId)
            ->where('status', 'active')
            ->count();
    }

    private function weeklyChestClaimed(int $squadId, int $userId): bool
    {
        if (!Schema::hasTable('squad_weekly_chests')) {
            return false;
        }

        $week = $this->currentWeek();

        return DB::table('squad_weekly_chests')
            ->where('squad_id', $squadId)
            ->where('claimed_by', $userId)
            ->where('week_start', $week['start']->toDateString())
            ->exists();
    }

    private function activitySources(): array
    {
        return [
            [
                'table' => 'button_clicks',
                'user_column' => 'user_id',
                'date_columns' => ['rewards_start_time', 'created_at', 'updated_at'],
            ],
            [
                'table' => 'tblreward',
                'user_column' => 'userid',
                'date_columns' => ['updated_at', 'created_at'],
            ],
            [
                'table' => 'task_claimeds',
                'user_column' => 'user_id',
                'date_columns' => ['created_at', 'updated_at'],
            ],
            [
                'table' => 'user_learn_progress',
                'user_column' => 'user_id',
                'date_columns' => ['completed_at', 'updated_at', 'created_at'],
                'where' => ['status' => 'completed'],
            ],
        ];
    }

    private function firstAvailableColumn(string $table, array $columns): ?string
    {
        if (!Schema::hasTable($table)) {
            return null;
        }

        foreach ($columns as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function activityCount(string $table, string $userColumn, array $memberIds, array $dateColumns, array $week, array $where = []): int
    {
        if (!$memberIds || !Schema::hasTable($table) || !Schema::hasColumn($table, $userColumn)) {
            return 0;
        }

        $dateColumn = $this->firstAvailableColumn($table, $dateColumns);
        if (!$dateColumn) {
            return 0;
        }

        $query = DB::table($table)
            ->whereIn($userColumn, $memberIds)
            ->whereBetween($dateColumn, [$week['start'], $week['end']]);

        foreach ($where as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $query->where($column, $value);
            }
        }

        return (int) $query->count();
    }

    private function referralActivityPoints(array $memberIds, array $week): int
    {
        if (!$memberIds || !Schema::hasTable('referrals') || !Schema::hasTable('res_users')) {
            return 0;
        }

        $codes = DB::table('res_users')->whereIn('id', $memberIds)->pluck('refferal_code')->filter()->all();
        $dateColumn = Schema::hasColumn('referrals', 'created_at') ? 'created_at' : null;
        if (!$dateColumn) {
            return 0;
        }

        $query = DB::table('referrals')
            ->where(function ($where) use ($memberIds, $codes) {
                $where->whereIn('referred_by', $memberIds);
                if ($codes) {
                    $where->orWhereIn('referred_by', $codes);
                }
            })
            ->whereBetween($dateColumn, [$week['start'], $week['end']]);

        return (int) $query->count() * 25;
    }

    private function missionProgress($mission, int $squadId, int $userId, array $week): int
    {
        switch ($mission->metric) {
            case 'weekly_points':
                return (int) $this->weeklyStats($squadId)['points'];
            case 'active_members':
                return $this->activeMembersThisWeek($squadId, $week);
            case 'shares':
                return $this->shareCount($squadId, $week);
            case 'learn_lessons':
                return $this->learnLessonCount($squadId, $week);
            case 'active_invites':
                return $this->activeInviteCount($userId, $squadId, $week['start'], $week['end']);
            default:
                return 0;
        }
    }

    private function countryRank($squad): ?int
    {
        $countryCode = $this->columnValue($squad, 'country_code');
        if (!$countryCode) {
            return null;
        }

        $leaderboard = $this->leaderboard(100, ['country_code' => $countryCode]);
        foreach ($leaderboard as $index => $row) {
            if ((int) $row['squad_id'] === (int) $squad->id) {
                return $index + 1;
            }
        }

        return null;
    }

    private function fastCountryLeaderboard(string $countryCode, int $limit = 5): array
    {
        if (!Schema::hasTable('squads')) {
            return [];
        }

        $countryCode = $this->normalizeCountryCode($countryCode);
        if (!$countryCode || !Schema::hasColumn('squads', 'country_code')) {
            return [];
        }

        $limit = min(20, max(1, $limit));
        $memberCounts = Schema::hasTable('squad_members')
            ? DB::table('squad_members')
                ->select('squad_id', DB::raw('COUNT(*) as member_count'))
                ->where('status', 'active')
                ->groupBy('squad_id')
            : null;

        $query = DB::table('squads')
            ->where('squads.is_active', 1)
            ->where('squads.country_code', $countryCode);

        if ($memberCounts) {
            $query->leftJoinSub($memberCounts, 'squad_member_counts', function ($join) {
                $join->on('squad_member_counts.squad_id', '=', 'squads.id');
            });
        }

        $selects = [
            'squads.id',
            'squads.name',
            'squads.slug',
            'squads.invite_code',
            'squads.country_code',
        ];

        if (Schema::hasColumn('squads', 'share_count')) {
            $selects[] = 'squads.share_count';
            $query->orderByDesc('squads.share_count');
        }

        if ($memberCounts) {
            $selects[] = DB::raw('COALESCE(squad_member_counts.member_count, 0) as member_count');
            $query->orderByDesc('member_count');
        }

        return $query
            ->select($selects)
            ->orderByDesc('squads.id')
            ->limit($limit)
            ->get()
            ->values()
            ->map(function ($squad, $index) {
                return [
                    'rank' => $index + 1,
                    'squad_id' => (int) $squad->id,
                    'name' => $squad->name,
                    'slug' => $squad->slug,
                    'country_code' => $this->columnValue($squad, 'country_code'),
                    'country_name' => $this->countryName($this->columnValue($squad, 'country_code')),
                    'member_count' => (int) ($squad->member_count ?? 0),
                    'weekly_points' => null,
                    'community_boost_percent' => 0,
                    'chest_unlocked' => false,
                    'share_count' => (int) ($squad->share_count ?? 0),
                    'share_url' => $this->shareUrl($squad->invite_code),
                    'fast_loaded' => true,
                ];
            })
            ->all();
    }

    private function activeMembersThisWeek(int $squadId, array $week): int
    {
        $memberIds = $this->activeMemberIds($squadId);
        if (!$memberIds) {
            return 0;
        }

        $activitySources = [
            ['button_clicks', 'user_id', ['rewards_start_time', 'created_at'], []],
            ['tblreward', 'userid', ['updated_at'], []],
            ['task_claimeds', 'user_id', ['created_at', 'updated_at'], []],
            ['user_learn_progress', 'user_id', ['completed_at'], ['status' => 'completed']],
        ];
        $activeMemberIds = [];

        foreach ($activitySources as [$table, $userColumn, $dateColumns, $filters]) {
            $activeMemberIds = array_merge(
                $activeMemberIds,
                $this->activeUserIdsBetween(
                    $table,
                    $userColumn,
                    $memberIds,
                    $dateColumns,
                    $week,
                    $filters
                )
            );
        }

        return count(array_unique($activeMemberIds));
    }

    private function activeUserIdsBetween(
        string $table,
        string $userColumn,
        array $memberIds,
        array $dateColumns,
        array $week,
        array $filters = []
    ): array {
        if (!$memberIds || !Schema::hasTable($table) || !Schema::hasColumn($table, $userColumn)) {
            return [];
        }

        $dateColumn = $this->firstAvailableColumn($table, $dateColumns);
        if (!$dateColumn) {
            return [];
        }

        $query = DB::table($table)
            ->whereIn($userColumn, $memberIds)
            ->whereBetween($dateColumn, [$week['start'], $week['end']]);

        foreach ($filters as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $query->where($column, $value);
            }
        }

        return $query->distinct()->pluck($userColumn)
            ->map(function ($userId) {
                return (int) $userId;
            })
            ->all();
    }

    private function shareCount(int $squadId, array $week): int
    {
        if (!Schema::hasTable('squad_share_events')) {
            return 0;
        }

        return (int) DB::table('squad_share_events')
            ->where('squad_id', $squadId)
            ->whereBetween('created_at', [$week['start'], $week['end']])
            ->count();
    }

    private function learnLessonCount(int $squadId, array $week): int
    {
        $memberIds = $this->activeMemberIds($squadId);
        if (!$memberIds || !Schema::hasTable('user_learn_progress') || !Schema::hasColumn('user_learn_progress', 'completed_at')) {
            return 0;
        }

        return (int) DB::table('user_learn_progress')
            ->whereIn('user_id', $memberIds)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$week['start'], $week['end']])
            ->count();
    }

    private function activeInviteCount(int $inviterUserId, int $squadId, Carbon $start, Carbon $end): int
    {
        if (!Schema::hasTable('squad_members') || !Schema::hasColumn('squad_members', 'invited_by_user_id')) {
            return 0;
        }

        $invitedIds = DB::table('squad_members')
            ->where('squad_id', $squadId)
            ->where('invited_by_user_id', $inviterUserId)
            ->where('status', 'active')
            ->pluck('user_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        if (!$invitedIds) {
            return 0;
        }

        $count = 0;
        foreach ($invitedIds as $memberId) {
            if ($this->userHasActivityBetween($memberId, $start, $end)) {
                $count++;
            }
        }

        return $count;
    }

    private function userHasActivityBetween(int $userId, Carbon $start, Carbon $end): bool
    {
        $window = ['start' => $start, 'end' => $end];
        return $this->activityCount('button_clicks', 'user_id', [$userId], ['rewards_start_time', 'created_at'], $window) > 0
            || $this->activityCount('tblreward', 'userid', [$userId], ['updated_at'], $window) > 0
            || $this->activityCount('task_claimeds', 'user_id', [$userId], ['created_at', 'updated_at'], $window) > 0
            || $this->activityCount('user_learn_progress', 'user_id', [$userId], ['completed_at'], $window, ['status' => 'completed']) > 0;
    }

    private function normalizeRole(string $role): ?string
    {
        $role = strtolower(trim($role));
        return array_key_exists($role, self::ROLE_ORDER) ? $role : null;
    }

    private function currentWeek(): array
    {
        return [
            'start' => Carbon::now()->startOfWeek(),
            'end' => Carbon::now()->endOfWeek(),
        ];
    }

    private function communityBoostPercent(int $points, int $target): int
    {
        if ($points >= $target) {
            return 10;
        }
        if ($points >= (int) ceil($target * 0.5)) {
            return 5;
        }
        if ($points > 0) {
            return 2;
        }
        return 0;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'squad';
        }

        $slug = $base;
        $i = 2;
        while (DB::table('squads')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function uniqueInviteCode(): string
    {
        do {
            $code = 'SQ' . strtoupper(Str::random(8));
        } while (DB::table('squads')->where('invite_code', $code)->exists()
            || (Schema::hasTable('squad_invites') && DB::table('squad_invites')->where('invite_code', $code)->exists()));

        return $code;
    }

    private function posterPayload($squad): array
    {
        $stats = $this->weeklyStats((int) $squad->id);
        $memberCount = $this->activeMemberCount((int) $squad->id);
        $maxMembers = (int) $this->columnValue($squad, 'max_members', self::DEFAULT_MAX_MEMBERS);
        $isUnlimited = $maxMembers <= 0;
        $template = $this->posterTemplate($this->columnValue($squad, 'poster_template', 'white'));
        $style = $this->posterStyle($template);
        $posterUrl = $this->posterSvgUrl($squad->invite_code);
        $shareUrl = $this->shareUrl($squad->invite_code);

        return [
            'template' => $template,
            'style' => $style,
            'background_color' => $style['background'],
            'text_color' => $style['text'],
            'accent_color' => $style['accent'],
            'title' => $squad->name,
            'subtitle' => 'Join my Blockrium squad and help unlock weekly activity chests.',
            'safe_note' => 'Blockrium rewards are based on app participation and backend-verified activity.',
            'invite_code' => $squad->invite_code,
            'share_url' => $shareUrl,
            'url' => $posterUrl,
            'poster_svg_url' => $posterUrl,
            'qr_url' => $this->qrUrl($squad->invite_code),
            'qr_text' => $shareUrl,
            'country_code' => $this->columnValue($squad, 'country_code'),
            'member_count' => $memberCount,
            'max_members' => $isUnlimited ? null : $maxMembers,
            'is_unlimited' => $isUnlimited,
            'capacity_label' => $isUnlimited ? 'Unlimited' : number_format($memberCount) . '/' . number_format($maxMembers) . ' members',
            'weekly_points' => $stats['points'],
            'weekly_target_points' => (int) $this->columnValue($squad, 'weekly_target_points', self::DEFAULT_WEEKLY_TARGET),
            'weekly_progress_percent' => $stats['progress_percent'],
            'community_boost_percent' => $stats['community_boost_percent'],
            'boost_percent' => $stats['community_boost_percent'],
            'templates' => $this->posterTemplates(),
        ];
    }

    private function posterStyle(string $template): array
    {
        if ($template === 'dark') {
            return ['background' => '#07111f', 'text' => '#ffffff', 'muted' => '#a8b3c7', 'primary' => '#00d6b0', 'accent' => '#35c7ff'];
        }
        if ($template === 'country') {
            return ['background' => '#f4fbf7', 'text' => '#07111f', 'muted' => '#526276', 'primary' => '#10876f', 'accent' => '#1fd6c5'];
        }
        if ($template === 'weekly_winner') {
            return ['background' => '#07111f', 'text' => '#ffffff', 'muted' => '#cbd5e1', 'primary' => '#f5b642', 'accent' => '#00d6b0'];
        }

        return ['background' => '#ffffff', 'text' => '#07111f', 'muted' => '#64748b', 'primary' => '#0f766e', 'accent' => '#2563eb'];
    }

    private function posterTemplates(): array
    {
        return [
            ['key' => 'white', 'title' => 'White'],
            ['key' => 'dark', 'title' => 'Dark'],
            ['key' => 'country', 'title' => 'Country'],
            ['key' => 'weekly_winner', 'title' => 'Weekly Winner'],
        ];
    }

    private function shareOptions($squad): array
    {
        return [
            'url' => $this->shareUrl($squad->invite_code),
            'text' => $this->shareText($squad),
            'channels' => self::ALLOWED_SHARE_CHANNELS,
        ];
    }

    private function rewardLabel(int $rewardXp, ?float $rewardBrc): ?string
    {
        $parts = [];
        if ($rewardXp > 0) {
            $parts[] = number_format($rewardXp) . ' XP';
        }
        if ($rewardBrc !== null && $rewardBrc > 0) {
            $parts[] = rtrim(rtrim(number_format($rewardBrc, 4, '.', ''), '0'), '.') . ' BRC';
        }

        return $parts ? implode(' + ', $parts) : null;
    }

    private function shareText($squad): string
    {
        return sprintf(
            'Join my Blockrium squad "%s" with code %s: %s',
            $squad->name,
            $squad->invite_code,
            $this->shareUrl($squad->invite_code)
        );
    }

    private function shareUrl(string $inviteCode): string
    {
        return url('/squad/' . rawurlencode(strtoupper(trim($inviteCode))));
    }

    private function posterSvgUrl(string $inviteCode): string
    {
        return url('/squad/' . rawurlencode(strtoupper(trim($inviteCode))) . '/poster.svg');
    }

    private function qrUrl(string $inviteCode): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=480x480&data=' . rawurlencode($this->shareUrl($inviteCode));
    }

    private function normalizeMaxMembers($value): int
    {
        if ($value === null || $value === '' || strtolower((string) $value) === 'unlimited') {
            return 0;
        }

        $members = (int) $value;
        return $members <= 0 ? 0 : max(5, $members);
    }

    private function normalizeCountryCode($value): ?string
    {
        $value = strtoupper(trim((string) $value));
        if ($value === '') {
            return null;
        }

        $map = [
            'INDIA' => 'IN',
            'UNITED STATES' => 'US',
            'USA' => 'US',
            'UNITED KINGDOM' => 'GB',
            'UK' => 'GB',
            'NIGERIA' => 'NG',
            'PAKISTAN' => 'PK',
            'BANGLADESH' => 'BD',
            'PHILIPPINES' => 'PH',
            'INDONESIA' => 'ID',
            'BRAZIL' => 'BR',
        ];

        if (isset($map[$value])) {
            return $map[$value];
        }

        return preg_match('/^[A-Z]{2}$/', $value) ? $value : null;
    }

    private function countryName(?string $countryCode): ?string
    {
        if (!$countryCode) {
            return null;
        }

        $countries = [
            'BD' => 'Bangladesh',
            'BR' => 'Brazil',
            'GB' => 'United Kingdom',
            'ID' => 'Indonesia',
            'IN' => 'India',
            'NG' => 'Nigeria',
            'PH' => 'Philippines',
            'PK' => 'Pakistan',
            'US' => 'United States',
        ];

        $countryCode = strtoupper($countryCode);
        return $countries[$countryCode] ?? $countryCode;
    }

    private function isPromotedSquad($squad): bool
    {
        $tier = $this->columnValue($squad, 'promotion_tier', 'normal');
        if ($tier && $tier !== 'normal') {
            return true;
        }

        $featuredUntil = $this->columnValue($squad, 'featured_until');
        if (!$featuredUntil) {
            return false;
        }

        try {
            return Carbon::parse($featuredUntil)->isFuture();
        } catch (\Throwable $error) {
            return false;
        }
    }

    private function normalizeLanguageCode($value): ?string
    {
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return null;
        }

        return preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,4})?$/', $value) ? $value : null;
    }

    private function posterTemplate($value): string
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, self::POSTER_TEMPLATES, true) ? $value : 'white';
    }

    private function settingValue(string $key, $default = null)
    {
        if (!Schema::hasTable('squad_settings')) {
            return $default;
        }

        $value = DB::table('squad_settings')
            ->where('group', 'squads')
            ->where('key', $key)
            ->value('value');

        return $value === null ? $default : $value;
    }

    private function columnValue($row, string $column, $default = null)
    {
        return is_object($row) && property_exists($row, $column) ? $row->{$column} : $default;
    }

    private function failure(string $message, int $status, array $data = []): array
    {
        return [
            'success' => false,
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ];
    }
}
