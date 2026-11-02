<?php

namespace Tests\Unit;

use App\Http\Controllers\AppHealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppHealthControllerTest extends TestCase
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

        Schema::create('app_issue_logs', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('severity')->default('error');
            $table->string('source');
            $table->string('action')->nullable();
            $table->string('endpoint')->nullable();
            $table->string('app_version')->nullable();
            $table->text('message')->nullable();
            $table->unsignedInteger('request_duration_ms')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('res_users', function ($table) {
            $table->increments('id');
            $table->string('username')->nullable();
            $table->string('email')->nullable();
        });
    }

    public function test_groups_api_latency_reports_by_endpoint_and_duration(): void
    {
        DB::table('app_issue_logs')->insert([
            ['source' => 'api_latency', 'endpoint' => 'auth/home', 'request_duration_ms' => 1200, 'created_at' => now()],
            ['source' => 'api_latency', 'endpoint' => 'auth/home', 'request_duration_ms' => 1800, 'created_at' => now()],
            ['source' => 'api_latency', 'endpoint' => 'auth/squads/me', 'request_duration_ms' => 2500, 'created_at' => now()],
            ['source' => 'api', 'endpoint' => 'auth/home', 'request_duration_ms' => 9000, 'created_at' => now()],
            ['source' => 'api_latency', 'endpoint' => 'auth/empty', 'request_duration_ms' => null, 'created_at' => now()],
        ]);

        $controller = (new \ReflectionClass(AppHealthController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(AppHealthController::class, 'topSlowEndpoints');
        $method->setAccessible(true);
        $rows = $method->invoke($controller, DB::table('app_issue_logs'));

        $this->assertCount(2, $rows);
        $this->assertSame('auth/squads/me', $rows[0]->endpoint);
        $this->assertSame(1, (int) $rows[0]->total);
        $this->assertSame(2500.0, (float) $rows[0]->average_duration_ms);
        $this->assertSame('auth/home', $rows[1]->endpoint);
        $this->assertSame(2, (int) $rows[1]->total);
        $this->assertSame(1500.0, (float) $rows[1]->average_duration_ms);
        $this->assertSame(1800, (int) $rows[1]->max_duration_ms);
    }

    public function test_root_error_filter_returns_crash_report_with_stack_metadata(): void
    {
        $stack = ['stack' => "TypeError: undefined is not a function\n at HomeOrg"];
        DB::table('app_issue_logs')->insert([
            [
                'source' => 'app',
                'action' => 'root_error_boundary',
                'severity' => 'fatal',
                'message' => 'Undefined is not a function',
                'app_version' => '1.10.56',
                'metadata' => json_encode($stack),
                'created_at' => now(),
            ],
            [
                'source' => 'api',
                'action' => 'mining_start_failed',
                'severity' => 'error',
                'message' => 'Service unavailable',
                'app_version' => null,
                'metadata' => null,
                'created_at' => now(),
            ],
        ]);

        $view = (new AppHealthController())->index(Request::create('/admin/app-health', 'GET', [
            'period' => '30d',
            'source' => 'app',
            'action' => 'root_error_boundary',
        ]));

        $issues = $view->getData()['recentIssues'];
        $this->assertSame('admin.app-health.index', $view->getName());
        $this->assertSame(1, $issues->total());
        $this->assertSame('root_error_boundary', $issues->first()->action);
        $this->assertStringContainsString('HomeOrg', $issues->first()->metadata);
    }
}
