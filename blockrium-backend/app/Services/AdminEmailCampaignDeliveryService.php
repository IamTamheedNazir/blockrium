<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AdminEmailCampaignDeliveryService
{
    public function refreshCampaign(int $campaignId): void
    {
        $counts = DB::table('admin_email_campaign_recipients')
            ->where('campaign_id', $campaignId)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $sent = (int) ($counts['sent'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);
        $skipped = (int) ($counts['skipped'] ?? 0);
        $deliveryUnknown = (int) ($counts['delivery_unknown'] ?? 0);
        $queued = (int) ($counts['queued'] ?? 0);
        $sending = (int) ($counts['sending'] ?? 0);
        $campaign = DB::table('admin_email_campaigns')->where('id', $campaignId)->first();
        $updates = [
            'sent_count' => $sent,
            'failed_count' => $failed,
            'delivery_unknown_count' => $deliveryUnknown,
            'skipped_count' => $skipped,
            'updated_at' => now(),
        ];

        if ($queued === 0 && $sending === 0 && $sent + $failed + $skipped + $deliveryUnknown > 0) {
            $updates['status'] = 'completed';
            $updates['completed_at'] = now();
            $updates['next_batch_at'] = null;
        } elseif ($campaign && $queued > 0 && $campaign->status === 'sending' && $campaign->next_batch_at) {
            $updates['status'] = 'scheduled';
        }

        DB::table('admin_email_campaigns')->where('id', $campaignId)->update($updates);
    }
}
