<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppIssueLogsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('app_issue_logs')) {
            return;
        }

        Schema::create('app_issue_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('severity', 20)->default('error')->index();
            $table->string('source', 40)->default('app')->index();
            $table->string('action', 80)->nullable()->index();
            $table->string('endpoint', 190)->nullable()->index();
            $table->string('method', 12)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable()->index();
            $table->string('error_code', 80)->nullable()->index();
            $table->text('message')->nullable();
            $table->string('app_version', 40)->nullable()->index();
            $table->string('build_number', 40)->nullable();
            $table->string('platform', 40)->nullable()->index();
            $table->string('device_model', 120)->nullable();
            $table->string('os_version', 80)->nullable();
            $table->unsignedInteger('request_duration_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();

            $table->index(['action', 'created_at']);
            $table->index(['endpoint', 'created_at']);
            $table->index(['severity', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('app_issue_logs');
    }
}
