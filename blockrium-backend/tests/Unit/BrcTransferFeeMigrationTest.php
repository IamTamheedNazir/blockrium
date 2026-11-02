<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\BrcAmount;
use Tests\TestCase;

class BrcTransferFeeMigrationTest extends TestCase
{
    public function test_migration_preserves_old_transfers_as_zero_fee_and_adds_fee_columns(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('brc_transfers', function ($table) {
            $table->bigIncrements('id');
            $table->decimal('amount', 20, 8);
        });
        DB::table('brc_transfers')->insert(['amount' => '25.50000000']);

        require_once database_path('migrations/2026_10_19_000001_add_fee_accounting_to_brc_transfers.php');
        (new \AddFeeAccountingToBrcTransfers())->up();

        $transfer = DB::table('brc_transfers')->first();
        $this->assertSame('0.00000000', BrcAmount::fromUnits(BrcAmount::toUnits($transfer->fee_amount)));
        $this->assertSame('25.50000000', BrcAmount::fromUnits(BrcAmount::toUnits($transfer->total_debit)));
        $this->assertSame(0, (int) $transfer->fee_rate_basis_points);

        require_once database_path('migrations/2026_10_19_000002_add_fee_wallet_user_id_to_brc_transfers.php');
        (new \AddFeeWalletUserIdToBrcTransfers())->up();

        $this->assertTrue(Schema::hasColumn('brc_transfers', 'fee_wallet_user_id'));
        $this->assertNull(DB::table('brc_transfers')->value('fee_wallet_user_id'));
    }
}
