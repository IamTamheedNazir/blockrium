<?php

namespace App\Http\Controllers;

use App\Services\PartnerCampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PartnerCampaignController extends Controller
{
    private $campaigns;

    public function __construct(PartnerCampaignService $campaigns)
    {
        $this->campaigns = $campaigns;
        $this->middleware('auth:api_res_user');
    }

    public function index()
    {
        $user = auth('api_res_user')->user();

        try {
            return response()->json([
                'success' => true,
                'data' => $this->campaigns->activeCampaigns((int) $user->id),
            ]);
        } catch (Throwable $error) {
            return $this->failure($error, 'Partner campaigns are temporarily unavailable.');
        }
    }

    public function click(Request $request, int $id)
    {
        $user = auth('api_res_user')->user();

        try {
            return response()->json([
                'success' => true,
                'data' => $this->campaigns->recordUserClick($id, (int) $user->id, $request),
            ]);
        } catch (Throwable $error) {
            return $this->failure($error, 'The partner campaign link could not be opened.');
        }
    }

    public function claim(int $id)
    {
        $user = auth('api_res_user')->user();

        try {
            $result = $this->campaigns->claim($id, (int) $user->id);
            $status = (int) ($result['status'] ?? 200);
            unset($result['status']);

            return response()->json($result, $status);
        } catch (Throwable $error) {
            return $this->failure($error, 'The partner campaign reward could not be claimed.');
        }
    }

    public function taskClick(Request $request, int $id, int $taskId)
    {
        $user = auth('api_res_user')->user();

        try {
            return response()->json([
                'success' => true,
                'data' => $this->campaigns->recordTaskClick($id, $taskId, (int) $user->id, $request),
            ]);
        } catch (Throwable $error) {
            return $this->failure($error, 'The partner campaign task link could not be opened.');
        }
    }

    public function submitTask(Request $request, int $id, int $taskId)
    {
        $user = auth('api_res_user')->user();
        $data = $request->validate([
            'evidence_url' => 'nullable|url|max:2000',
            'evidence_note' => 'nullable|string|max:2000',
        ]);

        try {
            return response()->json([
                'success' => true,
                'data' => $this->campaigns->submitTaskCompletion($id, $taskId, (int) $user->id, $data['evidence_url'] ?? null, $data['evidence_note'] ?? null),
            ]);
        } catch (Throwable $error) {
            return $this->failure($error, 'The partner campaign task could not be submitted.');
        }
    }

    public function taskClaim(int $id, int $taskId)
    {
        $user = auth('api_res_user')->user();

        try {
            $result = $this->campaigns->claimTask($id, $taskId, (int) $user->id);
            $status = (int) ($result['status'] ?? 200);
            unset($result['status']);

            return response()->json($result, $status);
        } catch (Throwable $error) {
            return $this->failure($error, 'The partner campaign task reward could not be claimed.');
        }
    }

    private function failure(Throwable $error, string $message)
    {
        Log::error($message, [
            'exception' => get_class($error),
            'error' => $error->getMessage(),
        ]);

        $status = method_exists($error, 'getStatusCode') ? (int) $error->getStatusCode() : 500;
        if ($status < 400 || $status > 599) {
            $status = 500;
        }

        return response()->json([
            'success' => false,
            'message' => $status === 500 ? $message : $error->getMessage(),
            'errors' => [],
        ], $status);
    }
}
