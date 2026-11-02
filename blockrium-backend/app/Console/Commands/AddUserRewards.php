<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ButtonClick;
class AddUserRewards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'add:user-rewards';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add rewards to the user\'s wallet every hour';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $users = ButtonClick::whereNotNull('rewards_start_time')->get();

        foreach ($users as $user) {
            $duration = now()->diffInSeconds($user->rewards_start_time);

            if ($duration >= 3600) {
                $hours = (int) ($duration / 60);
                $rewards = $hours * 1;

                $user->update([
                    'wallet' => $user->wallet + $rewards
                ]);
            }
        }
    }
}
