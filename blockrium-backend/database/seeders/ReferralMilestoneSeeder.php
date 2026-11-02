<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReferralMilestoneSeeder extends Seeder
{
    public function run()
    {
        if (!Schema::hasTable('referral_milestones')) {
            return;
        }

        $milestones = [
            ['key' => 'first-active-referral', 'title' => 'First Active Referral', 'description' => 'Help one direct referral participate within the last 24 hours.', 'required_active_referrals' => 1, 'reward_amount' => 1, 'reward_xp' => 25, 'sort_order' => 1],
            ['key' => 'three-active-referrals', 'title' => 'Growing Network', 'description' => 'Have three active direct referrals within the last 24 hours.', 'required_active_referrals' => 3, 'reward_amount' => 3, 'reward_xp' => 50, 'sort_order' => 2],
            ['key' => 'five-active-referrals', 'title' => 'Strong Network', 'description' => 'Have five active direct referrals within the last 24 hours.', 'required_active_referrals' => 5, 'reward_amount' => 5, 'reward_xp' => 75, 'sort_order' => 3],
        ];

        foreach ($milestones as $milestone) {
            DB::table('referral_milestones')->updateOrInsert(
                ['key' => $milestone['key']],
                array_merge($milestone, ['is_active' => 1, 'updated_at' => now(), 'created_at' => now()])
            );
        }
    }
}
