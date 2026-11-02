<?php

namespace Tests\Unit;

use App\Services\PartnerPromoCodeService;
use App\Services\WalletRewardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PartnerPromoCodeRedemptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => true,
            'app.key' => 'blockrium-promo-test-key',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        require_once base_path('database/migrations/2026_09_25_000002_create_partner_campaign_tables.php');
        require_once base_path('database/migrations/2026_10_14_000001_create_partner_promo_code_tables.php');
        (new \CreatePartnerCampaignTables())->up();
        (new \CreatePartnerPromoCodeTables())->up();

        Schema::create('res_users', function ($table) {
            $table->increments('id');
        });
    }

    public function test_repeating_the_same_redemption_request_credits_the_wallet_once(): void
    {
        $now = now();
        DB::table('partners')->insert([
            'id' => 1,
            'name' => 'Test Partner',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('partner_campaigns')->insert([
            'id' => 11,
            'partner_id' => 1,
            'public_id' => 'a514e8ec-8210-4d7a-9334-52ef19720a19',
            'slug' => 'test-redeem',
            'title' => 'Test reward',
            'destination_url' => 'https://example.test',
            'status' => 'active',
            'reward_mode' => 'click_claim',
            'reward_amount' => 5,
            'reward_label' => 'BRC',
            'budget_amount' => 50,
            'starts_at' => $now->copy()->subDay(),
            'ends_at' => $now->copy()->addDay(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('partner_promo_batches')->insert([
            'id' => 21,
            'campaign_id' => 11,
            'batch_reference' => 'PC-TEST-0001',
            'status' => 'active',
            'code_count' => 1,
            'generated_count' => 1,
            'total_reward_amount' => 5,
            'reward_label' => 'BRC',
            'expires_at' => $now->copy()->addDay(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $code = 'BRC-ABCD-EFGH-JKLM';
        DB::table('partner_promo_codes')->insert([
            'id' => 31,
            'batch_id' => 21,
            'campaign_id' => 11,
            'code_hash' => hash_hmac('sha256', $code, config('app.key')),
            'code_suffix' => 'JKLM',
            'reward_amount' => 5,
            'reward_label' => 'BRC',
            'status' => 'available',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('res_users')->insert(['id' => 51]);

        $wallet = $this->createMock(WalletRewardService::class);
        $wallet->expects($this->once())
            ->method('credit')
            ->with(51, 5.0)
            ->willReturn((object) ['id' => 91]);

        $service = new PartnerPromoCodeService($wallet);
        $first = $service->redeem($code, 51, 'request-abc');
        $retry = $service->redeem($code, 51, 'request-abc');

        $this->assertTrue($first['success']);
        $this->assertTrue($retry['success']);
        $this->assertTrue($retry['already_claimed']);
        $this->assertSame($first['redemption_reference'], $retry['redemption_reference']);
        $this->assertSame(1, DB::table('partner_campaign_claims')->count());
        $this->assertSame(1, DB::table('partner_promo_redemptions')->count());
        $this->assertSame('claimed', DB::table('partner_promo_codes')->where('id', 31)->value('status'));
        $this->assertSame(1, (int) DB::table('partner_promo_batches')->where('id', 21)->value('claimed_count'));
    }
}
