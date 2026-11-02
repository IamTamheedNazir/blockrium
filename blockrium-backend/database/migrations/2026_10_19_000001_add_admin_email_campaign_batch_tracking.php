<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAdminEmailCampaignBatchTracking extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('admin_email_campaign_settings')) {
            Schema::create('admin_email_campaign_settings', function (Blueprint $table) {
                $table->string('setting_key', 64)->primary();
                $table->text('setting_value')->nullable();
                $table->unsignedBigInteger('updated_by_admin_id')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('admin_email_campaigns')) {
            Schema::table('admin_email_campaigns', function (Blueprint $table) {
                if (!Schema::hasColumn('admin_email_campaigns', 'batch_size')) $table->unsignedSmallInteger('batch_size')->default(250);
                if (!Schema::hasColumn('admin_email_campaigns', 'daily_cap')) $table->unsignedSmallInteger('daily_cap')->default(500);
                if (!Schema::hasColumn('admin_email_campaigns', 'repeat_interval_hours')) $table->unsignedSmallInteger('repeat_interval_hours')->default(24);
                if (!Schema::hasColumn('admin_email_campaigns', 'next_batch_at')) $table->timestamp('next_batch_at')->nullable();
                if (!Schema::hasColumn('admin_email_campaigns', 'service_basis_confirmed_at')) $table->timestamp('service_basis_confirmed_at')->nullable();
            });
        }

        if (Schema::hasTable('admin_email_campaign_recipients')) {
            Schema::table('admin_email_campaign_recipients', function (Blueprint $table) {
                if (!Schema::hasColumn('admin_email_campaign_recipients', 'dispatched_at')) {
                    $table->timestamp('dispatched_at')->nullable();
                    $table->index(['campaign_id', 'dispatched_at'], 'admin_email_recipient_dispatched_idx');
                }
                if (!Schema::hasColumn('admin_email_campaign_recipients', 'tracking_token')) $table->string('tracking_token', 64)->nullable()->unique('admin_email_recipient_tracking_token_unique');
                if (!Schema::hasColumn('admin_email_campaign_recipients', 'first_opened_at')) $table->timestamp('first_opened_at')->nullable();
                if (!Schema::hasColumn('admin_email_campaign_recipients', 'first_clicked_at')) $table->timestamp('first_clicked_at')->nullable();
                if (!Schema::hasColumn('admin_email_campaign_recipients', 'open_count')) $table->unsignedInteger('open_count')->default(0);
                if (!Schema::hasColumn('admin_email_campaign_recipients', 'click_count')) $table->unsignedInteger('click_count')->default(0);
            });
        }
    }

    public function down()
    {
        // Retain operational history and brand settings if a deployment is rolled back.
    }
}
