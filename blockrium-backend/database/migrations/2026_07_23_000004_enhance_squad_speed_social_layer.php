<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnhanceSquadSpeedSocialLayer extends Migration
{
    public function up()
    {
        if (Schema::hasTable('squads')) {
            Schema::table('squads', function (Blueprint $table) {
                if (!Schema::hasColumn('squads', 'allow_followers')) {
                    $table->boolean('allow_followers')->default(true)->after('is_public');
                }
                if (!Schema::hasColumn('squads', 'feed_enabled')) {
                    $table->boolean('feed_enabled')->default(false)->after('allow_followers');
                }
                if (!Schema::hasColumn('squads', 'auto_feed_enabled')) {
                    $table->boolean('auto_feed_enabled')->default(true)->after('feed_enabled');
                }
            });

            if ($this->hasColumns('squads', ['invite_code', 'is_active'])) {
                $this->addIndexIfMissing('squads', 'squads_invite_active_idx', function (Blueprint $table) {
                    $table->index(['invite_code', 'is_active'], 'squads_invite_active_idx');
                });
            }
            if ($this->hasColumns('squads', ['is_active', 'is_public', 'country_code', 'share_count'])) {
                $this->addIndexIfMissing('squads', 'squads_weekly_discover_idx', function (Blueprint $table) {
                    $table->index(['is_active', 'is_public', 'country_code', 'share_count'], 'squads_weekly_discover_idx');
                });
            }
            if ($this->hasColumns('squads', ['promotion_tier', 'featured_until'])) {
                $this->addIndexIfMissing('squads', 'squads_promoted_idx', function (Blueprint $table) {
                    $table->index(['promotion_tier', 'featured_until'], 'squads_promoted_idx');
                });
            }
        }

        if (Schema::hasTable('squad_members')) {
            if ($this->hasColumns('squad_members', ['squad_id', 'status', 'role', 'joined_at'])) {
                $this->addIndexIfMissing('squad_members', 'squad_members_role_joined_idx', function (Blueprint $table) {
                    $table->index(['squad_id', 'status', 'role', 'joined_at'], 'squad_members_role_joined_idx');
                });
            }
            if ($this->hasColumns('squad_members', ['squad_id', 'join_source'])) {
                $this->addIndexIfMissing('squad_members', 'squad_members_join_source_idx', function (Blueprint $table) {
                    $table->index(['squad_id', 'join_source'], 'squad_members_join_source_idx');
                });
            }
        }

        if (!Schema::hasTable('squad_followers')) {
            Schema::create('squad_followers', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('squad_id');
                $table->unsignedBigInteger('user_id');
                $table->string('status', 30)->default('active');
                $table->timestamp('followed_at')->nullable();
                $table->timestamps();

                $table->unique(['squad_id', 'user_id'], 'squad_followers_squad_user_unique');
                $table->index(['squad_id', 'status'], 'squad_followers_squad_status_idx');
                $table->index(['user_id', 'status'], 'squad_followers_user_status_idx');
            });
        }

        if (!Schema::hasTable('squad_feed_events')) {
            Schema::create('squad_feed_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('squad_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('event_type', 60);
                $table->string('title', 160);
                $table->text('body')->nullable();
                $table->string('status', 30)->default('draft');
                $table->json('metadata')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->index(['squad_id', 'status', 'published_at'], 'squad_feed_squad_status_idx');
                $table->index(['event_type', 'status'], 'squad_feed_event_status_idx');
            });
        }

        if (!Schema::hasTable('squad_settings')) {
            Schema::create('squad_settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('key', 100)->unique();
                $table->text('value')->nullable();
                $table->string('value_type', 30)->default('string');
                $table->string('group', 60)->default('squads');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        $settings = [
            ['key' => 'squad_followers_enabled', 'value' => '1', 'value_type' => 'boolean', 'description' => 'Allow users to follow squads before joining.'],
            ['key' => 'squad_feed_enabled', 'value' => '0', 'value_type' => 'boolean', 'description' => 'Enable safe squad feed cards in app.'],
            ['key' => 'squad_join_approval_default', 'value' => 'open', 'value_type' => 'string', 'description' => 'Default squad join mode: open, approval, or invite_only.'],
            ['key' => 'squad_directory_cache_seconds', 'value' => '45', 'value_type' => 'integer', 'description' => 'Short cache window for squad directory and leaderboards.'],
        ];

        foreach ($settings as $setting) {
            DB::table('squad_settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, ['group' => 'squads', 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    public function down()
    {
        Schema::dropIfExists('squad_feed_events');
        Schema::dropIfExists('squad_followers');
    }

    private function addIndexIfMissing($tableName, $indexName, callable $callback)
    {
        if (!$this->indexExists($tableName, $indexName)) {
            Schema::table($tableName, $callback);
        }
    }

    private function indexExists($tableName, $indexName)
    {
        try {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $tableName)
                ->where('index_name', $indexName)
                ->exists();
        } catch (\Throwable $error) {
            return false;
        }
    }

    private function hasColumns($tableName, array $columns)
    {
        foreach ($columns as $column) {
            if (!Schema::hasColumn($tableName, $column)) {
                return false;
            }
        }

        return true;
    }
}
