<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIntegrationSettingsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('integration_settings')) {
            return;
        }

        Schema::create('integration_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key', 120)->unique();
            $table->text('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('integration_settings');
    }
}
