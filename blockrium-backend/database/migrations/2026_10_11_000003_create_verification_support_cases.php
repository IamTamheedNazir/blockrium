<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVerificationSupportCases extends Migration
{
    public function up()
    {
        if (Schema::hasTable('verification_support_cases')) {
            return;
        }

        Schema::create('verification_support_cases', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('verification_payment_id')->nullable();
            $table->string('type', 40)->default('payment_issue');
            $table->string('status', 24)->default('open');
            $table->string('provider', 80)->nullable();
            $table->string('provider_reference', 190)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->text('user_message')->nullable();
            $table->text('admin_notes')->nullable();
            $table->unsignedBigInteger('resolved_by_admin_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status'], 'verification_support_user_status_idx');
            $table->index(['provider', 'provider_reference'], 'verification_support_provider_ref_idx');
            $table->index(['status', 'created_at'], 'verification_support_status_date_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('verification_support_cases');
    }
}
