<?php

namespace App\Http\Controllers;

use App\Services\DiditService;
use App\Services\VerifiedMemberService;
use Illuminate\Http\Request;

class DiditWebhookController extends Controller
{
    private $didit;
    private $members;

    public function __construct(DiditService $didit, VerifiedMemberService $members)
    {
        $this->didit = $didit;
        $this->members = $members;
    }

    public function handle(Request $request)
    {
        $rawBody = $request->getContent();
        $body = json_decode($rawBody, true);
        if (!is_array($body)) {
            return response()->json(['message' => 'Invalid JSON.'], 400);
        }

        if (!$this->didit->verifyWebhook(
            $rawBody,
            $body,
            $request->header('X-Signature-V2'),
            $request->header('X-Signature'),
            $request->header('X-Timestamp')
        )) {
            return response()->json(['message' => 'Invalid Didit signature.'], 401);
        }

        try {
            $result = $this->members->handleDiditWebhook($body);
            return response()->json(['ok' => true, 'result' => $result]);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'Webhook processing failed.'], 500);
        }
    }
}
