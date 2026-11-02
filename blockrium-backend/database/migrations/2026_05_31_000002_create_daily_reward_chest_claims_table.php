<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDailyRewardChestClaimsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('daily_reward_chest_claims')) {
            Schema::table('daily_reward_chest_claims', function (Blueprint $table) {
                if (!Schema::hasColumn('daily_reward_chest_claims', 'streak_day')) {
                    $table->unsignedTinyInteger('streak_day')->default(7);
                }
                if (!Schema::hasColumn('daily_reward_chest_claims', 'reward_amount')) {
                    $table->decimal('reward_amount', 12, 4)->default(0);
                }
                if (!Schema::hasColumn('daily_reward_chest_claims', 'claim_date')) {
                    $table->date('claim_date')->nullable();
                }
                if (!Schema::hasColumn('daily_reward_chest_claims', 'claimed_at')) {
                    $table->timestamp('claimed_at')->nullable();
                }
                if (!Schema::hasColumn('daily_reward_chest_claims', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('daily_reward_chest_claims', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
            return;
        }

        Schema::create('daily_reward_chest_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('streak_day')->default(7);
            $table->decimal('reward_amount', 12, 4)->default(0);
            $table->date('claim_date');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'claim_date'], 'daily_reward_chest_user_date_unique');
            $table->index(['user_id', 'streak_day'], 'daily_reward_chest_user_streak_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('daily_reward_chest_claims');
    }
}
