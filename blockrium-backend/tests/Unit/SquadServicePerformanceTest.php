<?php

namespace Tests\Unit;

use App\Services\SquadService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SquadServicePerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('squad_members', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('squad_id');
            $table->unsignedInteger('user_id');
            $table->string('status');
            $table->string('role')->default('member');
            $table->string('join_source')->nullable();
            $table->unsignedInteger('invited_by_user_id')->nullable();
            $table->timestamp('joined_at')->nullable();
        });

        Schema::create('squads', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('slug');
            $table->string('invite_code');
            $table->string('description')->nullable();
            $table->string('country_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('max_members')->default(0);
            $table->unsignedInteger('weekly_target_points')->default(500);
            $table->unsignedInteger('share_count')->default(0);
        });

        Schema::create('res_users', function ($table) {
            $table->increments('id');
            $table->string('uid')->nullable();
            $table->string('username')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
        });

        Schema::create('button_clicks', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->timestamp('rewards_start_time')->nullable();
            $table->timestamps();
        });

        Schema::create('tblreward', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('userid');
            $table->timestamps();
        });

        Schema::create('task_claimeds', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->timestamps();
        });

        Schema::create('user_learn_progress', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('status');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_counts_weekly_active_members_with_a_bounded_number_of_queries(): void
    {
        $now = Carbon::now();
        $memberRows = [];
        for ($userId = 1; $userId <= 60; $userId++) {
            $memberRows[] = [
                'squad_id' => 7,
                'user_id' => $userId,
                'status' => 'active',
            ];
        }
        DB::table('squad_members')->insert($memberRows);

        DB::table('button_clicks')->insert([
            ['user_id' => 2, 'rewards_start_time' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => 3, 'rewards_start_time' => $now, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('task_claimeds')->insert([
            ['user_id' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => 5, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('user_learn_progress')->insert([
            ['user_id' => 5, 'status' => 'completed', 'completed_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $queryCount = 0;
        DB::listen(function ($query) use (&$queryCount) {
            if (preg_match('/\bfrom\s+[`"]?(squad_members|button_clicks|tblreward|task_claimeds|user_learn_progress)\b/i', $query->sql)) {
                $queryCount++;
            }
        });

        $service = (new \ReflectionClass(SquadService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(SquadService::class, 'activeMembersThisWeek');
        $method->setAccessible(true);
        $week = [
            'start' => $now->copy()->startOfWeek(),
            'end' => $now->copy()->endOfWeek(),
        ];

        $activeCount = $method->invoke($service, 7, $week);

        $this->assertSame(3, $activeCount);
        $this->assertLessThanOrEqual(5, $queryCount, 'Activity lookup query count should not scale with squad size.');
    }

    public function test_squad_member_payload_does_not_expose_email_addresses(): void
    {
        $now = Carbon::now();
        DB::table('res_users')->insert([
            'id' => 81,
            'uid' => 'member-81',
            'username' => 'mina',
            'name' => 'Mina',
            'email' => 'mina@example.test',
        ]);
        DB::table('squad_members')->insert([
            'squad_id' => 7,
            'user_id' => 81,
            'status' => 'active',
            'role' => 'member',
            'joined_at' => $now,
        ]);

        $service = (new \ReflectionClass(SquadService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(SquadService::class, 'members');
        $method->setAccessible(true);
        $members = $method->invoke($service, 7, 6);

        $this->assertCount(1, $members);
        $this->assertSame('Mina', $members[0]['name']);
        $this->assertArrayNotHasKey('email', $members[0]);

        $groupsMethod = new \ReflectionMethod(SquadService::class, 'membersFromIds');
        $groupsMethod->setAccessible(true);
        $groupMembers = $groupsMethod->invoke($service, 7, [81]);
        $this->assertCount(1, $groupMembers);
        $this->assertArrayNotHasKey('email', $groupMembers[0]);
    }

    public function test_my_squad_profile_does_not_build_a_full_country_leaderboard(): void
    {
        DB::table('res_users')->insert([
            'id' => 91,
            'uid' => 'owner-91',
            'username' => 'owner91',
            'name' => 'Squad Owner',
            'email' => 'owner@example.test',
        ]);
        DB::table('squads')->insert([
            'id' => 41,
            'name' => 'North Stars',
            'slug' => 'north-stars',
            'invite_code' => 'NORTH41',
            'country_code' => 'IN',
            'is_active' => 1,
            'is_public' => 1,
        ]);
        DB::table('squads')->insert([
            'id' => 42,
            'name' => 'South Stars',
            'slug' => 'south-stars',
            'invite_code' => 'SOUTH42',
            'country_code' => 'IN',
            'is_active' => 1,
            'is_public' => 1,
        ]);
        DB::table('squad_members')->insert([
            'squad_id' => 41,
            'user_id' => 91,
            'status' => 'active',
            'role' => 'owner',
            'joined_at' => Carbon::now(),
        ]);

        $rankQueries = 0;
        $memberQueries = 0;
        DB::listen(function ($query) use (&$rankQueries) {
            if (preg_match('/\bfrom\s+[\`"]?squads[\`"]?.*country_code/is', $query->sql)) {
                $rankQueries++;
            }
        });
        DB::listen(function ($query) use (&$memberQueries) {
            if (preg_match('/\bfrom\s+[\`"]?squad_members\b/i', $query->sql)) {
                $memberQueries++;
            }
        });

        $user = new \App\Models\ResUser();
        $user->id = 91;
        $user->country = 'IN';
        $service = (new \ReflectionClass(SquadService::class))->newInstanceWithoutConstructor();
        $profile = $service->mySquadProfile($user);

        $this->assertTrue($profile['has_squad']);
        $this->assertSame('North Stars', $profile['squad']['name']);
        $this->assertSame(0, $profile['squad']['weekly_points']);
        $this->assertSame(1, $profile['squad']['member_count']);
        $this->assertSame(0, $rankQueries, 'The profile endpoint must not load a full country leaderboard.');
        $this->assertLessThanOrEqual(3, $memberQueries, 'The score request should not reload profile member lists or tabs.');
    }
}
