<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOnboardingQuestClaimsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('onboarding_quest_claims')) {
            Schema::table('onboarding_quest_claims', function (Blueprint $table) {
                if (!Schema::hasColumn('onboarding_quest_claims', 'reward_amount')) {
                    $table->decimal('reward_amount', 12, 4)->default(0);
                }
                if (!Schema::hasColumn('onboarding_quest_claims', 'claimed_at')) {
                    $table->timestamp('claimed_at')->nullable();
                }
                if (!Schema::hasColumn('onboarding_quest_claims', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('onboarding_quest_claims', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
            return;
        }

        Schema::create('onboarding_quest_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->decimal('reward_amount', 12, 4)->default(0);
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->unique('user_id', 'onboarding_quest_claims_user_unique');
            $table->index('claimed_at', 'onboarding_quest_claims_claimed_at_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('onboarding_quest_claims');
    }
}
