<?php

namespace Tests\Unit;

use App\Services\VerifiedMemberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VerifiedMemberAdminQueryTest extends TestCase
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

        Schema::create('verified_memberships', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('kyc_status')->default('not_started');
            $table->string('payment_status')->nullable()->default('pending');
            $table->string('didit_session_id')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('res_users', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('username')->nullable();
        });
    }

    public function test_admin_listing_paginates_large_membership_sets_and_preserves_filters(): void
    {
        $users = [];
        $memberships = [];
        for ($id = 1; $id <= 120; $id++) {
            $users[] = ['id' => $id, 'name' => 'Miner ' . $id, 'email' => 'miner' . $id . '@example.test'];
            $memberships[] = [
                'user_id' => $id,
                'kyc_status' => $id <= 75 ? 'approved' : 'rejected',
                'payment_status' => $id <= 75 ? 'pending' : 'failed',
                'updated_at' => now(),
            ];
        }
        DB::table('res_users')->insert($users);
        DB::table('verified_memberships')->insert($memberships);

        $service = (new \ReflectionClass(VerifiedMemberService::class))->newInstanceWithoutConstructor();
        $page = $service->pendingForAdmin(['status' => 'payment_pending']);

        $this->assertSame(50, $page->count());
        $this->assertSame(75, $page->total());
        $this->assertSame('approved', $page->first()->kyc_status);
    }

    public function test_in_progress_summary_and_filter_include_canonical_and_legacy_statuses(): void
    {
        $users = [];
        $memberships = [];
        foreach ([
            201 => 'started',
            202 => 'submitted',
            203 => 'in_review',
            204 => 'in_progress',
            205 => 'IN_PROGRESS',
            206 => ' In_Review ',
            207 => 'approved',
        ] as $id => $status) {
            $users[] = ['id' => $id, 'name' => 'Miner ' . $id, 'email' => 'miner' . $id . '@example.test'];
            $memberships[] = [
                'user_id' => $id,
                'kyc_status' => $status,
                'payment_status' => 'pending',
                'updated_at' => now(),
            ];
        }
        DB::table('res_users')->insert($users);
        DB::table('verified_memberships')->insert($memberships);

        $service = (new \ReflectionClass(VerifiedMemberService::class))->newInstanceWithoutConstructor();
        $page = $service->pendingForAdmin(['status' => 'in_progress']);

        $summary = $service->adminSummary();

        $this->assertSame(6, $page->total());
        $this->assertSame($summary['in_progress'], $page->total());
        $this->assertEqualsCanonicalizing(['started', 'submitted', 'in_review', 'in_progress', 'IN_PROGRESS', ' In_Review '], $page->pluck('kyc_status')->all());
    }

    public function test_dashboard_summary_separates_kyc_approval_from_paid_membership(): void
    {
        DB::table('verified_memberships')->insert([
            ['user_id' => 301, 'kyc_status' => 'approved', 'payment_status' => 'pending'],
            ['user_id' => 302, 'kyc_status' => 'approved', 'payment_status' => 'paid'],
            ['user_id' => 309, 'kyc_status' => 'approved', 'payment_status' => null],
            ['user_id' => 303, 'kyc_status' => 'started', 'payment_status' => 'pending'],
            ['user_id' => 304, 'kyc_status' => 'in_review', 'payment_status' => 'pending'],
            ['user_id' => 305, 'kyc_status' => 'rejected', 'payment_status' => 'failed'],
        ]);
        Schema::create('kyc_waitlist_entries', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('status');
        });
        DB::table('kyc_waitlist_entries')->insert([
            ['user_id' => 306, 'status' => 'waiting'],
            ['user_id' => 307, 'status' => 'waiting'],
            ['user_id' => 308, 'status' => 'invited'],
        ]);

        $service = (new \ReflectionClass(VerifiedMemberService::class))->newInstanceWithoutConstructor();
        $summary = $service->adminSummary();

        $this->assertSame(3, $summary['kyc_approved']);
        $this->assertSame(1, $summary['verified']);
        $this->assertSame(2, $summary['payment_pending']);
        $paymentPending = $service->pendingForAdmin(['status' => 'payment_pending']);
        $this->assertSame(2, $paymentPending->total());
        $this->assertEqualsCanonicalizing([301, 309], $paymentPending->pluck('user_id')->all());
        $this->assertSame(2, $summary['in_progress']);
        $this->assertSame(2, $summary['waitlisted']);
    }

    public function test_admin_dashboard_renders_kyc_counts_and_filtered_links(): void
    {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());

        $html = view('admin.dashboard', [
            'verificationStats' => [
                'kyc_approved' => 104,
                'payment_pending' => 101,
                'verified' => 3,
                'in_progress' => 47,
                'waitlisted' => 430,
            ],
            'stats' => [],
            'recentUsers' => collect(),
            'topWallets' => collect(),
            'quickActions' => [],
            'subscriptionStats' => [],
        ])->render();

        foreach ([
            'KYC Approved' => '104',
            'Payment Pending' => '101',
            'Verified Members' => '3',
            'KYC In Progress' => '47',
            'KYC Waiting List' => '430',
        ] as $label => $count) {
            $this->assertStringContainsString($label, $html);
            $this->assertStringContainsString('>' . $count . '</strong>', $html);
        }

        $this->assertLessThan(
            strpos($html, 'Total Users'),
            strpos($html, 'KYC Approved'),
            'KYC summary should appear before the generic dashboard metrics.'
        );

        $this->assertStringContainsString(
            route('admin.verified-members.index', ['status' => 'approved']),
            $html
        );
        $this->assertStringContainsString(
            route('admin.verified-members.index', ['status' => 'payment_pending']),
            $html
        );
        $this->assertStringContainsString(
            route('admin.verified-members.index', ['status' => 'verified']),
            $html
        );
        $this->assertStringContainsString(
            route('admin.verified-members.index', ['status' => 'in_progress']),
            $html
        );
        $this->assertStringContainsString(url('admin/verified-members/waitlist'), $html);
    }

    public function test_google_play_payment_without_store_price_is_not_displayed_as_zero_xxx(): void
    {
        $view = file_get_contents(resource_path('views/verified-members/index.blade.php'));

        $this->assertStringContainsString('$membership->payment_provider === \'google_play\' && (float) $membership->payment_amount <= 0', $view);
        $this->assertStringContainsString('Google Play · amount unavailable', $view);
    }

    public function test_verified_member_status_filter_exposes_aggregate_in_progress_cohort(): void
    {
        $view = file_get_contents(resource_path('views/verified-members/index.blade.php'));

        $this->assertStringContainsString("'in_progress' => 'KYC in progress'", $view);
    }

    public function test_admin_can_revoke_member_access_without_erasing_approved_kyc_or_payment(): void
    {
        DB::table('verified_memberships')->insert([
            'user_id' => 801,
            'kyc_status' => 'approved',
            'payment_status' => 'paid',
            'verified_at' => now(),
            'updated_at' => now(),
        ]);

        $service = (new \ReflectionClass(VerifiedMemberService::class))->newInstanceWithoutConstructor();
        $result = $service->revokeAdminAccess(801, 'member_access', 'Terms violation', 42);
        $membership = DB::table('verified_memberships')->where('user_id', 801)->first();

        $this->assertTrue($result['changed']);
        $this->assertNotNull($membership->suspended_at);
        $this->assertNull($membership->verified_at);
        $this->assertSame('approved', $membership->kyc_status);
        $this->assertSame('paid', $membership->payment_status);
    }

    public function test_admin_can_revoke_kyc_and_member_access_without_mutating_payment_history(): void
    {
        DB::table('verified_memberships')->insert([
            'user_id' => 802,
            'kyc_status' => 'approved',
            'payment_status' => 'paid',
            'verified_at' => now(),
            'updated_at' => now(),
        ]);

        $service = (new \ReflectionClass(VerifiedMemberService::class))->newInstanceWithoutConstructor();
        $result = $service->revokeAdminAccess(802, 'kyc_and_member_access', 'Identity review reopened', 42);
        $membership = DB::table('verified_memberships')->where('user_id', 802)->first();

        $this->assertTrue($result['changed']);
        $this->assertNotNull($membership->suspended_at);
        $this->assertNull($membership->verified_at);
        $this->assertSame('revoked', $membership->kyc_status);
        $this->assertSame('paid', $membership->payment_status);
    }

    public function test_admin_filters_show_kyc_revoked_and_membership_access_revoked_records(): void
    {
        DB::table('verified_memberships')->insert([
            ['user_id' => 811, 'kyc_status' => 'approved', 'payment_status' => 'paid', 'suspended_at' => now()],
            ['user_id' => 812, 'kyc_status' => 'revoked', 'payment_status' => 'paid', 'suspended_at' => now()],
            ['user_id' => 813, 'kyc_status' => 'approved', 'payment_status' => 'paid', 'suspended_at' => null],
        ]);

        $service = (new \ReflectionClass(VerifiedMemberService::class))->newInstanceWithoutConstructor();

        $this->assertSame([811, 812], $service->pendingForAdmin(['status' => 'access_revoked'])->pluck('user_id')->all());
        $this->assertSame([812], $service->pendingForAdmin(['status' => 'revoked'])->pluck('user_id')->all());
    }

    public function test_verified_member_row_exposes_scoped_revocation_with_required_reason(): void
    {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $membership = (object) [
            'user_id' => 821,
            'name' => 'Verified User',
            'email' => 'verified@example.test',
            'kyc_status' => 'approved',
            'kyc_country' => 'NGA',
            'kyc_provider' => 'didit',
            'didit_session_id' => 'didit-821',
            'didit_status' => 'Approved',
            'payment_status' => 'paid',
            'payment_amount' => 5,
            'payment_currency' => 'USD',
            'payment_provider' => 'google_play',
            'suspended_at' => null,
        ];
        $memberships = new \Illuminate\Pagination\LengthAwarePaginator([$membership], 1, 50);

        $html = view('verified-members.index', [
            'memberships' => $memberships,
            'summary' => ['total' => 1, 'in_progress' => 0, 'payment_pending' => 0, 'verified' => 1, 'waitlisted' => 0],
            'filters' => [],
        ])->render();

        $this->assertStringContainsString('Blockrium Verified member access only', $html);
        $this->assertStringContainsString('KYC approval and member access', $html);
        $this->assertStringContainsString('Required reason (recorded in admin audit)', $html);
        $this->assertStringContainsString(route('admin.verified-members.access.revoke', 821), $html);
    }

    public function test_submitted_didit_case_keeps_manual_review_action_visible_when_provider_status_is_stale(): void
    {
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $membership = (object) [
            'user_id' => 701,
            'name' => 'KYC Review User',
            'email' => 'kyc-review@example.test',
            'kyc_status' => 'submitted',
            'kyc_country' => 'NGA',
            'kyc_provider' => 'didit',
            'didit_session_id' => 'didit-session-701',
            'didit_status' => 'Processing',
            'payment_status' => 'pending',
            'payment_amount' => 5,
            'payment_currency' => 'USD',
            'payment_provider' => null,
            'suspended_at' => null,
        ];
        $memberships = new \Illuminate\Pagination\LengthAwarePaginator([$membership], 1, 50);

        $html = view('verified-members.index', [
            'memberships' => $memberships,
            'summary' => ['total' => 1, 'in_progress' => 1, 'payment_pending' => 0, 'verified' => 0, 'waitlisted' => 0],
            'filters' => [],
        ])->render();

        $this->assertStringContainsString('I confirmed approval in Didit', $html);
        $this->assertStringContainsString('Approve in Blockrium', $html);
        $this->assertStringNotContainsString('Waiting for Didit result', $html);
    }
}
