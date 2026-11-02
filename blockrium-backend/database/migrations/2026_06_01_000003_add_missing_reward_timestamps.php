<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingRewardTimestamps extends Migration
{
    public function up()
    {
        $tables = [
            'res_users',
            'referrals',
            'tblreward',
            'tbluserwallet',
            'tblattendance',
            'tbl_user_mining',
            'button_clicks',
            'onboarding_quest_claims',
            'daily_reward_chest_claims',
            'task_claimeds',
            'user_referral_mission_claims',
            'referral_pings',
            'user_boosts',
            'check_fprint',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }

                if (!Schema::hasColumn($tableName, 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }
    }

    public function down()
    {
        // Intentionally non-destructive. Legacy reward tables may already depend on these columns.
    }
}
