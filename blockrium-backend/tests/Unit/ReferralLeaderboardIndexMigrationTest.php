<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferralLeaderboardIndexMigrationTest extends TestCase
{
    public function test_migration_adds_and_removes_the_referred_by_lookup_index(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Schema::create('referrals', function ($table) {
            $table->increments('id');
            $table->string('referred_by')->nullable();
        });

        require_once database_path('migrations/2026_10_19_000003_add_referrals_referred_by_index.php');
        $migration = new \AddReferralsReferredByIndex();
        $migration->up();

        $indexNames = array_column(DB::select("PRAGMA index_list('referrals')"), 'name');
        $this->assertContains('referrals_referred_by_idx', $indexNames);

        $migration->down();
        $indexNames = array_column(DB::select("PRAGMA index_list('referrals')"), 'name');
        $this->assertNotContains('referrals_referred_by_idx', $indexNames);
    }
}
