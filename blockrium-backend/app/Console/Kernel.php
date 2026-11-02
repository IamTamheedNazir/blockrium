<?php

namespace App\Console;

use App\Models\ButtonClick;
use Carbon\Carbon;
use App\Console\Commands\AddUserRewards;
use Illuminate\Console\Scheduling\Schedule;
use App\Jobs\SendMiningSessionPushNotificationJob;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;


class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     * /usr/local/bin/php /home/vxyxdrvh/public_html/artisan schedule:run > /dev/null 2>&1
     * @var array
     */
    protected $commands = [
        //AddUserRewards::class,
        // 'App\Console\Commands\AddUserRewards'
        Commands\TestCommand::class,
        Commands\refreshFirebaseAccessToken::class,
        Commands\SendMiningSessionPushNotification::class,
        
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        
        // $schedule->command('add:user-rewards')->everyMinute();
        // $schedule->command('queue:work')->everyMinute();
        // $schedule->command('queue:work --daemon')->everyMinute();
        //$schedule->command('test:log')->everyMinute();
        $schedule->command('refresh-firebase-access-token')->dailyAt('00:30');
        // $schedule->job(new SendMiningSessionPushNotificationJob)->hourly();
        $schedule->command('send:mining-session-push-notification')->hourly();
        $schedule->command('email-campaigns:tick')->everyMinute()->withoutOverlapping();
        $schedule->command('google-play:acknowledge-pending --limit=100')->everyFiveMinutes()->withoutOverlapping();
        // $schedule->job(new SendMiningSessionPushNotificationJob)->everyMinute();
        // $schedule->command('send:mining-session-push-notification')->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
