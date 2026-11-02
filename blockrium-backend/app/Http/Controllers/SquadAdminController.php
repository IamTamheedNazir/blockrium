<?php

namespace App\Http\Controllers;

use App\Services\EngagementAdminService;
use App\Services\SquadService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SquadAdminController extends Controller
{
    private $squads;
    private $engagement;

    public function __construct(SquadService $squads, EngagementAdminService $engagement)
    {
        $this->squads = $squads;
        $this->engagement = $engagement;
        $this->middleware('checklogin');
    }

    public function index(Request $request)
    {
        if (Schema::hasTable('squads')) {
            $memberCounts = DB::table('squad_members')
                ->select('squad_id', DB::raw('COUNT(*) as member_count'))
                ->where('status', 'active')
                ->groupBy('squad_id');

            $query = DB::table('squads')
                ->leftJoinSub($memberCounts, 'active_members', function ($join) {
                    $join->on('squads.id', '=', 'active_members.squad_id');
                })
                ->leftJoin('res_users', 'squads.owner_user_id', '=', 'res_users.id')
                ->select('squads.*', 'res_users.name as owner_name', 'res_users.username as owner_username', 'res_users.email as owner_email', DB::raw('COALESCE(active_members.member_count, 0) as member_count'));

            if ($request->filled('country') && Schema::hasColumn('squads', 'country_code')) {
                $query->where('squads.country_code', strtoupper(substr($request->query('country'), 0, 2)));
            }

            if ($request->filled('status')) {
                $query->where('squads.is_active', $request->query('status') === 'active' ? 1 : 0);
            }

            $squads = $query->orderByDesc('squads.id')->paginate(25)->withQueryString();
        } else {
            $squads = $this->emptyPage($request);
        }

        return view('engagement.squads.index', [
            'squads' => $squads,
            'squadService' => $this->squads,
            'leaderboard' => $this->squads->leaderboard(10, $request->query()),
        ]);
    }

    public function show(int $id)
    {
        $squad = DB::table('squads')->where('id', $id)->first();
        abort_unless($squad, 404);

        $members = Schema::hasTable('squad_members')
            ? DB::table('squad_members')
                ->leftJoin('res_users', 'squad_members.user_id', '=', 'res_users.id')
                ->where('squad_members.squad_id', $id)
                ->orderByDesc('squad_members.status')
                ->orderBy('squad_members.joined_at')
                ->select('squad_members.*', 'res_users.name', 'res_users.username', 'res_users.email', 'res_users.uid')
                ->get()
            : collect();

        $chests = Schema::hasTable('squad_weekly_chests')
            ? DB::table('squad_weekly_chests')
                ->leftJoin('res_users', 'squad_weekly_chests.claimed_by', '=', 'res_users.id')
                ->where('squad_weekly_chests.squad_id', $id)
                ->orderByDesc('squad_weekly_chests.week_start')
                ->select('squad_weekly_chests.*', 'res_users.name as claimed_by_name', 'res_users.username as claimed_by_username')
                ->paginate(20)
            : $this->emptyPage(request());

        $shares = Schema::hasTable('squad_share_events')
            ? DB::table('squad_share_events')
                ->where('squad_id', $id)
                ->select('channel', DB::raw('COUNT(*) as total'))
                ->groupBy('channel')
                ->orderByDesc('total')
                ->get()
            : collect();

        return view('engagement.squads.show', [
            'squad' => $squad,
            'payload' => $this->squads->squadPayload($squad, true),
            'members' => $members,
            'chests' => $chests,
            'shares' => $shares,
        ]);
    }

    public function settings(int $id)
    {
        $squad = DB::table('squads')->where('id', $id)->first();
        abort_unless($squad, 404);

        return view('engagement.squads.settings', [
            'squad' => $squad,
            'missions' => Schema::hasTable('squad_missions') ? DB::table('squad_missions')->orderBy('sort_order')->orderBy('id')->get() : collect(),
            'templates' => Schema::hasTable('squad_announcement_templates') ? DB::table('squad_announcement_templates')->where('is_active', 1)->orderBy('sort_order')->get() : collect(),
            'announcements' => Schema::hasTable('squad_announcements')
                ? DB::table('squad_announcements')->where(function ($query) use ($id) {
                    $query->whereNull('squad_id')->orWhere('squad_id', $id);
                })->orderByDesc('id')->limit(20)->get()
                : collect(),
            'globalSettings' => Schema::hasTable('squad_settings') ? DB::table('squad_settings')->where('group', 'squads')->orderBy('key')->get() : collect(),
        ]);
    }

    public function updateSettings(Request $request, int $id)
    {
        $squad = DB::table('squads')->where('id', $id)->first();
        abort_unless($squad, 404);

        $validated = $request->validate([
            'country_code' => 'nullable|string|max:2',
            'language_code' => 'nullable|string|max:8',
            'poster_template' => 'nullable|string|max:40',
            'promotion_tier' => 'nullable|string|max:30',
            'featured_until' => 'nullable|string|max:40',
            'weekly_target_points' => 'required|integer|min:1|max:1000000',
            'weekly_chest_reward_xp' => 'required|integer|min:0|max:1000000',
            'weekly_chest_reward_brc' => 'nullable|numeric|min:0|max:1000000',
            'admin_note' => 'nullable|string|max:2000',
            'squad_settings' => 'nullable|array',
            'squad_settings.*' => 'nullable|string|max:500',
        ]);

        $posterTemplate = in_array($validated['poster_template'] ?? 'white', ['white', 'dark', 'country', 'weekly_winner'], true)
            ? $validated['poster_template']
            : 'white';
        $promotionTier = in_array($validated['promotion_tier'] ?? 'normal', ['normal', 'featured', 'spotlight'], true)
            ? $validated['promotion_tier']
            : 'normal';

        $data = [
            'weekly_target_points' => (int) $validated['weekly_target_points'],
            'weekly_chest_reward_xp' => (int) $validated['weekly_chest_reward_xp'],
            'weekly_chest_reward_brc' => $validated['weekly_chest_reward_brc'] === null || $validated['weekly_chest_reward_brc'] === '' ? null : (float) $validated['weekly_chest_reward_brc'],
            'is_public' => $request->has('is_public') ? 1 : 0,
            'is_active' => $request->has('is_active') ? 1 : 0,
            'updated_at' => now(),
        ];

        foreach (['country_code', 'language_code', 'poster_template', 'promotion_tier', 'featured_until', 'admin_note'] as $column) {
            if (!Schema::hasColumn('squads', $column)) {
                continue;
            }
            if ($column === 'country_code') {
                $data[$column] = strtoupper(trim((string) ($validated[$column] ?? ''))) ?: null;
            } elseif ($column === 'language_code') {
                $data[$column] = strtolower(trim((string) ($validated[$column] ?? ''))) ?: null;
            } elseif ($column === 'poster_template') {
                $data[$column] = $posterTemplate;
            } elseif ($column === 'promotion_tier') {
                $data[$column] = $promotionTier;
            } elseif ($column === 'featured_until') {
                $data[$column] = trim((string) ($validated[$column] ?? '')) ?: null;
            } else {
                $data[$column] = $validated[$column] ?? null;
            }
        }

        foreach (['allow_followers', 'feed_enabled', 'auto_feed_enabled'] as $column) {
            if (Schema::hasColumn('squads', $column)) {
                $data[$column] = $request->has($column) ? 1 : 0;
            }
        }

        DB::transaction(function () use ($id, $data) {
            DB::table('squads')->where('id', $id)->update($data);
            $this->engagement->recordAdminAudit(session('ad_id') ? (int) session('ad_id') : null, 'settings', 'squad', $id, $data);
        });

        if ($request->has('squad_settings') && Schema::hasTable('squad_settings')) {
            foreach ((array) $request->input('squad_settings', []) as $key => $value) {
                $key = trim((string) $key);
                if ($key === '') {
                    continue;
                }
                DB::table('squad_settings')
                    ->where('group', 'squads')
                    ->where('key', $key)
                    ->update([
                        'value' => trim((string) $value),
                        'updated_at' => now(),
                    ]);
            }
        }

        return redirect()->route('admin.engagement.squads.settings', $id)->with('success', 'Squad settings updated.');
    }

    public function storeAnnouncement(Request $request, int $id)
    {
        $squad = DB::table('squads')->where('id', $id)->first();
        abort_unless($squad, 404);
        abort_unless(Schema::hasTable('squad_announcement_templates') && Schema::hasTable('squad_announcements'), 404);

        $validated = $request->validate([
            'template_id' => 'required|integer|min:1',
            'scope' => 'required|string|in:squad,global',
            'starts_at' => 'nullable|string|max:40',
            'ends_at' => 'nullable|string|max:40',
        ]);

        $template = DB::table('squad_announcement_templates')
            ->where('id', (int) $validated['template_id'])
            ->where('is_active', 1)
            ->first();
        abort_unless($template, 404);

        DB::table('squad_announcements')->insert([
            'squad_id' => $validated['scope'] === 'global' ? null : $id,
            'template_id' => (int) $template->id,
            'template_key' => $template->key,
            'title' => $template->title,
            'body' => $template->body,
            'cta_label' => $template->cta_label,
            'status' => 'active',
            'starts_at' => trim((string) ($validated['starts_at'] ?? '')) ?: null,
            'ends_at' => trim((string) ($validated['ends_at'] ?? '')) ?: null,
            'created_by_admin_id' => session('ad_id') ? (int) session('ad_id') : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.engagement.squads.settings', $id)->with('success', 'Squad announcement published.');
    }

    public function toggleMission(int $missionId)
    {
        abort_unless(Schema::hasTable('squad_missions'), 404);
        $mission = DB::table('squad_missions')->where('id', $missionId)->first();
        abort_unless($mission, 404);

        DB::table('squad_missions')->where('id', $missionId)->update([
            'is_active' => (int) !$mission->is_active,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Squad mission status updated.');
    }

    public function updateMemberRole(Request $request, int $id)
    {
        $squad = DB::table('squads')->where('id', $id)->first();
        abort_unless($squad, 404);

        $validated = $request->validate([
            'user_id' => 'required|integer|min:1',
            'role' => 'required|string|in:co_leader,moderator,member',
        ]);

        $member = DB::table('squad_members')
            ->where('squad_id', $id)
            ->where('user_id', (int) $validated['user_id'])
            ->where('status', 'active')
            ->first();
        abort_unless($member, 404);
        abort_if($member->role === 'owner', 422, 'Owner role cannot be changed.');

        DB::table('squad_members')->where('id', $member->id)->update([
            'role' => $validated['role'],
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Squad member role updated.');
    }

    public function removeMember(int $id, int $userId)
    {
        $member = DB::table('squad_members')
            ->where('squad_id', $id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();
        abort_unless($member, 404);
        abort_if($member->role === 'owner', 422, 'Owner cannot be removed.');

        DB::table('squad_members')->where('id', $member->id)->update([
            'status' => 'removed',
            'left_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Squad member removed.');
    }

    public function toggle(int $id)
    {
        $squad = DB::table('squads')->where('id', $id)->first();
        abort_unless($squad, 404);
        $active = (int) !$squad->is_active;

        DB::transaction(function () use ($id, $active) {
            DB::table('squads')->where('id', $id)->update([
                'is_active' => $active,
                'updated_at' => now(),
            ]);
            $this->engagement->recordAdminAudit(session('ad_id') ? (int) session('ad_id') : null, 'toggle', 'squad', $id, ['is_active' => $active]);
        });

        return back()->with('success', 'Squad status updated.');
    }

    private function emptyPage(Request $request)
    {
        return new LengthAwarePaginator([], 0, 25, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}
