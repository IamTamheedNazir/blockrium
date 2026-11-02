<?php

namespace Tests\Unit;

use App\Http\Controllers\AdminEmailCampaignController;
use App\Support\BladeRouteReferenceScanner;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CriticalAdminRoutesTest extends TestCase
{
    public function test_telegram_membership_verification_is_not_exposed_as_a_user_reward_gate(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('admin.verified-members.settings.telegram-webhook'));
        $telegramWebhook = collect(Route::getRoutes()->getRoutes())->first(
            fn ($candidate) => $candidate->uri() === 'api/webhooks/telegram'
        );
        $this->assertNull($telegramWebhook);
    }

    public function test_kyc_and_email_admin_routes_resolve_to_existing_controller_methods(): void
    {
        $routeNames = [
            'admin.verified-members.index',
            'admin.verified-members.sync-didit',
            'admin.verified-members.settings',
            'admin.verified-members.settings.update',
            'admin.verified-members.waitlist',
            'admin.verified-members.waitlist.invite',
            'admin.verified-members.waitlist.invite-selected',
            'admin.verified-members.waitlist.country-limit',
            'admin.verified-members.kyc.approve',
            'admin.verified-members.kyc.reject',
            'admin.verified-members.kyc.resend-invite',
            'admin.verified-members.access.revoke',
            'admin.verified-members.reminder',
            'admin.verified-members.reminders.send',
            'admin.verified-members.payment.confirm',
            'admin.verified-members.payment-support',
            'admin.verified-members.payment-support.update',
            'admin.app-health',
            'admin.partner-campaigns.index',
            'admin.partner-campaigns.create',
            'admin.partner-campaigns.store',
            'admin.partner-campaigns.toggle',
            'admin.email-campaigns.index',
            'admin.email-campaigns.create',
            'admin.email-campaigns.store',
            'admin.email-campaigns.brand-settings',
            'admin.email-campaigns.show',
            'admin.email-campaigns.test',
            'admin.email-campaigns.queue',
            'admin.email-campaigns.retry-failed',
            'email.campaign.click',
            'email.campaign.open',
        ];

        foreach ($routeNames as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route, 'Missing critical admin route: ' . $routeName);

            $action = $route->getActionName();
            [$controller, $method] = explode('@', $action, 2);
            $this->assertTrue(class_exists($controller), 'Missing route controller: ' . $controller);
            $this->assertTrue(method_exists($controller, $method), 'Missing route action: ' . $action);
        }

        $toggleRoute = Route::getRoutes()->getByName('admin.partner-campaigns.toggle');
        $this->assertSame(['POST'], $toggleRoute->methods(), 'Campaign status changes must not use a state-changing GET route.');
        $this->assertSame(['GET', 'HEAD'], Route::getRoutes()->getByName('email.campaign.click')->methods());
        $createRoute = Route::getRoutes()->getByName('admin.email-campaigns.create');
        $this->assertSame('admin/email-campaigns/create', $createRoute->uri());
        $this->assertSame(['GET', 'HEAD'], $createRoute->methods());
        $this->assertSame(AdminEmailCampaignController::class . '@index', $createRoute->getActionName());
    }

    public function test_kyc_access_revocation_requires_an_admin_session(): void
    {
        $response = $this->post(route('admin.verified-members.access.revoke', 801), [
            'scope' => 'member_access',
            'reason' => 'Security review',
        ]);

        $response->assertRedirect('/ad_maticlegend');
    }

    public function test_blade_views_only_reference_registered_literal_named_routes(): void
    {
        $scanner = new BladeRouteReferenceScanner();

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (!$scanner->shouldScanPath($file->getRelativePathname())) {
                continue;
            }

            $missing = $scanner->missingRoutes(
                $file->getContents(),
                function (string $name): bool {
                    return Route::getRoutes()->getByName($name) !== null;
                }
            );

            $this->assertSame([], $missing, 'Unregistered route(s) referenced in ' . $file->getRelativePathname());
        }
    }

    public function test_only_known_retired_blade_paths_are_excluded_from_route_checks(): void
    {
        $scanner = new BladeRouteReferenceScanner();

        $this->assertFalse($scanner->shouldScanPath('email-campaigns/form.blade.php'));
        $this->assertFalse($scanner->shouldScanPath('partner-promo-codes/index.blade.php'));
        $this->assertTrue($scanner->shouldScanPath('admin/email-campaigns/index.blade.php'));
        $this->assertTrue($scanner->shouldScanPath('verified-members/index.blade.php'));
    }

    public function test_blade_route_scanner_finds_missing_routes_and_allows_route_has_guards(): void
    {
        $scanner = new BladeRouteReferenceScanner();
        $userIdPlaceholder = '$userId';
        $source = "{{ route('admin.missing', {$userIdPlaceholder}) }} @if(Route::has('admin.optional')) {{ route('admin.optional', {$userIdPlaceholder}) }} @endif";

        $missing = $scanner->missingRoutes($source, function (string $name): bool {
            return $name === 'admin.optional';
        });

        $this->assertSame(['admin.missing'], $missing);
    }
}
