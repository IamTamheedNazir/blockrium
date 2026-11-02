<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartnerCampaignTaskTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('partner_campaign_tasks')) {
            Schema::create('partner_campaign_tasks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('campaign_id');
                $table->string('task_key', 100);
                $table->string('task_type', 40)->default('custom');
                $table->string('title', 180);
                $table->text('instructions')->nullable();
                $table->text('target_url')->nullable();
                $table->string('completion_mode', 30)->default('admin_approval');
                $table->decimal('reward_amount', 12, 4)->default(0);
                $table->string('reward_label', 20)->default('BRC');
                $table->unsignedInteger('participant_limit')->nullable();
                $table->string('status', 20)->default('active');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['campaign_id', 'task_key'], 'partner_campaign_task_key_unique');
                $table->index(['campaign_id', 'status', 'sort_order'], 'partner_campaign_task_order_idx');
            });
        }

        if (!Schema::hasTable('partner_campaign_task_clicks')) {
            Schema::create('partner_campaign_task_clicks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('task_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('visitor_hash', 64)->nullable();
                $table->string('source', 20)->default('app');
                $table->timestamp('redirected_at')->nullable();
                $table->timestamps();

                $table->index(['campaign_id', 'task_id', 'created_at'], 'partner_task_click_date_idx');
                $table->index(['task_id', 'user_id'], 'partner_task_click_user_idx');
            });
        }

        if (!Schema::hasTable('partner_campaign_task_completions')) {
            Schema::create('partner_campaign_task_completions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('task_id');
                $table->unsignedBigInteger('user_id');
                $table->string('status', 20)->default('pending');
                $table->text('evidence_url')->nullable();
                $table->text('evidence_note')->nullable();
                $table->unsignedBigInteger('approved_by_admin_id')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('admin_note')->nullable();
                $table->timestamps();

                $table->unique(['task_id', 'user_id'], 'partner_task_completion_user_unique');
                $table->index(['campaign_id', 'status'], 'partner_task_completion_status_idx');
            });
        }

        if (!Schema::hasTable('partner_campaign_task_claims')) {
            Schema::create('partner_campaign_task_claims', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('task_id');
                $table->unsignedBigInteger('user_id');
                $table->decimal('reward_amount', 12, 4)->default(0);
                $table->string('reward_label', 20)->default('BRC');
                $table->string('wallet_reference', 120)->nullable();
                $table->timestamp('claimed_at');
                $table->timestamps();

                $table->unique(['task_id', 'user_id'], 'partner_task_claim_user_unique');
                $table->index(['campaign_id', 'claimed_at'], 'partner_task_claim_campaign_date_idx');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('partner_campaign_task_claims');
        Schema::dropIfExists('partner_campaign_task_completions');
        Schema::dropIfExists('partner_campaign_task_clicks');
        Schema::dropIfExists('partner_campaign_tasks');
    }
}
