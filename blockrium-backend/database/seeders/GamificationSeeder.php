<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GamificationSeeder extends Seeder
{
    public function run()
    {
        if (!Schema::hasTable('badges') || !Schema::hasTable('reward_seasons')) {
            return;
        }

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
                array_merge($badge, ['is_active' => 1, 'updated_at' => now(), 'created_at' => now()])
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
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
