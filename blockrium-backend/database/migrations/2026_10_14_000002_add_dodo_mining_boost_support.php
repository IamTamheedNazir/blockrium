<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddDodoMiningBoostSupport extends Migration
{
    public function up()
    {
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
                    'slug' => 'basic-boost',
                    'name' => 'Blockrium Basic Mining Boost',
                    'description' => 'Monthly digital in-app subscription with a 10% mining-rate boost.',
                    'boost_percent' => 10,
                    'duration_days' => 30,
                    'price' => 5,
                    'currency' => 'USD',
                    'provider_product_id' => 'pdt_0NpjcrZyiWgvx1oUlI5dA',
                    'sort_order' => 1,
                ],
                [
                    'slug' => 'pro-boost',
                    'name' => 'Blockrium Pro Mining Boost',
                    'description' => 'Monthly digital in-app subscription with a 25% mining-rate boost.',
                    'boost_percent' => 25,
                    'duration_days' => 30,
                    'price' => 10.99,
                    'currency' => 'USD',
                    'provider_product_id' => 'pdt_0NpjdNVSSu10tZgrHtLRB',
                    'sort_order' => 2,
                ],
                [
                    'slug' => 'elite-boost',
                    'name' => 'Blockrium Elite Mining Boost',
                    'description' => 'Monthly digital in-app subscription with a 50% mining-rate boost.',
                    'boost_percent' => 50,
                    'duration_days' => 30,
                    'price' => 19.99,
                    'currency' => 'USD',
                    'provider_product_id' => 'pdt_0Npjds36e2gZHc0wiW5j0',
                    'sort_order' => 3,
                ],
            ];

            foreach ($plans as $plan) {
                DB::table('boost_plans')->updateOrInsert(
                    ['slug' => $plan['slug']],
                    array_merge($plan, [
                        'provider' => 'dodo',
                        'provider_entitlement_id' => null,
                        'status' => 1,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ])
                );
            }
        }

        if (Schema::hasTable('user_boosts')) {
            Schema::table('user_boosts', function (Blueprint $table) {
                if (!Schema::hasColumn('user_boosts', 'checkout_session_id')) {
                    $table->string('checkout_session_id', 190)->nullable()->after('purchase_reference');
                }
                if (!Schema::hasColumn('user_boosts', 'provider_payment_id')) {
                    $table->string('provider_payment_id', 190)->nullable()->after('checkout_session_id');
                }
                if (!Schema::hasColumn('user_boosts', 'provider_subscription_id')) {
                    $table->string('provider_subscription_id', 190)->nullable()->after('provider_payment_id');
                }
                if (!Schema::hasColumn('user_boosts', 'provider_customer_id')) {
                    $table->string('provider_customer_id', 190)->nullable()->after('provider_subscription_id');
                }
                if (!Schema::hasColumn('user_boosts', 'current_period_ends_at')) {
                    $table->dateTime('current_period_ends_at')->nullable()->after('ends_at');
                }
                if (!Schema::hasColumn('user_boosts', 'cancel_at_period_end')) {
                    $table->boolean('cancel_at_period_end')->default(false)->after('current_period_ends_at');
                }
            });
        }

        if (!Schema::hasTable('dodo_boost_webhook_events')) {
            Schema::create('dodo_boost_webhook_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('event_id', 190)->unique();
                $table->string('event_type', 100)->nullable();
                $table->string('payment_id', 190)->nullable();
                $table->string('subscription_id', 190)->nullable();
                $table->unsignedBigInteger('user_boost_id')->nullable();
                $table->string('status', 30)->default('received');
                $table->text('processing_error')->nullable();
                $table->longText('payload')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['payment_id', 'event_type'], 'dodo_boost_payment_event_idx');
                $table->index(['subscription_id', 'event_type'], 'dodo_boost_subscription_event_idx');
                $table->index(['status', 'created_at'], 'dodo_boost_event_status_idx');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('dodo_boost_webhook_events');

        if (Schema::hasTable('user_boosts')) {
            foreach ([
                'cancel_at_period_end',
                'current_period_ends_at',
                'provider_customer_id',
                'provider_subscription_id',
                'provider_payment_id',
                'checkout_session_id',
            ] as $column) {
                if (Schema::hasColumn('user_boosts', $column)) {
                    Schema::table('user_boosts', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
}
