<?php

namespace App\Services;

use App\Jobs\SendAdminCampaignEmailJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdminEmailCampaignBatchService
{
    public function dispatchNextBatch(int $campaignId): int
    {
        $recipientIds = DB::transaction(function () use ($campaignId) {
            $campaign = DB::table('admin_email_campaigns')->where('id', $campaignId)->lockForUpdate()->first();
            if (!$campaign || $campaign->status !== 'scheduled'
                || !$campaign->next_batch_at || now()->lt($campaign->next_batch_at)) {
                return [];
            }

            $todayStart = now()->startOfDay();
            $dispatchedToday = DB::table('admin_email_campaign_recipients')
                ->where('campaign_id', $campaignId)
                ->where('dispatched_at', '>=', $todayStart)
                ->count();
            $remainingDaily = max(0, (int) $campaign->daily_cap - $dispatchedToday);

            if ($remainingDaily === 0) {
                DB::table('admin_email_campaigns')->where('id', $campaignId)->update([
                    'next_batch_at' => $todayStart->addDay(), 'updated_at' => now(),
                ]);
                return [];
            }

            $recipients = DB::table('admin_email_campaign_recipients')
                ->where('campaign_id', $campaignId)
                ->where('status', 'queued')
                ->where(function ($query) use ($todayStart) {
                    $query->whereNull('dispatched_at')->orWhere('dispatched_at', '<', $todayStart);
                })
                ->orderBy('id')
                ->limit(min((int) $campaign->batch_size, $remainingDaily))
                ->lockForUpdate()
                ->get(['id']);

            if ($recipients->isEmpty()) {
                $active = DB::table('admin_email_campaign_recipients')
                    ->where('campaign_id', $campaignId)
                    ->whereIn('status', ['queued', 'sending'])
                    ->exists();
                DB::table('admin_email_campaigns')->where('id', $campaignId)->update([
                    'status' => $active ? 'scheduled' : 'completed',
                    'completed_at' => $active ? null : now(),
                    'next_batch_at' => $active ? now()->addHours((int) $campaign->repeat_interval_hours) : null,
                    'updated_at' => now(),
                ]);
                return [];
            }

            $ids = $recipients->pluck('id')->map(function ($id) { return (int) $id; })->all();
            DB::table('admin_email_campaign_recipients')->whereIn('id', $ids)->update([
                'dispatched_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('admin_email_campaigns')->where('id', $campaignId)->update([
                'status' => 'sending',
                'next_batch_at' => now()->addHours((int) $campaign->repeat_interval_hours),
                'updated_at' => now(),
            ]);

            return $ids;
        });

        foreach ($recipientIds as $index => $recipientId) {
            try {
                SendAdminCampaignEmailJob::dispatch($recipientId)
                    ->onQueue('email-campaigns')
                    ->delay(now()->addSeconds($index));
            } catch (Throwable $exception) {
                DB::table('admin_email_campaign_recipients')->where('id', $recipientId)->update([
                    'status' => 'failed',
                    'last_error' => get_class($exception),
                    'updated_at' => now(),
                ]);
                Log::warning('Admin account-update email could not be queued', [
                    'campaign_id' => $campaignId,
                    'recipient_id' => $recipientId,
                    'error_type' => get_class($exception),
                ]);
            }
        }

        if ($recipientIds) app(AdminEmailCampaignDeliveryService::class)->refreshCampaign($campaignId);

        return count($recipientIds);
    }
}
