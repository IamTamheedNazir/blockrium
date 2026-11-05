<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WidenGooglePlayAcknowledgementState extends Migration
{
    public function up()
    {
        if (Schema::hasTable('google_play_purchases') && Schema::hasColumn('google_play_purchases', 'acknowledgement_state')) {
            DB::statement('ALTER TABLE google_play_purchases MODIFY acknowledgement_state VARCHAR(64) NULL');
        }
    }

    public function down()
    {
        // Keep the widened column: Google acknowledgement values exceed the old 32-character limit.
    }
}
