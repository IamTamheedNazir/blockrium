<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddEmailDeliveryUnknownStatus extends Migration
{
    public function up()
    {
        if (Schema::hasTable('admin_email_campaigns')
            && !Schema::hasColumn('admin_email_campaigns', 'delivery_unknown_count')) {
            Schema::table('admin_email_campaigns', function (Blueprint $table) {
                $table->unsignedInteger('delivery_unknown_count')->default(0);
            });
        }

        if (Schema::hasTable('admin_email_campaign_recipients')) {
            DB::table('admin_email_campaign_recipients')
                ->where('status', 'failed')
                ->update([
                    'status' => 'delivery_unknown',
                    'last_error' => DB::raw("COALESCE(last_error, 'legacy_delivery_outcome_unknown')"),
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('admin_email_campaigns')
            && Schema::hasTable('admin_email_campaign_recipients')) {
            DB::table('admin_email_campaigns')->orderBy('id')->chunkById(500, function ($campaigns) {
                foreach ($campaigns as $campaign) {
                    $counts = DB::table('admin_email_campaign_recipients')
                        ->where('campaign_id', $campaign->id)
                        ->select('status', DB::raw('COUNT(*) as total'))
                        ->groupBy('status')
                        ->pluck('total', 'status');

                    DB::table('admin_email_campaigns')->where('id', $campaign->id)->update([
                        'failed_count' => (int) ($counts['failed'] ?? 0),
                        'delivery_unknown_count' => (int) ($counts['delivery_unknown'] ?? 0),
                    ]);
                }
            });
        }
    }

    public function down()
    {
        // Keep recipient history intact; deployment rollback must not re-enable retries.
    }
}
