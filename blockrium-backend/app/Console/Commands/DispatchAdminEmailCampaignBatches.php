<?php

namespace App\Console\Commands;

use App\Services\AdminEmailCampaignBatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchAdminEmailCampaignBatches extends Command
{
    protected $signature = 'email-campaigns:tick';
    protected $description = 'Dispatch due batches for active Blockrium account-update campaigns';

    public function handle(AdminEmailCampaignBatchService $batches)
    {
        $campaignIds = DB::table('admin_email_campaigns')
            ->where('status', 'scheduled')
            ->whereNotNull('next_batch_at')
            ->where('next_batch_at', '<=', now())
            ->orderBy('next_batch_at')
            ->limit(100)
            ->pluck('id');

        $dispatched = 0;
        foreach ($campaignIds as $campaignId) {
            $dispatched += $batches->dispatchNextBatch((int) $campaignId);
        }

        $this->info('Dispatched ' . $dispatched . ' account-update email(s) across ' . $campaignIds->count() . ' due campaign(s).');
        return 0;
    }
}
