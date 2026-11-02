<?php

namespace App\Http\Controllers;

use App\Jobs\QueueAdminEmailCampaignJob;
use App\Services\AdminEmailCampaignAudience;
use App\Services\EngagementAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminEmailCampaignController extends Controller
{
    private $audit;

    public function __construct(EngagementAdminService $audit)
    {
        $this->audit = $audit;
        $this->middleware('checklogin')->except(['optOutPage', 'optOut']);
    }

    public function index()
    {
        $brandSettings = array_merge([
            'website_url' => 'https://blockrium.com',
            'app_url' => 'https://play.google.com/store/apps/details?id=com.blockrium',
            'telegram_url' => 'https://t.me/blockrium',
            'instagram_url' => '',
            'facebook_url' => '',
            'x_url' => '',
            'youtube_url' => '',
            'support_email' => '',
        ], DB::table('admin_email_campaign_settings')->pluck('setting_value', 'setting_key')->all());

        $campaigns = DB::table('admin_email_campaigns')
            ->select('admin_email_campaigns.*')
            ->selectSub(function ($query) {
                $query->from('admin_email_campaign_recipients')
                    ->whereColumn('admin_email_campaign_recipients.campaign_id', 'admin_email_campaigns.id')
                    ->where('admin_email_campaign_recipients.status', 'delivery_unknown')
                    ->selectRaw('COUNT(*)');
            }, 'delivery_unknown_count')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.email-campaigns.index', [
            'campaigns' => $campaigns,
            'eligibleCounts' => [
                'all_active' => app(AdminEmailCampaignAudience::class)->recipients('all_active')->count(),
                'pending_kyc' => app(AdminEmailCampaignAudience::class)->recipients('pending_kyc')->count(),
            ],
            'queueConnection' => config('queue.default'),
            'queueReady' => !in_array(config('queue.default'), ['sync', 'null', null], true),
            'brandSettings' => $brandSettings,
        ]);
    }

    public function saveBrandSettings(Request $request)
    {
        $data = $request->validate([
            'website_url' => 'required|url|max:2048',
            'app_url' => 'required|url|max:2048',
            'telegram_url' => 'nullable|url|max:2048',
            'instagram_url' => 'nullable|url|max:2048',
            'facebook_url' => 'nullable|url|max:2048',
            'x_url' => 'nullable|url|max:2048',
            'youtube_url' => 'nullable|url|max:2048',
            'support_email' => 'nullable|email|max:254',
        ]);
        foreach (['website_url', 'app_url', 'telegram_url', 'instagram_url', 'facebook_url', 'x_url', 'youtube_url'] as $key) {
            if (!empty($data[$key]) && strtolower((string) parse_url($data[$key], PHP_URL_SCHEME)) !== 'https') {
                throw ValidationException::withMessages([$key => 'All brand links must use HTTPS.']);
            }
        }

        foreach ($data as $key => $value) {
            DB::table('admin_email_campaign_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => trim((string) $value) ?: null, 'updated_by_admin_id' => $this->adminId(), 'updated_at' => now(), 'created_at' => now()]
            );
        }

        $this->audit->recordAdminAudit($this->adminId(), 'update_brand_settings', 'admin_email_campaign', null, [
            'social_links_configured' => (int) collect(['instagram_url', 'facebook_url', 'x_url', 'youtube_url'])->filter(function ($key) use ($data) { return !empty($data[$key]); })->count(),
        ]);

        return back()->with('success', 'Shared email branding settings saved. Future campaign and test emails will use these links.');
    }

    public function show(int $campaignId)
    {
        $campaign = DB::table('admin_email_campaigns')->where('id', $campaignId)->first();
        abort_unless($campaign, 404);
        $campaign->delivery_unknown_count = DB::table('admin_email_campaign_recipients')
            ->where('campaign_id', $campaignId)
            ->where('status', 'delivery_unknown')
            ->count();
        $recipients = DB::table('admin_email_campaign_recipients as r')
            ->leftJoin('res_users as u', 'u.id', '=', 'r.user_id')
            ->where('r.campaign_id', $campaignId)
            ->orderByDesc('r.id')
            ->select('r.id', 'r.user_id', 'r.email', 'r.status', 'r.attempt_count', 'r.last_error', 'r.sent_at', 'r.updated_at', 'r.open_count', 'r.click_count', 'u.name')
            ->paginate(50)
            ->withQueryString();
        $trackingTotals = DB::table('admin_email_campaign_recipients')->where('campaign_id', $campaignId)
            ->selectRaw('COUNT(first_opened_at) as opened_recipients, COUNT(first_clicked_at) as clicked_recipients')
            ->first();

        return view('admin.email-campaigns.show', compact('campaign', 'recipients', 'trackingTotals'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'subject' => 'required|string|max:190',
            'body' => 'required|string|max:5000',
            'action_url' => 'required|url|max:2048',
            'audience' => 'required|in:all_active,pending_kyc',
            'batch_size' => 'required|integer|min:100|max:1000',
            'daily_cap' => 'required|integer|min:100|max:1000',
            'repeat_interval_hours' => 'required|integer|in:12,24',
        ]);
        if (strtolower((string) parse_url($data['action_url'], PHP_URL_SCHEME)) !== 'https') {
            throw ValidationException::withMessages(['action_url' => 'Use a secure https:// link for the KYC action.']);
        }

        $campaignId = DB::table('admin_email_campaigns')->insertGetId([
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body' => $data['body'],
            'action_url' => $data['action_url'],
            'audience' => $data['audience'],
            'batch_size' => (int) $data['batch_size'],
            'daily_cap' => (int) $data['daily_cap'],
            'repeat_interval_hours' => (int) $data['repeat_interval_hours'],
            'status' => 'draft',
            'created_by_admin_id' => $this->adminId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->recordAdminAudit($this->adminId(), 'create_draft', 'admin_email_campaign', $campaignId, [
            'audience' => $data['audience'],
            'eligible_count_at_creation' => (int) app(AdminEmailCampaignAudience::class)->recipients($data['audience'])->count(),
        ]);

        return redirect()->route('admin.email-campaigns.index')->with('success', 'Campaign draft saved. No email has been sent.');
    }

    public function testSend(Request $request, int $campaignId)
    {
        $data = $request->validate(['test_email' => 'required|email|max:254']);
        $campaign = DB::table('admin_email_campaigns')->where('id', $campaignId)->first();
        abort_unless($campaign, 404);

        $testUser = DB::table('res_users')->whereRaw('LOWER(email) = ?', [strtolower(trim($data['test_email']))])->first();
        $emailData = array_merge(self::brandTemplateData(), [
            'name' => $testUser ? ($testUser->name ?: $testUser->username ?: 'Blockrium member') : 'Blockrium team',
            'body' => $campaign->body,
            'subject' => $campaign->subject,
            'actionUrl' => $campaign->action_url,
            'unsubscribeUrl' => $testUser ? self::optOutUrl((int) $testUser->id) : null,
            'isTest' => true,
        ]);

        try {
            Mail::send([
                'html' => 'emails.blockrium-account-update',
                'text' => 'emails.blockrium-account-update-text',
            ], $emailData, function ($message) use ($data, $campaign, $emailData) {
                $message->to($data['test_email'])->subject('[TEST] ' . $campaign->subject);
                self::addUnsubscribeHeaders($message, $emailData['unsubscribeUrl'] ?? null);
            });
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors(['test_email' => 'The test message could not be sent. Check the mail connection and server logs, then try again.']);
        }

        $this->audit->recordAdminAudit($this->adminId(), 'test_send', 'admin_email_campaign', $campaignId, [
            'recipient_domain' => substr(strrchr($data['test_email'], '@') ?: '', 1),
        ]);

        return back()->with('success', 'Test email accepted by the configured mail transport. Check the test inbox, including Spam.');
    }

    public function queue(Request $request, int $campaignId)
    {
        if (!$this->queueReady()) {
            return back()->withErrors(['queue' => 'Email delivery is disabled while QUEUE_CONNECTION is sync or null. Configure an asynchronous queue and worker before sending.']);
        }
        $request->validate([
            'confirmation' => 'required|in:SEND',
            'service_basis' => 'required|accepted',
        ]);

        try {
            $campaign = DB::transaction(function () use ($campaignId) {
                $campaign = DB::table('admin_email_campaigns')->where('id', $campaignId)->lockForUpdate()->first();
                if (!$campaign || !in_array($campaign->status, ['draft', 'queue_failed'], true)) {
                    throw ValidationException::withMessages(['campaign' => 'This campaign is not in a sendable state.']);
                }

                DB::table('admin_email_campaigns')->where('id', $campaignId)->update([
                    'status' => 'queueing',
                    'service_basis_confirmed_at' => now(),
                    'queued_at' => now(),
                    'completed_at' => null,
                    'next_batch_at' => null,
                    'updated_at' => now(),
                ]);
                return DB::table('admin_email_campaigns')->where('id', $campaignId)->first();
            });

            QueueAdminEmailCampaignJob::dispatch($campaignId)->onQueue('email-campaigns');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            DB::table('admin_email_campaigns')->where('id', $campaignId)->update(['status' => 'queue_failed', 'updated_at' => now()]);
            return back()->withErrors(['queue' => 'The queue rejected this campaign. Confirm the queue tables and worker are available, then retry.']);
        }

        $this->audit->recordAdminAudit($this->adminId(), 'queue', 'admin_email_campaign', $campaignId, [
            'audience' => $campaign->audience,
            'recipient_count' => null,
            'batch_size' => (int) $campaign->batch_size,
            'daily_cap' => (int) $campaign->daily_cap,
        ]);

        return back()->with('success', 'Campaign recipient preparation has been queued. Delivery will begin in capped batches once preparation finishes.');
    }

    public function retryFailed(Request $request, int $campaignId)
    {
        if (!$this->queueReady()) {
            return back()->withErrors(['queue' => 'Configure an asynchronous queue and worker before retrying email delivery.']);
        }
        $request->validate(['confirmation' => 'required|in:RETRY']);

        $retryCount = DB::table('admin_email_campaign_recipients')
            ->where('campaign_id', $campaignId)
            ->where('status', 'failed')
            ->update(['status' => 'queued', 'last_error' => null, 'updated_at' => now()]);
        if ($retryCount === 0) {
            return back()->withErrors(['campaign' => 'There are no failed recipients to retry.']);
        }

        DB::table('admin_email_campaigns')->where('id', $campaignId)->update([
            'status' => 'queueing', 'completed_at' => null, 'updated_at' => now(),
        ]);
        QueueAdminEmailCampaignJob::dispatch($campaignId)->onQueue('email-campaigns');
        $this->audit->recordAdminAudit($this->adminId(), 'retry_failed', 'admin_email_campaign', $campaignId, ['recipient_count' => $retryCount]);

        return back()->with('success', number_format($retryCount) . ' confirmed pre-send failure(s) queued for retry. Recipients with an unknown SMTP outcome are never retried.');
    }

    public function optOutPage(int $userId)
    {
        return view('emails.bulk-email-opt-out', ['userId' => $userId]);
    }

    public function optOut(Request $request, int $userId)
    {
        $oneClick = $request->input('List-Unsubscribe');
        abort_if($oneClick !== null && $oneClick !== 'One-Click', 400);

        $now = now();
        $preference = [
            'bulk_email_opted_out_at' => $now,
            'opt_out_source' => $oneClick === 'One-Click' ? 'one_click' : 'email_link',
            'updated_at' => $now,
        ];
        DB::table('admin_email_preferences')->insertOrIgnore(array_merge($preference, [
            'user_id' => $userId,
            'created_at' => $now,
        ]));
        DB::table('admin_email_preferences')->where('user_id', $userId)->update($preference);

        return $oneClick === 'One-Click'
            ? response('', 200)
            : view('emails.bulk-email-opt-out', ['userId' => $userId, 'completed' => true]);
    }

    public static function optOutUrl(int $userId): string
    {
        return URL::signedRoute('email.bulk-opt-out', ['userId' => $userId]);
    }

    public static function addUnsubscribeHeaders($message, ?string $unsubscribeUrl): void
    {
        if (!$unsubscribeUrl) {
            return;
        }

        $headers = $message->getSwiftMessage()->getHeaders();
        $headers->addTextHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>');
        $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
    }

    private function eligibleRecipientCount(string $audience): int
    {
        $count = 0;
        foreach (app(AdminEmailCampaignAudience::class)->recipients($audience)->orderBy('u.id')->cursor() as $user) {
            if (filter_var(trim((string) $user->email), FILTER_VALIDATE_EMAIL)) {
                $count++;
            }
        }

        return $count;
    }

    private function adminId(): ?int
    {
        return Session::get('ad_id') ? (int) Session::get('ad_id') : null;
    }

    private function queueReady(): bool
    {
        return !in_array(config('queue.default'), ['sync', 'null', null], true);
    }

    public static function brandTemplateData(): array
    {
        $settings = DB::table('admin_email_campaign_settings')->pluck('setting_value', 'setting_key');
        return [
            'logoUrl' => url('/email-assets/blockrium-mark.png'),
            'siteUrl' => $settings->get('website_url') ?: 'https://blockrium.com',
            'appUrl' => $settings->get('app_url') ?: 'https://play.google.com/store/apps/details?id=com.blockrium',
            'telegramUrl' => $settings->get('telegram_url') ?: 'https://t.me/blockrium',
            'instagramUrl' => $settings->get('instagram_url') ?: null,
            'facebookUrl' => $settings->get('facebook_url') ?: null,
            'xUrl' => $settings->get('x_url') ?: null,
            'youtubeUrl' => $settings->get('youtube_url') ?: null,
            'supportEmail' => $settings->get('support_email') ?: null,
        ];
    }
}
