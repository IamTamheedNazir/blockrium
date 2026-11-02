<?php

namespace App\Http\Controllers;

use App\Services\PartnerPromoCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PartnerPromoCodeController extends Controller
{
    private $promoCodes;

    public function __construct(PartnerPromoCodeService $promoCodes)
    {
        $this->promoCodes = $promoCodes;
        $this->middleware('auth:api_res_user');
    }

    public function redeem(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:40',
            'request_key' => 'required|string|max:100|regex:/^[A-Za-z0-9._:-]+$/',
        ]);

        try {
            $userId = (int) auth('api_res_user')->id();
            $deviceHash = hash_hmac('sha256', (string) $request->userAgent(), (string) config('app.key'));
            $visitorHash = hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
            $result = $this->promoCodes->redeem(
                $data['code'],
                $userId,
                $data['request_key'],
                $deviceHash,
                $visitorHash
            );
            $status = (int) ($result['status'] ?? 200);
            unset($result['status']);

            return response()->json($result, $status);
        } catch (Throwable $error) {
            Log::error('Partner promo redemption failed.', [
                'exception' => get_class($error),
                'error' => $error->getMessage(),
                'user_id' => auth('api_res_user')->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'The promo code could not be redeemed right now. Please try again later.',
                'errors' => [],
            ], 500);
        }
    }

    public function redemptions()
    {
        return response()->json([
            'success' => true,
            'data' => $this->promoCodes->userRedemptions((int) auth('api_res_user')->id()),
        ]);
    }
}
