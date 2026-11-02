<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSquadMiningTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('squads')) {
            Schema::create('squads', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->string('invite_code', 40)->unique();
                $table->unsignedBigInteger('owner_user_id');
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('max_members')->default(20);
                $table->unsignedSmallInteger('weekly_target_points')->default(500);
                $table->unsignedInteger('weekly_chest_reward_xp')->default(50);
                $table->decimal('weekly_chest_reward_brc', 16, 4)->nullable();
                $table->boolean('is_public')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('owner_user_id', 'squads_owner_idx');
                $table->index(['is_active', 'is_public'], 'squads_public_idx');
            });
        }

        if (!Schema::hasTable('squad_members')) {
            Schema::create('squad_members', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('squad_id');
                $table->unsignedBigInteger('user_id');
                $table->string('role', 30)->default('member');
                $table->string('status', 30)->default('active');
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('left_at')->nullable();
                $table->timestamps();

                $table->unique(['squad_id', 'user_id'], 'squad_members_squad_user_unique');
                $table->index('user_id', 'squad_members_user_idx');
                $table->index(['squad_id', 'status'], 'squad_members_status_idx');
            });
        }

        if (!Schema::hasTable('squad_invites')) {
            Schema::create('squad_invites', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('squad_id');
                $table->unsignedBigInteger('inviter_user_id');
                $table->string('invite_code', 40)->unique();
                $table->unsignedInteger('uses_count')->default(0);
                $table->unsignedInteger('max_uses')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('squad_id', 'squad_invites_squad_idx');
                $table->index(['is_active', 'expires_at'], 'squad_invites_active_idx');
            });
        }

        if (!Schema::hasTable('squad_weekly_chests')) {
            Schema::create('squad_weekly_chests', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('squad_id');
                $table->date('week_start');
                $table->date('week_end');
                $table->string('status', 30)->default('claimed');
                $table->unsignedInteger('total_points')->default(0);
                $table->unsignedInteger('reward_xp')->default(0);
                $table->decimal('reward_brc', 16, 4)->nullable();
                $table->unsignedBigInteger('claimed_by')->nullable();
                $table->timestamp('claimed_at')->nullable();
                $table->timestamps();

                $table->unique(['squad_id', 'claimed_by', 'week_start'], 'squad_weekly_chests_user_unique');
                $table->index(['squad_id', 'week_start'], 'squad_weekly_chests_squad_week_idx');
                $table->index('claimed_by', 'squad_weekly_chests_claimed_by_idx');
            });
        }

        if (Schema::hasTable('badges')) {
            DB::table('badges')->updateOrInsert(
                ['key' => 'squad_builder'],
                [
                    'name' => 'Squad Builder',
                    'description' => 'Created or joined a Blockrium community squad.',
                    'icon' => 'users-round',
                    'category' => 'squad',
                    'is_active' => 1,
                    'sort_order' => 7,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down()
    {
        Schema::dropIfExists('squad_weekly_chests');
        Schema::dropIfExists('squad_invites');
        Schema::dropIfExists('squad_members');
        Schema::dropIfExists('squads');
    }
}
