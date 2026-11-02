<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateGamificationFoundationTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('user_xp_events')) {
            Schema::create('user_xp_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->string('event_key', 80);
                $table->string('source_type', 80)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('dedupe_key', 190)->unique('xp_events_dedupe_unique');
                $table->unsignedInteger('xp_amount');
                $table->json('metadata')->nullable();
                $table->timestamp('awarded_at');
                $table->timestamps();

                $table->index('user_id', 'xp_events_user_idx');
                $table->index('event_key', 'xp_events_event_idx');
                $table->index(['source_type', 'source_id'], 'xp_events_source_idx');
                $table->index('awarded_at', 'xp_events_awarded_idx');
                $table->index(['user_id', 'event_key', 'awarded_at'], 'xp_events_daily_cap_idx');
            });
        }

        if (!Schema::hasTable('badges')) {
            Schema::create('badges', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('key', 100)->unique();
                $table->string('name', 140);
                $table->text('description')->nullable();
                $table->string('icon', 190)->nullable();
                $table->string('category', 80)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['is_active', 'sort_order'], 'badges_active_sort_idx');
            });
        }

        if (!Schema::hasTable('user_badges')) {
            Schema::create('user_badges', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('badge_id');
                $table->string('source_type', 80)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->timestamp('awarded_at');
                $table->timestamps();

                $table->unique(['user_id', 'badge_id'], 'user_badges_user_badge_unique');
                $table->index('badge_id', 'user_badges_badge_idx');
                $table->index('awarded_at', 'user_badges_awarded_idx');
            });
        }

        if (!Schema::hasTable('reward_seasons')) {
            Schema::create('reward_seasons', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('key', 100)->unique();
                $table->string('title', 160);
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['is_active', 'starts_at', 'ends_at'], 'reward_seasons_active_idx');
            });
        }

        $this->seedBadgesAndSeason();
    }

    public function down()
    {
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('user_xp_events');
        Schema::dropIfExists('reward_seasons');
    }

    private function seedBadgesAndSeason(): void
    {
        $badges = [
            ['key' => 'first_participation', 'name' => 'First Participant', 'description' => 'Started the first backend-validated participation session.', 'icon' => 'pickaxe', 'category' => 'participation', 'sort_order' => 1],
            ['key' => 'streak_starter', 'name' => 'Streak Starter', 'description' => 'Claimed a validated daily streak reward.', 'icon' => 'calendar-check', 'category' => 'streak', 'sort_order' => 2],
            ['key' => 'starter_complete', 'name' => 'Blockrium Explorer', 'description' => 'Completed the Starter Quest.', 'icon' => 'compass', 'category' => 'onboarding', 'sort_order' => 3],
            ['key' => 'network_builder', 'name' => 'Network Builder', 'description' => 'Activated a valid referral connection.', 'icon' => 'users', 'category' => 'referral', 'sort_order' => 4],
            ['key' => 'web3_learner', 'name' => 'Web3 Learner', 'description' => 'Completed a validated learning module.', 'icon' => 'book-open', 'category' => 'learning', 'sort_order' => 5],
            ['key' => 'safety_first', 'name' => 'Safety First', 'description' => 'Completed wallet safety guidance without sharing wallet secrets.', 'icon' => 'shield-check', 'category' => 'safety', 'sort_order' => 6],
        ];

        foreach ($badges as $badge) {
            DB::table('badges')->updateOrInsert(
                ['key' => $badge['key']],
                array_merge($badge, ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()])
            );
        }

        DB::table('reward_seasons')->updateOrInsert(
            ['key' => 'genesis'],
            [
                'title' => 'Genesis Season',
                'starts_at' => now()->startOfYear(),
                'ends_at' => now()->endOfYear(),
                'is_active' => 1,
                'metadata' => json_encode(['xp_has_cash_value' => false]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
