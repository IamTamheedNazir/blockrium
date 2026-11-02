<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUpdateControlFieldsToAppVersion extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('app_version')) {
            return;
        }

        Schema::table('app_version', function (Blueprint $table) {
            if (!Schema::hasColumn('app_version', 'latest_version_code')) {
                $table->integer('latest_version_code')->nullable()->after('version');
            }

            if (!Schema::hasColumn('app_version', 'minimum_version_code')) {
                $table->integer('minimum_version_code')->default(0)->after('latest_version_code');
            }

            if (!Schema::hasColumn('app_version', 'force_update')) {
                $table->boolean('force_update')->default(false)->after('minimum_version_code');
            }

            if (!Schema::hasColumn('app_version', 'status')) {
                $table->boolean('status')->default(true)->after('force_update');
            }

            if (!Schema::hasColumn('app_version', 'platform')) {
                $table->string('platform', 20)->default('android')->after('status');
            }

            if (!Schema::hasColumn('app_version', 'release_notes')) {
                $table->text('release_notes')->nullable()->after('platform');
            }

            if (!Schema::hasColumn('app_version', 'android_link')) {
                $table->text('android_link')->nullable()->after('release_notes');
            }

            if (!Schema::hasColumn('app_version', 'ios_link')) {
                $table->text('ios_link')->nullable()->after('android_link');
            }

            if (!Schema::hasColumn('app_version', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }

            if (!Schema::hasColumn('app_version', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down()
    {
        // Intentionally non-destructive for the live admin table.
    }
}
