<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminEmailCampaignTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('admin_email_campaigns')) {
            Schema::create('admin_email_campaigns', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 160);
                $table->string('subject', 190);
                $table->text('body');
                $table->string('action_url', 2048);
                $table->string('audience', 32)->default('pending_kyc');
                $table->string('status', 24)->default('draft');
                $table->unsignedBigInteger('created_by_admin_id')->nullable();
                $table->unsignedInteger('recipient_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at'], 'admin_email_campaign_status_idx');
            });
        }

        if (!Schema::hasTable('admin_email_campaign_recipients')) {
            Schema::create('admin_email_campaign_recipients', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('user_id');
                $table->string('email', 254);
                $table->string('status', 24)->default('queued');
                $table->unsignedSmallInteger('attempt_count')->default(0);
                $table->string('last_error', 500)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->unique(['campaign_id', 'user_id'], 'admin_email_campaign_user_unique');
                $table->index(['campaign_id', 'status', 'id'], 'admin_email_campaign_recipient_status_idx');
                $table->index(['user_id', 'campaign_id'], 'admin_email_recipient_user_idx');
            });
        }

        if (!Schema::hasTable('admin_email_preferences')) {
            Schema::create('admin_email_preferences', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->unique();
                $table->timestamp('bulk_email_opted_out_at')->nullable();
                $table->string('opt_out_source', 32)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('admin_email_preferences');
        Schema::dropIfExists('admin_email_campaign_recipients');
        Schema::dropIfExists('admin_email_campaigns');
    }
}
