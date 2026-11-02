<?php

namespace App\Http\Controllers;

use App\Services\VerifiedMemberService;
use Illuminate\Http\Request;

class VerifiedMemberController extends Controller
{
    private $members;

    public function __construct(VerifiedMemberService $members)
    {
        $this->members = $members;
        $this->middleware('auth:api_res_user');
    }

    public function status()
    {
        return response()->json(['status' => 1, 'data' => $this->members->status($this->userId())]);
    }

    public function waitlist()
    {
        return response()->json(['status' => 1, 'data' => $this->members->waitlist($this->userId())]);
    }

    public function joinWaitlist(Request $request)
    {
        $data = $request->validate([
            'country_code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'country_name' => 'required|string|max:100',
        ]);

        return response()->json([
            'status' => 1,
            'message' => 'You joined the KYC waiting list.',
            'data' => $this->members->joinWaitlist($this->userId(), $data['country_code'], $data['country_name']),
        ]);
    }

    public function leaveWaitlist()
    {
        return response()->json([
            'status' => 1,
            'message' => 'You left the KYC waiting list.',
            'data' => $this->members->leaveWaitlist($this->userId()),
        ]);
    }

    public function startKyc()
    {
        return response()->json(['status' => 1, 'data' => $this->members->startKyc($this->userId())]);
    }

    public function submitKyc(Request $request)
    {
        return response()->json(['status' => 1, 'data' => $this->members->submitKyc($this->userId())]);
    }

    public function startPayment()
    {
        return response()->json(['status' => 1, 'data' => $this->members->startPayment($this->userId())]);
    }

    public function paymentSupport(Request $request)
    {
        $data = $request->validate([
            'payment_id' => 'nullable|integer|min:1',
            'provider_reference' => 'nullable|string|max:190',
            'message' => 'nullable|string|max:1000',
        ]);

        return response()->json([
            'status' => 1,
            'message' => 'Payment support request submitted.',
            'data' => $this->members->createPaymentSupportCase(
                $this->userId(),
                isset($data['payment_id']) ? (int) $data['payment_id'] : null,
                $data['provider_reference'] ?? null,
                $data['message'] ?? null
            ),
        ], 201);
    }

    public function paymentSupportCases()
    {
        return response()->json([
            'status' => 1,
            'data' => $this->members->paymentSupportCases($this->userId()),
        ]);
    }

    public function usernameAvailability(Request $request)
    {
        $data = $request->validate(['username' => 'required|string|max:20']);
        return response()->json(['status' => 1, 'available' => $this->members->usernameAvailable($data['username'])]);
    }

    public function createUsername(Request $request)
    {
        $data = $request->validate(['username' => 'required|string|max:20']);
        return response()->json(['status' => 1, 'data' => $this->members->createUsername($this->userId(), $data['username'])]);
    }

    public function transfer(Request $request)
    {
        $data = $request->validate([
            'recipient_username' => 'required|string|max:20',
            'amount' => 'required|string|max:30',
            'idempotency_key' => 'required|string|max:120',
            'note' => 'nullable|string|max:500',
        ]);
        return response()->json([
            'status' => 1,
            'message' => 'BRC transfer completed.',
            'data' => $this->members->transferByUsername($this->userId(), $data['recipient_username'], $data['amount'], $data['idempotency_key'], $data['note'] ?? null),
        ]);
    }

    public function transfers(Request $request)
    {
        $limit = min(100, max(1, (int) $request->input('limit', 25)));
        return response()->json(['status' => 1, 'data' => $this->members->transfers($this->userId(), $limit)]);
    }

    private function userId(): int
    {
        return (int) auth('api_res_user')->id();
    }
}
