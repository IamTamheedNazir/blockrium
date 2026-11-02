<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTelegramTaskVerifications extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('telegram_task_verifications')) {
            Schema::create('telegram_task_verifications', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('task_id');
                $table->unsignedBigInteger('user_id');
                $table->string('token_hash', 64)->nullable()->unique();
                $table->string('telegram_user_id', 32)->nullable();
                $table->timestamp('linked_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->unique(['task_id', 'user_id'], 'telegram_task_user_unique');
                $table->index(['telegram_user_id', 'task_id'], 'telegram_task_member_idx');
            });
        }

        if (!Schema::hasTable('telegram_account_links')) {
            Schema::create('telegram_account_links', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('telegram_user_id', 32)->unique();
                $table->timestamp('linked_at');
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('telegram_account_links');
        Schema::dropIfExists('telegram_task_verifications');
    }
}
