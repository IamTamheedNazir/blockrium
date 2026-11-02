<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AppHealthController extends Controller
{
    public function __construct()
    {
        $this->middleware('checklogin');
    }

    public function index(Request $request)
    {
        $period = in_array($request->query('period'), ['24h', '7d', '30d'])
            ? $request->query('period')
            : '24h';
        $since = $this->since($period);
        $tableReady = Schema::hasTable('app_issue_logs');

        if (!$tableReady) {
            return view('admin.app-health.index', [
                'tableReady' => false,
                'period' => $period,
                'since' => $since,
                'stats' => $this->emptyStats(),
                'topEndpoints' => collect(),
                'topSlowEndpoints' => collect(),
                'topActions' => collect(),
                'topVersions' => collect(),
                'recentIssues' => collect(),
            ]);
        }

        $base = DB::table('app_issue_logs')->where('created_at', '>=', $since);

        if ($request->filled('severity')) {
            $base->where('severity', $request->query('severity'));
        }

        if ($request->filled('source')) {
            $base->where('source', $request->query('source'));
        }

        if ($request->filled('action')) {
            $base->where('action', 'like', '%' . $request->query('action') . '%');
        }

        if ($request->filled('endpoint')) {
            $base->where('endpoint', 'like', '%' . $request->query('endpoint') . '%');
        }

        $statsBase = clone $base;
        $stats = [
            'total' => (clone $statsBase)->count(),
            'error_count' => (clone $statsBase)->whereIn('severity', ['error', 'fatal'])->count(),
            'fatal_count' => (clone $statsBase)->where('severity', 'fatal')->count(),
            'unique_users' => (clone $statsBase)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
            'timeout_count' => (clone $statsBase)->where(function ($query) {
                $query->where('message', 'like', '%timed out%')
                    ->orWhere('message', 'like', '%timeout%');
            })->count(),
            'auth_count' => (clone $statsBase)->where(function ($query) {
                $query->where('endpoint', 'like', '%login%')
                    ->orWhere('endpoint', 'like', '%register%')
                    ->orWhere('action', 'like', '%login%')
                    ->orWhere('action', 'like', '%signup%');
            })->count(),
            'mining_count' => (clone $statsBase)->where(function ($query) {
                $query->where('endpoint', 'like', '%click-button%')
                    ->orWhere('action', 'like', '%mining%');
            })->count(),
            'reward_count' => (clone $statsBase)->where(function ($query) {
                $query->where('endpoint', 'like', '%reward%')
                    ->orWhere('endpoint', 'like', '%ads-videos%')
                    ->orWhere('action', 'like', '%streak%')
                    ->orWhere('action', 'like', '%chest%');
            })->count(),
            'user_reports' => (clone $statsBase)->where('source', 'user_report')->count(),
            'latest_at' => (clone $statsBase)->max('created_at'),
        ];

        $topEndpoints = $this->topGroup(clone $base, 'endpoint');
        $topSlowEndpoints = $this->topSlowEndpoints(clone $base);
        $topActions = $this->topGroup(clone $base, 'action');
        $topVersions = $this->topGroup(clone $base, 'app_version');
        $recentIssues = (clone $base)
            ->leftJoin('res_users', 'app_issue_logs.user_id', '=', 'res_users.id')
            ->select(
                'app_issue_logs.*',
                'res_users.username',
                'res_users.email'
            )
            ->orderByDesc('app_issue_logs.created_at')
            ->paginate(30)
            ->appends($request->query());

        return view('admin.app-health.index', compact(
            'tableReady',
            'period',
            'since',
            'stats',
            'topEndpoints',
            'topSlowEndpoints',
            'topActions',
            'topVersions',
            'recentIssues'
        ));
    }

    private function since($period)
    {
        if ($period === '30d') {
            return Carbon::now()->subDays(30);
        }

        if ($period === '7d') {
            return Carbon::now()->subDays(7);
        }

        return Carbon::now()->subDay();
    }

    private function topGroup($query, $column)
    {
        return $query
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->select($column, DB::raw('COUNT(*) as total'))
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit(8)
            ->get();
    }

    private function topSlowEndpoints($query)
    {
        return $query
            ->where('source', 'api_latency')
            ->whereNotNull('endpoint')
            ->where('endpoint', '<>', '')
            ->whereNotNull('request_duration_ms')
            ->select(
                'endpoint',
                DB::raw('COUNT(*) as total'),
                DB::raw('AVG(request_duration_ms) as average_duration_ms'),
                DB::raw('MAX(request_duration_ms) as max_duration_ms')
            )
            ->groupBy('endpoint')
            ->orderByDesc('average_duration_ms')
            ->orderByDesc('total')
            ->limit(8)
            ->get();
    }

    private function emptyStats()
    {
        return [
            'total' => 0,
            'error_count' => 0,
            'fatal_count' => 0,
            'unique_users' => 0,
            'timeout_count' => 0,
            'auth_count' => 0,
            'mining_count' => 0,
            'reward_count' => 0,
            'user_reports' => 0,
            'latest_at' => null,
        ];
    }
}
