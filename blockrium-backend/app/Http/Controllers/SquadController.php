<?php

namespace App\Http\Controllers;

use App\Services\SquadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SquadController extends Controller
{
    private $squads;

    public function __construct(SquadService $squads)
    {
        $this->squads = $squads;
        $this->middleware('auth:api_res_user');
    }

    public function me(Request $request)
    {
        $user = auth('api_res_user')->user();

        try {
            if (filter_var($request->query('profile'), FILTER_VALIDATE_BOOLEAN)) {
                return response()->json([
                    'success' => true,
                    'data' => $this->squads->mySquadProfile($user),
                ]);
            }

            $requestedLimit = $request->query('member_limit');
            $memberLimit = is_numeric($requestedLimit) && (int) $requestedLimit > 0
                ? min(50, (int) $requestedLimit)
                : null;
            $includeMemberGroups = $request->has('include_member_groups')
                ? filter_var($request->query('include_member_groups'), FILTER_VALIDATE_BOOLEAN)
                : true;

            return response()->json([
                'success' => true,
                'data' => $this->squads->mySquad($user, $memberLimit, $includeMemberGroups),
            ]);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad details are temporarily unavailable.');
        }
    }

    public function home()
    {
        $user = auth('api_res_user')->user();

        try {
            return response()->json([
                'success' => true,
                'data' => $this->squads->homeSummary($user),
            ]);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad home is temporarily unavailable.');
        }
    }

    public function appHome()
    {
        $user = auth('api_res_user')->user();

        try {
            return response()->json([
                'success' => true,
                'data' => $this->squads->appHome($user),
            ]);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad app home is temporarily unavailable.');
        }
    }

    public function members(Request $request)
    {
        $user = auth('api_res_user')->user();

        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'members' => $this->squads->membersForTab(
                        $user,
                        (string) $request->query('tab', 'members'),
                        (int) $request->query('limit', 25)
                    ),
                ],
            ]);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad members are temporarily unavailable.');
        }
    }

    public function discover(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => ['squads' => $this->squads->discoverSquads((int) $request->query('limit', 30), $request->query())],
            ]);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad discovery is temporarily unavailable.');
        }
    }

    public function leaderboard(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => ['squads' => $this->squads->leaderboard((int) $request->query('limit', 30), $request->query())],
            ]);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad leaderboard is temporarily unavailable.');
        }
    }

    public function create(Request $request)
    {
        $user = auth('api_res_user')->user();
        $validated = $request->validate([
            'name' => 'required|string|min:3|max:120',
            'description' => 'nullable|string|max:1000',
            'max_members' => 'nullable',
            'is_public' => 'nullable|boolean',
            'country_code' => 'nullable|string|max:60',
            'language_code' => 'nullable|string|max:20',
            'poster_template' => 'nullable|string|max:40',
        ]);

        try {
            $result = $this->squads->createSquad($user, $validated);
            return $this->jsonResult($result);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad could not be created.');
        }
    }

    public function join(Request $request)
    {
        $user = auth('api_res_user')->user();
        $validated = $request->validate([
            'invite_code' => 'nullable|string|max:40',
            'squad_id' => 'nullable|integer|min:1',
        ]);

        if (empty($validated['invite_code']) && empty($validated['squad_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invite code or squad ID is required.',
                'data' => [],
            ], 422);
        }

        try {
            $result = $this->squads->joinSquad($user, $validated);
            return $this->jsonResult($result);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad could not be joined.');
        }
    }

    public function leave()
    {
        $user = auth('api_res_user')->user();

        try {
            $result = $this->squads->leaveSquad($user);
            return $this->jsonResult($result);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad could not be left.');
        }
    }

    public function claimChest()
    {
        $user = auth('api_res_user')->user();

        try {
            $result = $this->squads->claimWeeklyChest($user);
            return $this->jsonResult($result);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad chest could not be claimed.');
        }
    }

    public function missions()
    {
        $user = auth('api_res_user')->user();

        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'missions' => $this->squads->missionsForUser($user),
                    'invite_streak' => $this->squads->inviteStreak($user),
                ],
            ]);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad missions are temporarily unavailable.');
        }
    }

    public function claimMission(int $id)
    {
        $user = auth('api_res_user')->user();

        try {
            $result = $this->squads->claimMission($user, $id);
            return $this->jsonResult($result);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad mission could not be claimed.');
        }
    }

    public function claimInviteStreak()
    {
        $user = auth('api_res_user')->user();

        try {
            $result = $this->squads->claimInviteStreak($user);
            return $this->jsonResult($result);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Invite streak could not be claimed.');
        }
    }

    public function shareCard()
    {
        $user = auth('api_res_user')->user();

        try {
            $result = $this->squads->shareCard($user);
            return $this->jsonResult($result);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad share card is temporarily unavailable.');
        }
    }

    public function trackShare(Request $request)
    {
        $user = auth('api_res_user')->user();
        $validated = $request->validate([
            'channel' => 'nullable|string|max:40',
        ]);

        try {
            $result = $this->squads->recordShare($user, $validated, $request->ip(), $request->userAgent());
            return $this->jsonResult($result);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad share could not be tracked.');
        }
    }

    public function follow(Request $request)
    {
        $user = auth('api_res_user')->user();
        $validated = $request->validate([
            'squad_id' => 'required|integer|min:1',
            'follow' => 'nullable|boolean',
        ]);

        try {
            $result = $this->squads->followSquad($user, (int) $validated['squad_id'], $request->has('follow') ? (bool) $validated['follow'] : true);
            return $this->jsonResult($result);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad follow could not be updated.');
        }
    }

    public function regenerateInvite()
    {
        $user = auth('api_res_user')->user();

        try {
            $result = $this->squads->regenerateInvite($user);
            return $this->jsonResult($result);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad invite could not be refreshed.');
        }
    }

    public function updateMemberRole(Request $request)
    {
        $user = auth('api_res_user')->user();
        $validated = $request->validate([
            'user_id' => 'required|integer|min:1',
            'role' => 'required|string|max:30',
        ]);

        try {
            $result = $this->squads->updateMemberRole($user, (int) $validated['user_id'], $validated['role']);
            return $this->jsonResult($result);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad role could not be updated.');
        }
    }

    public function removeMember(Request $request)
    {
        $user = auth('api_res_user')->user();
        $validated = $request->validate([
            'user_id' => 'required|integer|min:1',
        ]);

        try {
            $result = $this->squads->removeMember($user, (int) $validated['user_id']);
            return $this->jsonResult($result);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad member could not be removed.');
        }
    }

    public function announcements()
    {
        $user = auth('api_res_user')->user();
        $membership = $this->squads->activeMembership((int) $user->id);

        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'announcements' => $membership ? $this->squads->announcements((int) $membership->squad_id) : [],
                ],
            ]);
        } catch (Throwable $error) {
            return $this->safeFailure($error, 'Squad announcements are temporarily unavailable.');
        }
    }

    private function jsonResult(array $result)
    {
        $status = (int) ($result['status'] ?? 200);
        unset($result['status']);

        return response()->json($result, $status);
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
