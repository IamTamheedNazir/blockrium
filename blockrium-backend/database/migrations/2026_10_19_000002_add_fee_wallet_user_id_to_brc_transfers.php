<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFeeWalletUserIdToBrcTransfers extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('brc_transfers') || Schema::hasColumn('brc_transfers', 'fee_wallet_user_id')) {
            return;
        }

        Schema::table('brc_transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('fee_wallet_user_id')->nullable()->after('recipient_user_id')->index();
        });
    }

    public function down()
    {
        if (!Schema::hasTable('brc_transfers') || !Schema::hasColumn('brc_transfers', 'fee_wallet_user_id')) {
            return;
        }

        Schema::table('brc_transfers', function (Blueprint $table) {
            $table->dropIndex(['fee_wallet_user_id']);
            $table->dropColumn('fee_wallet_user_id');
        });
    }
}
