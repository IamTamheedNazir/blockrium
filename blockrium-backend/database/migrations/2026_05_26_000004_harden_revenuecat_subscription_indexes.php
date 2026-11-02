<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HardenRevenuecatSubscriptionIndexes extends Migration
{
    public function up()
    {
        if (Schema::hasTable('revenuecat_events')) {
            Schema::table('revenuecat_events', function (Blueprint $table) {
                if (!Schema::hasColumn('revenuecat_events', 'price_usd')) {
                    $table->decimal('price_usd', 12, 4)->nullable()->after('price');
                }
                if (!Schema::hasColumn('revenuecat_events', 'price_in_purchased_currency')) {
                    $table->decimal('price_in_purchased_currency', 12, 4)->nullable()->after('price_usd');
                }
            });

            $this->addIndexIfMissing('revenuecat_events', 'revenuecat_events_event_id_unique', function (Blueprint $table) {
                $table->unique('event_id', 'revenuecat_events_event_id_unique');
            });
            $this->addIndexIfMissing('revenuecat_events', 'rc_events_user_idx', function (Blueprint $table) {
                $table->index('user_id', 'rc_events_user_idx');
            });
            $this->addIndexIfMissing('revenuecat_events', 'rc_events_type_created_idx', function (Blueprint $table) {
                $table->index(['event_type', 'created_at'], 'rc_events_type_created_idx');
            });
        }

        if (Schema::hasTable('revenuecat_subscriptions')) {
            Schema::table('revenuecat_subscriptions', function (Blueprint $table) {
                if (!Schema::hasColumn('revenuecat_subscriptions', 'price_usd')) {
                    $table->decimal('price_usd', 12, 4)->nullable()->after('price');
                }
                if (!Schema::hasColumn('revenuecat_subscriptions', 'price_in_purchased_currency')) {
                    $table->decimal('price_in_purchased_currency', 12, 4)->nullable()->after('price_usd');
                }
            });

            $this->addIndexIfMissing('revenuecat_subscriptions', 'rc_subs_user_idx', function (Blueprint $table) {
                $table->index('user_id', 'rc_subs_user_idx');
            });
            $this->addIndexIfMissing('revenuecat_subscriptions', 'rc_subs_app_user_idx', function (Blueprint $table) {
                $table->index('app_user_id', 'rc_subs_app_user_idx');
            });
            $this->addIndexIfMissing('revenuecat_subscriptions', 'rc_subs_status_idx', function (Blueprint $table) {
                $table->index('status', 'rc_subs_status_idx');
            });
        }

        if (Schema::hasTable('res_users')) {
            $this->addIndexIfMissing('res_users', 'rc_res_users_customer_idx', function (Blueprint $table) {
                $table->index('revenuecat_customer_id', 'rc_res_users_customer_idx');
            });
            $this->addIndexIfMissing('res_users', 'rc_res_users_sub_status_idx', function (Blueprint $table) {
                $table->index('subscription_status', 'rc_res_users_sub_status_idx');
            });
        }

        if (Schema::hasTable('boost_plans')) {
            $this->dedupeBoostPlanProducts();

            $this->addIndexIfMissing('boost_plans', 'rc_boost_plans_product_unique', function (Blueprint $table) {
                $table->unique(['provider', 'provider_product_id'], 'rc_boost_plans_product_unique');
            });
        }

        if (Schema::hasTable('user_boosts')) {
            $this->dedupeUserBoostPurchases();

            $this->addIndexIfMissing('user_boosts', 'rc_user_boosts_purchase_unique', function (Blueprint $table) {
                $table->unique(['provider', 'purchase_reference'], 'rc_user_boosts_purchase_unique');
            });
            $this->addIndexIfMissing('user_boosts', 'rc_user_boosts_active_lookup_idx', function (Blueprint $table) {
                $table->index(['user_id', 'provider', 'status', 'payment_status', 'ends_at'], 'rc_user_boosts_active_lookup_idx');
            });
        }
    }

    public function down()
    {
        $this->dropIndexIfExists('user_boosts', 'rc_user_boosts_active_lookup_idx');
        $this->dropUniqueIfExists('user_boosts', 'rc_user_boosts_purchase_unique');
        $this->dropUniqueIfExists('boost_plans', 'rc_boost_plans_product_unique');
        $this->dropIndexIfExists('res_users', 'rc_res_users_sub_status_idx');
        $this->dropIndexIfExists('res_users', 'rc_res_users_customer_idx');
        $this->dropIndexIfExists('revenuecat_subscriptions', 'rc_subs_status_idx');
        $this->dropIndexIfExists('revenuecat_subscriptions', 'rc_subs_app_user_idx');
        $this->dropIndexIfExists('revenuecat_subscriptions', 'rc_subs_user_idx');
        $this->dropIndexIfExists('revenuecat_events', 'rc_events_type_created_idx');
        $this->dropIndexIfExists('revenuecat_events', 'rc_events_user_idx');
    }

    private function addIndexIfMissing($tableName, $indexName, callable $callback)
    {
        if (!$this->indexExists($tableName, $indexName)) {
            Schema::table($tableName, $callback);
        }
    }

    private function dropIndexIfExists($tableName, $indexName)
    {
        if ($this->indexExists($tableName, $indexName)) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }
    }

    private function dropUniqueIfExists($tableName, $indexName)
    {
        if ($this->indexExists($tableName, $indexName)) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        }
    }

    private function indexExists($tableName, $indexName)
    {
        try {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $tableName)
                ->where('index_name', $indexName)
                ->exists();
        } catch (\Throwable $error) {
            return false;
        }
    }

    private function dedupeUserBoostPurchases()
    {
        $duplicates = DB::table('user_boosts')
            ->select('provider', 'purchase_reference', DB::raw('COUNT(*) as total'))
            ->whereNotNull('provider')
            ->whereNotNull('purchase_reference')
            ->groupBy('provider', 'purchase_reference')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            $rows = DB::table('user_boosts')
                ->where('provider', $duplicate->provider)
                ->where('purchase_reference', $duplicate->purchase_reference)
                ->orderByDesc('id')
                ->get();

            $keepFirst = true;
            foreach ($rows as $row) {
                if ($keepFirst) {
                    $keepFirst = false;
                    continue;
                }

                DB::table('user_boosts')->where('id', $row->id)->update([
                    'purchase_reference' => $row->purchase_reference . ':duplicate:' . $row->id,
                    'status' => $row->status === 'active' ? 'expired' : $row->status,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function dedupeBoostPlanProducts()
    {
        $duplicates = DB::table('boost_plans')
            ->select('provider', 'provider_product_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('provider')
            ->whereNotNull('provider_product_id')
            ->groupBy('provider', 'provider_product_id')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            $rows = DB::table('boost_plans')
                ->where('provider', $duplicate->provider)
                ->where('provider_product_id', $duplicate->provider_product_id)
                ->orderByDesc('status')
                ->orderBy('id')
                ->get();

            $keepFirst = true;
            foreach ($rows as $row) {
                if ($keepFirst) {
                    $keepFirst = false;
                    continue;
                }

                DB::table('boost_plans')->where('id', $row->id)->update([
                    'provider_product_id' => $row->provider_product_id . ':duplicate:' . $row->id,
                    'status' => 0,
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
