<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const FIREBASE_PUSH_DISABLED_KEY = 'firebase_push_disabled_until';
    private const FIREBASE_AUTH_LOG_KEY = 'firebase_push_auth_error_logged';

    protected $deviceToken;
    protected $userId;
    protected $title;
    protected $body;
    protected $data;

    public function __construct($deviceToken, $userId, $title, $body, array $data = [])
    {
        $this->deviceToken = $deviceToken;
        $this->userId      = $userId;
        $this->title       = $title;
        $this->body        = $body;
        $this->data        = $data;
    }

    public function handle()
    {
        if (empty($this->deviceToken)) {
            Log::warning("Missing device token for user ID {$this->userId}");
            return;
        }

        if ($this->firebasePushDisabled()) {
            return;
        }

        try {
            $firebaseCredentialsPath = env(
                'FIREBASE_CREDENTIALS_PATH',
                base_path('app/Library/GooglePushNotification.json')
            );
            if (!is_file($firebaseCredentialsPath)) {
                $firebaseCredentialsPath = base_path($firebaseCredentialsPath);
            }

            $firebase = (new Factory)->withServiceAccount($firebaseCredentialsPath);
            $messaging = $firebase->createMessaging();

            $message = CloudMessage::withTarget('token', $this->deviceToken)
                ->withNotification(['title' => $this->title, 'body' => $this->body])
                ->withData(self::prepareFcmData($this->data));

            $messaging->send($message);

            Log::info("Notification sent to user {$this->userId}");
            $this->recordNotification(1);
        } catch (\Exception $e) {
            if ($this->isFirebaseAuthFailure($e)) {
                $this->disableFirebasePush($e);
                return;
            }

            Log::error("Failed sending notification to user {$this->userId}: " . $e->getMessage());
            $this->recordNotification(0, $e->getMessage());
        }
    }

    public static function prepareFcmData(array $data): array
    {
        if (array_key_exists('message_type', $data)) {
            if (!array_key_exists('app_message_type', $data)) {
                $data['app_message_type'] = (string) $data['message_type'];
            }
            unset($data['message_type']);
        }

        return $data;
    }

    private function firebasePushDisabled()
    {
        return Cache::has(self::FIREBASE_PUSH_DISABLED_KEY);
    }

    private function disableFirebasePush(\Exception $e)
    {
        $until = now()->addMinutes(30);

        Cache::put(self::FIREBASE_PUSH_DISABLED_KEY, $until->toDateTimeString(), $until);

        if (Cache::add(self::FIREBASE_AUTH_LOG_KEY, true, $until)) {
            Log::error(
                'Firebase push notifications paused for 30 minutes because authentication failed: '
                . $e->getMessage()
                . ' Replace app/Library/GooglePushNotification.json with a fresh Firebase service-account key.'
            );
        }
    }

    private function isFirebaseAuthFailure(\Exception $e)
    {
        $message = strtolower($e->getMessage());

        return strpos($message, 'invalid_grant') !== false
            || strpos($message, 'invalid grant') !== false
            || strpos($message, 'unauthorized') !== false
            || strpos($message, 'invalid credentials') !== false
            || strpos($message, 'private key') !== false;
    }

    private function recordNotification($status, $error = null)
    {
        $payload = [
            'type'    => 'PUSH_NOTIFICATION',
            'subject' => $this->title,
            'data'    => [
                'message' => $this->body,
            ],
        ];

        if (!empty($error)) {
            $payload['data']['error'] = $error;
        }

        DB::table('notifications')->insert([
            'type'              => 'PUSH_NOTIFICATION',
            'notifiable_type'   => 'PUSH_NOTIFICATION',
            'notifiable_id'     => $this->userId,
            'data'              => json_encode($payload),
            'notification_type' => 'PUSH_NOTIFICATION',
            'status'            => $status,
            'created_at'        => now(),
            'n_type'            => 2,
        ]);
    }
}
