<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddClickButtonPerformanceIndexes extends Migration
{
    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function addIndexIfMissing(string $table, string $index, string $columns): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $index)) {
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

    public function up()
    {
        $this->addIndexIfMissing('button_clicks', 'button_clicks_user_created_idx', '`user_id`, `created_at`');
        $this->addIndexIfMissing('button_clicks', 'button_clicks_user_reward_start_idx', '`user_id`, `rewards_start_time`');
        $this->addIndexIfMissing('button_clicks', 'button_clicks_user_status_idx', '`user_id`, `button_status`');
        $this->addIndexIfMissing('user_xp_events', 'user_xp_events_user_event_awarded_idx', '`user_id`, `event_key`, `awarded_at`');
        $this->addIndexIfMissing('user_badges', 'user_badges_user_badge_idx', '`user_id`, `badge_id`');
    }

    public function down()
    {
        $this->dropIndexIfExists('user_badges', 'user_badges_user_badge_idx');
        $this->dropIndexIfExists('user_xp_events', 'user_xp_events_user_event_awarded_idx');
        $this->dropIndexIfExists('button_clicks', 'button_clicks_user_status_idx');
        $this->dropIndexIfExists('button_clicks', 'button_clicks_user_reward_start_idx');
        $this->dropIndexIfExists('button_clicks', 'button_clicks_user_created_idx');
    }
}
