<?php

namespace Tests\Unit;

use App\Console\Commands\DeploymentPreflight;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Routing\RouteCollection;
use Tests\TestCase;

class DeploymentPreflightTest extends TestCase
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

        foreach (DeploymentPreflight::REQUIRED_COLUMNS as $table => $columns) {
            Schema::create($table, function ($blueprint) use ($columns) {
                $blueprint->bigIncrements('id');
                foreach ($columns as $column) {
                    if ($column === 'id') {
                        continue;
                    }
                    $blueprint->string($column)->nullable();
                }
            });
        }
    }

    public function test_preflight_passes_when_critical_routes_and_tables_are_present(): void
    {
        $exitCode = Artisan::call('blockrium:preflight');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString(
            '39 critical route/controller actions, 17 feature tables, and 82 required columns verified.',
            $output
        );
        $this->assertStringContainsString('critical route/controller actions', $output);
        $this->assertStringContainsString('feature tables', $output);
        $this->assertStringContainsString('required columns verified.', $output);
        $this->assertStringContainsString(
            'Active Blade views checked for missing named routes; known retired views excluded.',
            $output
        );
        $this->assertStringContainsString(
            'KYC dashboard summary section and all five status cards verified.',
            $output
        );
        $this->assertStringContainsString('Critical controller constructor dependencies checked.', $output);
        $this->assertStringContainsString('Branded email logo asset is present.', $output);
        $this->assertStringContainsString('Blockrium deployment preflight passed.', $output);
    }

    public function test_preflight_fails_with_clear_migration_guidance_when_a_feature_table_is_missing(): void
    {
        Schema::drop('verification_support_cases');

        $exitCode = Artisan::call('blockrium:preflight');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            'Missing table verification_support_cases; run the matching backend migrations.',
            Artisan::output()
        );
    }

    public function test_preflight_fails_when_a_critical_kyc_payment_route_is_missing(): void
    {
        $router = app('router');
        $originalRoutes = $router->getRoutes();
        $routesWithoutPaymentConfirmation = new RouteCollection();

        foreach ($originalRoutes->getRoutes() as $route) {
            if (
                $route->uri() === 'admin/verified-members/{userId}/payment/confirm'
                && in_array('POST', $route->methods(), true)
            ) {
                continue;
            }

            $routesWithoutPaymentConfirmation->add($route);
        }

        $router->setRoutes($routesWithoutPaymentConfirmation);

        try {
            $exitCode = Artisan::call('blockrium:preflight');
            $output = Artisan::output();

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString(
                'Missing POST /admin/verified-members/{userId}/payment/confirm (App\\Http\\Controllers\\VerifiedMemberAdminController@confirmPayment).',
                $output
            );
        } finally {
            $router->setRoutes($originalRoutes);
        }
    }

    public function test_preflight_contract_detects_a_disconnected_kyc_dashboard_counter(): void
    {
        $errors = DeploymentPreflight::missingKycDashboardContracts(
            'return view("admin/dashboard", compact("stats"));',
            "return ['kyc_approved' => 0, 'payment_pending' => 0, 'in_progress' => 0, 'waitlisted' => 0];",
            "{{ number_format(0) }}"
        );

        $this->assertContains('Admin dashboard controller is not connected to the KYC summary service.', $errors);
        $this->assertContains('Admin dashboard controller does not pass verificationStats to its view.', $errors);
        $this->assertContains('KYC summary service does not provide the verified-member count.', $errors);
        $this->assertContains('Admin dashboard view does not render its KYC counter values.', $errors);
        $this->assertContains('KYC summary cards must appear before generic dashboard metrics.', $errors);
    }

    public function test_preflight_contract_requires_google_play_acknowledgement_command_and_schedule(): void
    {
        $errors = DeploymentPreflight::missingGooglePlayAcknowledgementContracts('', false);

        $this->assertContains(
            'Google Play acknowledgement retry command is missing or not registered.',
            $errors
        );
        $this->assertContains(
            'Google Play acknowledgement retries are not scheduled every five minutes.',
            $errors
        );

        $validKernel = "\$schedule->command('google-play:acknowledge-pending --limit=100')->everyFiveMinutes();";
        $this->assertSame([], DeploymentPreflight::missingGooglePlayAcknowledgementContracts($validKernel, true));
    }
}
