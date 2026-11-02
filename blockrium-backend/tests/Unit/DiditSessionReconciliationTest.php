<?php

namespace Tests\Unit;

use App\Services\DiditService;
use App\Services\IntegrationSettingsService;
use App\Services\VerifiedMemberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Client\Request;
use Tests\TestCase;

class DiditSessionReconciliationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => true,
            'services.didit.api_key' => 'test-didit-key',
            'services.didit.base_url' => 'https://verification.didit.me/v3',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Queue::fake();

        Schema::create('res_users', function ($table) {
            $table->increments('id');
            $table->string('device_id')->nullable();
        });
        Schema::create('verified_memberships', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->unique();
            $table->string('kyc_status')->default('not_started');
            $table->string('payment_status')->default('pending');
            $table->string('kyc_provider')->nullable();
            $table->string('kyc_reference')->nullable();
            $table->string('didit_session_id')->nullable()->unique();
            $table->string('didit_session_url')->nullable();
            $table->string('didit_status')->nullable();
            $table->string('kyc_country', 3)->nullable();
            $table->timestamp('kyc_started_at')->nullable();
            $table->timestamp('kyc_reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->decimal('payment_amount', 10, 2)->default(5);
            $table->string('payment_currency', 10)->default('USD');
            $table->timestamps();
        });
        Schema::create('notifications', function ($table) {
            $table->increments('id');
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->string('action')->nullable();
            $table->text('data')->nullable();
            $table->string('notification_type')->nullable();
            $table->string('status')->nullable();
            $table->string('n_type')->nullable();
            $table->string('notifiable_type')->nullable();
            $table->unsignedInteger('notifiable_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_sync_links_exact_blockrium_user_and_keeps_payment_pending_with_single_reminder(): void
    {
        DB::table('res_users')->insert(['id' => 42]);
        $session = [
            'session_id' => 'didit-42',
            'vendor_data' => 'user-42',
            'status' => 'Approved',
            'country' => 'NGA',
            'created_at' => '2026-10-18T12:00:00Z',
            'full_name' => 'Sensitive Test Name',
            'document_number' => 'DO-NOT-STORE',
        ];
        $this->fakeDiditSessions([$session]);

        $first = $this->service()->reconcileDiditSessions();
        $second = $this->service()->reconcileDiditSessions();
        $membership = DB::table('verified_memberships')->where('user_id', 42)->first();

        $this->assertSame('approved', $membership->kyc_status);
        $this->assertSame('pending', $membership->payment_status);
        $this->assertSame('didit-42', $membership->didit_session_id);
        $this->assertSame('NGA', $membership->kyc_country);
        $this->assertSame(1, $first['approved_payment_pending']);
        $this->assertSame(0, $second['approved_payment_pending']);
        $this->assertSame(1, DB::table('notifications')->where('notification_type', 'KYC_PAYMENT_REMINDER')->count());
        $this->assertStringNotContainsString('Sensitive Test Name', json_encode($membership));
        $this->assertStringNotContainsString('DO-NOT-STORE', json_encode($membership));
    }

    public function test_sync_does_not_downgrade_paid_verified_accounts_or_link_unknown_users(): void
    {
        DB::table('res_users')->insert([['id' => 7], ['id' => 8]]);
        DB::table('verified_memberships')->insert([
            'user_id' => 7,
            'kyc_status' => 'approved',
            'payment_status' => 'paid',
            'kyc_provider' => 'didit',
            'didit_session_id' => 'original-session',
            'didit_status' => 'Approved',
            'kyc_country' => 'NGA',
        ]);
        $this->fakeDiditSessions([
            ['session_id' => 'different-session', 'vendor_data' => 'user-7', 'status' => 'Declined', 'created_at' => '2026-10-19T12:00:00Z'],
            ['session_id' => 'unknown-user', 'vendor_data' => 'user-999', 'status' => 'In Progress'],
            ['session_id' => 'bad-link', 'vendor_data' => '42', 'status' => 'In Progress'],
        ]);

        $result = $this->service()->reconcileDiditSessions();

        $this->assertSame('approved', DB::table('verified_memberships')->where('user_id', 7)->value('kyc_status'));
        $this->assertSame('paid', DB::table('verified_memberships')->where('user_id', 7)->value('payment_status'));
        $this->assertSame(1, $result['protected']);
        $this->assertSame(2, $result['unlinked']);
        $this->assertSame(0, DB::table('verified_memberships')->where('user_id', 8)->count());
    }

    public function test_didit_session_listing_accepts_a_bounded_resume_offset(): void
    {
        Http::fake([
            'verification.didit.me/v3/sessions/*' => Http::response([
                'count' => 240,
                'next' => 'https://verification.didit.me/v3/sessions/?limit=100&offset=100',
                'results' => [],
            ]),
        ]);

        $result = (new DiditService(new IntegrationSettingsService()))->listRecentUserSessions(100, 100);

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['session_kind'] ?? null) === 'user'
                && ($query['limit'] ?? null) == 100
                && ($query['offset'] ?? null) == 100;
        });
        $this->assertSame(100, $result['offset']);
        $this->assertSame(200, $result['next_offset']);
        $this->assertSame(240, $result['count']);
        $this->assertTrue($result['has_more']);
    }

    public function test_reconciliation_processes_the_requested_didit_page(): void
    {
        DB::table('res_users')->insert(['id' => 91]);
        $page = array_fill(0, 100, [
            'session_id' => 'didit-91',
            'vendor_data' => 'user-91',
            'status' => 'In Progress',
            'created_at' => '2026-10-18T12:00:00Z',
        ]);
        Http::fake([
            'verification.didit.me/v3/sessions/*' => Http::response([
                'count' => 240,
                'next' => 'https://verification.didit.me/v3/sessions/?limit=100&offset=200',
                'results' => $page,
            ]),
        ]);

        $result = $this->service()->reconcileDiditSessions(100);

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['offset'] ?? null) == 100;
        });
        $membership = DB::table('verified_memberships')->where('user_id', 91)->first();
        $this->assertSame(100, $result['offset']);
        $this->assertSame(200, $result['next_offset']);
        $this->assertTrue($result['has_more']);
        $this->assertSame('submitted', $membership->kyc_status);
        $this->assertSame('didit-91', $membership->didit_session_id);
    }

    private function fakeDiditSessions(array $sessions): void
    {
        Http::fake([
            'verification.didit.me/v3/sessions/*' => Http::response([
                'count' => count($sessions),
                'next' => null,
                'results' => $sessions,
            ]),
        ]);
    }

    private function service(): VerifiedMemberService
    {
        $settings = new IntegrationSettingsService();
        $service = (new \ReflectionClass(VerifiedMemberService::class))->newInstanceWithoutConstructor();
        foreach (['settings' => $settings, 'didit' => new DiditService($settings)] as $propertyName => $value) {
            $property = new \ReflectionProperty(VerifiedMemberService::class, $propertyName);
            $property->setAccessible(true);
            $property->setValue($service, $value);
        }

        return $service;
    }
}
