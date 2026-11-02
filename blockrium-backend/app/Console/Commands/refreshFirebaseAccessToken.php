<?php

namespace App\Console\Commands;

use App\Models\GgToken;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class refreshFirebaseAccessToken extends Command
{
    protected $signature = 'refresh-firebase-access-token';
    protected $description = 'Refresh the Firebase access token used for push notifications.';

    public function handle()
    {
        if (!filter_var(env('FIREBASE_PUSH_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->warn('Firebase push is disabled by FIREBASE_PUSH_ENABLED=false.');
            return 0;
        }

        $keyFilePath = env('FIREBASE_CREDENTIALS_PATH', base_path('app/Library/GooglePushNotification.json'));

        if (empty($keyFilePath) || !is_file($keyFilePath)) {
            $message = "Firebase credential file not found: {$keyFilePath}";
            $this->error($message);
            Log::error($message);
            return 1;
        }

        try {
            $accessToken = $this->getAccessToken($keyFilePath);

            $firebaseAccessToken = GgToken::where('type', 1)->first() ?: new GgToken();
            $firebaseAccessToken->access_token = $accessToken;
            $firebaseAccessToken->type = 1;
            $firebaseAccessToken->status = 1;
            $firebaseAccessToken->created_at = $firebaseAccessToken->created_at ?: now();
            $firebaseAccessToken->save();

            $this->info('Firebase access token refreshed.');
            return 0;
        } catch (\Exception $e) {
            $message = 'Firebase access token refresh failed: ' . $e->getMessage();
            $this->error($message);
            Log::error($message);
            return 1;
        }
    }

    private function getAccessToken($keyFilePath)
    {
        $credentials = new ServiceAccountCredentials(
            'https://www.googleapis.com/auth/firebase.messaging',
            $keyFilePath
        );

        $accessToken = $credentials->fetchAuthToken();

        if (empty($accessToken['access_token'])) {
            throw new \RuntimeException($accessToken['error_description'] ?? $accessToken['error'] ?? 'No access token returned.');
        }

        return $accessToken['access_token'];
    }
}
