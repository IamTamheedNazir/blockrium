<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MakeSquadCapacityUnlimitedAndPosters extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('squads')) {
            return;
        }

        if (Schema::hasColumn('squads', 'max_members')) {
            try {
                DB::statement('ALTER TABLE squads MODIFY max_members SMALLINT UNSIGNED NOT NULL DEFAULT 0');
            } catch (\Throwable $error) {
                // Shared hosting may block ALTER MODIFY. Existing code still treats 0 as unlimited.
            }

            DB::table('squads')->where('max_members', '>', 0)->update([
                'max_members' => 0,
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasColumn('squads', 'poster_template')) {
            DB::table('squads')
                ->where(function ($query) {
                    $query->whereNull('poster_template')->orWhereIn('poster_template', ['default', '']);
                })
                ->update([
                    'poster_template' => 'white',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down()
    {
        if (!Schema::hasTable('squads') || !Schema::hasColumn('squads', 'max_members')) {
            return;
        }

        DB::table('squads')->where('max_members', 0)->update([
            'max_members' => 20,
            'updated_at' => now(),
        ]);
    }
}
