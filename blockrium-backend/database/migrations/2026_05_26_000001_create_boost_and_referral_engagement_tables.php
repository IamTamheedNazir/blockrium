<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateBoostAndReferralEngagementTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('boost_plans')) {
            Schema::create('boost_plans', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->text('description')->nullable();
                $table->decimal('boost_percent', 8, 2)->default(0);
                $table->unsignedInteger('duration_days')->default(7);
                $table->decimal('price', 12, 2)->default(0);
                $table->string('currency', 12)->default('INR');
                $table->unsignedTinyInteger('status')->default(1);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });

            DB::table('boost_plans')->insert([
                [
                    'name' => 'Basic Boost',
                    'slug' => 'basic-boost',
                    'description' => 'Entry paid mining boost for one week.',
                    'boost_percent' => 10,
                    'duration_days' => 7,
                    'price' => 99,
                    'currency' => 'INR',
                    'status' => 1,
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Pro Boost',
                    'slug' => 'pro-boost',
                    'description' => 'Higher mining boost for monthly active miners.',
                    'boost_percent' => 25,
                    'duration_days' => 30,
                    'price' => 299,
                    'currency' => 'INR',
                    'status' => 1,
                    'sort_order' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Elite Boost',
                    'slug' => 'elite-boost',
                    'description' => 'Maximum paid boost for serious miners.',
                    'boost_percent' => 50,
                    'duration_days' => 30,
                    'price' => 599,
                    'currency' => 'INR',
                    'status' => 1,
                    'sort_order' => 3,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        if (!Schema::hasTable('user_boosts')) {
            Schema::create('user_boosts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('boost_plan_id')->nullable();
                $table->string('provider', 50)->nullable();
                $table->string('purchase_reference', 190)->nullable();
                $table->decimal('amount_paid', 12, 2)->default(0);
                $table->string('currency', 12)->default('INR');
                $table->decimal('boost_percent', 8, 2)->default(0);
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                $table->string('status', 30)->default('pending');
                $table->string('payment_status', 30)->default('pending');
                $table->text('metadata')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status', 'payment_status']);
                $table->index(['boost_plan_id']);
                $table->index(['ends_at']);
            });
        }

        if (!Schema::hasTable('referral_pings')) {
            Schema::create('referral_pings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('sender_user_id');
                $table->unsignedBigInteger('recipient_user_id');
                $table->unsignedBigInteger('referral_id')->nullable();
                $table->string('message_type', 50)->default('mine_reminder');
                $table->string('status', 30)->default('sent');
                $table->dateTime('read_at')->nullable();
                $table->timestamps();

                $table->index(['sender_user_id', 'recipient_user_id', 'created_at'], 'referral_pings_sender_recipient_idx');
                $table->index(['recipient_user_id', 'created_at']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('referral_pings');
        Schema::dropIfExists('user_boosts');
        Schema::dropIfExists('boost_plans');
    }
}
