<?php

namespace App\Http\Controllers;

use App\Services\EngagementAdminService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class TrustCenterAdminController extends Controller
{
    private $engagement;

    public function __construct(EngagementAdminService $engagement)
    {
        $this->engagement = $engagement;
        $this->middleware('checklogin');
    }

    public function index(Request $request)
    {
        $sections = Schema::hasTable('trust_center_sections')
            ? DB::table('trust_center_sections')->orderBy('sort_order')->orderBy('id')->paginate(25)
            : $this->emptyPage($request);

        return view('trust-center.index', compact('sections'));
    }

    public function create()
    {
        return view('trust-center.form', ['section' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validateSection($request);
        $data['updated_by'] = $this->adminId();

        DB::transaction(function () use ($data) {
            $id = DB::table('trust_center_sections')->insertGetId(array_merge($data, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $this->engagement->recordAdminAudit($this->adminId(), 'create', 'trust_center_section', $id, $data);
        });

        return redirect()->route('admin.trust-center.index')->with('success', 'Trust Center section created.');
    }

    public function edit(int $id)
    {
        $section = DB::table('trust_center_sections')->where('id', $id)->first();
        abort_unless($section, 404);

        return view('trust-center.form', compact('section'));
    }

    public function update(Request $request, int $id)
    {
        abort_unless(DB::table('trust_center_sections')->where('id', $id)->exists(), 404);
        $data = $this->validateSection($request, $id);
        $data['updated_by'] = $this->adminId();

        DB::transaction(function () use ($id, $data) {
            DB::table('trust_center_sections')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
            $this->engagement->recordAdminAudit($this->adminId(), 'update', 'trust_center_section', $id, $data);
        });

        return redirect()->route('admin.trust-center.index')->with('success', 'Trust Center section updated.');
    }

    public function toggle(int $id)
    {
        $section = DB::table('trust_center_sections')->where('id', $id)->first();
        abort_unless($section, 404);
        $active = (int) !$section->is_active;

        DB::transaction(function () use ($id, $active) {
            DB::table('trust_center_sections')->where('id', $id)->update([
                'is_active' => $active,
                'updated_by' => $this->adminId(),
                'updated_at' => now(),
            ]);
            $this->engagement->recordAdminAudit($this->adminId(), 'toggle', 'trust_center_section', $id, ['is_active' => $active]);
        });

        return back()->with('success', 'Trust Center section status updated.');
    }

    private function validateSection(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'key' => ['required', 'max:120', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', Rule::unique('trust_center_sections', 'key')->ignore($id)],
            'title' => 'required|string|max:180',
            'body' => 'required|string|max:10000',
            'sort_order' => 'required|integer|min:0|max:100000',
            'is_active' => 'required|boolean',
        ]);
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
