<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppEngagementEventsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('app_engagement_events')) {
            return;
        }

        Schema::create('app_engagement_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('event_name', 80)->index();
            $table->string('area', 50)->nullable()->index();
            $table->string('status', 40)->default('info')->index();
            $table->string('source', 40)->default('app')->index();
            $table->string('route', 120)->nullable()->index();
            $table->string('endpoint', 190)->nullable()->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('app_version', 40)->nullable()->index();
            $table->string('build_number', 40)->nullable();
            $table->string('platform', 40)->nullable()->index();
            $table->string('device_model', 120)->nullable();
            $table->string('os_version', 80)->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();

            $table->index(['event_name', 'created_at']);
            $table->index(['area', 'created_at']);
            $table->index(['user_id', 'event_name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('app_engagement_events');
    }
}
