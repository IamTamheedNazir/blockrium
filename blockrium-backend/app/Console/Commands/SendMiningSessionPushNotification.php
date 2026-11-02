<?php

namespace App\Console\Commands;

use App\Jobs\SendPushNotificationJob;
use App\Models\ResUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendMiningSessionPushNotification extends Command
{
    protected $signature = 'send:mining-session-push-notification';
    protected $description = 'Send push notifications to users inactive for 24+ hours';

    public function handle()
    {
        if (!filter_var(env('FIREBASE_PUSH_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->warn('Firebase push reminders are disabled by FIREBASE_PUSH_ENABLED=false.');
            return 0;
        }

        $disabledUntil = Cache::get('firebase_push_disabled_until');
        if (!empty($disabledUntil)) {
            $message = "Skipping mining push reminders because Firebase auth is paused until {$disabledUntil}.";
            $this->warn($message);
            Log::warning($message);
            return 0;
        }

        $this->info('Starting optimized mining session push notification job...');

        $cutoff = now()->subHours(24);
        $today  = now()->toDateString();

        $query = "
            SELECT user_id, created_at
            FROM (
                SELECT bc.user_id, bc.created_at,
                       ROW_NUMBER() OVER (PARTITION BY bc.user_id ORDER BY bc.created_at DESC) as rn
                FROM button_clicks bc
            ) latest
            WHERE rn = 1 AND created_at < ?
        ";

        DB::table(DB::raw("($query) as t"))
            ->addBinding($cutoff)
            ->orderBy('t.user_id')
            ->chunk(1000, function ($userRows) use ($today) {
                $userIds = collect($userRows)->pluck('user_id')->all();

                if (empty($userIds)) {
                    return;
                }

                $notificationCounts = DB::table('notifications')
                    ->select('notifiable_id', DB::raw('COUNT(*) as total'))
                    ->whereIn('notifiable_id', $userIds)
                    ->where('notification_type', 'PUSH_NOTIFICATION')
                    ->where('status', 1)
                    ->whereDate('created_at', $today)
                    ->groupBy('notifiable_id')
                    ->pluck('total', 'notifiable_id');

                ResUser::select('id', 'device_id')
                    ->whereNotNull('device_id')
                    ->whereIn('id', $userIds)
                    ->chunk(100, function ($users) use ($notificationCounts) {
                        foreach ($users as $user) {
                            $todayCount = $notificationCounts[$user->id] ?? 0;

                            if ($todayCount >= 2) {
                                continue;
                            }

                            SendPushNotificationJob::dispatch(
                                $user->device_id,
                                $user->id,
                                "Don't forget to mine BRC!",
                                'Your last mining session ended about an hour ago. Come back to keep mining BRC!',
                                [
                                    'click_action'   => 'mining',
                                    'btn_label'      => '',
                                    'user_id'        => (string) $user->id,
                                    'category'       => '',
                                    'notificationId' => '',
                                ]
                            );
                        }
                    });
            });

        $this->info('All notification jobs dispatched successfully.');
        return 0;
    }
}
