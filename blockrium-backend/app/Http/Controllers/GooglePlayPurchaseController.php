<?php

namespace App\Http\Controllers;

use App\Services\GooglePlayBillingService;
use Illuminate\Http\Request;

class GooglePlayPurchaseController extends Controller
{
    private $billing;

    public function __construct(GooglePlayBillingService $billing)
    {
        $this->billing = $billing;
        $this->middleware('auth:api_res_user');
    }

    public function createIntent(Request $request)
    {
        $data = $request->validate([
            'purpose' => 'required|in:kyc,boost',
            'plan_id' => 'required_if:purpose,boost|nullable|integer|min:1',
        ]);

        $intent = $this->billing->createIntent(
            (int) auth('api_res_user')->id(),
            $data['purpose'],
            isset($data['plan_id']) ? (int) $data['plan_id'] : null
        );

        return response()->json(['status' => 1, 'message' => $intent['message'], 'data' => $intent], 201);
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'purchase_id' => 'required|uuid',
            'purchase_token' => 'required|string|min:20|max:4096',
        ]);

        $result = $this->billing->verifyIntent(
            (int) auth('api_res_user')->id(),
            $data['purchase_id'],
            $data['purchase_token']
        );

        return response()->json([
            'status' => 1,
            'message' => $result['status'] === 'paid' ? 'Google Play purchase verified.' : 'Purchase status is being confirmed.',
            'data' => $result,
        ]);
    }
}
