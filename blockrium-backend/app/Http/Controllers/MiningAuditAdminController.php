<?php

namespace App\Http\Controllers;

use App\Services\MiningAuditService;
use Illuminate\Http\Request;
use Session;

class MiningAuditAdminController extends Controller
{
    private $audit;

    public function __construct(MiningAuditService $audit)
    {
        $this->audit = $audit;
        $this->middleware('checklogin');
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'threshold' => 'nullable|numeric|min:0|max:100000000000',
            'status' => 'nullable|in:open,under_review,held,resolved,dismissed',
        ]);

        return view('mining-audit.index', $this->audit->dashboard($filters));
    }

    public function scan()
    {
        $result = $this->audit->scan($this->adminId());

        return back()->with('success', $result['message']);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|in:open,under_review,held,resolved,dismissed',
            'notes' => 'nullable|string|max:4000',
        ]);

        $this->audit->updateCase($id, $data['status'], $data['notes'] ?? '', $this->adminId());

        return back()->with('success', 'Mining audit case updated.');
    }

    public function adjustWallet(Request $request, int $userId)
    {
        $data = $request->validate([
            'adjustment' => 'required|numeric|between:-1000000000,1000000000|not_in:0',
            'reason' => 'required|string|max:2000',
        ]);

        $this->audit->adjustWallet($userId, (float) $data['adjustment'], $data['reason'], $this->adminId());

        return back()->with('success', 'Wallet adjustment recorded with before and after balances.');
    }

    private function adminId(): ?int
    {
        return Session::get('ad_id') ? (int) Session::get('ad_id') : null;
    }
}
