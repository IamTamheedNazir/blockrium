<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HardenSquadAppHomeAndSettings extends Migration
{
    public function up()
    {
        if (Schema::hasTable('squads')) {
            if (Schema::hasColumn('squads', 'max_members')) {
                DB::table('squads')->where('max_members', '>', 0)->update([
                    'max_members' => 0,
                    'updated_at' => now(),
                ]);
            }

            $this->addIndexIfMissing('squads', 'squads_invite_slug_idx', '`invite_code`, `slug`');
            $this->addIndexIfMissing('squads', 'squads_status_feature_idx', '`is_active`, `is_public`, `country_code`, `promotion_tier`');
            $this->addIndexIfMissing('squads', 'squads_share_count_idx', '`share_count`');
        }

        if (Schema::hasTable('squad_members')) {
            $this->addIndexIfMissing('squad_members', 'squad_members_user_status_squad_idx', '`user_id`, `status`, `squad_id`');
            $this->addIndexIfMissing('squad_members', 'squad_members_squad_status_joined_idx', '`squad_id`, `status`, `joined_at`');
            $this->addIndexIfMissing('squad_members', 'squad_members_squad_status_role_idx', '`squad_id`, `status`, `role`');
        }

        if (Schema::hasTable('squad_followers')) {
            $this->addIndexIfMissing('squad_followers', 'squad_followers_user_squad_status_idx', '`user_id`, `squad_id`, `status`');
        }

        if (Schema::hasTable('squad_share_events')) {
            $this->addIndexIfMissing('squad_share_events', 'squad_share_events_squad_user_channel_idx', '`squad_id`, `user_id`, `channel`, `created_at`');
        }

        if (Schema::hasTable('squad_mission_claims')) {
            $this->addIndexIfMissing('squad_mission_claims', 'squad_mission_claim_user_week_idx', '`user_id`, `week_start`');
        }

        if (Schema::hasTable('squad_weekly_chests')) {
            $this->addIndexIfMissing('squad_weekly_chests', 'squad_chests_user_week_idx', '`claimed_by`, `week_start`');
        }

        if (Schema::hasTable('app_engagement_events')) {
            $this->addIndexIfMissing('app_engagement_events', 'app_engagement_user_event_created_idx', '`user_id`, `event_name`, `created_at`');
        }

        if (Schema::hasTable('squad_settings')) {
            $settings = [
                ['key' => 'squads_enabled', 'value' => '1', 'value_type' => 'boolean', 'description' => 'Enable squad features in app.'],
                ['key' => 'squad_default_unlimited_capacity', 'value' => '1', 'value_type' => 'boolean', 'description' => 'New squads are unlimited by default.'],
                ['key' => 'squad_default_weekly_target_points', 'value' => '500', 'value_type' => 'integer', 'description' => 'Default weekly points needed for squad chest unlock.'],
                ['key' => 'squad_default_chest_reward_xp', 'value' => '50', 'value_type' => 'integer', 'description' => 'Default XP reward for weekly squad chest.'],
                ['key' => 'squad_invite_streak_reward_xp_base', 'value' => '30', 'value_type' => 'integer', 'description' => 'Base XP reward for active invite streak claims.'],
                ['key' => 'squad_allowed_poster_templates', 'value' => 'white,dark,country,weekly_winner', 'value_type' => 'csv', 'description' => 'Poster templates available in app and admin.'],
                ['key' => 'squad_country_leaderboard_enabled', 'value' => '1', 'value_type' => 'boolean', 'description' => 'Show country leaderboard previews.'],
                ['key' => 'squad_feed_status', 'value' => 'coming_soon', 'value_type' => 'string', 'description' => 'Safe squad feed state: disabled, coming_soon, enabled.'],
            ];

            foreach ($settings as $setting) {
                DB::table('squad_settings')->updateOrInsert(
                    ['key' => $setting['key']],
                    array_merge($setting, ['group' => 'squads', 'created_at' => now(), 'updated_at' => now()])
                );
            }
        }
    }

    public function down()
    {
        $this->dropIndexIfExists('app_engagement_events', 'app_engagement_user_event_created_idx');
        $this->dropIndexIfExists('squad_weekly_chests', 'squad_chests_user_week_idx');
        $this->dropIndexIfExists('squad_mission_claims', 'squad_mission_claim_user_week_idx');
        $this->dropIndexIfExists('squad_share_events', 'squad_share_events_squad_user_channel_idx');
        $this->dropIndexIfExists('squad_followers', 'squad_followers_user_squad_status_idx');
        $this->dropIndexIfExists('squad_members', 'squad_members_squad_status_role_idx');
        $this->dropIndexIfExists('squad_members', 'squad_members_squad_status_joined_idx');
        $this->dropIndexIfExists('squad_members', 'squad_members_user_status_squad_idx');
        $this->dropIndexIfExists('squads', 'squads_share_count_idx');
        $this->dropIndexIfExists('squads', 'squads_status_feature_idx');
        $this->dropIndexIfExists('squads', 'squads_invite_slug_idx');
    }

    private function addIndexIfMissing(string $table, string $index, string $columns): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $index) || !$this->columnsExist($table, $columns)) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$columns})");
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $index)) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->exists();
        } catch (Throwable $error) {
            return false;
        }
    }

    private function columnsExist(string $table, string $columns): bool
    {
        preg_match_all('/`([^`]+)`/', $columns, $matches);
        foreach ($matches[1] as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }
}
