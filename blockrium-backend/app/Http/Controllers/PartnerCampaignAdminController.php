<?php

namespace App\Http\Controllers;

use App\Services\EngagementAdminService;
use App\Services\PartnerCampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PartnerCampaignAdminController extends Controller
{
    private $campaigns;
    private $audit;

    public function __construct(PartnerCampaignService $campaigns, EngagementAdminService $audit)
    {
        $this->campaigns = $campaigns;
        $this->audit = $audit;
        $this->middleware('checklogin');
    }

    public function index()
    {
        $campaigns = $this->campaigns->campaignsForAdmin();
        $metrics = $this->campaigns->metricsForCampaigns($campaigns->getCollection());
        $campaigns->getCollection()->transform(function ($campaign) use ($metrics) {
            $campaign->metrics = $metrics[(int) $campaign->id] ?? [];
            return $campaign;
        });

        return view('partner-campaigns.index', [
            'campaigns' => $campaigns,
            'partners' => DB::table('partners')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('partner-campaigns.form', [
            'campaign' => null,
            'tasks' => collect(),
            'partners' => DB::table('partners')->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateCampaign($request);
        $tasks = $this->validateTasks($request);
        $data['image_url'] = $this->storeCampaignImage($request, $data['image_url'] ?? null);
        unset($data['image']);
        $data['public_id'] = (string) Str::uuid();
        $data['slug'] = $this->uniqueSlug($data['slug'] ?: $data['title']);
        $data['created_by_admin_id'] = $this->adminId();
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('partner_campaigns')->insertGetId($data);
        $this->syncTasks($id, $tasks);
        $this->audit->recordAdminAudit($this->adminId(), 'create', 'partner_campaign', $id, $data);

        return redirect()->route('admin.partner-campaigns.index')->with('success', 'Partner campaign created successfully.');
    }

    public function edit(int $id)
    {
        $campaign = DB::table('partner_campaigns')->where('id', $id)->first();
        abort_unless($campaign, 404);

        return view('partner-campaigns.form', [
            'campaign' => $campaign,
            'tasks' => DB::table('partner_campaign_tasks')->where('campaign_id', $id)->orderBy('sort_order')->orderBy('id')->get(),
            'partners' => DB::table('partners')->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        abort_unless(DB::table('partner_campaigns')->where('id', $id)->exists(), 404);
        $data = $this->validateCampaign($request, $id);
        $tasks = $this->validateTasks($request);
        $data['image_url'] = $this->storeCampaignImage($request, $data['image_url'] ?? null);
        unset($data['image']);
        $data['slug'] = $this->uniqueSlug($data['slug'] ?: $data['title'], $id);
        $data['updated_at'] = now();

        DB::table('partner_campaigns')->where('id', $id)->update($data);
        $this->syncTasks($id, $tasks);
        $this->audit->recordAdminAudit($this->adminId(), 'update', 'partner_campaign', $id, $data);

        return redirect()->route('admin.partner-campaigns.index')->with('success', 'Partner campaign updated successfully.');
    }

    public function toggle(int $id)
    {
        $campaign = DB::table('partner_campaigns')->where('id', $id)->first();
        abort_unless($campaign, 404);

        $status = $campaign->status === 'active' ? 'paused' : 'active';
        DB::table('partner_campaigns')->where('id', $id)->update(['status' => $status, 'updated_at' => now()]);
        $this->audit->recordAdminAudit($this->adminId(), 'toggle', 'partner_campaign', $id, ['status' => $status]);

        return back()->with('success', 'Partner campaign status updated.');
    }

    public function completions()
    {
        return view('partner-campaigns.completions', [
            'completions' => $this->campaigns->pendingCompletions(),
            'taskCompletions' => $this->campaigns->pendingTaskCompletions(),
        ]);
    }

    public function approve(int $id)
    {
        $this->campaigns->approveCompletion($id, $this->adminId());
        $this->audit->recordAdminAudit($this->adminId(), 'approve', 'partner_campaign_completion', $id, []);

        return back()->with('success', 'Campaign completion approved.');
    }

    public function reject(Request $request, int $id)
    {
        $note = $request->validate(['admin_note' => 'nullable|string|max:2000'])['admin_note'] ?? null;
        $this->campaigns->rejectCompletion($id, $this->adminId(), $note);
        $this->audit->recordAdminAudit($this->adminId(), 'reject', 'partner_campaign_completion', $id, ['admin_note' => $note]);

        return back()->with('success', 'Campaign completion rejected.');
    }

    public function taskCompletions()
    {
        return view('partner-campaigns.completions', [
            'completions' => $this->campaigns->pendingCompletions(),
            'taskCompletions' => $this->campaigns->pendingTaskCompletions(),
        ]);
    }

    public function approveTask(int $id)
    {
        $this->campaigns->approveTaskCompletion($id, $this->adminId());
        $this->audit->recordAdminAudit($this->adminId(), 'approve', 'partner_campaign_task_completion', $id, []);

        return back()->with('success', 'Task completion approved.');
    }

    public function rejectTask(Request $request, int $id)
    {
        $note = $request->validate(['admin_note' => 'nullable|string|max:2000'])['admin_note'] ?? null;
        $this->campaigns->rejectTaskCompletion($id, $this->adminId(), $note);
        $this->audit->recordAdminAudit($this->adminId(), 'reject', 'partner_campaign_task_completion', $id, ['admin_note' => $note]);

        return back()->with('success', 'Task completion rejected.');
    }

    public function storePartner(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'contact_email' => 'nullable|email|max:190',
        ]);
        $data['status'] = 'active';
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('partners')->insertGetId($data);
        $this->audit->recordAdminAudit($this->adminId(), 'create', 'partner', $id, $data);

        return back()->with('success', 'Partner created successfully.');
    }

    public function issueDashboardToken(int $partnerId)
    {
        $result = $this->campaigns->issueDashboardToken($partnerId, 'Initial dashboard link');
        $this->audit->recordAdminAudit($this->adminId(), 'issue_token', 'partner', $partnerId, []);

        return back()->with('dashboard_link', $result['url']);
    }

    public function rotateDashboardToken(int $partnerId)
    {
        $result = $this->campaigns->rotateDashboardToken($partnerId, 'Rotated dashboard link');
        $this->audit->recordAdminAudit($this->adminId(), 'rotate_token', 'partner', $partnerId, []);

        return back()->with('dashboard_link', $result['url']);
    }

    private function validateCampaign(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'partner_id' => 'required|integer|exists:partners,id',
            'title' => 'required|string|max:180',
            'description' => 'nullable|string|max:5000',
            'image_url' => 'nullable|url|max:2000',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif|max:4096',
            'destination_url' => 'required|url|max:2000',
            'slug' => ['nullable', 'string', 'max:190', 'regex:/^[A-Za-z0-9]+(?:[-_][A-Za-z0-9]+)*$/', Rule::unique('partner_campaigns', 'slug')->ignore($id)],
            'status' => ['required', Rule::in(['draft', 'active', 'paused'])],
            'reward_mode' => ['nullable', Rule::in(['click_claim', 'admin_approval'])],
            'reward_amount' => 'nullable|numeric|min:0|max:1000000',
            'reward_label' => 'nullable|string|max:20|regex:/^[A-Za-z0-9._-]+$/',
            'participant_limit' => 'nullable|integer|min:1|max:100000000',
            'budget_amount' => 'nullable|numeric|min:0|max:1000000000',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'sort_order' => 'nullable|integer|min:0|max:100000',
        ]);

        $scheme = strtolower((string) parse_url($data['destination_url'], PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new HttpException(422, 'The campaign destination must use HTTP or HTTPS.');
        }

        if ($data['budget_amount'] !== null && (float) $data['budget_amount'] < (float) ($data['reward_amount'] ?? 0)) {
            throw new HttpException(422, 'The total campaign budget must cover at least one reward.');
        }

        $data['reward_mode'] = $data['reward_mode'] ?? 'click_claim';
        $data['reward_amount'] = (float) ($data['reward_amount'] ?? 0);
        $data['reward_label'] = $data['reward_label'] ?? 'BRC';
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        return $data;
    }

    private function validateTasks(Request $request): array
    {
        $request->validate([
            'tasks' => 'nullable|array|max:30',
            'tasks.*.id' => 'nullable|integer',
            'tasks.*.title' => 'required|string|max:180',
            'tasks.*.task_type' => ['required', Rule::in(['website_visit', 'follow_x', 'join_telegram', 'join_discord', 'follow_founder', 'custom'])],
            'tasks.*.instructions' => 'nullable|string|max:3000',
            'tasks.*.target_url' => 'required|url|max:2000',
            'tasks.*.completion_mode' => ['nullable', Rule::in(['click_claim', 'admin_approval', 'telegram_membership'])],
            'tasks.*.reward_amount' => 'required|numeric|gt:0|max:1000000',
            'tasks.*.reward_label' => 'nullable|string|max:20|regex:/^[A-Za-z0-9._-]+$/',
            'tasks.*.participant_limit' => 'nullable|integer|min:1|max:100000000',
            'tasks.*.status' => ['nullable', Rule::in(['active', 'paused'])],
        ]);

        $tasks = array_values($request->input('tasks', []));
        $budget = $request->input('budget_amount');
        if ($request->input('status') === 'active' && !$tasks) {
            throw new HttpException(422, 'Add at least one task before activating a campaign.');
        }
        if ($tasks && ($budget === null || $budget === '' || (float) $budget <= 0)) {
            throw new HttpException(422, 'A positive total campaign allocation is required when tasks are configured.');
        }
        if ($budget !== null && $budget !== '') {
            $potential = 0.0;
            foreach ($tasks as $task) {
                if (!empty($task['participant_limit'])) {
                    $potential += (float) $task['reward_amount'] * (int) $task['participant_limit'];
                }
            }
            if ($potential > (float) $budget) {
                throw new HttpException(422, 'The task allocation exceeds the total campaign budget. Increase the budget or lower task limits.');
            }
        }

        return $tasks;
    }

    private function syncTasks(int $campaignId, array $tasks): void
    {
        $keepIds = [];
        foreach ($tasks as $index => $task) {
            $taskId = !empty($task['id']) ? (int) $task['id'] : null;
            $existing = $taskId ? DB::table('partner_campaign_tasks')->where('id', $taskId)->where('campaign_id', $campaignId)->first() : null;
            $taskKey = $existing ? $existing->task_key : $this->uniqueTaskKey($campaignId, $task['title']);
            $data = [
                'campaign_id' => $campaignId,
                'task_key' => substr($taskKey, 0, 100),
                'task_type' => $task['task_type'],
                'title' => $task['title'],
                'instructions' => $task['instructions'] ?? null,
                'target_url' => $task['target_url'],
                'completion_mode' => in_array($task['task_type'], ['website_visit', 'join_telegram'], true)
                    ? 'click_claim'
                    : 'admin_approval',
                'reward_amount' => (float) $task['reward_amount'],
                'reward_label' => $task['reward_label'] ?? 'BRC',
                'participant_limit' => !empty($task['participant_limit']) ? (int) $task['participant_limit'] : null,
                'status' => $task['status'] ?? 'active',
                'sort_order' => $index,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('partner_campaign_tasks')->where('id', $existing->id)->update($data);
                $keepIds[] = (int) $existing->id;
            } else {
                $data['created_at'] = now();
                $keepIds[] = (int) DB::table('partner_campaign_tasks')->insertGetId($data);
            }
        }

        $query = DB::table('partner_campaign_tasks')->where('campaign_id', $campaignId);
        if ($keepIds) {
            $query->whereNotIn('id', $keepIds);
        }
        $query->update(['status' => 'paused', 'updated_at' => now()]);
    }

    private function storeCampaignImage(Request $request, ?string $existing): ?string
    {
        if (!$request->hasFile('image')) {
            return $existing;
        }

        $directory = public_path('partner-campaigns');
        File::ensureDirectoryExists($directory);
        $file = $request->file('image');
        $filename = Str::random(24) . '.' . strtolower($file->getClientOriginalExtension());
        $file->move($directory, $filename);

        return asset('partner-campaigns/' . $filename);
    }

    private function uniqueTaskKey(int $campaignId, string $title): string
    {
        $base = Str::slug($title) ?: 'task';
        $key = substr($base, 0, 92) . '-' . Str::lower(Str::random(6));
        while (DB::table('partner_campaign_tasks')->where('campaign_id', $campaignId)->where('task_key', $key)->exists()) {
            $key = substr($base, 0, 90) . '-' . Str::lower(Str::random(8));
        }

        return substr($key, 0, 100);
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'partner-campaign';
        $slug = $base;
        $suffix = 1;

        while (DB::table('partner_campaigns')->where('slug', $slug)->when($ignoreId, function ($query) use ($ignoreId) {
            return $query->where('id', '<>', $ignoreId);
        })->exists()) {
            $suffix++;
            $slug = $base . '-' . $suffix;
        }

        return $slug;
    }

    private function adminId(): int
    {
        return (int) session('ad_id');
    }
}
