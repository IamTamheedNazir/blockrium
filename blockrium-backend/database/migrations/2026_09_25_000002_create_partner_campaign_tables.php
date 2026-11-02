<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartnerCampaignTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('partners')) {
            Schema::create('partners', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 160);
                $table->string('contact_email', 190)->nullable();
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->index('status', 'partners_status_idx');
            });
        }

        if (!Schema::hasTable('partner_dashboard_tokens')) {
            Schema::create('partner_dashboard_tokens', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('partner_id');
                $table->string('token_hash', 64)->unique('partner_dashboard_token_hash_unique');
                $table->string('label', 120)->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['partner_id', 'revoked_at'], 'partner_dashboard_partner_status_idx');
            });
        }

        if (!Schema::hasTable('partner_campaigns')) {
            Schema::create('partner_campaigns', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('partner_id');
                $table->uuid('public_id')->unique('partner_campaign_public_id_unique');
                $table->string('slug', 190)->unique('partner_campaign_slug_unique');
                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->text('image_url')->nullable();
                $table->text('destination_url');
                $table->string('status', 20)->default('draft');
                $table->string('reward_mode', 30)->default('click_claim');
                $table->decimal('reward_amount', 12, 4)->default(0);
                $table->string('reward_label', 20)->default('BRC');
                $table->unsignedInteger('participant_limit')->nullable();
                $table->decimal('budget_amount', 12, 4)->nullable();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->timestamps();

                $table->index(['status', 'starts_at', 'ends_at'], 'partner_campaign_active_window_idx');
                $table->index(['partner_id', 'status'], 'partner_campaign_partner_status_idx');
            });
        }

        if (!Schema::hasTable('partner_campaign_clicks')) {
            Schema::create('partner_campaign_clicks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('visitor_hash', 64)->nullable();
                $table->string('source', 20)->default('unknown');
                $table->timestamp('redirected_at')->nullable();
                $table->timestamps();

                $table->index(['campaign_id', 'created_at'], 'partner_click_campaign_date_idx');
                $table->index(['campaign_id', 'user_id'], 'partner_click_campaign_user_idx');
                $table->index(['campaign_id', 'visitor_hash'], 'partner_click_campaign_visitor_idx');
            });
        }

        if (!Schema::hasTable('partner_campaign_completions')) {
            Schema::create('partner_campaign_completions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('user_id');
                $table->string('status', 20)->default('pending');
                $table->unsignedBigInteger('approved_by_admin_id')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('admin_note')->nullable();
                $table->timestamps();

                $table->index(['campaign_id', 'status'], 'partner_completion_campaign_status_idx');
                $table->index(['user_id', 'status'], 'partner_completion_user_status_idx');
            });
        }

        if (!Schema::hasTable('partner_campaign_claims')) {
            Schema::create('partner_campaign_claims', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('user_id');
                $table->decimal('reward_amount', 12, 4)->default(0);
                $table->string('reward_label', 20)->default('BRC');
                $table->string('wallet_reference', 120)->nullable();
                $table->timestamp('claimed_at');
                $table->timestamps();

                $table->unique(['campaign_id', 'user_id'], 'partner_claim_campaign_user_unique');
                $table->index(['campaign_id', 'claimed_at'], 'partner_claim_campaign_date_idx');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('partner_campaign_claims');
        Schema::dropIfExists('partner_campaign_completions');
        Schema::dropIfExists('partner_campaign_clicks');
        Schema::dropIfExists('partner_campaigns');
        Schema::dropIfExists('partner_dashboard_tokens');
        Schema::dropIfExists('partners');
    }
}
