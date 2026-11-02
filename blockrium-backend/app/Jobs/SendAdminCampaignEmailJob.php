<?php

namespace App\Jobs;

use App\Http\Controllers\AdminEmailCampaignController;
use App\Services\AdminEmailCampaignDeliveryService;
use App\Services\AdminEmailCampaignAudience;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendAdminCampaignEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 60;
    private $recipientId;

    public function __construct(int $recipientId)
    {
        $this->recipientId = $recipientId;
    }

    public function handle(AdminEmailCampaignDeliveryService $delivery)
    {
        $recipient = DB::transaction(function () {
            $recipient = DB::table('admin_email_campaign_recipients')->where('id', $this->recipientId)->lockForUpdate()->first();
            if (!$recipient || $recipient->status !== 'queued') {
                return null;
            }
            DB::table('admin_email_campaign_recipients')->where('id', $this->recipientId)->update([
                'status' => 'sending',
                'attempt_count' => DB::raw('attempt_count + 1'),
                'last_error' => null,
                'updated_at' => now(),
            ]);
            return $recipient;
        });

        if (!$recipient) {
            return;
        }

        if (empty($recipient->tracking_token)) {
            $recipient->tracking_token = Str::random(64);
            DB::table('admin_email_campaign_recipients')->where('id', $this->recipientId)->update([
                'tracking_token' => $recipient->tracking_token,
                'updated_at' => now(),
            ]);
        }

        $campaign = DB::table('admin_email_campaigns')->where('id', $recipient->campaign_id)->first();
        $user = DB::table('res_users')->where('id', $recipient->user_id)->first();
        $preference = DB::table('admin_email_preferences')->where('user_id', $recipient->user_id)->first();
        $membership = DB::table('verified_memberships')->where('user_id', $recipient->user_id)->first();
        $stillEligible = $campaign
            && strtolower(trim((string) ($user->email ?? ''))) === strtolower(trim((string) $recipient->email))
            && app(AdminEmailCampaignAudience::class)->isEligible($campaign->audience, $user, $preference, $membership);

        if (!$stillEligible) {
            DB::table('admin_email_campaign_recipients')->where('id', $this->recipientId)->update([
                'status' => 'skipped', 'last_error' => null, 'updated_at' => now(),
            ]);
            $delivery->refreshCampaign((int) $recipient->campaign_id);
            return;
        }

        try {
            $unsubscribeUrl = AdminEmailCampaignController::optOutUrl((int) $recipient->user_id);
            $clickUrl = route('email.campaign.click', $recipient->tracking_token);
            $openTrackingUrl = route('email.campaign.open', $recipient->tracking_token);
            Mail::send([
                'html' => 'emails.blockrium-account-update',
                'text' => 'emails.blockrium-account-update-text',
            ], array_merge(AdminEmailCampaignController::brandTemplateData(), [
                'name' => $user->name ?: $user->username ?: 'Blockrium member',
                'body' => $campaign->body,
                'subject' => $campaign->subject,
                'actionUrl' => $campaign->action_url,
                'unsubscribeUrl' => $unsubscribeUrl,
                'clickUrl' => $clickUrl,
                'openTrackingUrl' => $openTrackingUrl,
                'isTest' => false,
            ]), function ($message) use ($recipient, $campaign, $unsubscribeUrl) {
                $message->to($recipient->email)->subject($campaign->subject);
                AdminEmailCampaignController::addUnsubscribeHeaders($message, $unsubscribeUrl);
            });

            DB::table('admin_email_campaign_recipients')->where('id', $this->recipientId)->update([
                'status' => 'sent', 'sent_at' => now(), 'last_error' => null, 'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            DB::table('admin_email_campaign_recipients')->where('id', $this->recipientId)->update([
                'status' => 'delivery_unknown',
                'last_error' => mb_substr(get_class($exception), 0, 500),
                'updated_at' => now(),
            ]);
            Log::warning('Admin account-update email delivery outcome is unknown; recipient will not be retried', [
                'campaign_id' => (int) $recipient->campaign_id,
                'recipient_id' => (int) $recipient->id,
                'error_type' => get_class($exception),
            ]);
        }

        $delivery->refreshCampaign((int) $recipient->campaign_id);
    }

    public function failed(Throwable $exception)
    {
        $recipient = DB::table('admin_email_campaign_recipients')->where('id', $this->recipientId)->first();
        if (!$recipient || $recipient->status !== 'sending') {
            return;
        }

        DB::table('admin_email_campaign_recipients')->where('id', $this->recipientId)->update([
            'status' => 'delivery_unknown',
            'last_error' => mb_substr(get_class($exception), 0, 500),
            'updated_at' => now(),
        ]);
        app(AdminEmailCampaignDeliveryService::class)->refreshCampaign((int) $recipient->campaign_id);
    }
}
