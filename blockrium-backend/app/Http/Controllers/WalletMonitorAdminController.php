<?php

namespace App\Http\Controllers;

use App\Services\WalletMonitorService;
use Illuminate\Http\Request;

class WalletMonitorAdminController extends Controller
{
    private $monitor;

    public function __construct(WalletMonitorService $monitor)
    {
        $this->monitor = $monitor;
        $this->middleware('checklogin');
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:120',
            'min_balance' => 'nullable|numeric|min:0|max:100000000000',
        ]);

        return view('wallet-monitor.index', $this->monitor->dashboard($filters));
    }
}
