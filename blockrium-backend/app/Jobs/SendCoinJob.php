<?php 
namespace App\Jobs;

use App\Models\ResUser;
use App\Notifications\CoinsSentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue; // This interface is required
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use App\Services\WalletRewardService;
use App\Jobs\SendPushNotificationJob;

class SendCoinJob implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;

    public $users;
    public $token;

    /**
     * Create a new job instance.
     *
     * @param  mixed  $users
     * @param  float  $token
     * @return void
     */
    public function __construct($users, $token)
    {
        $this->users = $users;
        $this->token = $token;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        foreach ($this->users as $user) {
            $userId = is_object($user) ? ($user->userid ?? $user->id) : $user;
            $userInstance = ResUser::find((int) $userId);
            if (!$userInstance) {
                continue;
            }

            DB::transaction(function () use ($userInstance) {
                app(WalletRewardService::class)->credit((int) $userInstance->id, (float) $this->token);
                $userInstance->notify(new CoinsSentNotification(
                    (float) $this->token,
                    $userInstance->name,
                    'BRC Reward',
                    $userInstance->name
                ));

                if (!empty($userInstance->device_id)) {
                    SendPushNotificationJob::dispatch(
                        $userInstance->device_id,
                        (int) $userInstance->id,
                        'BRC reward received',
                        "Congratulations! {$this->token} BRC has been added to your wallet.",
                        [
                            'type' => 'BRC_REWARD',
                            'action' => 'CLAIM_REWARDS',
                            'amount' => (string) $this->token,
                        ]
                    )->afterCommit();
                }
            });
        }
    }
}
