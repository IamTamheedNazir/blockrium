<?php

namespace App\Http\Controllers;

use App\Services\GooglePlayBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GooglePlayRtdnController extends Controller
{
    private $billing;

    public function __construct(GooglePlayBillingService $billing)
    {
        $this->billing = $billing;
    }

    public function handle(Request $request)
    {
        $authorization = (string) $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)
            || !$this->billing->verifyRtdnIdentity(trim($matches[1]))) {
            return response()->json(['message' => 'Unauthorized notification.'], 401);
        }

        try {
            $message = $request->input('message');
            if (!is_array($message)) {
                return response()->json(['message' => 'Invalid notification envelope.'], 400);
            }
            $result = $this->billing->processRtdn($message);
            return response()->json(['ok' => true, 'result' => $result]);
        } catch (\Throwable $exception) {
            Log::warning('Google Play RTDN handler failed.', [
                'exception' => get_class($exception),
                'message_id' => data_get($request->all(), 'message.messageId'),
            ]);
            return response()->json(['message' => 'Notification processing failed.'], 500);
        }
    }
}
