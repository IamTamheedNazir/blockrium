<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSquadRolesMissionsAnnouncements extends Migration
{
    public function up()
    {
        if (Schema::hasTable('squads')) {
            Schema::table('squads', function (Blueprint $table) {
                if (!Schema::hasColumn('squads', 'promotion_tier')) {
                    $table->string('promotion_tier', 30)->default('normal')->after('featured_until');
                }
                if (!Schema::hasColumn('squads', 'admin_note')) {
                    $table->text('admin_note')->nullable()->after('promotion_tier');
                }
            });
            if (Schema::hasColumn('squads', 'poster_template')) {
                try {
                    DB::statement("ALTER TABLE squads MODIFY poster_template VARCHAR(40) NOT NULL DEFAULT 'white'");
                } catch (\Throwable $error) {
                    // Existing code normalizes poster templates even when shared hosting blocks ALTER MODIFY.
                }
            }
        }

        if (!Schema::hasTable('squad_missions')) {
            Schema::create('squad_missions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('key', 80)->unique();
                $table->string('title', 160);
                $table->text('description')->nullable();
                $table->string('metric', 60);
                $table->unsignedInteger('target_value')->default(1);
                $table->unsignedInteger('reward_xp')->default(0);
                $table->decimal('reward_brc', 16, 4)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('squad_mission_claims')) {
            Schema::create('squad_mission_claims', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('squad_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('mission_id');
                $table->date('week_start');
                $table->unsignedInteger('progress_value')->default(0);
                $table->unsignedInteger('reward_xp')->default(0);
                $table->decimal('reward_brc', 16, 4)->nullable();
                $table->timestamp('claimed_at')->nullable();
                $table->timestamps();

                $table->unique(['squad_id', 'user_id', 'mission_id', 'week_start'], 'squad_mission_claim_unique');
                $table->index(['squad_id', 'week_start'], 'squad_mission_claim_squad_week_idx');
                $table->index('user_id', 'squad_mission_claim_user_idx');
            });
        }

        if (!Schema::hasTable('squad_invite_streak_claims')) {
            Schema::create('squad_invite_streak_claims', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('squad_id');
                $table->unsignedBigInteger('user_id');
                $table->date('streak_date');
                $table->unsignedInteger('active_invites')->default(0);
                $table->unsignedInteger('streak_days')->default(0);
                $table->unsignedInteger('reward_xp')->default(0);
                $table->decimal('reward_brc', 16, 4)->nullable();
                $table->timestamp('claimed_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'streak_date'], 'squad_invite_streak_user_date_unique');
                $table->index(['squad_id', 'streak_date'], 'squad_invite_streak_squad_date_idx');
            });
        }

        if (!Schema::hasTable('squad_announcement_templates')) {
            Schema::create('squad_announcement_templates', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('key', 80)->unique();
                $table->string('title', 160);
                $table->text('body');
                $table->string('cta_label', 80)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('squad_announcements')) {
            Schema::create('squad_announcements', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('squad_id')->nullable();
                $table->unsignedBigInteger('template_id')->nullable();
                $table->string('template_key', 80)->nullable();
                $table->string('title', 160);
                $table->text('body');
                $table->string('cta_label', 80)->nullable();
                $table->string('status', 30)->default('active');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->timestamps();

                $table->index(['squad_id', 'status'], 'squad_announcements_squad_status_idx');
                $table->index(['starts_at', 'ends_at'], 'squad_announcements_window_idx');
            });
        }

        $this->seedMissions();
        $this->seedAnnouncementTemplates();
    }

    public function down()
    {
        Schema::dropIfExists('squad_announcements');
        Schema::dropIfExists('squad_announcement_templates');
        Schema::dropIfExists('squad_invite_streak_claims');
        Schema::dropIfExists('squad_mission_claims');
        Schema::dropIfExists('squad_missions');
    }

    private function seedMissions(): void
    {
        if (!Schema::hasTable('squad_missions')) {
            return;
        }

        $missions = [
            ['key' => 'weekly_points_500', 'title' => 'Reach 500 Squad Points', 'description' => 'Work together through mining, streaks, tasks, learning, referrals, and shares.', 'metric' => 'weekly_points', 'target_value' => 500, 'reward_xp' => 60, 'sort_order' => 1],
            ['key' => 'active_members_3', 'title' => '3 Active Members', 'description' => 'Have at least 3 members contribute activity this week.', 'metric' => 'active_members', 'target_value' => 3, 'reward_xp' => 40, 'sort_order' => 2],
            ['key' => 'squad_shares_5', 'title' => 'Share Squad 5 Times', 'description' => 'Share the squad invite from supported channels this week.', 'metric' => 'shares', 'target_value' => 5, 'reward_xp' => 35, 'sort_order' => 3],
            ['key' => 'learn_lessons_5', 'title' => 'Complete 5 Learn Lessons', 'description' => 'Squad members complete 5 Learn & Earn lessons this week.', 'metric' => 'learn_lessons', 'target_value' => 5, 'reward_xp' => 50, 'sort_order' => 4],
            ['key' => 'invite_active_1', 'title' => 'Bring 1 Active Member', 'description' => 'Invite a member who joins and becomes active.', 'metric' => 'active_invites', 'target_value' => 1, 'reward_xp' => 75, 'sort_order' => 5],
        ];

        foreach ($missions as $mission) {
            DB::table('squad_missions')->updateOrInsert(
                ['key' => $mission['key']],
                array_merge($mission, [
                    'reward_brc' => null,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    private function seedAnnouncementTemplates(): void
    {
        if (!Schema::hasTable('squad_announcement_templates')) {
            return;
        }

        $templates = [
            ['key' => 'start_week', 'title' => 'New Squad Week Started', 'body' => 'A fresh squad week is live. Start mining, keep streaks active, and help your team unlock the weekly chest.', 'cta_label' => 'Start Mining', 'sort_order' => 1],
            ['key' => 'chest_close', 'title' => 'Squad Chest Is Close', 'body' => 'Your squad is close to the weekly chest. A few more verified actions can unlock it for active members.', 'cta_label' => 'Help Squad', 'sort_order' => 2],
            ['key' => 'invite_push', 'title' => 'Invite More Members', 'body' => 'Share your squad invite and bring active members into the team. Active invites help the whole squad grow.', 'cta_label' => 'Share Squad', 'sort_order' => 3],
            ['key' => 'learn_boost', 'title' => 'Learn Together', 'body' => 'Complete Learn & Earn lessons this week to add verified activity points for your squad.', 'cta_label' => 'Open Lessons', 'sort_order' => 4],
            ['key' => 'weekly_winner', 'title' => 'Weekly Squad Winner', 'body' => 'Your squad performed strongly this week. Share the weekly poster and keep the momentum going.', 'cta_label' => 'Share Poster', 'sort_order' => 5],
        ];

        foreach ($templates as $template) {
            DB::table('squad_announcement_templates')->updateOrInsert(
                ['key' => $template['key']],
                array_merge($template, [
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
