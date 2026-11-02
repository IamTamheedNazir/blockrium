<?php

namespace App\Http\Controllers;

use App\Services\EngagementAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EngagementAdminController extends Controller
{
    private $engagement;

    public function __construct(EngagementAdminService $engagement)
    {
        $this->engagement = $engagement;
        $this->middleware('checklogin');
    }

    public function dashboard(Request $request)
    {
        $period = in_array($request->query('period'), ['today', '7days', '30days'])
            ? $request->query('period')
            : '7days';

        return view('engagement.dashboard', $this->engagement->dashboard($period));
    }

    public function xpEvents(Request $request)
    {
        return view('engagement.xp-events', [
            'events' => $this->engagement->xpEvents($request),
            'filters' => $this->engagement->xpFilterOptions(),
            'engagementService' => $this->engagement,
        ]);
    }

    public function badges(Request $request)
    {
        $badges = Schema::hasTable('badges')
            ? DB::table('badges')
                ->leftJoin('user_badges', 'badges.id', '=', 'user_badges.badge_id')
                ->groupBy('badges.id', 'badges.key', 'badges.name', 'badges.description', 'badges.icon', 'badges.category', 'badges.is_active', 'badges.sort_order', 'badges.created_at', 'badges.updated_at')
                ->select('badges.*', DB::raw('COUNT(user_badges.id) as earned_count'))
                ->orderBy('badges.sort_order')
                ->orderBy('badges.id')
                ->paginate(25)
            : $this->emptyPage();

        return view('engagement.badges.index', compact('badges'));
    }

    public function createBadge()
    {
        return view('engagement.badges.form', ['badge' => null]);
    }

    public function storeBadge(Request $request)
    {
        $data = $this->validateBadge($request);

        DB::transaction(function () use ($data) {
            $id = DB::table('badges')->insertGetId(array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $this->engagement->recordAdminAudit($this->adminId(), 'create', 'badge', $id, $data);
        });

        return redirect()->route('admin.engagement.badges')->with('success', 'Badge created successfully.');
    }

    public function editBadge(int $id)
    {
        $badge = DB::table('badges')->where('id', $id)->first();
        abort_unless($badge, 404);

        return view('engagement.badges.form', compact('badge'));
    }

    public function updateBadge(Request $request, int $id)
    {
        abort_unless(DB::table('badges')->where('id', $id)->exists(), 404);
        $data = $this->validateBadge($request, $id);

        DB::transaction(function () use ($id, $data) {
            DB::table('badges')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
            $this->engagement->recordAdminAudit($this->adminId(), 'update', 'badge', $id, $data);
        });

        return redirect()->route('admin.engagement.badges')->with('success', 'Badge updated successfully.');
    }

    public function toggleBadge(int $id)
    {
        $badge = DB::table('badges')->where('id', $id)->first();
        abort_unless($badge, 404);
        $active = (int) !$badge->is_active;

        DB::transaction(function () use ($id, $active) {
            DB::table('badges')->where('id', $id)->update(['is_active' => $active, 'updated_at' => now()]);
            $this->engagement->recordAdminAudit($this->adminId(), 'toggle', 'badge', $id, ['is_active' => $active]);
        });

        return back()->with('success', 'Badge status updated.');
    }

    public function userBadges(Request $request)
    {
        return view('engagement.user-badges', [
            'awards' => $this->engagement->userBadges($request),
            'badges' => Schema::hasTable('badges') ? DB::table('badges')->orderBy('name')->get() : collect(),
        ]);
    }

    public function seasons()
    {
        $seasons = Schema::hasTable('reward_seasons')
            ? DB::table('reward_seasons')->orderByDesc('starts_at')->paginate(25)
            : $this->emptyPage();

        return view('engagement.seasons.index', compact('seasons'));
    }

    public function createSeason()
    {
        return view('engagement.seasons.form', ['season' => null]);
    }

    public function storeSeason(Request $request)
    {
        $data = $this->validateSeason($request);

        DB::transaction(function () use ($data) {
            $id = DB::table('reward_seasons')->insertGetId(array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $this->engagement->recordAdminAudit($this->adminId(), 'create', 'reward_season', $id, $data);
        });

        return redirect()->route('admin.engagement.seasons')->with('success', 'Season created successfully.');
    }

    public function editSeason(int $id)
    {
        $season = DB::table('reward_seasons')->where('id', $id)->first();
        abort_unless($season, 404);

        return view('engagement.seasons.form', compact('season'));
    }

    public function updateSeason(Request $request, int $id)
    {
        abort_unless(DB::table('reward_seasons')->where('id', $id)->exists(), 404);
        $data = $this->validateSeason($request, $id);

        DB::transaction(function () use ($id, $data) {
            DB::table('reward_seasons')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
            $this->engagement->recordAdminAudit($this->adminId(), 'update', 'reward_season', $id, $data);
        });

        return redirect()->route('admin.engagement.seasons')->with('success', 'Season updated successfully.');
    }

    public function referralMilestones()
    {
        $milestones = Schema::hasTable('referral_milestones')
            ? DB::table('referral_milestones')
                ->leftJoin('referral_milestone_claims', 'referral_milestones.id', '=', 'referral_milestone_claims.referral_milestone_id')
                ->groupBy('referral_milestones.id', 'referral_milestones.key', 'referral_milestones.title', 'referral_milestones.description', 'referral_milestones.reward_category', 'referral_milestones.required_active_referrals', 'referral_milestones.required_verified_referrals', 'referral_milestones.required_task_referrals', 'referral_milestones.required_streak_referrals', 'referral_milestones.required_quality_score', 'referral_milestones.reward_amount', 'referral_milestones.reward_label', 'referral_milestones.reward_xp', 'referral_milestones.is_active', 'referral_milestones.sort_order', 'referral_milestones.created_at', 'referral_milestones.updated_at')
                ->select('referral_milestones.*', DB::raw('COUNT(referral_milestone_claims.id) as claim_count'))
                ->orderBy('referral_milestones.sort_order')
                ->paginate(25)
            : $this->emptyPage();

        return view('engagement.referral-milestones.index', compact('milestones'));
    }

    public function createReferralMilestone()
    {
        return view('engagement.referral-milestones.form', ['milestone' => null]);
    }

    public function storeReferralMilestone(Request $request)
    {
        $data = $this->validateMilestone($request);

        DB::transaction(function () use ($data) {
            $id = DB::table('referral_milestones')->insertGetId(array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $this->engagement->recordAdminAudit($this->adminId(), 'create', 'referral_milestone', $id, $data);
        });

        return redirect()->route('admin.engagement.referral-milestones')->with('success', 'Referral milestone created successfully.');
    }

    public function editReferralMilestone(int $id)
    {
        $milestone = DB::table('referral_milestones')->where('id', $id)->first();
        abort_unless($milestone, 404);

        return view('engagement.referral-milestones.form', compact('milestone'));
    }

    public function updateReferralMilestone(Request $request, int $id)
    {
        abort_unless(DB::table('referral_milestones')->where('id', $id)->exists(), 404);
        $data = $this->validateMilestone($request, $id);

        DB::transaction(function () use ($id, $data) {
            DB::table('referral_milestones')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
            $this->engagement->recordAdminAudit($this->adminId(), 'update', 'referral_milestone', $id, $data);
        });

        return redirect()->route('admin.engagement.referral-milestones')->with('success', 'Referral milestone updated. Existing claims were preserved.');
    }

    public function toggleReferralMilestone(int $id)
    {
        $milestone = DB::table('referral_milestones')->where('id', $id)->first();
        abort_unless($milestone, 404);
        $active = (int) !$milestone->is_active;

        DB::transaction(function () use ($id, $active) {
            DB::table('referral_milestones')->where('id', $id)->update(['is_active' => $active, 'updated_at' => now()]);
            $this->engagement->recordAdminAudit($this->adminId(), 'toggle', 'referral_milestone', $id, ['is_active' => $active]);
        });

        return back()->with('success', 'Referral milestone status updated.');
    }

    public function referralClaims(Request $request)
    {
        return view('engagement.referral-claims', [
            'claims' => $this->engagement->referralClaims($request),
            'claimTotal' => $this->engagement->referralClaimTotal($request),
            'milestones' => Schema::hasTable('referral_milestones') ? DB::table('referral_milestones')->orderBy('title')->get() : collect(),
        ]);
    }

    public function dailyMissions()
    {
        return view('engagement.daily-missions', $this->engagement->dailyMissionMonitor());
    }

    public function health()
    {
        return view('engagement.health', $this->engagement->productionHealth());
    }

    private function validateBadge(Request $request, ?int $id = null): array
    {
        $validated = $request->validate([
            'key' => ['required', 'max:100', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', Rule::unique('badges', 'key')->ignore($id)],
            'name' => 'required|string|max:140',
            'description' => 'nullable|string|max:2000',
            'icon' => 'nullable|string|max:190',
            'category' => 'nullable|string|max:80',
            'is_active' => 'required|boolean',
            'sort_order' => 'required|integer|min:0|max:100000',
        ]);

        return $validated;
    }

    private function validateSeason(Request $request, ?int $id = null): array
    {
        $validated = $request->validate([
            'key' => ['required', 'max:100', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', Rule::unique('reward_seasons', 'key')->ignore($id)],
            'title' => 'required|string|max:160',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'is_active' => 'required|boolean',
            'metadata' => 'nullable|json',
        ]);

        $metadata = !empty($validated['metadata']) ? json_decode($validated['metadata'], true) : null;
        if (is_array($metadata) && $this->containsSensitiveKey($metadata)) {
            throw ValidationException::withMessages([
                'metadata' => 'Metadata cannot contain credentials, tokens, wallet secrets, private keys, recovery phrases, or passcodes.',
            ]);
        }
        $validated['metadata'] = is_array($metadata) ? json_encode($metadata) : null;

        return $validated;
    }

    private function validateMilestone(Request $request, ?int $id = null): array
    {
        $validated = $request->validate([
            'key' => ['required', 'max:100', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', Rule::unique('referral_milestones', 'key')->ignore($id)],
            'title' => 'required|string|max:160',
            'description' => 'nullable|string|max:2000',
            'reward_category' => 'nullable|string|max:40|regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/',
            'required_active_referrals' => 'required|integer|min:1|max:1000000',
            'required_verified_referrals' => 'nullable|integer|min:0|max:1000000',
            'required_task_referrals' => 'nullable|integer|min:0|max:1000000',
            'required_streak_referrals' => 'nullable|integer|min:0|max:1000000',
            'required_quality_score' => 'nullable|integer|min:0|max:100',
            'reward_amount' => 'required|numeric|min:0|max:1000000',
            'reward_label' => 'required|string|max:20|regex:/^[A-Za-z0-9._-]+$/',
            'reward_xp' => 'required|integer|min:0|max:1000000',
            'is_active' => 'required|boolean',
            'sort_order' => 'required|integer|min:0|max:100000',
        ]);

        return array_merge([
            'reward_category' => 'active_referral',
            'required_verified_referrals' => 0,
            'required_task_referrals' => 0,
            'required_streak_referrals' => 0,
            'required_quality_score' => 0,
        ], $validated);
    }

    private function adminId(): ?int
    {
        $id = session('ad_id');
        return $id !== null ? (int) $id : null;
    }

    private function emptyPage()
    {
        return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25, 1, ['path' => request()->url()]);
    }

    private function containsSensitiveKey(array $data): bool
    {
        foreach ($data as $key => $value) {
            if (preg_match('/password|passcode|secret|token|authorization|private.?key|recovery|seed.?phrase|keystore/i', (string) $key)) {
                return true;
            }
            if (is_array($value) && $this->containsSensitiveKey($value)) {
                return true;
            }
        }

        return false;
    }
}
