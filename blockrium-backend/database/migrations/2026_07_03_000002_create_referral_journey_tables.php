<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateReferralJourneyTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('referral_milestones')) {
            Schema::create('referral_milestones', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('key', 100)->unique();
                $table->string('title', 160);
                $table->text('description')->nullable();
                $table->unsignedInteger('required_active_referrals')->default(1);
                $table->decimal('reward_amount', 12, 4)->default(0);
                $table->unsignedInteger('reward_xp')->default(0);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'sort_order'], 'ref_milestones_active_idx');
            });
        }

        if (!Schema::hasTable('referral_milestone_claims')) {
            Schema::create('referral_milestone_claims', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('referral_milestone_id');
                $table->string('milestone_key', 100);
                $table->decimal('reward_amount', 12, 4)->default(0);
                $table->timestamp('claimed_at');
                $table->timestamps();

                $table->unique(['user_id', 'referral_milestone_id'], 'ref_milestone_claim_unique');
                $table->index('milestone_key', 'ref_milestone_claim_key_idx');
                $table->index('claimed_at', 'ref_milestone_claimed_idx');
            });
        }

        $milestones = [
            ['key' => 'first-active-referral', 'title' => 'First Active Referral', 'description' => 'Help one direct referral participate within the last 24 hours.', 'required_active_referrals' => 1, 'reward_amount' => 1, 'reward_xp' => 25, 'sort_order' => 1],
            ['key' => 'three-active-referrals', 'title' => 'Growing Network', 'description' => 'Have three active direct referrals within the last 24 hours.', 'required_active_referrals' => 3, 'reward_amount' => 3, 'reward_xp' => 50, 'sort_order' => 2],
            ['key' => 'five-active-referrals', 'title' => 'Strong Network', 'description' => 'Have five active direct referrals within the last 24 hours.', 'required_active_referrals' => 5, 'reward_amount' => 5, 'reward_xp' => 75, 'sort_order' => 3],
        ];

        foreach ($milestones as $milestone) {
            DB::table('referral_milestones')->updateOrInsert(
                ['key' => $milestone['key']],
                array_merge($milestone, ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    public function down()
    {
        Schema::dropIfExists('referral_milestone_claims');
        Schema::dropIfExists('referral_milestones');
    }
}
