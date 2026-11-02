<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVerifiedMemberTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('verified_memberships')) {
            Schema::create('verified_memberships', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('kyc_status', 24)->default('not_started');
                $table->string('payment_status', 24)->default('pending');
                $table->string('kyc_provider', 80)->nullable();
                $table->string('kyc_reference', 190)->nullable();
                $table->string('payment_provider', 80)->nullable();
                $table->string('payment_reference', 190)->nullable();
                $table->string('didit_session_id', 80)->nullable()->unique();
                $table->string('didit_session_url', 500)->nullable();
                $table->string('didit_status', 32)->nullable();
                $table->string('kyc_country', 3)->nullable();
                $table->timestamp('kyc_started_at')->nullable();
                $table->string('didit_event_id', 80)->nullable();
                $table->decimal('payment_amount', 10, 2)->default(5.00);
                $table->string('payment_currency', 10)->default('USD');
                $table->unsignedBigInteger('kyc_reviewed_by_admin_id')->nullable();
                $table->timestamp('kyc_reviewed_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->timestamps();

                $table->index(['kyc_status', 'payment_status'], 'verified_membership_review_idx');
            });
        }

        if (!Schema::hasTable('verification_payments')) {
            Schema::create('verification_payments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->string('provider', 80);
                $table->string('provider_payment_id', 190)->nullable();
                $table->char('provider_payment_key', 64)->nullable()->unique();
                $table->string('status', 24)->default('pending');
                $table->decimal('amount', 10, 2);
                $table->string('currency', 10)->default('USD');
                $table->text('checkout_url')->nullable();
                $table->text('metadata')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status'], 'verification_payment_user_status_idx');
            });
        }

        if (!Schema::hasTable('brc_usernames')) {
            Schema::create('brc_usernames', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('username', 20)->unique();
                $table->string('normalized_username', 20)->unique();
                $table->timestamp('changed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('brc_transfers')) {
            Schema::create('brc_transfers', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sender_user_id');
                $table->unsignedBigInteger('recipient_user_id');
                $table->decimal('amount', 20, 8);
                $table->string('status', 24)->default('completed');
                $table->string('reference', 80)->unique();
                $table->string('idempotency_key', 120)->unique();
                $table->string('note', 500)->nullable();
                $table->timestamps();

                $table->index(['sender_user_id', 'created_at'], 'brc_transfer_sender_date_idx');
                $table->index(['recipient_user_id', 'created_at'], 'brc_transfer_recipient_date_idx');
            });
        }

        if (!Schema::hasTable('didit_webhook_events')) {
            Schema::create('didit_webhook_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('event_id', 80)->unique();
                $table->string('webhook_type', 80);
                $table->string('session_id', 80)->nullable();
                $table->string('status', 32)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['session_id', 'status'], 'didit_webhook_session_status_idx');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('brc_transfers');
        Schema::dropIfExists('didit_webhook_events');
        Schema::dropIfExists('brc_usernames');
        Schema::dropIfExists('verification_payments');
        Schema::dropIfExists('verified_memberships');
    }
}
