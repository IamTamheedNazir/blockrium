<?php

namespace Tests\Unit;

use App\Http\Controllers\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommunityLeaderboardTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('APP_URL=http://localhost');
        $_ENV['APP_URL'] = 'http://localhost';
        $_SERVER['APP_URL'] = 'http://localhost';
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('res_users', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('user_image')->nullable();
            $table->string('refferal_code')->nullable();
            $table->boolean('is_active')->default(true);
        });
        Schema::create('button_clicks', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->decimal('wallet', 14, 4)->default(0);
        });
        Schema::create('referrals', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->decimal('rewards', 14, 4)->default(0);
            $table->string('referred_by')->nullable();
        });
    }

    public function test_top_miners_are_limited_in_sql_and_current_user_rank_is_calculated_separately(): void
    {
        $this->seedUsersAndActivity('button_clicks', 'wallet');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = (new Api())->topMiners(new Request(['userId' => 60]));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $payload = $response->getData(true)['data'];
        $this->assertCount(50, $payload['top50Miners']);
        $this->assertSame(1, $payload['top50Miners'][0]['id']);
        $this->assertSame(50, $payload['top50Miners'][49]['position']);
        $this->assertSame(60, $payload['authUserMiner']['id']);
        $this->assertSame(60, $payload['authUserMiner']['position']);
        $this->assertSame('https://maticlegend.com/public/user_image/avatar-60.png', $payload['authUserMiner']['image']);

        $limitedTopQuery = collect($queries)->first(function ($query) {
            return stripos($query['query'], 'limit 50') !== false;
        });
        $this->assertNotNull($limitedTopQuery, 'The top-50 result must be bounded by the database query.');
    }

    public function test_referral_leaderboard_counts_referrals_by_id_or_code_without_duplicate_users(): void
    {
        $users = [];
        $referrals = [];
        $referredUserId = 100;
        for ($id = 1; $id <= 60; $id++) {
            $users[] = [
                'id' => $id,
                'name' => 'Miner ' . $id,
                'refferal_code' => 'REF-' . $id,
                'is_active' => $id !== 1,
            ];
            for ($invite = 0; $invite < $id; $invite++) {
                $referrals[] = [
                    'user_id' => $referredUserId++,
                    'rewards' => 5,
                    'referred_by' => 'REF-' . $id,
                ];
            }
        }
        $referrals[] = ['user_id' => $referredUserId++, 'rewards' => 5, 'referred_by' => '2'];
        $referrals[] = ['user_id' => $referredUserId, 'rewards' => 5, 'referred_by' => '2'];
        DB::table('res_users')->insert($users);
        DB::table('referrals')->insert($referrals);

        $payload = (new Api())->topRefferals(new Request(['userId' => 60]))->getData(true)['data'];

        $this->assertCount(50, $payload['top50Miners']);
        $this->assertSame(60, $payload['top50Miners'][0]['id']);
        $this->assertSame(60, $payload['top50Miners'][0]['total']);
        $this->assertSame(1, $payload['authUserMiner']['position']);
        $userTwo = (new Api())->topRefferals(new Request(['userId' => 2]))->getData(true)['data']['authUserMiner'];
        $this->assertSame(4, $userTwo['total'], 'Legacy ID and referral-code keys must contribute to one referrer.');
        $this->assertSame(57, $userTwo['position']);
    }

    private function seedUsersAndActivity(string $table, string $amountColumn): void
    {
        $users = [];
        $activity = [];
        for ($id = 1; $id <= 60; $id++) {
            $users[] = [
                'id' => $id,
                'name' => 'Miner ' . $id,
                'user_image' => $id === 60 ? 'avatar-60.png' : null,
                'refferal_code' => 'REF-' . $id,
                'is_active' => true,
            ];
            $activity[] = ['user_id' => $id, $amountColumn => 10];
        }

        DB::table('res_users')->insert($users);
        DB::table($table)->insert($activity);
    }
}
