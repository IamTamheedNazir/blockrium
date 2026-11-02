<?php

namespace App\Http\Controllers;

use App\Services\DailyMissionService;
use App\Services\GamificationService;
use App\Services\ReferralJourneyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class EngagementController extends Controller
{
    private $dailyMissions;
    private $gamification;
    private $referralJourney;

    public function __construct(
        DailyMissionService $dailyMissions,
        GamificationService $gamification,
        ReferralJourneyService $referralJourney
    ) {
        $this->dailyMissions = $dailyMissions;
        $this->gamification = $gamification;
        $this->referralJourney = $referralJourney;
        $this->middleware('auth:api_res_user');
    }

    public function dailyMissions(Request $request)
    {
        $user = auth('api_res_user')->user();

        try {
            return response()->json([
                'success' => true,
                'data' => $this->dailyMissions->getDashboard($user),
            ]);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Daily missions are temporarily unavailable.');
        }
    }

    public function gamificationSummary(Request $request)
    {
        $user = auth('api_res_user')->user();

        try {
            return response()->json([
                'success' => true,
                'data' => $this->gamification->getSummary($user),
            ]);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Gamification summary is temporarily unavailable.');
        }
    }

    public function gamificationBadges(Request $request)
    {
        $user = auth('api_res_user')->user();

        try {
            return response()->json([
                'success' => true,
                'data' => $this->gamification->badges($user),
            ]);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Badges are temporarily unavailable.');
        }
    }

    public function referralJourney(Request $request)
    {
        $user = auth('api_res_user')->user();

        try {
            return response()->json([
                'success' => true,
                'data' => $this->referralJourney->getJourney($user),
            ]);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Referral journey is temporarily unavailable.');
        }
    }

    public function claimReferralMilestone(Request $request, int $id)
    {
        $user = auth('api_res_user')->user();

        try {
            $result = $this->referralJourney->claimMilestone($user, $id);
            $status = (int) ($result['status'] ?? 200);
            unset($result['status']);

            return response()->json($result, $status);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'The referral milestone could not be claimed.');
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
