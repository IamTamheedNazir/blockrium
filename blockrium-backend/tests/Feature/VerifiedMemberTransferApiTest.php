<?php

namespace Tests\Feature;

use App\Models\ResUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VerifiedMemberTransferApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => true,
            'jwt.secret' => 'blockrium-feature-test-secret',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('verified_memberships', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('kyc_status');
            $table->string('payment_status');
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
            $table->decimal('amount', 20, 8);
            $table->decimal('fee_amount', 20, 8)->default(0);
            $table->decimal('total_debit', 20, 8)->default(0);
            $table->unsignedSmallInteger('fee_rate_basis_points')->default(200);
            $table->string('status', 24)->default('completed');
            $table->string('reference', 80)->unique();
            $table->string('idempotency_key', 120)->unique();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        foreach ([11, 22, 33] as $userId) {
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
            ['userid' => 11, 'totaltoken' => '100.00000000'],
            ['userid' => 22, 'totaltoken' => '2.00000000'],
            ['userid' => 33, 'totaltoken' => '0.00000000'],
        ]);
        DB::table('integration_settings')->insert([
            'key' => 'brc_transfer_treasury_username',
            'value' => 'fee_treasury',
            'is_encrypted' => false,
        ]);
    }

    public function test_authenticated_transfer_api_debits_fee_and_returns_wallet_ledger_result(): void
    {
        $user = new ResUser();
        $user->setAttribute('id', 11);
        $this->actingAs($user, 'api_res_user');

        $this->postJson('/api/auth/brc/transfers', [
            'recipient_username' => 'recipient_user',
            'amount' => '10.00000000',
            'idempotency_key' => 'feature-transfer-1',
            'note' => 'Verified member transfer',
        ])
            ->assertOk()
            ->assertJsonPath('status', 1)
            ->assertJsonPath('data.amount', '10.00000000')
            ->assertJsonPath('data.fee_amount', '0.20000000')
            ->assertJsonPath('data.total_debit', '10.20000000');

        $this->assertSame('89.80000000', DB::table('tbluserwallet')->where('userid', 11)->value('totaltoken'));
        $this->assertSame('12.00000000', DB::table('tbluserwallet')->where('userid', 22)->value('totaltoken'));
        $this->assertSame('0.20000000', DB::table('tbluserwallet')->where('userid', 33)->value('totaltoken'));
        $this->assertSame(1, DB::table('brc_transfers')->count());
    }
}
