<?php

namespace Tests\Unit;

use App\Services\DiditService;
use App\Services\IntegrationSettingsService;
use App\Services\VerifiedMemberService;
use App\Services\WalletRewardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class VerifiedMemberTransferPrecisionTest extends TestCase
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

        Schema::create('res_users', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->boolean('is_active')->default(true);
        });
        Schema::create('verified_memberships', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('kyc_status');
            $table->string('payment_status');
            $table->string('didit_status')->nullable();
            $table->string('kyc_country')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
        });
        Schema::create('integration_settings', function ($table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();
        });
        Schema::create('brc_usernames', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('username')->unique();
            $table->string('normalized_username')->unique();
        });
        Schema::create('tbluserwallet', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('userid');
            $table->string('totaltoken', 40);
        });
        Schema::create('brc_transfers', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sender_user_id');
            $table->unsignedBigInteger('recipient_user_id');
            $table->unsignedBigInteger('fee_wallet_user_id')->nullable();
            $table->string('amount', 40);
            $table->string('fee_amount', 40)->default('0.00000000');
            $table->string('total_debit', 40)->default('0.00000000');
            $table->unsignedSmallInteger('fee_rate_basis_points')->default(200);
            $table->string('status');
            $table->string('reference')->unique();
            $table->string('idempotency_key')->unique();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        foreach ([11, 22, 33] as $userId) {
            DB::table('res_users')->insert(['id' => $userId, 'is_active' => 1]);
            DB::table('verified_memberships')->insert([
                'user_id' => $userId,
                'kyc_status' => 'approved',
                'payment_status' => 'paid',
            ]);
        }
        DB::table('brc_usernames')->insert([
            ['user_id' => 11, 'username' => 'sender_user', 'normalized_username' => 'sender_user'],
            ['user_id' => 22, 'username' => 'recipient_user', 'normalized_username' => 'recipient_user'],
            ['user_id' => 33, 'username' => 'fee_treasury', 'normalized_username' => 'fee_treasury'],
        ]);
        DB::table('tbluserwallet')->insert([
            ['userid' => 11, 'totaltoken' => '123456789.00000002'],
            ['userid' => 22, 'totaltoken' => '0.00000001'],
            ['userid' => 33, 'totaltoken' => '0.00000000'],
        ]);
        DB::table('integration_settings')->insert([
            'key' => 'brc_transfer_treasury_username',
            'value' => 'fee_treasury',
            'is_encrypted' => false,
        ]);
    }

    public function test_transfer_rejects_amounts_below_the_fee_precision_minimum(): void
    {
        $settings = new IntegrationSettingsService();
        $service = new VerifiedMemberService(
            new WalletRewardService(),
            new DiditService($settings),
            $settings
        );

        try {
            $service->transferByUsername(11, 'recipient_user', '0.00000049', 'below-fee-minimum');
            $this->fail('Transfers below the fee-precision minimum must be rejected.');
        } catch (HttpException $error) {
            $this->assertSame(422, $error->getStatusCode());
        }

        $this->assertSame('123456789.00000002', DB::table('tbluserwallet')->where('userid', 11)->value('totaltoken'));
        $this->assertSame('0.00000001', DB::table('tbluserwallet')->where('userid', 22)->value('totaltoken'));
        $this->assertSame('0.00000000', DB::table('tbluserwallet')->where('userid', 33)->value('totaltoken'));
        $this->assertSame(0, DB::table('brc_transfers')->count());
    }

    public function test_minimum_transfer_charges_a_fee_without_exceeding_two_percent(): void
    {
        $settings = new IntegrationSettingsService();
        $service = new VerifiedMemberService(
            new WalletRewardService(),
            new DiditService($settings),
            $settings
        );

        $result = $service->transferByUsername(11, 'recipient_user', '0.00000050', 'minimum-fee-transfer');

        $this->assertSame('0.00000050', $result['amount']);
        $this->assertSame('0.00000001', $result['fee_amount']);
        $this->assertSame('0.00000051', $result['total_debit']);
        $this->assertSame('123456788.99999951', DB::table('tbluserwallet')->where('userid', 11)->value('totaltoken'));
        $this->assertSame('0.00000051', DB::table('tbluserwallet')->where('userid', 22)->value('totaltoken'));
        $this->assertSame('0.00000001', DB::table('tbluserwallet')->where('userid', 33)->value('totaltoken'));
    }

    public function test_transfer_rejects_amounts_that_cannot_represent_an_exact_two_percent_fee(): void
    {
        $settings = new IntegrationSettingsService();
        $service = new VerifiedMemberService(
            new WalletRewardService(),
            new DiditService($settings),
            $settings
        );

        try {
            $service->transferByUsername(11, 'recipient_user', '0.00000075', 'fee-step-invalid');
            $this->fail('Amounts outside the exact-fee step must be rejected.');
        } catch (HttpException $error) {
            $this->assertSame(422, $error->getStatusCode());
        }

        $this->assertSame(0, DB::table('brc_transfers')->count());
    }

    public function test_transfer_step_charges_exactly_two_percent(): void
    {
        $settings = new IntegrationSettingsService();
        $service = new VerifiedMemberService(
            new WalletRewardService(),
            new DiditService($settings),
            $settings
        );

        $result = $service->transferByUsername(11, 'recipient_user', '0.00001250', 'fee-step-valid');

        $this->assertSame('0.00000025', $result['fee_amount']);
        $this->assertSame('0.00001275', $result['total_debit']);
        $this->assertSame('0.00001251', DB::table('tbluserwallet')->where('userid', 22)->value('totaltoken'));
        $this->assertSame('0.00000025', DB::table('tbluserwallet')->where('userid', 33)->value('totaltoken'));
    }

    public function test_transfer_verification_is_read_inside_the_balance_transaction(): void
    {
        $membershipReadWasTransactional = false;
        DB::listen(function ($query) use (&$membershipReadWasTransactional) {
            if (strpos(strtolower($query->sql), 'verified_memberships') !== false
                && strpos(strtolower($query->sql), 'select') === 0
                && DB::transactionLevel() > 0) {
                $membershipReadWasTransactional = true;
            }
        });

        $this->service()->transferByUsername(11, 'recipient_user', '10', 'eligibility-locked-transfer');

        $this->assertTrue($membershipReadWasTransactional, 'Sender, recipient, and fee-wallet eligibility must be checked within the balance transaction.');
    }

    public function test_unverified_or_suspended_transfer_participants_cannot_move_balances(): void
    {
        $service = $this->service();

        DB::table('verified_memberships')->where('user_id', 11)->update(['payment_status' => 'pending']);
        try {
            $service->transferByUsername(11, 'recipient_user', '10', 'unpaid-sender-transfer');
            $this->fail('An unpaid sender must not be able to transfer.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        DB::table('verified_memberships')->where('user_id', 11)->update(['payment_status' => 'paid']);

        DB::table('verified_memberships')->where('user_id', 22)->update(['suspended_at' => now()]);
        try {
            $service->transferByUsername(11, 'recipient_user', '10', 'suspended-recipient-transfer');
            $this->fail('A suspended recipient must not receive a transfer.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame('123456789.00000002', DB::table('tbluserwallet')->where('userid', 11)->value('totaltoken'));
        $this->assertSame('0.00000001', DB::table('tbluserwallet')->where('userid', 22)->value('totaltoken'));
        $this->assertSame('0.00000000', DB::table('tbluserwallet')->where('userid', 33)->value('totaltoken'));
        $this->assertSame(0, DB::table('brc_transfers')->count());
    }

    public function test_inactive_sender_or_recipient_cannot_transfer_brc(): void
    {
        $service = $this->service();

        foreach ([11, 22] as $inactiveUserId) {
            DB::table('res_users')->where('id', $inactiveUserId)->update(['is_active' => 0]);

            try {
                $service->transferByUsername(11, 'recipient_user', '10', 'inactive-account-' . $inactiveUserId);
                $this->fail('An inactive transfer participant must be rejected.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }

            DB::table('res_users')->where('id', $inactiveUserId)->update(['is_active' => 1]);
        }

        $this->assertSame('123456789.00000002', DB::table('tbluserwallet')->where('userid', 11)->value('totaltoken'));
        $this->assertSame('0.00000001', DB::table('tbluserwallet')->where('userid', 22)->value('totaltoken'));
        $this->assertSame(0, DB::table('brc_transfers')->count());
    }

    public function test_transfer_debits_two_percent_on_top_and_credits_full_amount(): void
    {
        $settings = new IntegrationSettingsService();
        $service = new VerifiedMemberService(
            new WalletRewardService(),
            new DiditService($settings),
            $settings
        );

        $result = $service->transferByUsername(11, 'recipient_user', '10.00000000', 'fee-transfer-1');

        $this->assertSame('0.20000000', $result['fee_amount']);
        $this->assertSame('10.20000000', $result['total_debit']);
        $this->assertSame('123456778.80000002', $result['balance']);
        $this->assertSame('10.00000001', DB::table('tbluserwallet')->where('userid', 22)->value('totaltoken'));
        $this->assertSame('0.20000000', DB::table('tbluserwallet')->where('userid', 33)->value('totaltoken'));
        $this->assertSame('0.20000000', DB::table('brc_transfers')->value('fee_amount'));
        $this->assertSame('10.20000000', DB::table('brc_transfers')->value('total_debit'));
        $this->assertSame(33, (int) DB::table('brc_transfers')->value('fee_wallet_user_id'));
    }

    public function test_replaying_transfer_key_does_not_charge_fee_twice(): void
    {
        $settings = new IntegrationSettingsService();
        $service = new VerifiedMemberService(
            new WalletRewardService(),
            new DiditService($settings),
            $settings
        );

        $first = $service->transferByUsername(11, 'recipient_user', '10', 'fee-transfer-replay');
        $second = $service->transferByUsername(11, 'recipient_user', '10', 'fee-transfer-replay');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame('123456778.80000002', DB::table('tbluserwallet')->where('userid', 11)->value('totaltoken'));
        $this->assertSame('10.00000001', DB::table('tbluserwallet')->where('userid', 22)->value('totaltoken'));
        $this->assertSame('0.20000000', DB::table('tbluserwallet')->where('userid', 33)->value('totaltoken'));
        $this->assertSame(1, DB::table('brc_transfers')->count());
    }

    public function test_replaying_completed_transfer_returns_original_result_after_sender_membership_is_suspended(): void
    {
        $service = $this->service();
        $first = $service->transferByUsername(11, 'recipient_user', '10', 'suspended-transfer-replay');
        DB::table('verified_memberships')->where('user_id', 11)->update(['suspended_at' => now()]);

        $replay = $service->transferByUsername(11, 'recipient_user', '10', 'suspended-transfer-replay');

        $this->assertSame($first['id'], $replay['id']);
        $this->assertSame('123456778.80000002', DB::table('tbluserwallet')->where('userid', 11)->value('totaltoken'));
        $this->assertSame('10.00000001', DB::table('tbluserwallet')->where('userid', 22)->value('totaltoken'));
        $this->assertSame('0.20000000', DB::table('tbluserwallet')->where('userid', 33)->value('totaltoken'));
        $this->assertSame(1, DB::table('brc_transfers')->count());
    }

    public function test_reusing_transfer_key_for_a_different_request_is_rejected(): void
    {
        $settings = new IntegrationSettingsService();
        $service = new VerifiedMemberService(
            new WalletRewardService(),
            new DiditService($settings),
            $settings
        );
        DB::table('res_users')->insert(['id' => 44, 'is_active' => 1]);
        DB::table('verified_memberships')->insert([
            'user_id' => 44,
            'kyc_status' => 'approved',
            'payment_status' => 'paid',
        ]);
        DB::table('brc_usernames')->insert([
            'user_id' => 44,
            'username' => 'another_user',
            'normalized_username' => 'another_user',
        ]);
        DB::table('tbluserwallet')->insert(['userid' => 44, 'totaltoken' => '0.00000000']);

        $service->transferByUsername(11, 'recipient_user', '10', 'conflicting-transfer-key');

        try {
            $service->transferByUsername(11, 'another_user', '11', 'conflicting-transfer-key');
            $this->fail('A transfer key must not be reused with different transfer details.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertSame('123456778.80000002', DB::table('tbluserwallet')->where('userid', 11)->value('totaltoken'));
        $this->assertSame('10.00000001', DB::table('tbluserwallet')->where('userid', 22)->value('totaltoken'));
        $this->assertSame('0.00000000', DB::table('tbluserwallet')->where('userid', 44)->value('totaltoken'));
        $this->assertSame(1, DB::table('brc_transfers')->count());
    }

    public function test_fee_bearing_transfer_is_rejected_without_treasury_configuration(): void
    {
        DB::table('integration_settings')->where('key', 'brc_transfer_treasury_username')->delete();
        $service = $this->service();

        try {
            $service->transferByUsername(11, 'recipient_user', '10', 'missing-fee-treasury');
            $this->fail('Expected transfer to require a configured fee wallet.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }

        $this->assertSame('123456789.00000002', DB::table('tbluserwallet')->where('userid', 11)->value('totaltoken'));
        $this->assertSame('0.00000001', DB::table('tbluserwallet')->where('userid', 22)->value('totaltoken'));
        $this->assertSame('0.00000000', DB::table('tbluserwallet')->where('userid', 33)->value('totaltoken'));
        $this->assertSame(0, DB::table('brc_transfers')->count());
    }

    public function test_fee_wallet_cannot_be_sender_or_recipient(): void
    {
        DB::table('integration_settings')->where('key', 'brc_transfer_treasury_username')->update(['value' => 'recipient_user']);
        $service = $this->service();

        try {
            $service->transferByUsername(11, 'recipient_user', '10', 'recipient-is-fee-wallet');
            $this->fail('Expected recipient/treasury collision to be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }

        $this->assertSame('123456789.00000002', DB::table('tbluserwallet')->where('userid', 11)->value('totaltoken'));
        $this->assertSame('0.00000001', DB::table('tbluserwallet')->where('userid', 22)->value('totaltoken'));
        $this->assertSame(0, DB::table('brc_transfers')->count());
    }

    public function test_unpaid_fee_wallet_is_rejected_without_changing_balances(): void
    {
        DB::table('verified_memberships')->where('user_id', 33)->update(['payment_status' => 'pending']);
        $service = $this->service();

        try {
            $service->transferByUsername(11, 'recipient_user', '10', 'unpaid-fee-wallet');
            $this->fail('Expected an unpaid fee wallet to be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(503, $exception->getStatusCode());
        }

        $this->assertSame('123456789.00000002', DB::table('tbluserwallet')->where('userid', 11)->value('totaltoken'));
        $this->assertSame('0.00000001', DB::table('tbluserwallet')->where('userid', 22)->value('totaltoken'));
        $this->assertSame('0.00000000', DB::table('tbluserwallet')->where('userid', 33)->value('totaltoken'));
        $this->assertSame(0, DB::table('brc_transfers')->count());
    }

    public function test_sender_must_have_balance_for_amount_plus_fee(): void
    {
        DB::table('tbluserwallet')->where('userid', 11)->update(['totaltoken' => '10.10000000']);
        $settings = new IntegrationSettingsService();
        $service = new VerifiedMemberService(
            new WalletRewardService(),
            new DiditService($settings),
            $settings
        );

        try {
            $service->transferByUsername(11, 'recipient_user', '10', 'fee-transfer-insufficient');
            $this->fail('Expected insufficient balance to be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame('10.10000000', DB::table('tbluserwallet')->where('userid', 11)->value('totaltoken'));
        $this->assertSame(0, DB::table('brc_transfers')->count());
    }

    public function test_verification_status_returns_the_spendable_transfer_wallet_balance(): void
    {
        $service = $this->service();

        $status = $service->status(11);

        $this->assertSame('123456789.00000002', $status['brc_transfer_balance']);
        DB::table('verified_memberships')->where('user_id', 11)->update(['payment_status' => 'pending']);
        $unverifiedStatus = $service->status(11);
        $this->assertSame('0.00000000', $unverifiedStatus['brc_transfer_balance']);

        DB::table('verified_memberships')->where('user_id', 11)->update([
            'kyc_status' => 'approved',
            'payment_status' => 'paid',
            'suspended_at' => now(),
        ]);
        $suspendedStatus = $service->status(11);
        $this->assertTrue($suspendedStatus['access_suspended']);
        $this->assertFalse($suspendedStatus['is_verified']);
    }

    private function service(): VerifiedMemberService
    {
        $settings = new IntegrationSettingsService();
        return new VerifiedMemberService(
            new WalletRewardService(),
            new DiditService($settings),
            $settings
        );
    }
}
