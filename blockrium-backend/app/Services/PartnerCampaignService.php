<?php

namespace App\Services;

use App\Models\ResUser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PartnerCampaignService
{
    private $walletRewards;

    public function __construct(WalletRewardService $walletRewards)
    {
        $this->walletRewards = $walletRewards;
    }

    public function activeCampaigns(int $userId): array
    {
        $now = Carbon::now();
        $campaigns = DB::table('partner_campaigns')
            ->join('partners', 'partner_campaigns.partner_id', '=', 'partners.id')
            ->where('partners.status', 'active')
            ->where('partner_campaigns.status', 'active')
            ->where('partner_campaigns.starts_at', '<=', $now)
            ->where('partner_campaigns.ends_at', '>=', $now)
            ->orderBy('partner_campaigns.sort_order')
            ->orderByDesc('partner_campaigns.id')
            ->select('partner_campaigns.*', 'partners.name as partner_name')
            ->get();

        return $campaigns
            ->filter(function ($campaign) {
                return $this->hasCapacity($campaign);
            })
            ->map(function ($campaign) use ($userId) {
                return $this->formatCampaign($campaign, $userId);
            })
            ->values()
            ->all();
    }

    public function recordUserClick(int $campaignId, int $userId, Request $request): array
    {
        $campaign = $this->getCampaign($campaignId);
        $this->assertOpen($campaign);
        if (!$this->hasCapacity($campaign)) {
            throw new HttpException(409, 'This campaign has reached its participant or budget limit.');
        }

        $this->recordClick($campaign, $userId, $request, 'app');

        if ($campaign->reward_mode === 'admin_approval') {
            $completion = DB::table('partner_campaign_completions')
                ->where('campaign_id', $campaign->id)
                ->where('user_id', $userId)
                ->whereIn('status', ['pending', 'approved'])
                ->orderByDesc('id')
                ->first();

            if (!$completion) {
                DB::table('partner_campaign_completions')->insert([
                    'campaign_id' => $campaign->id,
                    'user_id' => $userId,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $completion = DB::table('partner_campaign_completions')
                    ->where('campaign_id', $campaign->id)
                    ->where('user_id', $userId)
                    ->orderByDesc('id')
                    ->first();
            }
        } else {
            $completion = null;
        }

        return [
            'campaign_id' => (int) $campaign->id,
            'destination_url' => $campaign->destination_url,
            'completion_status' => $completion ? $completion->status : 'clicked',
            'claim_url' => url('/api/auth/partner-campaigns/' . $campaign->id . '/claim'),
        ];
    }

    public function recordPublicClick(string $slug, Request $request): string
    {
        $campaign = DB::table('partner_campaigns')
            ->where('slug', $slug)
            ->first();

        $this->assertOpen($campaign);
        if (!$this->hasCapacity($campaign)) {
            throw new HttpException(404, 'This campaign is no longer available.');
        }

        $userId = null;
        try {
            $userId = auth('api_res_user')->id();
        } catch (\Throwable $error) {
            // Anonymous public redirects should still be tracked.
        }
        $this->recordClick($campaign, $userId ? (int) $userId : null, $request, 'public_link');

        return $campaign->destination_url;
    }

    public function claim(int $campaignId, int $userId): array
    {
        return DB::transaction(function () use ($campaignId, $userId) {
            $campaign = DB::table('partner_campaigns')
                ->where('id', $campaignId)
                ->lockForUpdate()
                ->first();

            $this->assertOpen($campaign);

            $existing = DB::table('partner_campaign_claims')
                ->where('campaign_id', $campaign->id)
                ->where('user_id', $userId)
                ->first();

            if ($existing) {
                return [
                    'status' => 409,
                    'success' => false,
                    'already_claimed' => true,
                    'message' => 'This campaign reward has already been claimed.',
                    'claim' => $this->claimPayload($existing),
                ];
            }

            if (!$this->userClicked($campaign->id, $userId)) {
                return [
                    'status' => 422,
                    'success' => false,
                    'message' => 'Open the partner campaign link before claiming the reward.',
                ];
            }

            if ($campaign->reward_mode === 'admin_approval') {
                $approved = DB::table('partner_campaign_completions')
                    ->where('campaign_id', $campaign->id)
                    ->where('user_id', $userId)
                    ->where('status', 'approved')
                    ->orderByDesc('id')
                    ->first();

                if (!$approved) {
                    return [
                        'status' => 422,
                        'success' => false,
                        'message' => 'This campaign completion is waiting for admin approval.',
                    ];
                }
            }

            if (!$this->hasClaimCapacity($campaign)) {
                return [
                    'status' => 409,
                    'success' => false,
                    'message' => 'This campaign has reached its reward limit.',
                ];
            }

            $claimedAmount = (float) DB::table('partner_campaign_claims')
                ->where('campaign_id', $campaign->id)
                ->sum('reward_amount');
            $rewardAmount = (float) $campaign->reward_amount;

            if ($campaign->budget_amount !== null && $claimedAmount + $rewardAmount > (float) $campaign->budget_amount) {
                return [
                    'status' => 409,
                    'success' => false,
                    'message' => 'This campaign budget has been exhausted.',
                ];
            }

            $wallet = $this->walletRewards->credit($userId, $rewardAmount);
            $claimId = DB::table('partner_campaign_claims')->insertGetId([
                'campaign_id' => $campaign->id,
                'user_id' => $userId,
                'reward_amount' => $rewardAmount,
                'reward_label' => $campaign->reward_label,
                'wallet_reference' => $wallet && isset($wallet->id) ? (string) $wallet->id : null,
                'claimed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->insertRewardNotification($userId, $rewardAmount, $campaign->title);

            return [
                'status' => 200,
                'success' => true,
                'already_claimed' => false,
                'message' => 'Campaign reward claimed successfully.',
                'claim' => [
                    'id' => $claimId,
                    'campaign_id' => (int) $campaign->id,
                    'amount' => $rewardAmount,
                    'label' => $campaign->reward_label,
                    'claimed_at' => now()->toDateTimeString(),
                ],
                'wallet' => $wallet,
            ];
        });
    }

    public function recordTaskClick(int $campaignId, int $taskId, int $userId, Request $request): array
    {
        $campaign = $this->getCampaign($campaignId);
        $task = $this->getTask($campaignId, $taskId);
        $this->assertTaskOpen($campaign, $task);

        if (!$this->taskHasCapacity($task)) {
            throw new HttpException(409, 'This task has reached its participant limit.');
        }

        DB::table('partner_campaign_task_clicks')->insert([
            'campaign_id' => $campaignId,
            'task_id' => $taskId,
            'user_id' => $userId,
            'visitor_hash' => $this->visitorHash($request),
            'source' => 'app',
            'redirected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $completion = DB::table('partner_campaign_task_completions')
            ->where('task_id', $taskId)
            ->where('user_id', $userId)
            ->first();

        $result = [
            'campaign_id' => $campaignId,
            'task_id' => $taskId,
            'destination_url' => $task->target_url,
            'completion_mode' => $this->isTelegramClickReward($task)
                ? 'click_claim'
                : $task->completion_mode,
            'completion_status' => $completion ? $completion->status : 'clicked',
            'claim_url' => url('/api/auth/partner-campaigns/' . $campaignId . '/tasks/' . $taskId . '/claim'),
        ];

        return $result;
    }

    public function submitTaskCompletion(int $campaignId, int $taskId, int $userId, ?string $evidenceUrl, ?string $evidenceNote): array
    {
        $campaign = $this->getCampaign($campaignId);
        $task = $this->getTask($campaignId, $taskId);
        $this->assertTaskOpen($campaign, $task);

        if ($task->task_type === 'join_telegram') {
            throw new HttpException(422, 'Telegram tasks are rewarded when opened and do not require proof.');
        }

        if ($task->completion_mode !== 'admin_approval') {
            throw new HttpException(422, 'This task is completed by opening its link and claiming the reward.');
        }

        if (!$this->taskClicked($taskId, $userId)) {
            throw new HttpException(422, 'Open the task link before submitting completion.');
        }

        $completion = DB::table('partner_campaign_task_completions')
            ->where('task_id', $taskId)
            ->where('user_id', $userId)
            ->first();

        if ($completion && in_array($completion->status, ['pending', 'approved'], true)) {
            return [
                'task_id' => $taskId,
                'status' => $completion->status,
                'message' => $completion->status === 'approved'
                    ? 'This task has already been approved.'
                    : 'This task is already waiting for admin review.',
            ];
        }

        $data = [
            'campaign_id' => $campaignId,
            'task_id' => $taskId,
            'user_id' => $userId,
            'status' => 'pending',
            'evidence_url' => $evidenceUrl,
            'evidence_note' => $evidenceNote,
            'approved_by_admin_id' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'admin_note' => null,
            'updated_at' => now(),
        ];

        if ($completion) {
            DB::table('partner_campaign_task_completions')->where('id', $completion->id)->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('partner_campaign_task_completions')->insert($data);
        }

        return [
            'task_id' => $taskId,
            'status' => 'pending',
            'message' => 'Task completion submitted for admin review.',
        ];
    }

    public function claimTask(int $campaignId, int $taskId, int $userId): array
    {
        return DB::transaction(function () use ($campaignId, $taskId, $userId) {
            $campaign = DB::table('partner_campaigns')->where('id', $campaignId)->lockForUpdate()->first();
            $task = DB::table('partner_campaign_tasks')->where('id', $taskId)->where('campaign_id', $campaignId)->lockForUpdate()->first();
            $this->assertTaskOpen($campaign, $task);

            $existing = DB::table('partner_campaign_task_claims')
                ->where('task_id', $taskId)
                ->where('user_id', $userId)
                ->first();
            if ($existing) {
                return [
                    'status' => 409,
                    'success' => false,
                    'already_claimed' => true,
                    'message' => 'This task reward has already been claimed.',
                    'claim' => $this->taskClaimPayload($existing),
                ];
            }

            $telegramClickReward = $this->isTelegramClickReward($task);
            if (($task->completion_mode === 'click_claim' || $telegramClickReward)
                && !$this->taskClicked($taskId, $userId)) {
                return ['status' => 422, 'success' => false, 'message' => 'Open the task link before claiming the reward.'];
            }

            if (!$telegramClickReward && in_array($task->completion_mode, ['admin_approval', 'telegram_membership'], true)) {
                $approved = DB::table('partner_campaign_task_completions')
                    ->where('task_id', $taskId)
                    ->where('user_id', $userId)
                    ->where('status', 'approved')
                    ->first();
                if (!$approved) {
                    return ['status' => 422, 'success' => false, 'message' => 'This task is waiting for admin approval.'];
                }
            }

            if (!$this->taskHasClaimCapacity($task)) {
                return ['status' => 409, 'success' => false, 'message' => 'This task has reached its reward limit.'];
            }

            if (!$this->campaignUserHasCapacity($campaign, $userId)) {
                return ['status' => 409, 'success' => false, 'message' => 'This campaign has reached its participant limit.'];
            }

            $distributed = $this->taskDistributed($campaignId);
            $rewardAmount = (float) $task->reward_amount;
            if ($campaign->budget_amount !== null && $distributed + $rewardAmount > (float) $campaign->budget_amount) {
                return ['status' => 409, 'success' => false, 'message' => 'This campaign budget has been exhausted.'];
            }

            $wallet = $this->walletRewards->credit($userId, $rewardAmount);
            $claimId = DB::table('partner_campaign_task_claims')->insertGetId([
                'campaign_id' => $campaignId,
                'task_id' => $taskId,
                'user_id' => $userId,
                'reward_amount' => $rewardAmount,
                'reward_label' => $task->reward_label,
                'wallet_reference' => $wallet && isset($wallet->id) ? (string) $wallet->id : null,
                'claimed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->insertRewardNotification($userId, $rewardAmount, $campaign->title . ' - ' . $task->title);

            return [
                'status' => 200,
                'success' => true,
                'already_claimed' => false,
                'message' => 'Task reward claimed successfully.',
                'claim' => [
                    'id' => $claimId,
                    'campaign_id' => $campaignId,
                    'task_id' => $taskId,
                    'amount' => $rewardAmount,
                    'label' => $task->reward_label,
                    'claimed_at' => now()->toDateTimeString(),
                ],
                'wallet' => $wallet,
            ];
        });
    }

    public function campaignsForAdmin()
    {
        return DB::table('partner_campaigns')
            ->join('partners', 'partner_campaigns.partner_id', '=', 'partners.id')
            ->select('partner_campaigns.*', 'partners.name as partner_name')
            ->orderByDesc('partner_campaigns.id')
            ->paginate(25);
    }

    public function metrics(int $campaignId): array
    {
        $clicks = DB::table('partner_campaign_clicks')->where('campaign_id', $campaignId);
        $claims = DB::table('partner_campaign_claims')->where('campaign_id', $campaignId);
        $completions = DB::table('partner_campaign_completions')->where('campaign_id', $campaignId);

        $totalClicks = (clone $clicks)->count();
        $uniqueUsers = (clone $clicks)->whereNotNull('user_id')->distinct()->count('user_id');
        $uniqueVisitors = (clone $clicks)->whereNotNull('visitor_hash')->distinct()->count('visitor_hash');
        $claimCount = (clone $claims)->count();
        $distributed = (float) (clone $claims)->sum('reward_amount');
        $taskClickCount = 0;
        $taskUniqueUsers = 0;
        $taskUniqueVisitors = 0;
        $taskClaimCount = 0;
        $taskPending = 0;
        $taskApproved = 0;
        $taskRejected = 0;
        if (Schema::hasTable('partner_campaign_task_clicks')) {
            $taskClicks = DB::table('partner_campaign_task_clicks')->where('campaign_id', $campaignId);
            $taskClickCount = (clone $taskClicks)->count();
            $taskUniqueUsers = (clone $taskClicks)->whereNotNull('user_id')->distinct()->count('user_id');
            $taskUniqueVisitors = (clone $taskClicks)->whereNotNull('visitor_hash')->distinct()->count('visitor_hash');
        }
        if (Schema::hasTable('partner_campaign_task_claims')) {
            $taskClaims = DB::table('partner_campaign_task_claims')->where('campaign_id', $campaignId);
            $taskClaimCount = (clone $taskClaims)->count();
        }
        if (Schema::hasTable('partner_campaign_task_completions')) {
            $taskCompletions = DB::table('partner_campaign_task_completions')->where('campaign_id', $campaignId);
            $taskPending = (clone $taskCompletions)->where('status', 'pending')->count();
            $taskApproved = (clone $taskCompletions)->where('status', 'approved')->count();
            $taskRejected = (clone $taskCompletions)->where('status', 'rejected')->count();
        }
        $totalClicks += $taskClickCount;
        $uniqueUsers += $taskUniqueUsers;
        $uniqueVisitors += $taskUniqueVisitors;
        $claimCount += $taskClaimCount;

        $campaign = $this->getCampaign($campaignId);
        $taskDistributed = Schema::hasTable('partner_campaign_task_claims') ? $this->taskDistributed($campaignId) : 0;
        $distributedTotal = $distributed + $taskDistributed;
        $taskMetrics = Schema::hasTable('partner_campaign_tasks') ? $this->taskMetrics($campaignId) : [];

        return [
            'clicks' => $totalClicks,
            'unique_users' => $uniqueUsers,
            'unique_visitors' => $uniqueVisitors,
            'pending_completions' => (clone $completions)->where('status', 'pending')->count() + $taskPending,
            'approved_completions' => (clone $completions)->where('status', 'approved')->count() + $taskApproved,
            'rejected_completions' => (clone $completions)->where('status', 'rejected')->count() + $taskRejected,
            'claims' => $claimCount,
            'brc_distributed' => $distributedTotal,
            'budget_total' => $campaign && $campaign->budget_amount !== null ? (float) $campaign->budget_amount : null,
            'budget_remaining' => $campaign && $campaign->budget_amount !== null ? max(0, (float) $campaign->budget_amount - $distributedTotal) : null,
            'tasks' => $taskMetrics,
            'conversion_rate' => $totalClicks > 0 ? round(($claimCount / $totalClicks) * 100, 2) : 0,
        ];
    }

    public function metricsForCampaigns($campaigns): array
    {
        $campaigns = collect($campaigns);
        $campaignIds = $campaigns->pluck('id')->map(function ($id) {
            return (int) $id;
        })->filter()->values()->all();

        if (!$campaignIds) {
            return [];
        }

        $grouped = function (string $table, string $select) use ($campaignIds) {
            if (!Schema::hasTable($table)) {
                return collect();
            }

            return DB::table($table)
                ->whereIn('campaign_id', $campaignIds)
                ->selectRaw($select)
                ->groupBy('campaign_id')
                ->get()
                ->keyBy('campaign_id');
        };

        $clickStats = $grouped('partner_campaign_clicks', 'campaign_id, COUNT(*) AS clicks, COUNT(DISTINCT user_id) AS users, COUNT(DISTINCT visitor_hash) AS visitors');
        $claimStats = $grouped('partner_campaign_claims', 'campaign_id, COUNT(*) AS claims, COALESCE(SUM(reward_amount), 0) AS distributed');
        $completionStats = $grouped('partner_campaign_completions', "campaign_id, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved, SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected");
        $taskClickStats = $grouped('partner_campaign_task_clicks', 'campaign_id, COUNT(*) AS clicks, COUNT(DISTINCT user_id) AS users, COUNT(DISTINCT visitor_hash) AS visitors');
        $taskClaimStats = $grouped('partner_campaign_task_claims', 'campaign_id, COUNT(*) AS claims, COALESCE(SUM(reward_amount), 0) AS distributed');
        $taskCompletionStats = $grouped('partner_campaign_task_completions', "campaign_id, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved, SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected");

        $tasksByCampaign = collect();
        $taskIds = [];
        if (Schema::hasTable('partner_campaign_tasks')) {
            $tasksByCampaign = DB::table('partner_campaign_tasks')
                ->whereIn('campaign_id', $campaignIds)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->groupBy('campaign_id');
            $taskIds = $tasksByCampaign->flatten(1)->pluck('id')->map(function ($id) {
                return (int) $id;
            })->all();
        }

        $taskGrouped = function (string $table, string $select) use ($taskIds) {
            if (!$taskIds || !Schema::hasTable($table)) {
                return collect();
            }

            return DB::table($table)
                ->whereIn('task_id', $taskIds)
                ->selectRaw($select)
                ->groupBy('task_id')
                ->get()
                ->keyBy('task_id');
        };

        $taskClicks = $taskGrouped('partner_campaign_task_clicks', 'task_id, COUNT(*) AS clicks, COUNT(DISTINCT user_id) AS users');
        $taskClaims = $taskGrouped('partner_campaign_task_claims', 'task_id, COUNT(*) AS claims, COALESCE(SUM(reward_amount), 0) AS distributed');
        $taskCompletions = $taskGrouped('partner_campaign_task_completions', "task_id, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved");

        $result = [];
        foreach ($campaigns as $campaign) {
            $id = (int) $campaign->id;
            $click = $clickStats->get($id);
            $claim = $claimStats->get($id);
            $completion = $completionStats->get($id);
            $taskClick = $taskClickStats->get($id);
            $taskClaim = $taskClaimStats->get($id);
            $taskCompletion = $taskCompletionStats->get($id);
            $taskMetrics = [];

            foreach ($tasksByCampaign->get($id, collect()) as $task) {
                $taskClickRow = $taskClicks->get((int) $task->id);
                $taskClaimRow = $taskClaims->get((int) $task->id);
                $taskCompletionRow = $taskCompletions->get((int) $task->id);
                $taskClickCount = (int) ($taskClickRow->clicks ?? 0);
                $taskClaimCount = (int) ($taskClaimRow->claims ?? 0);

                $taskMetrics[] = [
                    'id' => (int) $task->id,
                    'title' => $task->title,
                    'task_type' => $task->task_type,
                    'completion_mode' => $task->completion_mode,
                    'reward_amount' => (float) $task->reward_amount,
                    'reward_label' => $task->reward_label,
                    'clicks' => $taskClickCount,
                    'unique_users' => (int) ($taskClickRow->users ?? 0),
                    'pending_completions' => (int) ($taskCompletionRow->pending ?? 0),
                    'approved_completions' => (int) ($taskCompletionRow->approved ?? 0),
                    'claims' => $taskClaimCount,
                    'brc_distributed' => (float) ($taskClaimRow->distributed ?? 0),
                    'conversion_rate' => $taskClickCount > 0 ? round(($taskClaimCount / $taskClickCount) * 100, 2) : 0,
                ];
            }

            $totalClicks = (int) ($click->clicks ?? 0) + (int) ($taskClick->clicks ?? 0);
            $claimCount = (int) ($claim->claims ?? 0) + (int) ($taskClaim->claims ?? 0);
            $distributed = (float) ($claim->distributed ?? 0) + (float) ($taskClaim->distributed ?? 0);
            $budget = $campaign->budget_amount !== null ? (float) $campaign->budget_amount : null;

            $result[$id] = [
                'clicks' => $totalClicks,
                'unique_users' => (int) ($click->users ?? 0) + (int) ($taskClick->users ?? 0),
                'unique_visitors' => (int) ($click->visitors ?? 0) + (int) ($taskClick->visitors ?? 0),
                'pending_completions' => (int) ($completion->pending ?? 0) + (int) ($taskCompletion->pending ?? 0),
                'approved_completions' => (int) ($completion->approved ?? 0) + (int) ($taskCompletion->approved ?? 0),
                'rejected_completions' => (int) ($completion->rejected ?? 0) + (int) ($taskCompletion->rejected ?? 0),
                'claims' => $claimCount,
                'brc_distributed' => $distributed,
                'budget_total' => $budget,
                'budget_remaining' => $budget !== null ? max(0, $budget - $distributed) : null,
                'tasks' => $taskMetrics,
                'conversion_rate' => $totalClicks > 0 ? round(($claimCount / $totalClicks) * 100, 2) : 0,
            ];
        }

        return $result;
    }

    public function pendingTaskCompletions()
    {
        return DB::table('partner_campaign_task_completions')
            ->join('partner_campaign_tasks', 'partner_campaign_task_completions.task_id', '=', 'partner_campaign_tasks.id')
            ->join('partner_campaigns', 'partner_campaign_task_completions.campaign_id', '=', 'partner_campaigns.id')
            ->join('partners', 'partner_campaigns.partner_id', '=', 'partners.id')
            ->leftJoin('res_users', 'partner_campaign_task_completions.user_id', '=', 'res_users.id')
            ->where('partner_campaign_task_completions.status', 'pending')
            ->select('partner_campaign_task_completions.*', 'partner_campaign_tasks.title as task_title', 'partner_campaigns.title as campaign_title', 'partners.name as partner_name', 'res_users.username', 'res_users.email')
            ->orderBy('partner_campaign_task_completions.created_at')
            ->paginate(40, ['*'], 'task_page');
    }

    public function approveTaskCompletion(int $completionId, int $adminId): void
    {
        DB::transaction(function () use ($completionId, $adminId) {
            $completion = DB::table('partner_campaign_task_completions')->where('id', $completionId)->lockForUpdate()->first();
            if (!$completion || $completion->status !== 'pending') {
                throw new HttpException(404, 'Pending task completion was not found.');
            }

            $campaign = DB::table('partner_campaigns')->where('id', $completion->campaign_id)->lockForUpdate()->first();
            $task = DB::table('partner_campaign_tasks')->where('id', $completion->task_id)->first();
            $this->assertTaskOpen($campaign, $task);
            if ($campaign->budget_amount !== null && $this->taskDistributed($campaign->id) + (float) $task->reward_amount > (float) $campaign->budget_amount) {
                throw new HttpException(409, 'The campaign does not have enough remaining budget for this task.');
            }

            DB::table('partner_campaign_task_completions')->where('id', $completionId)->update([
                'status' => 'approved',
                'approved_by_admin_id' => $adminId,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function rejectTaskCompletion(int $completionId, int $adminId, ?string $note = null): void
    {
        $updated = DB::table('partner_campaign_task_completions')
            ->where('id', $completionId)
            ->where('status', 'pending')
            ->update(['status' => 'rejected', 'approved_by_admin_id' => $adminId, 'rejected_at' => now(), 'admin_note' => $note, 'updated_at' => now()]);
        if (!$updated) {
            throw new HttpException(404, 'Pending task completion was not found.');
        }
    }

    public function pendingCompletions()
    {
        return DB::table('partner_campaign_completions')
            ->join('partner_campaigns', 'partner_campaign_completions.campaign_id', '=', 'partner_campaigns.id')
            ->join('partners', 'partner_campaigns.partner_id', '=', 'partners.id')
            ->leftJoin('res_users', 'partner_campaign_completions.user_id', '=', 'res_users.id')
            ->where('partner_campaign_completions.status', 'pending')
            ->select(
                'partner_campaign_completions.*',
                'partner_campaigns.title as campaign_title',
                'partners.name as partner_name',
                'res_users.username',
                'res_users.email'
            )
            ->orderBy('partner_campaign_completions.created_at')
            ->paginate(40);
    }

    public function approveCompletion(int $completionId, int $adminId): void
    {
        DB::transaction(function () use ($completionId, $adminId) {
            $completion = DB::table('partner_campaign_completions')
                ->where('id', $completionId)
                ->lockForUpdate()
                ->first();

            if (!$completion || $completion->status !== 'pending') {
                throw new HttpException(404, 'Pending campaign completion was not found.');
            }

            $campaign = DB::table('partner_campaigns')
                ->where('id', $completion->campaign_id)
                ->lockForUpdate()
                ->first();
            $this->assertOpen($campaign);

            if (!$this->hasApprovalCapacity($campaign)) {
                throw new HttpException(409, 'The campaign has reached its participant or budget limit.');
            }

            DB::table('partner_campaign_completions')
                ->where('id', $completion->id)
                ->update([
                    'status' => 'approved',
                    'approved_by_admin_id' => $adminId,
                    'approved_at' => now(),
                    'updated_at' => now(),
                ]);
        });
    }

    public function rejectCompletion(int $completionId, int $adminId, ?string $note = null): void
    {
        $updated = DB::table('partner_campaign_completions')
            ->where('id', $completionId)
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'approved_by_admin_id' => $adminId,
                'rejected_at' => now(),
                'admin_note' => $note,
                'updated_at' => now(),
            ]);

        if (!$updated) {
            throw new HttpException(404, 'Pending campaign completion was not found.');
        }
    }

    public function dashboardData(string $token): ?array
    {
        $tokenRow = DB::table('partner_dashboard_tokens')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->first();

        if (!$tokenRow) {
            return null;
        }

        DB::table('partner_dashboard_tokens')->where('id', $tokenRow->id)->update(['last_used_at' => now()]);
        $partner = DB::table('partners')->where('id', $tokenRow->partner_id)->first();
        if (!$partner) {
            return null;
        }

        $campaigns = DB::table('partner_campaigns')
            ->where('partner_id', $partner->id)
            ->orderByDesc('id')
            ->get()
            ->map(function ($campaign) {
                return (object) array_merge((array) $campaign, ['metrics' => $this->metrics((int) $campaign->id)]);
            });

        $summary = [
            'clicks' => $campaigns->sum(function ($campaign) { return $campaign->metrics['clicks']; }),
            'unique_users' => $campaigns->sum(function ($campaign) { return $campaign->metrics['unique_users']; }),
            'claims' => $campaigns->sum(function ($campaign) { return $campaign->metrics['claims']; }),
            'brc_distributed' => $campaigns->sum(function ($campaign) { return $campaign->metrics['brc_distributed']; }),
        ];

        return compact('partner', 'campaigns', 'summary');
    }

    public function dashboardCampaignMetrics(string $token, int $campaignId): ?array
    {
        $data = $this->dashboardData($token);
        if (!$data) {
            return null;
        }

        foreach ($data['campaigns'] as $campaign) {
            if ((int) $campaign->id === $campaignId) {
                return [
                    'campaign' => $campaign,
                    'metrics' => $campaign->metrics,
                ];
            }
        }

        return null;
    }

    public function issueDashboardToken(int $partnerId, ?string $label = null): array
    {
        $partner = DB::table('partners')->where('id', $partnerId)->first();
        if (!$partner) {
            throw new HttpException(404, 'Partner was not found.');
        }

        $token = Str::random(64);
        DB::table('partner_dashboard_tokens')->insert([
            'partner_id' => $partnerId,
            'token_hash' => hash('sha256', $token),
            'label' => $label,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'token' => $token,
            'url' => url('/partner/dashboard/' . $token),
        ];
    }

    public function rotateDashboardToken(int $partnerId, ?string $label = null): array
    {
        DB::table('partner_dashboard_tokens')
            ->where('partner_id', $partnerId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]);

        return $this->issueDashboardToken($partnerId, $label);
    }

    private function getCampaign(int $campaignId)
    {
        return DB::table('partner_campaigns')->where('id', $campaignId)->first();
    }

    private function getTask(int $campaignId, int $taskId)
    {
        return DB::table('partner_campaign_tasks')
            ->where('id', $taskId)
            ->where('campaign_id', $campaignId)
            ->first();
    }

    private function assertTaskOpen($campaign, $task): void
    {
        $this->assertOpen($campaign);
        if (!$task || $task->status !== 'active') {
            throw new HttpException(404, 'This campaign task is not currently available.');
        }

        if ($task->target_url) {
            $scheme = strtolower((string) parse_url($task->target_url, PHP_URL_SCHEME));
            if (!in_array($scheme, ['http', 'https'], true)) {
                throw new HttpException(404, 'This task destination is unavailable.');
            }
        }
    }

    private function taskClicked(int $taskId, int $userId): bool
    {
        return DB::table('partner_campaign_task_clicks')
            ->where('task_id', $taskId)
            ->where('user_id', $userId)
            ->exists();
    }

    private function isTelegramClickReward($task): bool
    {
        return $task && $task->task_type === 'join_telegram';
    }

    private function taskHasCapacity($task): bool
    {
        if (!$task || $task->participant_limit === null) {
            return true;
        }

        return DB::table('partner_campaign_task_claims')
            ->where('task_id', $task->id)
            ->count() < (int) $task->participant_limit;
    }

    private function taskHasClaimCapacity($task): bool
    {
        return $this->taskHasCapacity($task);
    }

    private function campaignUserHasCapacity($campaign, int $userId): bool
    {
        if ($campaign->participant_limit === null) {
            return true;
        }

        $alreadyParticipating = DB::table('partner_campaign_task_claims')
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $userId)
            ->exists();
        if ($alreadyParticipating) {
            return true;
        }

        $participants = DB::table('partner_campaign_task_claims')
            ->where('campaign_id', $campaign->id)
            ->distinct()
            ->count('user_id');

        return $participants < (int) $campaign->participant_limit;
    }

    private function taskDistributed(int $campaignId): float
    {
        if (!Schema::hasTable('partner_campaign_task_claims')) {
            return 0;
        }

        return (float) DB::table('partner_campaign_task_claims')
            ->where('campaign_id', $campaignId)
            ->sum('reward_amount');
    }

    private function taskMetrics(int $campaignId): array
    {
        if (!Schema::hasTable('partner_campaign_tasks')) {
            return [];
        }

        return DB::table('partner_campaign_tasks')
            ->where('campaign_id', $campaignId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function ($task) {
                $clicks = DB::table('partner_campaign_task_clicks')->where('task_id', $task->id);
                $completions = DB::table('partner_campaign_task_completions')->where('task_id', $task->id);
                $claims = DB::table('partner_campaign_task_claims')->where('task_id', $task->id);
                $claimCount = (clone $claims)->count();
                $clickCount = (clone $clicks)->count();

                return [
                    'id' => (int) $task->id,
                    'title' => $task->title,
                    'task_type' => $task->task_type,
                    'completion_mode' => $task->completion_mode,
                    'reward_amount' => (float) $task->reward_amount,
                    'reward_label' => $task->reward_label,
                    'clicks' => $clickCount,
                    'unique_users' => (clone $clicks)->whereNotNull('user_id')->distinct()->count('user_id'),
                    'pending_completions' => (clone $completions)->where('status', 'pending')->count(),
                    'approved_completions' => (clone $completions)->where('status', 'approved')->count(),
                    'claims' => $claimCount,
                    'brc_distributed' => (float) (clone $claims)->sum('reward_amount'),
                    'conversion_rate' => $clickCount > 0 ? round(($claimCount / $clickCount) * 100, 2) : 0,
                ];
            })
            ->values()
            ->all();
    }

    private function assertOpen($campaign): void
    {
        $partnerActive = $campaign && DB::table('partners')
            ->where('id', $campaign->partner_id)
            ->where('status', 'active')
            ->exists();

        if (!$campaign || !$partnerActive || $campaign->status !== 'active' || Carbon::now()->lt(Carbon::parse($campaign->starts_at)) || Carbon::now()->gt(Carbon::parse($campaign->ends_at))) {
            throw new HttpException(404, 'This campaign is not currently available.');
        }

        $scheme = strtolower((string) parse_url($campaign->destination_url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new HttpException(404, 'This campaign destination is unavailable.');
        }
    }

    private function recordClick($campaign, ?int $userId, Request $request, string $source): void
    {
        DB::table('partner_campaign_clicks')->insert([
            'campaign_id' => $campaign->id,
            'user_id' => $userId,
            'visitor_hash' => $this->visitorHash($request),
            'source' => $source,
            'redirected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function visitorHash(Request $request): string
    {
        return hash_hmac('sha256', (string) $request->ip() . '|' . (string) $request->userAgent(), (string) config('app.key'));
    }

    private function userClicked(int $campaignId, int $userId): bool
    {
        return DB::table('partner_campaign_clicks')
            ->where('campaign_id', $campaignId)
            ->where('user_id', $userId)
            ->exists();
    }

    private function hasCapacity($campaign): bool
    {
        if (!$campaign) {
            return false;
        }

        if ($campaign->participant_limit !== null) {
            $count = $campaign->reward_mode === 'admin_approval'
                ? DB::table('partner_campaign_completions')->where('campaign_id', $campaign->id)->where('status', 'approved')->count()
                : DB::table('partner_campaign_claims')->where('campaign_id', $campaign->id)->count();
            if ($count >= (int) $campaign->participant_limit) {
                return false;
            }
        }

        if ($campaign->budget_amount !== null) {
            $claimed = (float) DB::table('partner_campaign_claims')->where('campaign_id', $campaign->id)->sum('reward_amount');
            $reserved = $campaign->reward_mode === 'admin_approval'
                ? (float) DB::table('partner_campaign_completions')->where('campaign_id', $campaign->id)->where('status', 'approved')->count() * (float) $campaign->reward_amount
                : 0;
            if ($claimed + $reserved + (float) $campaign->reward_amount > (float) $campaign->budget_amount) {
                return false;
            }
        }

        return true;
    }

    private function hasClaimCapacity($campaign): bool
    {
        if ($campaign->participant_limit === null) {
            return true;
        }

        return DB::table('partner_campaign_claims')
            ->where('campaign_id', $campaign->id)
            ->count() < (int) $campaign->participant_limit;
    }

    private function hasApprovalCapacity($campaign): bool
    {
        $approved = DB::table('partner_campaign_completions')
            ->where('campaign_id', $campaign->id)
            ->where('status', 'approved')
            ->count();
        if ($campaign->participant_limit !== null && $approved >= (int) $campaign->participant_limit) {
            return false;
        }

        if ($campaign->budget_amount !== null) {
            $claimed = (float) DB::table('partner_campaign_claims')->where('campaign_id', $campaign->id)->sum('reward_amount');
            $reserved = (float) DB::table('partner_campaign_completions')
                ->where('campaign_id', $campaign->id)
                ->where('status', 'approved')
                ->count() * (float) $campaign->reward_amount;
            if ($claimed + $reserved + (float) $campaign->reward_amount > (float) $campaign->budget_amount) {
                return false;
            }
        }

        return true;
    }

    private function formatCampaign($campaign, int $userId): array
    {
        $claim = DB::table('partner_campaign_claims')
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $userId)
            ->first();
        $completion = DB::table('partner_campaign_completions')
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->first();
        $clicked = $this->userClicked($campaign->id, $userId);

        $tasks = Schema::hasTable('partner_campaign_tasks')
            ? DB::table('partner_campaign_tasks')->where('campaign_id', $campaign->id)->where('status', 'active')->orderBy('sort_order')->orderBy('id')->get()->map(function ($task) use ($userId) {
                return $this->formatTask($task, $userId);
            })->values()->all()
            : [];

        return [
            'id' => (int) $campaign->id,
            'public_id' => $campaign->public_id,
            'slug' => $campaign->slug,
            'partner_name' => $campaign->partner_name,
            'title' => $campaign->title,
            'description' => $campaign->description,
            'image_url' => $campaign->image_url,
            'starts_at' => $campaign->starts_at,
            'ends_at' => $campaign->ends_at,
            'reward' => [
                'amount' => (float) $campaign->reward_amount,
                'label' => $campaign->reward_label,
                'mode' => $campaign->reward_mode,
            ],
            'budget' => [
                'total' => $campaign->budget_amount !== null ? (float) $campaign->budget_amount : null,
                'distributed' => $this->metrics((int) $campaign->id)['brc_distributed'],
                'remaining' => $campaign->budget_amount !== null ? max(0, (float) $campaign->budget_amount - $this->metrics((int) $campaign->id)['brc_distributed']) : null,
            ],
            'tasks' => $tasks,
            'smart_link' => url('/partner/campaign/' . $campaign->slug),
            'click_url' => url('/api/auth/partner-campaigns/' . $campaign->id . '/click'),
            'claim_url' => url('/api/auth/partner-campaigns/' . $campaign->id . '/claim'),
            'user_status' => $claim ? 'claimed' : ($completion ? $completion->status : ($clicked ? 'clicked' : 'available')),
            'clicked' => $clicked,
            'claimed' => (bool) $claim,
            'can_claim' => !$claim && $clicked && ($campaign->reward_mode === 'click_claim' || ($completion && $completion->status === 'approved')),
        ];
    }

    private function formatTask($task, int $userId): array
    {
        $claim = DB::table('partner_campaign_task_claims')->where('task_id', $task->id)->where('user_id', $userId)->first();
        $completion = DB::table('partner_campaign_task_completions')->where('task_id', $task->id)->where('user_id', $userId)->first();
        $clicked = $this->taskClicked((int) $task->id, $userId);

        return [
            'id' => (int) $task->id,
            'key' => $task->task_key,
            'type' => $task->task_type,
            'title' => $task->title,
            'instructions' => $task->instructions,
            'target_url' => $task->target_url,
            'completion_mode' => $this->isTelegramClickReward($task)
                ? 'click_claim'
                : $task->completion_mode,
            'reward' => ['amount' => (float) $task->reward_amount, 'label' => $task->reward_label],
            'click_url' => url('/api/auth/partner-campaigns/' . $task->campaign_id . '/tasks/' . $task->id . '/click'),
            'submit_url' => url('/api/auth/partner-campaigns/' . $task->campaign_id . '/tasks/' . $task->id . '/submit'),
            'claim_url' => url('/api/auth/partner-campaigns/' . $task->campaign_id . '/tasks/' . $task->id . '/claim'),
            'user_status' => $claim ? 'claimed' : ($completion ? $completion->status : ($clicked ? 'clicked' : 'available')),
            'clicked' => $clicked,
            'claimed' => (bool) $claim,
            'can_claim' => !$claim && ((($task->completion_mode === 'click_claim' || $this->isTelegramClickReward($task)) && $clicked) || ($completion && $completion->status === 'approved')),
        ];
    }

    private function taskClaimPayload($claim): array
    {
        return [
            'id' => (int) $claim->id,
            'campaign_id' => (int) $claim->campaign_id,
            'task_id' => (int) $claim->task_id,
            'amount' => (float) $claim->reward_amount,
            'label' => $claim->reward_label,
            'claimed_at' => $claim->claimed_at,
        ];
    }

    private function claimPayload($claim): array
    {
        return [
            'id' => (int) $claim->id,
            'campaign_id' => (int) $claim->campaign_id,
            'amount' => (float) $claim->reward_amount,
            'label' => $claim->reward_label,
            'claimed_at' => $claim->claimed_at,
        ];
    }

    private function insertRewardNotification(int $userId, float $amount, string $campaignTitle): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        $message = 'Congratulations! ' . $amount . ' BRC has been added for completing ' . $campaignTitle . '.';
        $payload = [
            'type' => 'Gift Reward',
            'title' => 'Partner campaign reward received',
            'action' => 'CLAIM_REWARDS',
            'reward' => $amount,
            'data' => [
                'coin' => $amount,
                'message' => $message,
                'campaign' => $campaignTitle,
            ],
        ];
        $insert = [];

        if (Schema::hasColumn('notifications', 'type')) {
            $insert['type'] = 'BRC_REWARD';
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
            $insert['notification_type'] = 'BRC_REWARD';
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

        if ($insert) {
            DB::table('notifications')->insert($insert);
        }
    }
}
