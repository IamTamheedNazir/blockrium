<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGooglePlayPurchaseTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('google_play_purchases')) {
            Schema::create('google_play_purchases', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('user_id');
                $table->string('purpose', 24);
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('product_id', 150);
                $table->string('base_plan_id', 100)->nullable();
                $table->char('account_binding', 64);
                $table->char('purchase_token_hash', 64)->nullable()->unique();
                $table->text('purchase_token_encrypted')->nullable();
                $table->string('google_order_id', 190)->nullable();
                $table->string('status', 30)->default('pending');
                $table->string('entitlement_status', 30)->default('inactive');
                $table->decimal('store_amount', 12, 4)->nullable();
                $table->string('store_currency', 3)->nullable();
                $table->string('store_region', 2)->nullable();
                $table->timestamp('purchased_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('acknowledgement_state', 64)->nullable();
                $table->unsignedBigInteger('verification_payment_id')->nullable();
                $table->unsignedBigInteger('user_boost_id')->nullable();
                $table->timestamp('last_verified_at')->nullable();
                $table->string('last_event_type', 80)->nullable();
                $table->text('metadata')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'purpose', 'status'], 'gplay_purchases_user_purpose_status_idx');
                $table->index(['purpose', 'status', 'created_at'], 'gplay_purchases_purpose_status_date_idx');
                $table->index(['user_id', 'entitlement_status', 'expires_at'], 'gplay_purchases_entitlement_idx');
                $table->index('google_order_id', 'gplay_purchases_order_idx');
            });
        }

        if (!Schema::hasTable('google_play_rtdn_events')) {
            Schema::create('google_play_rtdn_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('message_id', 190)->unique();
                $table->string('event_type', 80)->nullable();
                $table->char('purchase_token_hash', 64)->nullable();
                $table->unsignedBigInteger('purchase_id')->nullable();
                $table->string('google_order_id', 190)->nullable();
                $table->decimal('store_amount', 12, 4)->nullable();
                $table->string('store_currency', 3)->nullable();
                $table->string('status', 24)->default('received');
                $table->string('error_code', 80)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at'], 'gplay_rtdn_status_date_idx');
                $table->index('purchase_token_hash', 'gplay_rtdn_token_hash_idx');
                $table->index('purchase_id', 'gplay_rtdn_purchase_idx');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('google_play_rtdn_events');
        Schema::dropIfExists('google_play_purchases');
    }
}
