<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnhanceSquadSharingCountry extends Migration
{
    public function up()
    {
        if (Schema::hasTable('squads')) {
            Schema::table('squads', function (Blueprint $table) {
                if (!Schema::hasColumn('squads', 'country_code')) {
                    $table->string('country_code', 2)->nullable()->after('description');
                }
                if (!Schema::hasColumn('squads', 'language_code')) {
                    $table->string('language_code', 8)->nullable()->after('country_code');
                }
                if (!Schema::hasColumn('squads', 'poster_template')) {
                    $table->string('poster_template', 40)->default('default')->after('language_code');
                }
                if (!Schema::hasColumn('squads', 'join_policy')) {
                    $table->string('join_policy', 30)->default('open')->after('poster_template');
                }
                if (!Schema::hasColumn('squads', 'featured_until')) {
                    $table->timestamp('featured_until')->nullable()->after('join_policy');
                }
                if (!Schema::hasColumn('squads', 'share_count')) {
                    $table->unsignedInteger('share_count')->default(0)->after('featured_until');
                }
            });

            $this->addIndexIfMissing('squads', 'squads_country_language_idx', function (Blueprint $table) {
                $table->index(['country_code', 'language_code'], 'squads_country_language_idx');
            });
            $this->addIndexIfMissing('squads', 'squads_public_country_idx', function (Blueprint $table) {
                $table->index(['is_active', 'is_public', 'country_code'], 'squads_public_country_idx');
            });
            $this->addIndexIfMissing('squads', 'squads_featured_until_idx', function (Blueprint $table) {
                $table->index(['featured_until'], 'squads_featured_until_idx');
            });
        }

        if (Schema::hasTable('squad_members')) {
            Schema::table('squad_members', function (Blueprint $table) {
                if (!Schema::hasColumn('squad_members', 'invited_by_user_id')) {
                    $table->unsignedBigInteger('invited_by_user_id')->nullable()->after('user_id');
                }
                if (!Schema::hasColumn('squad_members', 'join_source')) {
                    $table->string('join_source', 40)->nullable()->after('invited_by_user_id');
                }
            });

            $this->addIndexIfMissing('squad_members', 'squad_members_inviter_idx', function (Blueprint $table) {
                $table->index(['invited_by_user_id'], 'squad_members_inviter_idx');
            });
        }

        if (!Schema::hasTable('squad_share_events')) {
            Schema::create('squad_share_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('squad_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('invite_code', 40)->nullable();
                $table->string('channel', 40)->default('copy_link');
                $table->string('share_url', 500)->nullable();
                $table->string('ip_hash', 64)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamps();

                $table->index(['squad_id', 'created_at'], 'squad_share_events_squad_created_idx');
                $table->index(['user_id', 'created_at'], 'squad_share_events_user_created_idx');
                $table->index('channel', 'squad_share_events_channel_idx');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('squad_share_events');
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
}
