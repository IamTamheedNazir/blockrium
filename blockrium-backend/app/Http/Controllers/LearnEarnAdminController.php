<?php

namespace App\Http\Controllers;

use App\Services\EngagementAdminService;
use App\Services\LearnEarnService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class LearnEarnAdminController extends Controller
{
    private $learn;
    private $engagement;

    public function __construct(LearnEarnService $learn, EngagementAdminService $engagement)
    {
        $this->learn = $learn;
        $this->engagement = $engagement;
        $this->middleware('checklogin');
    }

    public function index(Request $request)
    {
        $modules = Schema::hasTable('learn_modules')
            ? DB::table('learn_modules')
                ->leftJoin('learn_questions', 'learn_modules.id', '=', 'learn_questions.module_id')
                ->groupBy('learn_modules.id', 'learn_modules.key', 'learn_modules.title', 'learn_modules.description', 'learn_modules.content', 'learn_modules.youtube_url', 'learn_modules.youtube_video_id', 'learn_modules.video_title', 'learn_modules.estimated_minutes', 'learn_modules.reward_xp', 'learn_modules.reward_brc', 'learn_modules.passing_score', 'learn_modules.is_active', 'learn_modules.sort_order', 'learn_modules.created_at', 'learn_modules.updated_at')
                ->select('learn_modules.*', DB::raw('COUNT(learn_questions.id) as questions_count'))
                ->orderBy('learn_modules.sort_order')
                ->orderBy('learn_modules.id')
                ->paginate(25)
            : $this->emptyPage($request);

        return view('engagement.learn.index', compact('modules'));
    }

    public function create()
    {
        return view('engagement.learn.form', ['module' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validateModule($request);

        DB::transaction(function () use ($data) {
            $id = DB::table('learn_modules')->insertGetId(array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $this->engagement->recordAdminAudit($this->adminId(), 'create', 'learn_module', $id, $data);
        });

        return redirect()->route('admin.engagement.learn')->with('success', 'Learn module created.');
    }

    public function edit(int $id)
    {
        $module = DB::table('learn_modules')->where('id', $id)->first();
        abort_unless($module, 404);

        return view('engagement.learn.form', compact('module'));
    }

    public function update(Request $request, int $id)
    {
        abort_unless(DB::table('learn_modules')->where('id', $id)->exists(), 404);
        $data = $this->validateModule($request, $id);

        DB::transaction(function () use ($id, $data) {
            DB::table('learn_modules')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
            $this->engagement->recordAdminAudit($this->adminId(), 'update', 'learn_module', $id, $data);
        });

        return redirect()->route('admin.engagement.learn')->with('success', 'Learn module updated.');
    }

    public function toggle(int $id)
    {
        $module = DB::table('learn_modules')->where('id', $id)->first();
        abort_unless($module, 404);
        $active = (int) !$module->is_active;

        DB::transaction(function () use ($id, $active) {
            DB::table('learn_modules')->where('id', $id)->update(['is_active' => $active, 'updated_at' => now()]);
            $this->engagement->recordAdminAudit($this->adminId(), 'toggle', 'learn_module', $id, ['is_active' => $active]);
        });

        return back()->with('success', 'Learn module status updated.');
    }

    public function questions(int $id)
    {
        $module = DB::table('learn_modules')->where('id', $id)->first();
        abort_unless($module, 404);

        $questions = DB::table('learn_questions')
            ->where('module_id', $id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('engagement.learn.questions', compact('module', 'questions'));
    }

    public function storeQuestion(Request $request, int $id)
    {
        abort_unless(DB::table('learn_modules')->where('id', $id)->exists(), 404);
        $data = $this->validateQuestion($request);
        $data['module_id'] = $id;

        DB::transaction(function () use ($data) {
            $questionId = DB::table('learn_questions')->insertGetId(array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $this->engagement->recordAdminAudit($this->adminId(), 'create', 'learn_question', $questionId, $data);
        });

        return back()->with('success', 'Question added.');
    }

    public function editQuestion(int $id, int $questionId)
    {
        $module = DB::table('learn_modules')->where('id', $id)->first();
        $question = DB::table('learn_questions')->where('id', $questionId)->where('module_id', $id)->first();
        abort_unless($module && $question, 404);
        $options = json_decode((string) $question->options, true) ?: [];

        return view('engagement.learn.question-form', compact('module', 'question', 'options'));
    }

    public function updateQuestion(Request $request, int $id, int $questionId)
    {
        abort_unless(DB::table('learn_questions')->where('id', $questionId)->where('module_id', $id)->exists(), 404);
        $data = $this->validateQuestion($request);

        DB::transaction(function () use ($questionId, $data) {
            DB::table('learn_questions')->where('id', $questionId)->update(array_merge($data, ['updated_at' => now()]));
            $this->engagement->recordAdminAudit($this->adminId(), 'update', 'learn_question', $questionId, $data);
        });

        return redirect()->route('admin.engagement.learn.questions', $id)->with('success', 'Question updated.');
    }

    public function deleteQuestion(int $id, int $questionId)
    {
        $question = DB::table('learn_questions')->where('id', $questionId)->where('module_id', $id)->first();
        abort_unless($question, 404);

        DB::transaction(function () use ($questionId) {
            DB::table('learn_questions')->where('id', $questionId)->delete();
            $this->engagement->recordAdminAudit($this->adminId(), 'delete', 'learn_question', $questionId, []);
        });

        return back()->with('success', 'Question deleted.');
    }

    public function progress(Request $request)
    {
        $progress = $this->progressQuery($request);
        $modules = Schema::hasTable('learn_modules') ? DB::table('learn_modules')->orderBy('title')->get() : collect();

        return view('engagement.learn.progress', compact('progress', 'modules'));
    }

    private function validateModule(Request $request, ?int $id = null): array
    {
        $validated = $request->validate([
            'key' => ['required', 'max:120', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', Rule::unique('learn_modules', 'key')->ignore($id)],
            'title' => 'required|string|max:180',
            'description' => 'required|string|max:2000',
            'content' => 'required|string|max:30000',
            'youtube_url' => 'nullable|string|max:500',
            'estimated_minutes' => 'nullable|integer|min:1|max:120',
            'reward_xp' => 'required|integer|min:0|max:100',
            'reward_brc' => 'nullable|numeric|min:0|max:1000000',
            'passing_score' => 'required|integer|min:1|max:100',
            'is_active' => 'required|boolean',
            'sort_order' => 'required|integer|min:0|max:100000',
        ]);

        $validated['youtube_video_id'] = $this->learn->validateYoutubeUrl($validated['youtube_url'] ?? null);
        $validated['youtube_url'] = trim((string) ($validated['youtube_url'] ?? '')) ?: null;
        $estimatedMinutes = $validated['estimated_minutes'] ?? null;
        $rewardBrc = $validated['reward_brc'] ?? null;
        $validated['estimated_minutes'] = $estimatedMinutes !== null ? (int) $estimatedMinutes : null;
        $validated['reward_brc'] = $rewardBrc !== null && $rewardBrc !== '' ? (float) $rewardBrc : null;

        return $validated;
    }

    private function validateQuestion(Request $request): array
    {
        $validated = $request->validate([
            'question' => 'required|string|max:5000',
            'option_a' => 'required|string|max:1000',
            'option_b' => 'required|string|max:1000',
            'option_c' => 'required|string|max:1000',
            'option_d' => 'required|string|max:1000',
            'correct_option' => 'required|string|in:A,B,C,D',
            'explanation' => 'nullable|string|max:5000',
            'sort_order' => 'required|integer|min:0|max:100000',
        ]);

        return [
            'question' => $validated['question'],
            'options' => json_encode([
                'A' => $validated['option_a'],
                'B' => $validated['option_b'],
                'C' => $validated['option_c'],
                'D' => $validated['option_d'],
            ]),
            'correct_option' => $validated['correct_option'],
            'explanation' => $validated['explanation'] ?? null,
            'sort_order' => (int) $validated['sort_order'],
        ];
    }

    private function progressQuery(Request $request)
    {
        if (!Schema::hasTable('user_learn_progress')) {
            return $this->emptyPage($request);
        }

        $query = DB::table('user_learn_progress')
            ->leftJoin('learn_modules', 'user_learn_progress.module_id', '=', 'learn_modules.id')
            ->leftJoin('res_users', 'user_learn_progress.user_id', '=', 'res_users.id')
            ->select(
                'user_learn_progress.*',
                'learn_modules.title as module_title',
                'learn_modules.key as module_key',
                'res_users.name as user_name',
                'res_users.username',
                'res_users.email'
            )
            ->orderByDesc('user_learn_progress.updated_at');

        $search = trim((string) $request->query('user'));
        if ($search !== '') {
            $query->where(function ($where) use ($search) {
                if (ctype_digit($search)) {
                    $where->orWhere('user_learn_progress.user_id', (int) $search);
                }
                $where->orWhere('res_users.name', 'like', '%' . $search . '%')
                    ->orWhere('res_users.username', 'like', '%' . $search . '%')
                    ->orWhere('res_users.email', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('module_id')) {
            $query->where('user_learn_progress.module_id', (int) $request->query('module_id'));
        }
        if ($request->filled('status')) {
            $query->where('user_learn_progress.status', $request->query('status'));
        }
        if ($request->filled('from')) {
            $query->where('user_learn_progress.updated_at', '>=', $request->query('from') . ' 00:00:00');
        }
        if ($request->filled('to')) {
            $query->where('user_learn_progress.updated_at', '<=', $request->query('to') . ' 23:59:59');
        }

        return $query->paginate(25)->appends($request->query());
    }

    private function adminId(): ?int
    {
        $id = session('ad_id');
        return $id !== null ? (int) $id : null;
    }

    private function emptyPage(Request $request)
    {
        return new LengthAwarePaginator([], 0, 25, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}
