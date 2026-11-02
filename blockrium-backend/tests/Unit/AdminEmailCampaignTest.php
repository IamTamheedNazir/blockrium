<?php

namespace Tests\Unit;

use App\Http\Controllers\AdminEmailCampaignController;
use App\Services\EngagementAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminEmailCampaignTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('res_users', function ($table) {
            $table->increments('id');
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->boolean('is_active')->default(true);
        });
        Schema::create('verified_memberships', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('kyc_status')->nullable();
            $table->timestamp('suspended_at')->nullable();
        });
        require_once base_path('database/migrations/2026_10_14_000001_create_admin_email_campaign_tables.php');
        (new \CreateAdminEmailCampaignTables())->up();
        require_once base_path('database/migrations/2026_10_19_000001_add_admin_email_campaign_batch_tracking.php');
        (new \AddAdminEmailCampaignBatchTracking())->up();
        require_once base_path('database/migrations/2026_10_21_000001_add_email_delivery_unknown_status.php');
        (new \AddEmailDeliveryUnknownStatus())->up();
        DB::table('admin_email_campaigns')->insert([
            'id' => 1,
            'name' => 'Update test',
            'subject' => 'Blockrium account update',
            'body' => 'Your account update is ready.',
            'action_url' => 'https://blockrium.com',
            'audience' => 'pending_kyc',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_admin_can_send_a_test_using_the_saved_campaign_content(): void
    {
        Mail::shouldReceive('send')
            ->once()
            ->with(
                [
                    'html' => 'emails.blockrium-account-update',
                    'text' => 'emails.blockrium-account-update-text',
                ],
                \Mockery::on(function ($data) {
                    return $data['body'] === 'Your account update is ready.'
                        && $data['subject'] === 'Blockrium account update'
                        && $data['actionUrl'] === 'https://blockrium.com'
                        && $data['isTest'] === true
                        && $data['logoUrl'] === url('/email-assets/blockrium-mark.png');
                }),
                \Mockery::on(function ($callback) {
                    $message = \Mockery::mock();
                    $message->shouldReceive('to')->once()->with('qa@example.test')->andReturnSelf();
                    $message->shouldReceive('subject')->once()->with('[TEST] Blockrium account update');
                    $callback($message);
                    return true;
                })
            );

        $controller = new AdminEmailCampaignController(new EngagementAdminService());
        $response = $controller->testSend(Request::create('/admin/email-campaigns/1/test', 'POST', [
            'test_email' => 'qa@example.test',
        ]), 1);

        $this->assertSame('Test email accepted by the configured mail transport. Check the test inbox, including Spam.', session('success'));
        $this->assertNotNull($response);
    }

    public function test_smtp_failure_returns_a_safe_admin_error_instead_of_a_transport_exception_page(): void
    {
        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \Swift_TransportException('Connection refused for qa@example.test'));

        $controller = new AdminEmailCampaignController(new EngagementAdminService());
        $response = $controller->testSend(Request::create('/admin/email-campaigns/1/test', 'POST', [
            'test_email' => 'qa@example.test',
        ]), 1);

        $message = session('errors')->first('test_email');
        $this->assertSame('The test message could not be sent. Check the mail connection and server logs, then try again.', $message);
        $this->assertStringNotContainsString('Connection refused', $message);
        $this->assertNotNull($response);
    }

    public function test_ambiguous_smtp_exception_is_terminal_and_never_retried(): void
    {
        DB::table('res_users')->insert([
            'id' => 42, 'email' => 'member@example.test', 'name' => 'Member', 'username' => 'member', 'is_active' => 1,
        ]);
        DB::table('admin_email_campaigns')->where('id', 1)->update([
            'audience' => 'all_active', 'status' => 'sending',
        ]);
        DB::table('admin_email_campaign_recipients')->insert([
            'id' => 10, 'campaign_id' => 1, 'user_id' => 42, 'email' => 'member@example.test',
            'status' => 'queued', 'attempt_count' => 0, 'tracking_token' => str_repeat('a', 64),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('connection dropped after SMTP handoff'));

        (new \App\Jobs\SendAdminCampaignEmailJob(10))->handle(new \App\Services\AdminEmailCampaignDeliveryService());

        $this->assertSame('delivery_unknown', DB::table('admin_email_campaign_recipients')->where('id', 10)->value('status'));
        $this->assertSame(1, (int) DB::table('admin_email_campaigns')->where('id', 1)->value('delivery_unknown_count'));
        $this->assertSame(0, (int) DB::table('admin_email_campaigns')->where('id', 1)->value('failed_count'));
        $this->assertSame('completed', DB::table('admin_email_campaigns')->where('id', 1)->value('status'));
    }

    public function test_worker_failure_after_delivery_started_is_recorded_as_unknown(): void
    {
        DB::table('admin_email_campaign_recipients')->insert([
            'id' => 11, 'campaign_id' => 1, 'user_id' => 99, 'email' => 'member@example.test',
            'status' => 'sending', 'attempt_count' => 1, 'tracking_token' => str_repeat('b', 64),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        (new \App\Jobs\SendAdminCampaignEmailJob(11))->failed(new \RuntimeException('worker terminated'));

        $this->assertSame('delivery_unknown', DB::table('admin_email_campaign_recipients')->where('id', 11)->value('status'));
        $this->assertSame(1, (int) DB::table('admin_email_campaigns')->where('id', 1)->value('delivery_unknown_count'));
        $this->assertSame('completed', DB::table('admin_email_campaigns')->where('id', 1)->value('status'));
    }

    public function test_manual_retry_only_requeues_confirmed_pre_send_failures(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        config(['queue.default' => 'database']);
        DB::table('admin_email_campaign_recipients')->insert([
            ['id' => 12, 'campaign_id' => 1, 'user_id' => 100, 'email' => 'safe@example.test', 'status' => 'failed', 'attempt_count' => 0, 'tracking_token' => str_repeat('c', 64), 'created_at' => now(), 'updated_at' => now()],
            ['id' => 13, 'campaign_id' => 1, 'user_id' => 101, 'email' => 'unknown@example.test', 'status' => 'delivery_unknown', 'attempt_count' => 1, 'tracking_token' => str_repeat('d', 64), 'created_at' => now(), 'updated_at' => now()],
        ]);
        $audit = \Mockery::mock(EngagementAdminService::class);
        $audit->shouldReceive('recordAdminAudit')->once()->with(null, 'retry_failed', 'admin_email_campaign', 1, ['recipient_count' => 1]);

        $controller = new AdminEmailCampaignController($audit);
        $controller->retryFailed(Request::create('/admin/email-campaigns/1/retry-failed', 'POST', ['confirmation' => 'RETRY']), 1);

        $this->assertSame('queued', DB::table('admin_email_campaign_recipients')->where('id', 12)->value('status'));
        $this->assertSame('delivery_unknown', DB::table('admin_email_campaign_recipients')->where('id', 13)->value('status'));
        $this->assertStringContainsString('unknown SMTP outcome are never retried', session('success'));
    }

    public function test_manual_retry_refuses_campaigns_with_only_unknown_delivery_outcomes(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        config(['queue.default' => 'database']);
        DB::table('admin_email_campaign_recipients')->insert([
            'id' => 14, 'campaign_id' => 1, 'user_id' => 102, 'email' => 'unknown@example.test',
            'status' => 'delivery_unknown', 'attempt_count' => 1, 'tracking_token' => str_repeat('e', 64),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = (new AdminEmailCampaignController(new EngagementAdminService()))
            ->retryFailed(Request::create('/admin/email-campaigns/1/retry-failed', 'POST', ['confirmation' => 'RETRY']), 1);

        $this->assertSame('delivery_unknown', DB::table('admin_email_campaign_recipients')->where('id', 14)->value('status'));
        $this->assertStringContainsString('There are no failed recipients to retry.', session('errors')->first('campaign'));
        $this->assertNotNull($response);
    }

    public function test_migration_conservatively_reclassifies_legacy_failures_as_unknown(): void
    {
        DB::table('admin_email_campaign_recipients')->insert([
            'id' => 15, 'campaign_id' => 1, 'user_id' => 103, 'email' => 'legacy@example.test',
            'status' => 'failed', 'attempt_count' => 1, 'tracking_token' => str_repeat('f', 64),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('admin_email_campaigns')->where('id', 1)->update(['failed_count' => 1]);

        (new \AddEmailDeliveryUnknownStatus())->up();

        $this->assertSame('delivery_unknown', DB::table('admin_email_campaign_recipients')->where('id', 15)->value('status'));
        $this->assertSame('legacy_delivery_outcome_unknown', DB::table('admin_email_campaign_recipients')->where('id', 15)->value('last_error'));
        $this->assertSame(0, (int) DB::table('admin_email_campaigns')->where('id', 1)->value('failed_count'));
        $this->assertSame(1, (int) DB::table('admin_email_campaigns')->where('id', 1)->value('delivery_unknown_count'));
    }

    public function test_campaign_admin_views_expose_unknown_delivery_counts(): void
    {
        DB::table('admin_email_campaign_recipients')->insert([
            'campaign_id' => 1, 'user_id' => 104, 'email' => 'unknown@example.test',
            'status' => 'delivery_unknown', 'attempt_count' => 1, 'tracking_token' => str_repeat('g', 64),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $controller = new AdminEmailCampaignController(new EngagementAdminService());

        $index = $controller->index()->getData()['campaigns']->first();
        $details = $controller->show(1)->getData()['campaign'];

        $this->assertSame(1, (int) $index->delivery_unknown_count);
        $this->assertSame(1, (int) $details->delivery_unknown_count);
    }

    public function test_branded_template_has_shared_site_app_telegram_and_unsubscribe_footer(): void
    {
        DB::table('admin_email_campaign_settings')->insert([
            ['setting_key' => 'instagram_url', 'setting_value' => 'https://instagram.com/blockrium', 'created_at' => now(), 'updated_at' => now()],
            ['setting_key' => 'support_email', 'setting_value' => 'support@blockrium.com', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $html = view('emails.blockrium-account-update', array_merge(
            AdminEmailCampaignController::brandTemplateData(),
            [
                'name' => 'Test member',
                'subject' => 'Your KYC is ready',
                'body' => 'A factual account update.',
                'actionUrl' => 'https://blockrium.com/account',
                'unsubscribeUrl' => 'https://blockrium.com/email/bulk-opt-out/1?signature=test',
                'isTest' => false,
            ]
        ))->render();

        $this->assertStringContainsString('email-assets/blockrium-mark.png', $html);
        $this->assertStringContainsString('Your KYC is ready', $html);
        $this->assertStringContainsString('ACCOUNT SERVICES', $html);
        $this->assertStringContainsString('alt="Blockrium"', $html);
        $this->assertStringContainsString('https://blockrium.com', $html);
        $this->assertStringContainsString('play.google.com/store/apps/details?id=com.blockrium', $html);
        $this->assertStringContainsString('https://t.me/blockrium', $html);
        $this->assertStringContainsString('https://instagram.com/blockrium', $html);
        $this->assertStringContainsString('support@blockrium.com', $html);
        $this->assertStringContainsString('Unsubscribe from these email updates', $html);
        $this->assertStringContainsString('A factual account update.', $html);

        $text = view('emails.blockrium-account-update-text', array_merge(
            AdminEmailCampaignController::brandTemplateData(),
            [
                'name' => 'Test member',
                'subject' => 'Your KYC is ready',
                'body' => 'A factual account update.',
                'actionUrl' => 'https://blockrium.com/account',
                'unsubscribeUrl' => 'https://blockrium.com/email/bulk-opt-out/1?signature=test',
                'isTest' => false,
            ]
        ))->render();
        $this->assertStringContainsString('Open Blockrium: https://blockrium.com/account', $text);
        $this->assertStringContainsString('Unsubscribe from these email updates:', $text);
    }

    public function test_email_template_embeds_the_local_brand_mark_inline_when_mail_message_is_available(): void
    {
        $message = \Mockery::mock(\Illuminate\Mail\Message::class);
        $message->shouldReceive('embed')
            ->once()
            ->with(base_path('email-assets/blockrium-mark.png'))
            ->andReturn('cid:blockrium-mark');

        $this->assertFileExists(base_path('email-assets/blockrium-mark.png'));
        $html = view('emails.blockrium-account-update', array_merge(
            AdminEmailCampaignController::brandTemplateData(),
            [
                'message' => $message,
                'name' => 'Test member',
                'subject' => 'Your account update',
                'body' => 'A factual account update.',
                'actionUrl' => 'https://blockrium.com/account',
                'isTest' => true,
            ]
        ))->render();

        $this->assertStringContainsString('src="cid:blockrium-mark"', $html);
        $this->assertStringNotContainsString('email-assets/blockrium-mark.png"', $html);
        $this->assertStringNotContainsString('>B</td>', $html);
    }

    public function test_account_update_email_adds_rfc_one_click_unsubscribe_headers(): void
    {
        $url = 'https://blockrium.com/email/bulk-opt-out/42?expires=1999999999&signature=abc123';
        $swiftMessage = new \Swift_Message();
        $message = new \Illuminate\Mail\Message($swiftMessage);

        AdminEmailCampaignController::addUnsubscribeHeaders($message, $url);

        $this->assertSame('<' . $url . '>', $swiftMessage->getHeaders()->get('List-Unsubscribe')->getFieldBody());
        $this->assertSame('List-Unsubscribe=One-Click', $swiftMessage->getHeaders()->get('List-Unsubscribe-Post')->getFieldBody());
    }

    public function test_rfc_one_click_unsubscribe_persists_suppression_and_returns_success(): void
    {
        $controller = new AdminEmailCampaignController(new EngagementAdminService());
        $request = Request::create('/email/bulk-opt-out/42', 'POST', [
            'List-Unsubscribe' => 'One-Click',
        ]);

        $response = $controller->optOut($request, 42);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('one_click', DB::table('admin_email_preferences')->where('user_id', 42)->value('opt_out_source'));
        $this->assertNotNull(DB::table('admin_email_preferences')->where('user_id', 42)->value('bulk_email_opted_out_at'));
    }

    public function test_rfc_one_click_unsubscribe_rejects_an_invalid_post_marker(): void
    {
        $controller = new AdminEmailCampaignController(new EngagementAdminService());
        $request = Request::create('/email/bulk-opt-out/42', 'POST', [
            'List-Unsubscribe' => 'not-one-click',
        ]);

        try {
            $controller->optOut($request, 42);
            $this->fail('Invalid one-click unsubscribe marker should be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(400, $exception->getStatusCode());
        }
        $this->assertSame(0, DB::table('admin_email_preferences')->count());
    }

    public function test_signed_one_click_unsubscribe_route_records_suppression(): void
    {
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'email.bulk-opt-out',
            now()->addHour(),
            ['userId' => 42]
        );

        $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk();
        $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk();

        $this->assertSame('one_click', DB::table('admin_email_preferences')->where('user_id', 42)->value('opt_out_source'));
        $this->assertSame(1, DB::table('admin_email_preferences')->where('user_id', 42)->count());
    }

    public function test_unsigned_one_click_unsubscribe_request_cannot_create_suppression(): void
    {
        $this->post('/email/bulk-opt-out/42?signature=invalid', [
            'List-Unsubscribe' => 'One-Click',
        ])->assertForbidden();

        $this->assertSame(0, DB::table('admin_email_preferences')->count());
    }

    public function test_email_brand_mark_is_present_as_a_valid_png_at_the_public_asset_path(): void
    {
        $logoPath = base_path('email-assets/blockrium-mark.png');

        $this->assertFileExists($logoPath);
        $image = getimagesize($logoPath);
        $this->assertIsArray($image);
        $this->assertSame(IMAGETYPE_PNG, $image[2]);
    }

    public function test_campaign_dashboard_distinguishes_smtp_acceptance_from_inbox_delivery(): void
    {
        $index = \Illuminate\Support\Facades\File::get(resource_path('views/admin/email-campaigns/index.blade.php'));
        $details = \Illuminate\Support\Facades\File::get(resource_path('views/admin/email-campaigns/show.blade.php'));

        $this->assertStringContainsString('<th>Accepted</th>', $index);
        $this->assertStringContainsString('Accepted by mail server', $details);
        $this->assertStringContainsString('does not confirm inbox delivery', $details);
        $this->assertStringContainsString("\$recipient->status === 'sent' ? 'Accepted by SMTP'", $details);
    }

    public function test_audience_rules_keep_pending_kyc_separate_from_all_active(): void
    {
        $audience = new \App\Services\AdminEmailCampaignAudience();
        $activeUser = (object) ['is_active' => 1, 'email' => 'member@example.test'];
        $dormantUser = (object) [
            'is_active' => 1,
            'email' => 'dormant@example.test',
            'last_app_event_at' => now()->subYears(2),
        ];
        $disabledUser = (object) ['is_active' => 0, 'email' => 'disabled@example.test'];
        $optedOut = (object) ['bulk_email_opted_out_at' => now()];
        $approvedMembership = (object) ['kyc_status' => 'approved', 'suspended_at' => null];
        $pendingMembership = (object) ['kyc_status' => 'started', 'suspended_at' => null];

        $this->assertTrue($audience->isEligible('all_active', $activeUser, null, $approvedMembership));
        $this->assertTrue($audience->isEligible('all_active', $dormantUser, null, $approvedMembership));
        $this->assertFalse($audience->isEligible('all_active', $disabledUser, null, null));
        $this->assertFalse($audience->isEligible('pending_kyc', $activeUser, null, $approvedMembership));
        $this->assertTrue($audience->isEligible('pending_kyc', $activeUser, null, $pendingMembership));
        $this->assertFalse($audience->isEligible('all_active', $activeUser, $optedOut, null));
        $page = \Illuminate\Support\Facades\File::get(resource_path('views/admin/email-campaigns/index.blade.php'));
        $this->assertStringContainsString('All enabled accounts (including app-inactive users)', $page);
    }

    public function test_batch_dispatch_honors_campaign_batch_limit_and_daily_cap(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        DB::table('admin_email_campaigns')->where('id', 1)->update([
            'status' => 'scheduled', 'batch_size' => 2, 'daily_cap' => 3,
            'repeat_interval_hours' => 12, 'next_batch_at' => now()->subMinute(),
        ]);
        for ($i = 1; $i <= 4; $i++) {
            DB::table('admin_email_campaign_recipients')->insert([
                'campaign_id' => 1, 'user_id' => $i, 'email' => 'user'.$i.'@example.test',
                'status' => 'queued', 'tracking_token' => str_repeat((string) $i, 64),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $batcher = new \App\Services\AdminEmailCampaignBatchService();
        $this->assertSame(2, $batcher->dispatchNextBatch(1));
        $this->assertSame(2, DB::table('admin_email_campaign_recipients')->where('campaign_id', 1)->whereNotNull('dispatched_at')->count());
        $campaign = DB::table('admin_email_campaigns')->where('id', 1)->first();
        $this->assertSame('scheduled', $campaign->status);
        $minutesUntilNextBatch = now()->diffInMinutes(\Illuminate\Support\Carbon::parse($campaign->next_batch_at));
        $this->assertGreaterThanOrEqual(719, $minutesUntilNextBatch);
        $this->assertLessThanOrEqual(720, $minutesUntilNextBatch);

        DB::table('admin_email_campaign_recipients')->where('campaign_id', 1)->whereNotNull('dispatched_at')->update(['status' => 'sent']);
        DB::table('admin_email_campaigns')->where('id', 1)->update(['status' => 'scheduled', 'next_batch_at' => now()->addMinute()]);
        \Illuminate\Support\Carbon::setTestNow(now()->addMinutes(2));
        $this->assertSame(1, $batcher->dispatchNextBatch(1));
        $this->assertSame(3, DB::table('admin_email_campaign_recipients')->where('campaign_id', 1)->whereNotNull('dispatched_at')->count());
        DB::table('admin_email_campaigns')->where('id', 1)->update(['status' => 'scheduled', 'next_batch_at' => now()->subMinute()]);
        $this->assertSame(0, $batcher->dispatchNextBatch(1));
        $expectedNextDay = now()->startOfDay()->addDay()->toDateString();
        $this->assertSame($expectedNextDay, substr(DB::table('admin_email_campaigns')->where('id', 1)->value('next_batch_at'), 0, 10));
        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_tracking_pixel_and_click_record_events_and_click_redirects_to_campaign_action(): void
    {
        DB::table('admin_email_campaign_recipients')->insert([
            'campaign_id' => 1, 'user_id' => 42, 'email' => 'member@example.test',
            'status' => 'sent', 'tracking_token' => str_repeat('a', 64),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $tracking = new \App\Http\Controllers\EmailCampaignTrackingController();

        $pixel = $tracking->open(str_repeat('a', 64));
        $this->assertSame('image/gif', $pixel->headers->get('Content-Type'));
        $this->assertSame(1, DB::table('admin_email_campaign_recipients')->where('user_id', 42)->value('open_count'));

        $redirect = $tracking->click(str_repeat('a', 64));
        $this->assertSame('https://blockrium.com', $redirect->getTargetUrl());
        $this->assertSame(1, DB::table('admin_email_campaign_recipients')->where('user_id', 42)->value('click_count'));
        $this->assertNotNull(DB::table('admin_email_campaign_recipients')->where('user_id', 42)->value('first_clicked_at'));
    }

    public function test_queue_preparation_seeds_unique_recipients_off_the_admin_request(): void
    {
        DB::table('admin_email_campaigns')->where('id', 1)->update([
            'audience' => 'all_active', 'status' => 'queueing',
        ]);
        DB::table('res_users')->insert([
            ['id' => 101, 'email' => 'one@example.test', 'name' => 'One', 'username' => 'one', 'is_active' => 1],
            ['id' => 102, 'email' => 'two@example.test', 'name' => 'Two', 'username' => 'two', 'is_active' => 1],
        ]);
        DB::table('admin_email_campaign_recipients')->insert([
            'campaign_id' => 1, 'user_id' => 101, 'email' => 'one@example.test',
            'status' => 'queued', 'tracking_token' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        (new \App\Jobs\QueueAdminEmailCampaignJob(1))->handle();

        $recipients = DB::table('admin_email_campaign_recipients')->where('campaign_id', 1)->orderBy('user_id')->get();
        $this->assertCount(2, $recipients);
        $this->assertNotEmpty($recipients[0]->tracking_token);
        $this->assertNotSame($recipients[0]->tracking_token, $recipients[1]->tracking_token);
        $this->assertSame('scheduled', DB::table('admin_email_campaigns')->where('id', 1)->value('status'));
        $this->assertSame(2, (int) DB::table('admin_email_campaigns')->where('id', 1)->value('recipient_count'));

        $originalTokens = $recipients->pluck('tracking_token', 'user_id')->all();
        DB::table('res_users')->insert([
            'id' => 103, 'email' => 'three@example.test', 'name' => 'Three', 'username' => 'three', 'is_active' => 1,
        ]);
        DB::table('admin_email_campaigns')->where('id', 1)->update(['status' => 'queueing']);

        (new \App\Jobs\QueueAdminEmailCampaignJob(1))->handle();

        $recipients = DB::table('admin_email_campaign_recipients')->where('campaign_id', 1)->orderBy('user_id')->get();
        $this->assertCount(3, $recipients);
        foreach ($recipients as $recipient) {
            $this->assertSame($originalTokens[$recipient->user_id] ?? $recipient->tracking_token, $recipient->tracking_token);
        }
        $this->assertSame(3, (int) DB::table('admin_email_campaigns')->where('id', 1)->value('recipient_count'));
    }
}
