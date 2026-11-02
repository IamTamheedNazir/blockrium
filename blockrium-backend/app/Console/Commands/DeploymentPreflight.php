<?php

namespace App\Console\Commands;

use App\Http\Controllers\AdminEmailCampaignController;
use App\Http\Controllers\AppHealthController;
use App\Http\Controllers\AppIssueLogController;
use App\Http\Controllers\EmailCampaignTrackingController;
use App\Http\Controllers\GooglePlayPurchaseController;
use App\Http\Controllers\GooglePlayRtdnController;
use App\Http\Controllers\PartnerCampaignAdminController;
use App\Http\Controllers\PartnerCampaignController;
use App\Http\Controllers\VerifiedMemberAdminController;
use App\Http\Controllers\VerifiedMemberController;
use App\Console\Commands\DispatchAdminEmailCampaignBatches;
use App\Jobs\QueueAdminEmailCampaignJob;
use App\Jobs\SendAdminCampaignEmailJob;
use App\Services\AdminEmailCampaignAudience;
use App\Services\AdminEmailCampaignBatchService;
use App\Services\AdminEmailCampaignDeliveryService;
use App\Support\BladeRouteReferenceScanner;
use App\Support\ControllerDependencyScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class DeploymentPreflight extends Command
{
    public const REQUIRED_COLUMNS = [
        'engagement_admin_audits' => ['admin_id', 'action', 'entity_type', 'entity_id', 'changes', 'created_at'],
        'verified_memberships' => ['user_id', 'kyc_status', 'payment_status', 'didit_session_id', 'suspended_at'],
        'partners' => ['id', 'name', 'status'],
        'partner_campaigns' => ['partner_id', 'status', 'starts_at', 'ends_at'],
        'partner_campaign_tasks' => ['campaign_id', 'task_type', 'target_url', 'completion_mode', 'status'],
        'partner_campaign_task_completions' => ['campaign_id', 'task_id', 'user_id', 'status', 'approved_at'],
        'partner_campaign_task_claims' => ['campaign_id', 'task_id', 'user_id', 'wallet_reference', 'claimed_at'],
        'verification_support_cases' => ['user_id', 'status', 'provider_reference'],
        'app_issue_logs' => ['source', 'action', 'endpoint', 'metadata', 'occurred_at'],
        'admin_email_campaigns' => ['name', 'subject', 'body', 'status', 'batch_size', 'daily_cap', 'repeat_interval_hours', 'next_batch_at', 'delivery_unknown_count'],
        'admin_email_campaign_recipients' => ['campaign_id', 'user_id', 'email', 'status', 'dispatched_at', 'tracking_token', 'first_opened_at', 'first_clicked_at', 'open_count', 'click_count'],
        'admin_email_preferences' => ['user_id', 'bulk_email_opted_out_at'],
        'admin_email_campaign_settings' => ['setting_key', 'setting_value'],
        'google_play_purchases' => ['public_id', 'user_id', 'product_id', 'purchase_token_hash', 'acknowledgement_state'],
        'google_play_rtdn_events' => ['message_id', 'purchase_token_hash', 'status'],
        'brc_usernames' => ['user_id', 'username', 'normalized_username'],
        'brc_transfers' => ['sender_user_id', 'recipient_user_id', 'amount', 'fee_amount', 'total_debit', 'fee_rate_basis_points', 'fee_wallet_user_id'],
    ];

    protected $signature = 'blockrium:preflight';

    protected $description = 'Check critical Blockrium routes, controller actions, and feature tables after deployment';

    public function handle(): int
    {
        $routes = [
            ['GET', 'admin/verified-members', VerifiedMemberAdminController::class, 'index'],
            ['GET', 'admin/verified-members/waitlist', VerifiedMemberAdminController::class, 'waitlist'],
            ['GET', 'admin/verified-members/settings', VerifiedMemberAdminController::class, 'settings'],
            ['POST', 'admin/verified-members/sync-didit', VerifiedMemberAdminController::class, 'syncDidit'],
            ['POST', 'admin/verified-members/settings', VerifiedMemberAdminController::class, 'updateSettings'],
            ['POST', 'admin/verified-members/payment-support/{id}', VerifiedMemberAdminController::class, 'updatePaymentSupport'],
            ['POST', 'admin/verified-members/waitlist/invite', VerifiedMemberAdminController::class, 'inviteWaitlist'],
            ['POST', 'admin/verified-members/waitlist/invite-selected', VerifiedMemberAdminController::class, 'inviteSelectedWaitlist'],
            ['POST', 'admin/verified-members/waitlist/country-limit', VerifiedMemberAdminController::class, 'saveCountryLimit'],
            ['POST', 'admin/verified-members/{userId}/kyc/approve', VerifiedMemberAdminController::class, 'approveKyc'],
            ['POST', 'admin/verified-members/{userId}/kyc/reject', VerifiedMemberAdminController::class, 'rejectKyc'],
            ['POST', 'admin/verified-members/{userId}/kyc/resend-invite', VerifiedMemberAdminController::class, 'resendWaitlistInvite'],
            ['POST', 'admin/verified-members/{userId}/access/revoke', VerifiedMemberAdminController::class, 'revokeAccess'],
            ['POST', 'admin/verified-members/{userId}/reminder', VerifiedMemberAdminController::class, 'sendReminder'],
            ['POST', 'admin/verified-members/reminders', VerifiedMemberAdminController::class, 'sendReminders'],
            ['POST', 'admin/verified-members/{userId}/payment/confirm', VerifiedMemberAdminController::class, 'confirmPayment'],
            ['GET', 'admin/verified-members/payment-support', VerifiedMemberAdminController::class, 'paymentSupport'],
            ['GET', 'admin/app-health', AppHealthController::class, 'index'],
            ['GET', 'admin/email-campaigns', AdminEmailCampaignController::class, 'index'],
            ['GET', 'admin/email-campaigns/create', AdminEmailCampaignController::class, 'index'],
            ['POST', 'admin/email-campaigns/{campaignId}/test', AdminEmailCampaignController::class, 'testSend'],
            ['POST', 'admin/email-campaigns/{campaignId}/queue', AdminEmailCampaignController::class, 'queue'],
            ['POST', 'admin/email-campaigns/brand-settings', AdminEmailCampaignController::class, 'saveBrandSettings'],
            ['GET', 'email/campaign/click/{token}', EmailCampaignTrackingController::class, 'click'],
            ['GET', 'email/campaign/open/{token}.gif', EmailCampaignTrackingController::class, 'open'],
            ['GET', 'admin/partner-campaigns', PartnerCampaignAdminController::class, 'index'],
            ['POST', 'admin/partner-campaigns', PartnerCampaignAdminController::class, 'store'],
            ['POST', 'admin/partner-campaigns/{id}/toggle', PartnerCampaignAdminController::class, 'toggle'],
            ['POST', 'admin/partner-campaigns/partners', PartnerCampaignAdminController::class, 'storePartner'],
            ['GET', 'admin/partner-campaigns/task-completions', PartnerCampaignAdminController::class, 'taskCompletions'],
            ['POST', 'admin/partner-campaigns/task-completions/{id}/approve', PartnerCampaignAdminController::class, 'approveTask'],
            ['POST', 'api/app-issue-log', AppIssueLogController::class, 'store'],
            ['POST', 'api/webhooks/google-play', GooglePlayRtdnController::class, 'handle'],
            ['GET', 'api/auth/partner-campaigns', PartnerCampaignController::class, 'index'],
            ['POST', 'api/auth/partner-campaigns/{id}/tasks/{taskId}/claim', PartnerCampaignController::class, 'taskClaim'],
            ['POST', 'api/auth/google-play/purchase-intents', GooglePlayPurchaseController::class, 'createIntent'],
            ['POST', 'api/auth/google-play/verify', GooglePlayPurchaseController::class, 'verify'],
            ['POST', 'api/auth/brc/transfers', VerifiedMemberController::class, 'transfer'],
            ['GET', 'api/auth/brc/transfers', VerifiedMemberController::class, 'transfers'],
        ];

        $errors = [];
        $dependencyScanner = new ControllerDependencyScanner();
        $checkedControllers = [];
        foreach ($routes as [$method, $uri, $controller, $action]) {
            $route = collect(Route::getRoutes()->getRoutes())->first(
                fn ($candidate) => $candidate->uri() === $uri && in_array($method, $candidate->methods(), true)
            );
            $expectedAction = $controller . '@' . $action;

            if (!$route) {
                $errors[] = "Missing {$method} /{$uri} ({$expectedAction}).";
                continue;
            }

            if ($route->getActionName() !== $expectedAction || !class_exists($controller) || !method_exists($controller, $action)) {
                $errors[] = "Invalid action for {$method} /{$uri}; expected {$expectedAction}.";
            }

            if (class_exists($controller) && !isset($checkedControllers[$controller])) {
                foreach ($dependencyScanner->missingDependencies($controller) as $chain) {
                    $errors[] = "Missing constructor dependency for {$controller}: {$chain}. Upload the complete backend source set.";
                }
                $checkedControllers[$controller] = true;
            }
        }

        if (!isset(Artisan::all()['email-campaigns:tick'])) {
            $errors[] = 'Missing email-campaigns:tick command; verify the email batch dispatcher is included and auto-loaded.';
        }

        $consoleKernelPath = app_path('Console/Kernel.php');
        $consoleKernelSource = File::exists($consoleKernelPath) ? File::get($consoleKernelPath) : '';
        if (strpos($consoleKernelSource, "command('email-campaigns:tick')->everyMinute()") === false) {
            $errors[] = 'Email batch tick is not scheduled every minute in app/Console/Kernel.php.';
        }

        $googlePlayCommandRegistered = class_exists(AcknowledgeGooglePlayPurchases::class)
            && isset(Artisan::all()['google-play:acknowledge-pending']);
        foreach (self::missingGooglePlayAcknowledgementContracts(
            $consoleKernelSource,
            $googlePlayCommandRegistered
        ) as $contractError) {
            $errors[] = $contractError;
        }

        foreach ([
            DispatchAdminEmailCampaignBatches::class,
            QueueAdminEmailCampaignJob::class,
            SendAdminCampaignEmailJob::class,
            AdminEmailCampaignBatchService::class,
            AdminEmailCampaignDeliveryService::class,
            AdminEmailCampaignAudience::class,
        ] as $criticalClass) {
            if (!class_exists($criticalClass)) {
                $errors[] = 'Missing email pipeline class [' . $criticalClass . ']; include the complete backend email campaign source set.';
            }
        }

        $viewFiles = File::allFiles(resource_path('views'));
        $routeScanner = new BladeRouteReferenceScanner();
        foreach ($viewFiles as $viewFile) {
            if (!$routeScanner->shouldScanPath($viewFile->getRelativePathname())) {
                continue;
            }

            $missingRoutes = $routeScanner->missingRoutes(
                $viewFile->getContents(),
                function (string $name): bool {
                    return Route::getRoutes()->getByName($name) !== null;
                }
            );

            foreach ($missingRoutes as $routeName) {
                $errors[] = 'Unregistered named route [' . $routeName . '] referenced in view ' . $viewFile->getRelativePathname() . '.';
            }
        }

        $dashboardPath = resource_path('views/admin/dashboard.blade.php');
        $dashboardSource = File::exists($dashboardPath) ? File::get($dashboardPath) : '';
        foreach ([
            'KYC &amp; Verification' => 'KYC verification summary section',
            "'label' => 'KYC Approved'" => 'KYC Approved card',
            "'label' => 'Payment Pending'" => 'Payment Pending card',
            "'label' => 'Verified Members'" => 'Verified Members card',
            "'label' => 'KYC In Progress'" => 'KYC In Progress card',
            "'label' => 'KYC Waiting List'" => 'KYC Waiting List card',
        ] as $requiredText => $label) {
            if (strpos($dashboardSource, $requiredText) === false) {
                $errors[] = "Admin dashboard is missing the {$label}; upload the current resources/views/admin/dashboard.blade.php.";
            }
        }

        $dashboardControllerSource = File::exists(app_path('Http/Controllers/AdminController.php'))
            ? File::get(app_path('Http/Controllers/AdminController.php'))
            : '';
        $dashboardServiceSource = File::exists(app_path('Services/VerifiedMemberService.php'))
            ? File::get(app_path('Services/VerifiedMemberService.php'))
            : '';
        foreach (self::missingKycDashboardContracts(
            $dashboardControllerSource,
            $dashboardServiceSource,
            $dashboardSource
        ) as $contractError) {
            $errors[] = $contractError;
        }

        $columnCount = 0;
        try {
            foreach (self::REQUIRED_COLUMNS as $table => $columns) {
                if (!Schema::hasTable($table)) {
                    $errors[] = "Missing table {$table}; run the matching backend migrations.";
                    continue;
                }

                foreach ($columns as $column) {
                    $columnCount++;
                    if (!Schema::hasColumn($table, $column)) {
                        $errors[] = "Missing column {$table}.{$column}; run the matching backend migrations.";
                    }
                }
            }
        } catch (\Throwable $error) {
            $errors[] = 'Database schema check failed (' . class_basename($error) . '). Confirm database configuration and permissions.';
        }

        if (!is_file(base_path('email-assets/blockrium-mark.png'))) {
            $errors[] = 'Missing email-assets/blockrium-mark.png; include the branded email asset in the backend upload.';
        }

        if ($errors) {
            $this->error('Blockrium deployment preflight failed:');
            foreach ($errors as $error) {
                $this->line(' - ' . $error);
            }
            return 1;
        }

        $this->info(count($routes) . ' critical route/controller actions, ' . count(self::REQUIRED_COLUMNS) . ' feature tables, and ' . $columnCount . ' required columns verified.');
        $this->info('Active Blade views checked for missing named routes; known retired views excluded.');
        $this->info('KYC dashboard summary section and all five status cards verified.');
        $this->info('Critical controller constructor dependencies checked.');
        $this->info('Branded email logo asset is present.');
        $this->info('Blockrium deployment preflight passed.');

        return 0;
    }

    public static function missingKycDashboardContracts(string $controllerSource, string $serviceSource, string $dashboardSource): array
    {
        $checks = [
            [$controllerSource, 'app(VerifiedMemberService::class)->adminSummary()', 'Admin dashboard controller is not connected to the KYC summary service.'],
            [$controllerSource, "'verificationStats'))", 'Admin dashboard controller does not pass verificationStats to its view.'],
            [$serviceSource, "'kyc_approved' =>", 'KYC summary service does not provide the approved count.'],
            [$serviceSource, "'payment_pending' =>", 'KYC summary service does not provide the payment-pending count.'],
            [$serviceSource, "'verified' =>", 'KYC summary service does not provide the verified-member count.'],
            [$serviceSource, "'in_progress' =>", 'KYC summary service does not provide the in-progress count.'],
            [$serviceSource, "'waitlisted' =>", 'KYC summary service does not provide the waiting-list count.'],
            [$dashboardSource, 'number_format($verificationStats[$card[\'key\']] ?? 0)', 'Admin dashboard view does not render its KYC counter values.'],
        ];

        $errors = [];
        foreach ($checks as [$source, $requiredText, $error]) {
            if (strpos($source, $requiredText) === false) {
                $errors[] = $error;
            }
        }

        $kycCardPosition = strpos($dashboardSource, "['label' => 'KYC Approved'");
        $overviewCardPosition = strpos($dashboardSource, '@foreach($statCards as $card)');
        if ($kycCardPosition === false || $overviewCardPosition === false || $kycCardPosition > $overviewCardPosition) {
            $errors[] = 'KYC summary cards must appear before generic dashboard metrics.';
        }

        return $errors;
    }

    public static function missingGooglePlayAcknowledgementContracts(string $kernelSource, bool $commandRegistered): array
    {
        $errors = [];
        if (!$commandRegistered) {
            $errors[] = 'Google Play acknowledgement retry command is missing or not registered.';
        }
        if (strpos($kernelSource, "command('google-play:acknowledge-pending --limit=100')->everyFiveMinutes()") === false) {
            $errors[] = 'Google Play acknowledgement retries are not scheduled every five minutes.';
        }

        return $errors;
    }
}
