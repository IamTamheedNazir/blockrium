<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateReferralSettingsAndMissionsTables extends Migration
{
    public function up()
    {
        if (Schema::hasTable('referrals')) {
            try {
                DB::statement('ALTER TABLE referrals DROP FOREIGN KEY referrals_referred_by_foreign');
            } catch (\Throwable $error) {
            }

            try {
                DB::statement('ALTER TABLE referrals MODIFY referred_by VARCHAR(120) NULL');
            } catch (\Throwable $error) {
            }

            try {
                DB::statement('ALTER TABLE referrals MODIFY rewards DECIMAL(12,4) DEFAULT 0');
            } catch (\Throwable $error) {
            }
        }

        if (!Schema::hasTable('referral_settings')) {
            Schema::create('referral_settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('setting_key', 100)->unique();
                $table->text('setting_value')->nullable();
                $table->string('label', 160);
                $table->string('input_type', 40)->default('number');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });

            DB::table('referral_settings')->insert([
                [
                    'setting_key' => 'referrer_signup_reward',
                    'setting_value' => '5',
                    'label' => 'Referrer signup reward',
                    'input_type' => 'number',
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'setting_key' => 'new_user_signup_reward',
                    'setting_value' => '0',
                    'label' => 'New user signup reward',
                    'input_type' => 'number',
                    'sort_order' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'setting_key' => 'referral_ping_daily_limit',
                    'setting_value' => '3',
                    'label' => 'Pings per referral per day',
                    'input_type' => 'number',
                    'sort_order' => 3,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'setting_key' => 'team_boost_tier_1_count',
                    'setting_value' => '1',
                    'label' => 'Team boost tier 1 active referrals',
                    'input_type' => 'number',
                    'sort_order' => 4,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'setting_key' => 'team_boost_tier_1_percent',
                    'setting_value' => '2',
                    'label' => 'Team boost tier 1 percent',
                    'input_type' => 'number',
                    'sort_order' => 5,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'setting_key' => 'team_boost_tier_2_count',
                    'setting_value' => '5',
                    'label' => 'Team boost tier 2 active referrals',
                    'input_type' => 'number',
                    'sort_order' => 6,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'setting_key' => 'team_boost_tier_2_percent',
                    'setting_value' => '5',
                    'label' => 'Team boost tier 2 percent',
                    'input_type' => 'number',
                    'sort_order' => 7,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'setting_key' => 'team_boost_tier_3_count',
                    'setting_value' => '10',
                    'label' => 'Team boost tier 3 active referrals',
                    'input_type' => 'number',
                    'sort_order' => 8,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'setting_key' => 'team_boost_tier_3_percent',
                    'setting_value' => '10',
                    'label' => 'Team boost tier 3 percent',
                    'input_type' => 'number',
                    'sort_order' => 9,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'setting_key' => 'referral_code_prefix',
                    'setting_value' => 'BRC',
                    'label' => 'Referral code prefix',
                    'input_type' => 'text',
                    'sort_order' => 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        if (!Schema::hasTable('referral_missions')) {
            Schema::create('referral_missions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('title', 160);
                $table->string('slug', 180)->unique();
                $table->text('description')->nullable();
                $table->string('metric', 80);
                $table->unsignedInteger('target_count')->default(1);
                $table->decimal('reward_amount', 12, 4)->default(0);
                $table->string('frequency', 20)->default('once');
                $table->unsignedTinyInteger('status')->default(1);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });

            DB::table('referral_missions')->insert([
                [
                    'title' => 'Invite 1 user',
                    'slug' => 'invite-1-user',
                    'description' => 'Bring your first direct referral into Blockrium.',
                    'metric' => 'direct_referrals',
                    'target_count' => 1,
                    'reward_amount' => 5,
                    'frequency' => 'once',
                    'status' => 1,
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'title' => '3 referrals mine today',
                    'slug' => '3-referrals-mine-today',
                    'description' => 'Keep three direct referrals active in the last 24 hours.',
                    'metric' => 'active_referrals_today',
                    'target_count' => 3,
                    'reward_amount' => 10,
                    'frequency' => 'daily',
                    'status' => 1,
                    'sort_order' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'title' => 'Help 2 inactive referrals',
                    'slug' => 'help-2-inactive-referrals',
                    'description' => 'Ping two inactive referrals to bring them back.',
                    'metric' => 'pinged_today',
                    'target_count' => 2,
                    'reward_amount' => 3,
                    'frequency' => 'daily',
                    'status' => 1,
                    'sort_order' => 3,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        if (!Schema::hasTable('user_referral_mission_claims')) {
            Schema::create('user_referral_mission_claims', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('referral_mission_id');
                $table->decimal('reward_amount', 12, 4)->default(0);
                $table->date('claim_date')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'referral_mission_id', 'claim_date'], 'ref_mission_claim_lookup');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('user_referral_mission_claims');
        Schema::dropIfExists('referral_missions');
        Schema::dropIfExists('referral_settings');
    }
}
