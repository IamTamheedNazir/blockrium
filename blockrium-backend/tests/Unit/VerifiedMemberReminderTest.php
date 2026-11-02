<?php

namespace Tests\Unit;

use App\Services\VerifiedMemberService;
use App\Services\IntegrationSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class VerifiedMemberReminderTest extends TestCase
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
            $table->string('kyc_status');
            $table->string('payment_status')->nullable();
            $table->string('payment_provider')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('kyc_provider')->nullable();
            $table->string('kyc_reference')->nullable();
            $table->string('didit_status')->nullable();
            $table->string('kyc_country')->nullable();
            $table->string('didit_session_id')->nullable();
            $table->string('didit_session_url')->nullable();
            $table->string('didit_event_id')->nullable();
            $table->timestamp('kyc_started_at')->nullable();
            $table->decimal('payment_amount', 10, 2)->default(5);
            $table->string('payment_currency', 10)->default('USD');
            $table->unsignedInteger('kyc_reviewed_by_admin_id')->nullable();
            $table->timestamp('kyc_reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('res_users', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('username')->nullable();
            $table->string('device_id')->nullable();
        });

        Schema::create('notifications', function ($table) {
            $table->increments('id');
            $table->string('type')->nullable();
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

        Schema::create('kyc_waitlist_entries', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->unique();
            $table->string('country_code', 3);
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('verification_payments', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('provider');
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_payment_key')->nullable()->unique();
            $table->string('status');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('verification_support_cases', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('verification_payment_id')->nullable();
            $table->string('type')->default('payment_issue');
            $table->string('status')->default('open');
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency')->nullable();
            $table->text('user_message')->nullable();
            $table->text('admin_notes')->nullable();
            $table->unsignedInteger('resolved_by_admin_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('didit_webhook_events', function ($table) {
            $table->increments('id');
            $table->string('event_id')->unique();
            $table->string('webhook_type');
            $table->string('session_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_didit_webhook_approval_adds_one_payment_reminder_and_keeps_membership_unpaid(): void
    {
        $this->insertDiditCase(85, 'in_review', 'In Review');
        $service = $this->service();
        $payload = [
            'event_id' => 'didit-event-85',
            'webhook_type' => 'status.updated',
            'session_id' => 'didit-session-85',
            'status' => 'Approved',
            'decision' => [
                'id_verifications' => [
                    ['issuing_state' => 'NGA'],
                ],
            ],
        ];

        $result = $service->handleDiditWebhook($payload);
        $replay = $service->handleDiditWebhook($payload);
        $subsequentApprovalEvent = $service->handleDiditWebhook(array_merge($payload, [
            'event_id' => 'didit-event-85-followup',
        ]));
        $membership = DB::table('verified_memberships')->where('user_id', 85)->first();

        $this->assertFalse($result['ignored']);
        $this->assertTrue($replay['ignored']);
        $this->assertFalse($subsequentApprovalEvent['ignored']);
        $this->assertSame('approved', $membership->kyc_status);
        $this->assertSame('pending', $membership->payment_status);
        $this->assertFalse($service->status(85)['is_verified']);
        $this->assertSame('payment_required', $service->status(85)['next_action']);
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', 85)->count());
        $this->assertSame('KYC_PAYMENT_REMINDER', DB::table('notifications')->where('notifiable_id', 85)->value('notification_type'));
    }

    public function test_approved_unpaid_case_gets_a_payment_reminder(): void
    {
        $this->insertCase(71, 'approved', 'pending');

        $result = $this->service()->sendAdminReminder(71);
        $notification = DB::table('notifications')->first();

        $this->assertSame('KYC_PAYMENT_REMINDER', $result['type']);
        $this->assertFalse($result['push_queued']);
        $this->assertSame('KYC_PAYMENT_REMINDER', $notification->notification_type);
        $this->assertSame(71, (int) $notification->notifiable_id);
        $payload = json_decode($notification->data, true);
        $this->assertIsArray($payload);
        $this->assertStringContainsString('Blockrium Verified membership', $payload['message'] ?? '');
        $this->assertStringNotContainsString('KYC fee', $payload['message'] ?? '');
    }

    public function test_rejected_case_gets_a_kyc_retry_request_not_a_payment_request(): void
    {
        $this->insertCase(72, 'rejected', 'pending');

        $result = $this->service()->sendAdminReminder(72);

        $this->assertSame('KYC_RETRY_REQUEST', $result['type']);
        $this->assertSame('KYC_RETRY_REQUEST', DB::table('notifications')->value('notification_type'));
    }

    public function test_invited_waitlist_user_can_receive_a_resend_invitation(): void
    {
        DB::table('res_users')->insert(['id' => 78, 'device_id' => null]);
        DB::table('kyc_waitlist_entries')->insert([
            'user_id' => 78,
            'country_code' => 'NG',
            'status' => 'invited',
        ]);

        $result = $this->service()->resendWaitlistInvitation(78);

        $this->assertSame(1, $result['notifications_created']);
        $this->assertSame('KYC_WAITLIST_INVITATION', DB::table('notifications')->value('notification_type'));
        $this->assertSame('invited', DB::table('kyc_waitlist_entries')->where('user_id', 78)->value('status'));
    }

    public function test_waitlist_invitation_resend_rejects_users_already_in_kyc_review(): void
    {
        $this->insertCase(79, 'in_review', 'pending');
        DB::table('kyc_waitlist_entries')->insert([
            'user_id' => 79,
            'country_code' => 'NG',
            'status' => 'started',
        ]);

        try {
            $this->service()->resendWaitlistInvitation(79);
            $this->fail('A user already in KYC review must not get a resend invitation.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }

        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_verified_or_in_review_cases_cannot_receive_a_reminder(): void
    {
        $this->insertCase(73, 'verified', 'paid');
        $this->insertCase(74, 'in_review', 'pending');
        $service = $this->service();

        foreach ([73, 74] as $userId) {
            try {
                $service->sendAdminReminder($userId);
                $this->fail('Reminder should have been rejected for user ' . $userId . '.');
            } catch (HttpException $error) {
                $this->assertSame(409, $error->getStatusCode());
            }
        }

        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_admin_can_confirm_approval_completed_in_didit_and_user_stays_payment_pending(): void
    {
        $this->insertDiditCase(75, 'in_review', 'In Review');

        $result = $this->service()->approveKyc(75, 9, true);
        $this->service()->approveKyc(75, 9);

        $membership = DB::table('verified_memberships')->where('user_id', 75)->first();
        $notification = DB::table('notifications')->first();
        $this->assertSame('approved', $membership->kyc_status);
        $this->assertSame('Approved', $membership->didit_status);
        $this->assertSame('pending', $membership->payment_status);
        $this->assertSame(9, (int) $membership->kyc_reviewed_by_admin_id);
        $this->assertFalse($result['is_verified']);
        $this->assertSame('payment_required', $result['next_action']);
        $this->assertSame('KYC_PAYMENT_REMINDER', $notification->notification_type);
        $this->assertSame(1, DB::table('notifications')->count());
    }

    public function test_admin_approval_reads_and_updates_membership_inside_transaction(): void
    {
        $this->insertDiditCase(83, 'in_review', 'In Review');
        $membershipReadWasTransactional = false;

        DB::listen(function ($query) use (&$membershipReadWasTransactional) {
            if (strpos(strtolower($query->sql), 'verified_memberships') !== false
                && strpos(strtolower($query->sql), 'select') === 0
                && DB::transactionLevel() > 0) {
                $membershipReadWasTransactional = true;
            }
        });

        $result = $this->service()->approveKyc(83, 9, true);

        $this->assertTrue($membershipReadWasTransactional, 'The approval check must use a fresh membership row inside a transaction.');
        $this->assertFalse($result['is_verified']);
        $this->assertSame('pending', DB::table('verified_memberships')->where('user_id', 83)->value('payment_status'));
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', 83)->count());
    }

    public function test_manual_didit_confirmation_requires_a_real_didit_session(): void
    {
        $this->insertDiditCase(76, 'in_review', 'In Review', false);

        try {
            $this->service()->approveKyc(76, 9, true);
            $this->fail('Manual approval without a Didit session should be rejected.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }

        $this->assertSame('in_review', DB::table('verified_memberships')->where('user_id', 76)->value('kyc_status'));
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_manual_didit_confirmation_cannot_override_a_terminal_decline(): void
    {
        $this->insertDiditCase(86, 'in_review', 'Declined');

        try {
            $this->service()->approveKyc(86, 9, true);
            $this->fail('Manual approval must not override a terminal Didit decline.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }

        $this->assertSame('in_review', DB::table('verified_memberships')->where('user_id', 86)->value('kyc_status'));
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_manual_didit_confirmation_explains_when_document_country_has_not_synced(): void
    {
        $this->insertDiditCase(84, 'in_review', 'In Review');
        DB::table('verified_memberships')->where('user_id', 84)->update(['kyc_country' => null]);

        try {
            $this->service()->approveKyc(84, 9, true);
            $this->fail('Manual approval without a synced document country should be rejected.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
            $this->assertSame(
                'The Didit session has no document country in Blockrium yet. Sync Didit sessions before approving this user.',
                $error->getMessage()
            );
        }

        $this->assertSame('in_review', DB::table('verified_memberships')->where('user_id', 84)->value('kyc_status'));
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_admin_reconciliation_records_the_actual_confirmed_amount_and_currency(): void
    {
        $this->insertCase(77, 'approved', 'pending');
        config([
            'services.dodo.verification_amount' => 5,
            'services.dodo.verification_currency' => 'USD',
        ]);

        $result = $this->service()->confirmPayment(77, 'Bank transfer', 'BANK-REF-77', 3.50, 'EUR');

        $payment = DB::table('verification_payments')->where('user_id', 77)->first();
        $membership = DB::table('verified_memberships')->where('user_id', 77)->first();
        $this->assertTrue($result['is_verified']);
        $this->assertSame('paid', $membership->payment_status);
        $this->assertSame('BANK-REF-77', $membership->payment_reference);
        $this->assertEquals(3.50, (float) $payment->amount);
        $this->assertSame('EUR', $payment->currency);
    }

    public function test_payment_confirmation_replay_is_idempotent_and_preserves_the_original_amount(): void
    {
        $this->insertCase(81, 'approved', 'pending');
        $service = $this->service();

        $service->confirmPayment(81, 'Bank transfer', 'BANK-REF-81', 3.50, 'EUR');
        $service->confirmPayment(81, 'Bank transfer', 'BANK-REF-81', 3.50, 'EUR');

        $this->assertSame(1, DB::table('verification_payments')->count());
        $this->assertEquals(3.50, (float) DB::table('verification_payments')->value('amount'));
        $this->assertSame('EUR', DB::table('verification_payments')->value('currency'));
    }

    public function test_reused_payment_reference_with_changed_amount_is_rejected_without_mutation(): void
    {
        $this->insertCase(82, 'approved', 'pending');
        $service = $this->service();
        $service->confirmPayment(82, 'Bank transfer', 'BANK-REF-82', 3.50, 'EUR');

        foreach ([[4.50, 'EUR'], [3.50, 'USD']] as [$amount, $currency]) {
            try {
                $service->confirmPayment(82, 'Bank transfer', 'BANK-REF-82', $amount, $currency);
                $this->fail('A reused payment reference with changed details should conflict.');
            } catch (HttpException $error) {
                $this->assertSame(409, $error->getStatusCode());
            }
        }

        $this->assertSame(1, DB::table('verification_payments')->count());
        $this->assertEquals(3.50, (float) DB::table('verification_payments')->value('amount'));
        $this->assertEquals(3.50, (float) DB::table('verified_memberships')->where('user_id', 82)->value('payment_amount'));
    }

    public function test_admin_payment_confirmation_rejects_invalid_currency_and_subcent_amounts(): void
    {
        $this->insertCase(80, 'approved', 'pending');

        foreach ([['3.50', 'U$D'], ['3.501', 'USD']] as [$amount, $currency]) {
            try {
                $this->service()->confirmPayment(80, 'Bank transfer', 'INVALID-' . $amount . $currency, (float) $amount, $currency);
                $this->fail('Invalid payment amount or currency should be rejected.');
            } catch (HttpException $error) {
                $this->assertSame(422, $error->getStatusCode());
            }
        }

        $this->assertSame('pending', DB::table('verified_memberships')->where('user_id', 80)->value('payment_status'));
        $this->assertSame(0, DB::table('verification_payments')->count());
    }

    public function test_payment_support_responses_never_expose_internal_admin_notes(): void
    {
        DB::table('res_users')->insert([
            'id' => 84,
            'name' => 'Support User',
            'email' => 'support-user@example.test',
            'username' => 'support-user',
            'device_id' => null,
        ]);
        DB::table('verified_memberships')->insert([
            'user_id' => 84,
            'kyc_status' => 'approved',
            'payment_status' => 'pending',
        ]);
        $paymentId = DB::table('verification_payments')->insertGetId([
            'user_id' => 84,
            'provider' => 'google_play',
            'provider_payment_id' => 'gp-84',
            'status' => 'pending',
            'amount' => 5,
            'currency' => 'USD',
        ]);
        $service = $this->service();

        $created = $service->createPaymentSupportCase(84, $paymentId, 'GP-REF-84', 'Charged but still pending');
        DB::table('verification_support_cases')->where('id', $created['id'])->update([
            'admin_notes' => 'Internal reconciliation note',
        ]);
        $listed = $service->paymentSupportCases(84)->first();
        $adminUpdate = $service->updatePaymentSupportCase($created['id'], 'in_review', 'Internal reconciliation note', 9);
        $adminQueue = $service->adminPaymentSupportCases('in_review');

        foreach ([$created, $listed, $adminUpdate] as $payload) {
            $this->assertArrayNotHasKey('admin_notes', $payload);
        }
        $this->assertSame('Charged but still pending', $created['message']);
        $this->assertSame('in_review', $adminUpdate['status']);
        $this->assertSame('Internal reconciliation note', DB::table('verification_support_cases')->value('admin_notes'));
        $this->assertCount(1, $adminQueue);
        $this->assertSame('Support User', $adminQueue->first()->name);
        $this->assertSame('support-user@example.test', $adminQueue->first()->email);
        $this->assertSame('Internal reconciliation note', $adminQueue->first()->admin_notes);
        $this->assertSame('pending', $adminQueue->first()->payment_record_status);
    }

    private function insertCase(int $userId, string $kycStatus, string $paymentStatus): void
    {
        DB::table('res_users')->insert(['id' => $userId]);
        DB::table('verified_memberships')->insert([
            'user_id' => $userId,
            'kyc_status' => $kycStatus,
            'payment_status' => $paymentStatus,
        ]);
    }

    private function insertDiditCase(int $userId, string $kycStatus, string $diditStatus, bool $withSession = true): void
    {
        DB::table('res_users')->insert(['id' => $userId]);
        DB::table('verified_memberships')->insert([
            'user_id' => $userId,
            'kyc_status' => $kycStatus,
            'payment_status' => 'pending',
            'kyc_provider' => 'didit',
            'didit_status' => $diditStatus,
            'kyc_country' => 'NG',
            'didit_session_id' => $withSession ? 'didit-session-' . $userId : null,
        ]);
    }

    private function service(): VerifiedMemberService
    {
        $service = (new \ReflectionClass(VerifiedMemberService::class))->newInstanceWithoutConstructor();
        $settings = $this->createMock(IntegrationSettingsService::class);
        $settings->method('get')->willReturnCallback(function ($key, $fallback = null) {
            return $fallback;
        });
        $property = new \ReflectionProperty(VerifiedMemberService::class, 'settings');
        $property->setAccessible(true);
        $property->setValue($service, $settings);

        return $service;
    }
}
