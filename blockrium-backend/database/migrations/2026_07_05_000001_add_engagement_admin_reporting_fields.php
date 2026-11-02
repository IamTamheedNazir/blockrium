<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddEngagementAdminReportingFields extends Migration
{
    public function up()
    {
        if (Schema::hasTable('referral_milestones') && !Schema::hasColumn('referral_milestones', 'reward_label')) {
            Schema::table('referral_milestones', function (Blueprint $table) {
                $table->string('reward_label', 20)->default('BRC')->after('reward_amount');
            });
        }

        if (Schema::hasTable('referral_milestone_claims') && !Schema::hasColumn('referral_milestone_claims', 'reward_label')) {
            Schema::table('referral_milestone_claims', function (Blueprint $table) {
                $table->string('reward_label', 20)->default('BRC')->after('reward_amount');
            });
        }

        if (Schema::hasTable('referral_milestones')) {
            DB::table('referral_milestones')->whereNull('reward_label')->update(['reward_label' => 'BRC']);
        }

        if (Schema::hasTable('referral_milestone_claims')) {
            DB::table('referral_milestone_claims')->whereNull('reward_label')->update(['reward_label' => 'BRC']);
        }

        if (!Schema::hasTable('engagement_admin_audits')) {
            Schema::create('engagement_admin_audits', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->string('action', 60);
                $table->string('entity_type', 80);
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->json('changes')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['entity_type', 'entity_id'], 'eng_admin_audit_entity_idx');
                $table->index(['admin_id', 'created_at'], 'eng_admin_audit_admin_idx');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('engagement_admin_audits');
        // Reward labels are intentionally retained to preserve claim history.
    }
}
