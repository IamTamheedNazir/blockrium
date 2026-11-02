<?php

namespace App\Http\Controllers;

use App\Services\TrustCenterService;
use Illuminate\Support\Facades\Log;
use Throwable;

class TrustCenterController extends Controller
{
    private $trustCenter;

    public function __construct(TrustCenterService $trustCenter)
    {
        $this->trustCenter = $trustCenter;
    }

    public function show()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->trustCenter->publicPayload(),
            ]);
        } catch (Throwable $error) {
            Log::error('Trust Center failed', [
                'exception' => get_class($error),
                'error' => $error->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Trust Center is temporarily unavailable.',
                'data' => [],
            ], 500);
        }
    }
}
