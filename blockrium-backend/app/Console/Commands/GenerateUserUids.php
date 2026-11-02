<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ResUser;

class GenerateUserUids extends Command
{
    protected $signature = 'users:generate-uids';
    protected $description = 'Generate unique 8-digit UIDs for users who don\'t have one';

    // To avoid UID collisions, we’ll cache existing ones
    protected $existingUids = [];

    public function handle()
    {
        $this->info('Starting UID generation for users without a UID...');

        // Load all existing UIDs into memory (as they are unique 8-digit ints, this is small)
        $this->existingUids = ResUser::whereNotNull('uid')->pluck('uid')->toArray();
        $this->existingUids = array_flip($this->existingUids); // for faster lookup

        $count = 0;

        ResUser::whereNull('uid')->chunkById(1000, function ($users) use (&$count) {
            foreach ($users as $user) {
                $user->uid = $this->generateUniqueUid();
                $user->save();
                $count++;
            }

            $this->info("Processed {$count} users so far...");
        });

        $this->info("✅ UID generation complete. Total updated: {$count}");
    }

    protected function generateUniqueUid()
    {
        do {
            $uid = random_int(10000000, 99999999);
        } while (isset($this->existingUids[$uid]));

        // Mark it as used
        $this->existingUids[$uid] = true;

        return $uid;
    }
}
