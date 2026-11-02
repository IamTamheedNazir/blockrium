<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddFeeAccountingToBrcTransfers extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('brc_transfers')) {
            return;
        }

        Schema::table('brc_transfers', function (Blueprint $table) {
            if (!Schema::hasColumn('brc_transfers', 'fee_amount')) {
                $table->decimal('fee_amount', 20, 8)->default('0.00000000')->after('amount');
            }
            if (!Schema::hasColumn('brc_transfers', 'total_debit')) {
                $table->decimal('total_debit', 20, 8)->default('0.00000000')->after('fee_amount');
            }
            if (!Schema::hasColumn('brc_transfers', 'fee_rate_basis_points')) {
                $table->unsignedSmallInteger('fee_rate_basis_points')->default(200)->after('total_debit');
            }
        });

        DB::table('brc_transfers')
            ->where('total_debit', '0.00000000')
            ->update([
                'total_debit' => DB::raw('amount'),
                'fee_rate_basis_points' => 0,
            ]);
    }

    public function down()
    {
        if (!Schema::hasTable('brc_transfers')) {
            return;
        }

        Schema::table('brc_transfers', function (Blueprint $table) {
            foreach (['fee_rate_basis_points', 'total_debit', 'fee_amount'] as $column) {
                if (Schema::hasColumn('brc_transfers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
