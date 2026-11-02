<?php

namespace App\Http\Controllers;

use App\Services\DodoBoostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DodoBoostWebhookController extends Controller
{
    private $boosts;

    public function __construct(DodoBoostService $boosts)
    {
        $this->boosts = $boosts;
    }

    public function handle(Request $request)
    {
        $rawBody = $request->getContent();
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return response()->json(['success' => false, 'message' => 'Invalid JSON.'], 400);
        }

        if (!$this->boosts->verifyWebhook($request, $rawBody)) {
            return response()->json(['success' => false, 'message' => 'Invalid Dodo webhook signature.'], 401);
        }

        try {
            $result = $this->boosts->processWebhook(
                $payload,
                $request->header('webhook-id') ?: $request->header('x-webhook-id')
            );

            return response()->json($result, 200);
        } catch (\Throwable $exception) {
            Log::error('Dodo mining boost webhook processing failed.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Temporary processing error. Dodo should retry this webhook.',
            ], 500);
        }
    }
}
