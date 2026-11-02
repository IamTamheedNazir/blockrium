<?php

namespace App\Http\Controllers;

use App\Services\RevenueCatService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class RevenueCatController extends BaseController
{
    private $revenueCatService;

    public function __construct(RevenueCatService $revenueCatService)
    {
        $this->revenueCatService = $revenueCatService;
    }

    public function handle(Request $request)
    {
        if (!$this->isAuthorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $result = $this->revenueCatService->processWebhook($request->all());

            return response()->json($result, 200);
        } catch (InvalidArgumentException $error) {
            Log::warning('RevenueCat webhook rejected malformed payload', [
                'message' => $error->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $error->getMessage(),
            ], 422);
        } catch (QueryException $error) {
            Log::error('RevenueCat webhook database failure', [
                'message' => $error->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Temporary database error. RevenueCat should retry this webhook.',
            ], 500);
        } catch (Throwable $error) {
            Log::error('RevenueCat webhook processing failure', [
                'message' => $error->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Temporary processing error. RevenueCat should retry this webhook.',
            ], 500);
        }
    }

    public function stats()
    {
        return response()->json([
            'success' => true,
            'data' => $this->revenueCatService->subscriptionStats(),
        ]);
    }

    public function users(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->revenueCatService->subscriptionUsers($request->all()),
        ]);
    }

    public function revenue()
    {
        return response()->json([
            'success' => true,
            'data' => $this->revenueCatService->revenueMetrics(),
        ]);
    }

    private function isAuthorized(Request $request): bool
    {
        $expected = env('REVENUECAT_WEBHOOK_AUTH');

        if (!$expected) {
            return false;
        }

        $actual = (string) $request->header('Authorization', '');

        return hash_equals((string) $expected, $actual);
    }
}
