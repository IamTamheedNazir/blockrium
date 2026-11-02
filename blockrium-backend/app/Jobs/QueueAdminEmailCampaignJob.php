<?php

namespace App\Jobs;

use App\Services\AdminEmailCampaignDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class QueueAdminEmailCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 120;
    private $campaignId;

    public function __construct(int $campaignId)
    {
        $this->campaignId = $campaignId;
    }

    public function handle()
    {
        $campaign = DB::table('admin_email_campaigns')->where('id', $this->campaignId)->first();
        if (!$campaign || $campaign->status !== 'queueing') {
            return;
        }
        app(\App\Services\AdminEmailCampaignAudience::class)
            ->recipients($campaign->audience)
            ->orderBy('u.id')
            ->chunkById(500, function ($users) {
                $rows = [];
                foreach ($users as $user) {
                    $email = trim((string) $user->email);
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
                    $rows[] = [
                        'campaign_id' => $this->campaignId,
                        'user_id' => (int) $user->id,
                        'email' => $email,
                        'status' => 'queued',
                        'tracking_token' => Str::random(64),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if ($rows) DB::table('admin_email_campaign_recipients')->insertOrIgnore($rows);
            }, 'u.id', 'id');

        DB::table('admin_email_campaign_recipients')
            ->where('campaign_id', $this->campaignId)
            ->whereNull('tracking_token')
            ->orderBy('id')
            ->chunkById(500, function ($recipients) {
                foreach ($recipients as $recipient) {
                    DB::table('admin_email_campaign_recipients')->where('id', $recipient->id)->update([
                        'tracking_token' => Str::random(64),
                        'updated_at' => now(),
                    ]);
                }
            });

        $recipientCount = DB::table('admin_email_campaign_recipients')->where('campaign_id', $this->campaignId)->count();
        DB::table('admin_email_campaigns')->where('id', $this->campaignId)->update([
            'recipient_count' => $recipientCount,
            'status' => $recipientCount > 0 ? 'scheduled' : 'completed',
            'next_batch_at' => $recipientCount > 0 ? now() : null,
            'completed_at' => $recipientCount > 0 ? null : now(),
            'updated_at' => now(),
        ]);

        app(AdminEmailCampaignDeliveryService::class)->refreshCampaign($this->campaignId);
    }

    public function failed(Throwable $exception)
    {
        DB::table('admin_email_campaigns')->where('id', $this->campaignId)->update([
            'status' => 'queue_failed',
            'updated_at' => now(),
        ]);
    }
}
