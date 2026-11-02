<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReferralsReferredByIndex extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('referrals') || !Schema::hasColumn('referrals', 'referred_by')) {
            return;
        }

        Schema::table('referrals', function (Blueprint $table) {
            $table->index('referred_by', 'referrals_referred_by_idx');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('referrals') || !Schema::hasColumn('referrals', 'referred_by')) {
            return;
        }

        Schema::table('referrals', function (Blueprint $table) {
            $table->dropIndex('referrals_referred_by_idx');
        });
    }
}
