<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateRevenuecatSubscriptionTables extends Migration
{
    public function up()
    {
        if (Schema::hasTable('res_users')) {
            Schema::table('res_users', function (Blueprint $table) {
                if (!Schema::hasColumn('res_users', 'is_pro')) {
                    $table->boolean('is_pro')->default(false)->after('is_active');
                }
                if (!Schema::hasColumn('res_users', 'subscription_status')) {
                    $table->string('subscription_status', 40)->nullable()->after('is_pro');
                }
                if (!Schema::hasColumn('res_users', 'subscription_type')) {
                    $table->string('subscription_type', 80)->nullable()->after('subscription_status');
                }
                if (!Schema::hasColumn('res_users', 'subscription_expiry')) {
                    $table->dateTime('subscription_expiry')->nullable()->after('subscription_type');
                }
                if (!Schema::hasColumn('res_users', 'revenuecat_customer_id')) {
                    $table->string('revenuecat_customer_id', 120)->nullable()->after('subscription_expiry');
                }
                if (!Schema::hasColumn('res_users', 'revenuecat_app_user_id')) {
                    $table->string('revenuecat_app_user_id', 120)->nullable()->after('revenuecat_customer_id');
                }
                if (!Schema::hasColumn('res_users', 'revenuecat_product_id')) {
                    $table->string('revenuecat_product_id', 160)->nullable()->after('revenuecat_app_user_id');
                }
                if (!Schema::hasColumn('res_users', 'revenuecat_entitlement_id')) {
                    $table->string('revenuecat_entitlement_id', 160)->nullable()->after('revenuecat_product_id');
                }
                if (!Schema::hasColumn('res_users', 'subscription_store')) {
                    $table->string('subscription_store', 60)->nullable()->after('revenuecat_entitlement_id');
                }
                if (!Schema::hasColumn('res_users', 'subscription_last_event')) {
                    $table->string('subscription_last_event', 80)->nullable()->after('subscription_store');
                }
                if (!Schema::hasColumn('res_users', 'subscription_updated_at')) {
                    $table->dateTime('subscription_updated_at')->nullable()->after('subscription_last_event');
                }
            });
        }

        if (Schema::hasTable('boost_plans')) {
            Schema::table('boost_plans', function (Blueprint $table) {
                if (!Schema::hasColumn('boost_plans', 'provider')) {
                    $table->string('provider', 50)->nullable()->after('currency');
                }
                if (!Schema::hasColumn('boost_plans', 'provider_product_id')) {
                    $table->string('provider_product_id', 190)->nullable()->after('provider');
                }
                if (!Schema::hasColumn('boost_plans', 'provider_entitlement_id')) {
                    $table->string('provider_entitlement_id', 190)->nullable()->after('provider_product_id');
                }
            });

            $now = now();
            $plans = [
                [
                    'name' => 'Blockrium Pro Monthly',
                    'slug' => 'blockrium-pro-monthly',
                    'description' => 'RevenueCat monthly Pro subscription with paid mining boost.',
                    'boost_percent' => 10,
                    'duration_days' => 30,
                    'provider_product_id' => 'monthly',
                    'sort_order' => 10,
                ],
                [
                    'name' => 'Blockrium Pro Three Month',
                    'slug' => 'blockrium-pro-three-month',
                    'description' => 'RevenueCat three-month Pro subscription with paid mining boost.',
                    'boost_percent' => 15,
                    'duration_days' => 90,
                    'provider_product_id' => 'three_month',
                    'sort_order' => 20,
                ],
                [
                    'name' => 'Blockrium Pro Six Month',
                    'slug' => 'blockrium-pro-six-month',
                    'description' => 'RevenueCat six-month Pro subscription with paid mining boost.',
                    'boost_percent' => 20,
                    'duration_days' => 180,
                    'provider_product_id' => 'six_month',
                    'sort_order' => 30,
                ],
                [
                    'name' => 'Blockrium Pro Yearly',
                    'slug' => 'blockrium-pro-yearly',
                    'description' => 'RevenueCat yearly Pro subscription with paid mining boost.',
                    'boost_percent' => 30,
                    'duration_days' => 365,
                    'provider_product_id' => 'yearly',
                    'sort_order' => 40,
                ],
            ];

            foreach ($plans as $plan) {
                DB::table('boost_plans')->updateOrInsert(
                    ['provider' => 'revenuecat', 'provider_product_id' => $plan['provider_product_id']],
                    array_merge($plan, [
                        'provider' => 'revenuecat',
                        'provider_entitlement_id' => env('REVENUECAT_PRO_ENTITLEMENT_ID', 'blockrium Pro'),
                        'price' => 0,
                        'currency' => 'USD',
                        'status' => 1,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ])
                );
            }
        }

        if (!Schema::hasTable('revenuecat_events')) {
            Schema::create('revenuecat_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('event_id', 190)->unique();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('app_user_id', 120)->nullable();
                $table->string('event_type', 80);
                $table->string('product_id', 190)->nullable();
                $table->text('entitlement_ids')->nullable();
                $table->string('transaction_id', 190)->nullable();
                $table->string('original_transaction_id', 190)->nullable();
                $table->string('store', 60)->nullable();
                $table->decimal('price', 12, 4)->nullable();
                $table->decimal('price_usd', 12, 4)->nullable();
                $table->decimal('price_in_purchased_currency', 12, 4)->nullable();
                $table->string('currency', 12)->nullable();
                $table->dateTime('purchased_at')->nullable();
                $table->dateTime('expiration_at')->nullable();
                $table->longText('raw_payload')->nullable();
                $table->dateTime('processed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'event_type']);
                $table->index(['user_id']);
                $table->index(['app_user_id']);
                $table->index(['product_id']);
                $table->index(['event_type', 'created_at']);
            });
        }

        if (!Schema::hasTable('revenuecat_subscriptions')) {
            Schema::create('revenuecat_subscriptions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->string('app_user_id', 120);
                $table->string('entitlement_id', 190)->nullable();
                $table->string('product_id', 190)->nullable();
                $table->string('plan_name', 120)->nullable();
                $table->string('status', 40)->default('inactive');
                $table->string('store', 60)->nullable();
                $table->string('environment', 40)->nullable();
                $table->string('transaction_id', 190)->nullable();
                $table->string('original_transaction_id', 190)->nullable();
                $table->decimal('price', 12, 4)->nullable();
                $table->decimal('price_usd', 12, 4)->nullable();
                $table->decimal('price_in_purchased_currency', 12, 4)->nullable();
                $table->string('currency', 12)->nullable();
                $table->dateTime('purchased_at')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->dateTime('last_event_at')->nullable();
                $table->longText('metadata')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'entitlement_id'], 'revenuecat_user_entitlement_unique');
                $table->index(['user_id']);
                $table->index(['app_user_id']);
                $table->index(['status', 'expires_at']);
                $table->index(['product_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('revenuecat_subscriptions');
        Schema::dropIfExists('revenuecat_events');
    }
}
