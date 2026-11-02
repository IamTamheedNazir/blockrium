<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewColumnToNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('status', 20)->default('1')->comment('1=>success,2=>failed');
            $table->string('n_type', 50)->default('1')->comment('1=>default,2=>push notification');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    
}
