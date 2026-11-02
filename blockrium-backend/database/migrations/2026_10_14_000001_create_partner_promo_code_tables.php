<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartnerPromoCodeTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('partner_promo_batches')) {
            Schema::create('partner_promo_batches', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('campaign_id');
                $table->string('batch_reference', 80)->unique('partner_promo_batch_reference_unique');
                $table->string('status', 20)->default('generating');
                $table->unsignedInteger('code_count')->default(0);
                $table->unsignedInteger('generated_count')->default(0);
                $table->unsignedInteger('claimed_count')->default(0);
                $table->decimal('total_reward_amount', 16, 4)->default(0);
                $table->string('reward_label', 20)->default('BRC');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('exported_at')->nullable();
                $table->char('export_sha256', 64)->nullable();
                $table->unsignedBigInteger('export_downloaded_by_admin_id')->nullable();
                $table->timestamp('export_downloaded_at')->nullable();
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->text('generation_error')->nullable();
                $table->timestamps();

                $table->index(['campaign_id', 'status'], 'partner_promo_batch_campaign_status_idx');
                $table->index(['status', 'expires_at'], 'partner_promo_batch_status_expiry_idx');
            });
        }

        if (!Schema::hasTable('partner_promo_codes')) {
            Schema::create('partner_promo_codes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('batch_id');
                $table->unsignedBigInteger('campaign_id');
                $table->char('code_hash', 64)->unique('partner_promo_code_hash_unique');
                $table->string('code_suffix', 8);
                $table->decimal('reward_amount', 16, 4)->default(0);
                $table->string('reward_label', 20)->default('BRC');
                $table->string('status', 20)->default('available');
                $table->unsignedBigInteger('claimed_by_user_id')->nullable();
                $table->timestamp('claimed_at')->nullable();
                $table->string('redemption_reference', 100)->nullable();
                $table->timestamps();

                $table->index(['batch_id', 'status'], 'partner_promo_code_batch_status_idx');
                $table->index(['campaign_id', 'status'], 'partner_promo_code_campaign_status_idx');
                $table->index(['claimed_by_user_id', 'campaign_id'], 'partner_promo_code_claimed_user_idx');
            });
        }

        if (!Schema::hasTable('partner_promo_redemptions')) {
            Schema::create('partner_promo_redemptions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('promo_code_id');
                $table->unsignedBigInteger('batch_id');
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('user_id');
                $table->string('request_key', 100);
                $table->string('status', 20)->default('succeeded');
                $table->string('failure_reason', 120)->nullable();
                $table->string('wallet_reference', 120)->nullable();
                $table->string('redemption_reference', 100)->nullable();
                $table->decimal('reward_amount', 16, 4)->default(0);
                $table->string('reward_label', 20)->default('BRC');
                $table->string('device_hash', 64)->nullable();
                $table->string('visitor_hash', 64)->nullable();
                $table->timestamps();

                $table->unique(['campaign_id', 'user_id', 'request_key'], 'partner_promo_request_unique');
                $table->index(['user_id', 'campaign_id'], 'partner_promo_redemption_user_campaign_idx');
                $table->index(['promo_code_id', 'status'], 'partner_promo_redemption_code_status_idx');
                $table->index(['campaign_id', 'created_at'], 'partner_promo_redemption_campaign_date_idx');
            });
        }

        if (Schema::hasTable('partner_promo_redemptions')) {
            Schema::table('partner_promo_redemptions', function (Blueprint $table) {
                if (!Schema::hasColumn('partner_promo_redemptions', 'redemption_reference')) {
                    $table->string('redemption_reference', 100)->nullable()->after('wallet_reference');
                }
                if (!Schema::hasColumn('partner_promo_redemptions', 'reward_label')) {
                    $table->string('reward_label', 20)->default('BRC')->after('reward_amount');
                }
            });
        }

        if (Schema::hasTable('partner_campaign_claims')) {
            Schema::table('partner_campaign_claims', function (Blueprint $table) {
                if (!Schema::hasColumn('partner_campaign_claims', 'promo_code_id')) {
                    $table->unsignedBigInteger('promo_code_id')->nullable()->after('user_id');
                    $table->index('promo_code_id', 'partner_claim_promo_code_idx');
                }
                if (!Schema::hasColumn('partner_campaign_claims', 'claim_source')) {
                    $table->string('claim_source', 30)->nullable()->after('promo_code_id');
                    $table->index('claim_source', 'partner_claim_source_idx');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('partner_campaign_claims')) {
            Schema::table('partner_campaign_claims', function (Blueprint $table) {
                if (Schema::hasColumn('partner_campaign_claims', 'promo_code_id')) {
                    $table->dropIndex('partner_claim_promo_code_idx');
                    $table->dropColumn('promo_code_id');
                }
                if (Schema::hasColumn('partner_campaign_claims', 'claim_source')) {
                    $table->dropIndex('partner_claim_source_idx');
                    $table->dropColumn('claim_source');
                }
            });
        }

        Schema::dropIfExists('partner_promo_redemptions');
        Schema::dropIfExists('partner_promo_codes');
        Schema::dropIfExists('partner_promo_batches');
    }
}
