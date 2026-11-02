<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnhanceReferralQualityMilestones extends Migration
{
    public function up()
    {
        if (Schema::hasTable('referral_milestones')) {
            Schema::table('referral_milestones', function (Blueprint $table) {
                if (!Schema::hasColumn('referral_milestones', 'reward_category')) {
                    $table->string('reward_category', 40)->default('active_referral')->after('description')->index();
                }
                if (!Schema::hasColumn('referral_milestones', 'required_verified_referrals')) {
                    $table->unsignedInteger('required_verified_referrals')->default(0)->after('required_active_referrals');
                }
                if (!Schema::hasColumn('referral_milestones', 'required_task_referrals')) {
                    $table->unsignedInteger('required_task_referrals')->default(0)->after('required_verified_referrals');
                }
                if (!Schema::hasColumn('referral_milestones', 'required_streak_referrals')) {
                    $table->unsignedInteger('required_streak_referrals')->default(0)->after('required_task_referrals');
                }
                if (!Schema::hasColumn('referral_milestones', 'required_quality_score')) {
                    $table->unsignedInteger('required_quality_score')->default(0)->after('required_streak_referrals');
                }
            });
        }

        if (Schema::hasTable('referral_milestones')) {
            $now = now();
            $milestones = [
                [
                    'key' => 'first-quality-referral',
                    'title' => 'First Real Miner',
                    'description' => 'Invite one referral who starts mining and becomes active.',
                    'reward_category' => 'quality',
                    'required_active_referrals' => 1,
                    'required_verified_referrals' => 0,
                    'required_task_referrals' => 0,
                    'required_streak_referrals' => 0,
                    'required_quality_score' => 20,
                    'reward_amount' => 1.5,
                    'reward_xp' => 35,
                    'reward_label' => 'BRC',
                    'sort_order' => 4,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'key' => 'task-active-network',
                    'title' => 'Task Active Network',
                    'description' => 'Help three referrals complete at least one task.',
                    'reward_category' => 'quality',
                    'required_active_referrals' => 3,
                    'required_verified_referrals' => 0,
                    'required_task_referrals' => 3,
                    'required_streak_referrals' => 0,
                    'required_quality_score' => 45,
                    'reward_amount' => 4,
                    'reward_xp' => 70,
                    'reward_label' => 'BRC',
                    'sort_order' => 5,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'key' => 'streak-network-builder',
                    'title' => 'Streak Network Builder',
                    'description' => 'Build a network where five referrals mine and at least three claim streak rewards.',
                    'reward_category' => 'quality',
                    'required_active_referrals' => 5,
                    'required_verified_referrals' => 0,
                    'required_task_referrals' => 0,
                    'required_streak_referrals' => 3,
                    'required_quality_score' => 65,
                    'reward_amount' => 7.5,
                    'reward_xp' => 110,
                    'reward_label' => 'BRC',
                    'sort_order' => 6,
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ];

            foreach ($milestones as $milestone) {
                DB::table('referral_milestones')->updateOrInsert(
                    ['key' => $milestone['key']],
                    $milestone
                );
            }
        }
    }

    public function down()
    {
        if (!Schema::hasTable('referral_milestones')) {
            return;
        }

        Schema::table('referral_milestones', function (Blueprint $table) {
            foreach ([
                'reward_category',
                'required_verified_referrals',
                'required_task_referrals',
                'required_streak_referrals',
                'required_quality_score',
            ] as $column) {
                if (Schema::hasColumn('referral_milestones', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
