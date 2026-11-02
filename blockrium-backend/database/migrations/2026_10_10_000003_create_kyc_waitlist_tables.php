<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKycWaitlistTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('kyc_waitlist_entries')) {
            Schema::create('kyc_waitlist_entries', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->unique();
                $table->char('country_code', 3);
                $table->string('country_name', 100);
                $table->string('status', 24)->default('waiting');
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('invited_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('converted_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->unsignedBigInteger('invited_by_admin_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['country_code', 'status', 'joined_at'], 'kyc_waitlist_country_status_idx');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('kyc_waitlist_entries');
    }
}


