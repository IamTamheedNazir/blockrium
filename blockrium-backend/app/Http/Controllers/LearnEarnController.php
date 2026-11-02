<?php

namespace App\Http\Controllers;

use App\Services\LearnEarnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class LearnEarnController extends Controller
{
    private $learn;

    public function __construct(LearnEarnService $learn)
    {
        $this->learn = $learn;
        $this->middleware('auth:api_res_user');
    }

    public function modules()
    {
        $user = auth('api_res_user')->user();

        try {
            return response()->json([
                'success' => true,
                'data' => $this->learn->modulesForUser($user),
            ]);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Learn modules are temporarily unavailable.');
        }
    }

    public function detail(int $id)
    {
        $user = auth('api_res_user')->user();

        try {
            $payload = $this->learn->moduleDetail($user, $id);
            if (!$payload) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lesson not found.',
                    'data' => [],
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $payload,
            ]);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Learn module is temporarily unavailable.');
        }
    }

    public function complete(Request $request, int $id)
    {
        $user = auth('api_res_user')->user();

        $validated = $request->validate([
            'answers' => 'required|array|min:1|max:20',
            'answers.*.question_id' => 'required|integer|min:1',
            'answers.*.selected_option' => 'required|string|in:A,B,C,D,a,b,c,d',
        ]);

        try {
            $result = $this->learn->completeModule($user, $id, $validated['answers']);
            $status = (int) ($result['status'] ?? 200);
            unset($result['status']);

            return response()->json($result, $status);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'The lesson could not be completed.');
        }
    }

    private function safeFailure(Throwable $error, string $message)
    {
        Log::error($message, [
            'exception' => get_class($error),
            'error' => $error->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => [],
        ], 500);
    }
}
