<?php

namespace Tests\Unit;

use App\Services\PartnerCampaignService;
use App\Services\WalletRewardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PartnerCampaignAdminMetricsTest extends TestCase
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

        require_once base_path('database/migrations/2026_09_25_000002_create_partner_campaign_tables.php');
        require_once base_path('database/migrations/2026_10_01_000001_create_partner_campaign_task_tables.php');
        (new \CreatePartnerCampaignTables())->up();
        (new \CreatePartnerCampaignTaskTables())->up();
    }

    public function test_campaign_list_metrics_are_aggregated_without_changing_the_displayed_totals(): void
    {
        $now = now();
        DB::table('partners')->insert(['id' => 1, 'name' => 'Partner', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('partner_campaigns')->insert([
            ['id' => 10, 'partner_id' => 1, 'public_id' => 'a514e8ec-8210-4d7a-9334-52ef19720a10', 'slug' => 'campaign-one', 'title' => 'Campaign One', 'destination_url' => 'https://example.test', 'status' => 'active', 'reward_mode' => 'click_claim', 'reward_amount' => 2.5, 'reward_label' => 'BRC', 'budget_amount' => 20, 'starts_at' => $now, 'ends_at' => $now->copy()->addDay(), 'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'partner_id' => 1, 'public_id' => 'a514e8ec-8210-4d7a-9334-52ef19720a11', 'slug' => 'campaign-two', 'title' => 'Campaign Two', 'destination_url' => 'https://example.test', 'status' => 'paused', 'reward_mode' => 'click_claim', 'reward_amount' => 1, 'reward_label' => 'BRC', 'budget_amount' => null, 'starts_at' => $now, 'ends_at' => $now->copy()->addDay(), 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('partner_campaign_clicks')->insert([
            ['campaign_id' => 10, 'user_id' => 7, 'visitor_hash' => 'visitor-a', 'source' => 'app', 'created_at' => $now, 'updated_at' => $now],
            ['campaign_id' => 10, 'user_id' => 7, 'visitor_hash' => 'visitor-b', 'source' => 'app', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('partner_campaign_claims')->insert([
            'campaign_id' => 10, 'user_id' => 7, 'reward_amount' => 2.5, 'reward_label' => 'BRC', 'claimed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('partner_campaign_completions')->insert([
            ['campaign_id' => 10, 'user_id' => 7, 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now],
            ['campaign_id' => 10, 'user_id' => 8, 'status' => 'approved', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('partner_campaign_tasks')->insert([
            'id' => 100, 'campaign_id' => 10, 'task_key' => 'follow', 'task_type' => 'telegram', 'title' => 'Follow channel', 'completion_mode' => 'admin_approval', 'reward_amount' => 3, 'reward_label' => 'BRC', 'status' => 'active', 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('partner_campaign_task_clicks')->insert([
            ['campaign_id' => 10, 'task_id' => 100, 'user_id' => 7, 'visitor_hash' => 'task-visitor', 'source' => 'app', 'created_at' => $now, 'updated_at' => $now],
            ['campaign_id' => 10, 'task_id' => 100, 'user_id' => 8, 'visitor_hash' => null, 'source' => 'app', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('partner_campaign_task_completions')->insert([
            'campaign_id' => 10, 'task_id' => 100, 'user_id' => 7, 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('partner_campaign_task_claims')->insert([
            'campaign_id' => 10, 'task_id' => 100, 'user_id' => 8, 'reward_amount' => 3, 'reward_label' => 'BRC', 'claimed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $wallet = $this->createMock(WalletRewardService::class);
        $service = new PartnerCampaignService($wallet);
        $campaigns = DB::table('partner_campaigns')->get();
        $singleCampaign = DB::table('partner_campaigns')->where('id', 10)->get();
        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $service->metricsForCampaigns($singleCampaign);
        $singleCampaignQueryCount = $queryCount;
        $queryCount = 0;

        $metrics = $service->metricsForCampaigns($campaigns);
        $multipleCampaignQueryCount = $queryCount;

        $this->assertSame(4, $metrics[10]['clicks']);
        $this->assertSame(3, $metrics[10]['unique_users']);
        $this->assertSame(3, $metrics[10]['unique_visitors']);
        $this->assertSame(2, $metrics[10]['claims']);
        $this->assertEquals(5.5, $metrics[10]['brc_distributed']);
        $this->assertEquals(14.5, $metrics[10]['budget_remaining']);
        $this->assertSame(2, $metrics[10]['pending_completions']);
        $this->assertSame(1, $metrics[10]['approved_completions']);
        $this->assertSame(1, count($metrics[10]['tasks']));
        $this->assertSame(2, $metrics[10]['tasks'][0]['clicks']);
        $this->assertSame(1, $metrics[10]['tasks'][0]['pending_completions']);
        $this->assertSame(1, $metrics[10]['tasks'][0]['claims']);
        $this->assertSame(0, $metrics[11]['clicks']);
        $this->assertNull($metrics[11]['budget_remaining']);
        $this->assertSame($singleCampaignQueryCount, $multipleCampaignQueryCount, 'Metrics query count should not grow with the campaign batch size.');
        $this->assertSame([], $service->metricsForCampaigns(collect()));
    }

    public function test_telegram_task_rewards_once_after_click_without_membership_verification(): void
    {
        $now = now();
        DB::table('partners')->insert([
            'id' => 1,
            'name' => 'Partner',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('partner_campaigns')->insert([
            'id' => 12,
            'partner_id' => 1,
            'public_id' => 'a514e8ec-8210-4d7a-9334-52ef19720a12',
            'slug' => 'telegram-reward',
            'title' => 'Telegram reward',
            'destination_url' => 'https://example.test',
            'status' => 'active',
            'reward_mode' => 'click_claim',
            'reward_amount' => 0,
            'reward_label' => 'BRC',
            'budget_amount' => 100,
            'starts_at' => $now->copy()->subMinute(),
            'ends_at' => $now->copy()->addDay(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('partner_campaign_tasks')->insert([
            'id' => 202,
            'campaign_id' => 12,
            'task_key' => 'join-telegram',
            'task_type' => 'join_telegram',
            'title' => 'Join Telegram',
            'target_url' => 'https://t.me/BlockriumOfficial',
            'completion_mode' => 'telegram_membership',
            'reward_amount' => 3,
            'reward_label' => 'BRC',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $wallet = $this->createMock(WalletRewardService::class);
        $wallet->expects($this->once())
            ->method('credit')
            ->with(99, 3.0)
            ->willReturn((object) ['id' => 501]);
        $service = new PartnerCampaignService($wallet);

        $click = $service->recordTaskClick(
            12,
            202,
            99,
            Request::create('/api/auth/partner-campaigns/12/tasks/202/click', 'POST')
        );
        $campaigns = $service->activeCampaigns(99);
        $taskPayload = $campaigns[0]['tasks'][0];
        $firstClaim = $service->claimTask(12, 202, 99);
        $replay = $service->claimTask(12, 202, 99);

        $this->assertSame('click_claim', $click['completion_mode']);
        $this->assertSame('click_claim', $taskPayload['completion_mode']);
        $this->assertTrue($taskPayload['can_claim']);
        $this->assertTrue($firstClaim['success']);
        $this->assertFalse($firstClaim['already_claimed']);
        $this->assertFalse($replay['success']);
        $this->assertTrue($replay['already_claimed']);
        $this->assertSame(1, DB::table('partner_campaign_task_claims')->count());
        $this->assertSame(0, DB::table('partner_campaign_task_completions')->count());
    }
}
