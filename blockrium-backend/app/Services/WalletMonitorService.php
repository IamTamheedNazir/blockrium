<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WalletMonitorService
{
    private const PAGE_SIZE = 50;
    private const HIGH_BALANCE_THRESHOLD = 100000.0;
    private const BASE_MINING_REWARD = 1.5;

    private $warnings = [];

    public function dashboard(array $filters = []): array
    {
        $this->warnings = [];
        $threshold = isset($filters['min_balance']) ? max(0, (float) $filters['min_balance']) : 0.0;
        $users = $this->users($filters, $threshold);

        return [
            'filters' => $filters,
            'threshold' => self::HIGH_BALANCE_THRESHOLD,
            'summary' => $this->summary(),
            'users' => $users,
            'sourceAvailability' => $this->sourceAvailability(),
            'warnings' => array_values(array_unique($this->warnings)),
        ];
    }

    private function users(array $filters, float $threshold)
    {
        if (!Schema::hasTable('res_users') || !Schema::hasTable('tbluserwallet')) {
            $this->warn('The user or wallet table is not available in this database.');
            return collect();
        }

        $userColumns = Schema::getColumnListing('res_users');
        $walletColumns = Schema::getColumnListing('tbluserwallet');
        if (!in_array('id', $userColumns, true) || !in_array('userid', $walletColumns, true) || !in_array('totaltoken', $walletColumns, true)) {
            $this->warn('The legacy user or wallet table is missing required balance columns.');
            return collect();
        }

        try {
            $wallets = DB::table('tbluserwallet')
                ->select('userid')
                ->selectRaw('SUM(totaltoken) as wallet_balance')
                ->groupBy('userid');

            $query = DB::table('res_users as u')
                ->leftJoinSub($wallets, 'wallets', function ($join) {
                    $join->on('wallets.userid', '=', 'u.id');
                })
                ->select([
                    'u.id',
                    $this->columnOrNull('u', 'name', $userColumns),
                    $this->columnOrNull('u', 'username', $userColumns),
                    $this->columnOrNull('u', 'email', $userColumns),
                    DB::raw('COALESCE(wallets.wallet_balance, 0) as wallet_balance'),
                ])
                ->whereRaw('COALESCE(wallets.wallet_balance, 0) >= ?', [$threshold])
                ->orderByDesc('wallet_balance');

            if (!empty($filters['search'])) {
                $search = '%' . trim($filters['search']) . '%';
                $searchColumns = array_values(array_intersect(['name', 'username', 'email'], $userColumns));
                if ($searchColumns) {
                    $query->where(function ($where) use ($search, $searchColumns) {
                        foreach ($searchColumns as $index => $column) {
                            $method = $index === 0 ? 'where' : 'orWhere';
                            $where->{$method}('u.' . $column, 'like', $search);
                        }
                    });
                }
            }

            $rows = $query->paginate(self::PAGE_SIZE)->appends($filters);
        } catch (\Throwable $error) {
            $this->warn('Wallet monitor could not read the legacy wallet schema.');
            return collect();
        }
        $userIds = $rows->getCollection()->pluck('id')->map(function ($id) {
            return (int) $id;
        })->all();

        if (!$userIds) {
            return $rows;
        }

        $sources = [
            'mining' => $this->sourceTotals('tbl_user_mining', 'userid', 'totalamount', $userIds),
            'rewards' => $this->sourceTotals('tblreward', 'userid', 'token', $userIds),
            'referrals' => $this->sourceTotals('referrals', 'user_id', 'rewards', $userIds),
            'activity' => $this->sourceTotals('button_clicks', 'user_id', 'wallet', $userIds),
            'tasks' => $this->sourceTotals('task_claimeds', 'user_id', 'coin_reward', $userIds),
        ];
        $sessions = $this->sourceCounts('button_clicks', 'user_id', $userIds);
        $referralRows = $this->referralRows($userIds);
        $verification = $this->verificationRows($userIds);

        $rows->getCollection()->transform(function ($row) use ($sources, $sessions, $referralRows, $verification) {
            $id = (int) $row->id;
            $mining = (float) ($sources['mining'][$id] ?? 0);
            $rewards = (float) ($sources['rewards'][$id] ?? 0);
            $referrals = (float) ($sources['referrals'][$id] ?? 0);
            $activity = (float) ($sources['activity'][$id] ?? 0);
            $tasks = (float) ($sources['tasks'][$id] ?? 0);
            $sessionCount = (int) ($sessions[$id] ?? 0);
            $sourceTotal = $mining + $rewards + $referrals + $activity + $tasks;
            $riskReasons = [];

            if ((float) $row->wallet_balance >= self::HIGH_BALANCE_THRESHOLD) {
                $riskReasons[] = 'balance_above_100k';
            }

            $expectedActivity = $sessionCount * self::BASE_MINING_REWARD;
            if ($sessionCount >= 5 && $activity > ($expectedActivity * 2)) {
                $riskReasons[] = 'mining_activity_above_baseline';
            }

            $member = $verification[$id] ?? null;
            $row->sources = [
                'wallet' => (float) $row->wallet_balance,
                'mining' => $mining,
                'rewards' => $rewards,
                'referrals' => $referrals,
                'activity' => $activity,
                'tasks' => $tasks,
            ];
            $row->mining_sessions = $sessionCount;
            $row->known_source_total = $sourceTotal;
            $row->reconciliation_delta = (float) $row->wallet_balance - $sourceTotal;
            $row->referred_by = $referralRows[$id]['referred_by'] ?? null;
            $row->referral_code = $referralRows[$id]['referral_code'] ?? null;
            $row->kyc_status = $member ? $member->kyc_status : 'unavailable';
            $row->payment_status = $member ? $member->payment_status : 'unavailable';
            $row->risk_reasons = $riskReasons;
            $row->risk = count($riskReasons) > 1 ? 'high' : (count($riskReasons) === 1 ? 'review' : 'normal');

            return $row;
        });

        return $rows;
    }

    private function summary(): array
    {
        $summary = [
            'users' => 0,
            'recorded_wallet_balance' => 0.0,
            'high_balance_users' => 0,
            'verified_members' => 0,
            'payment_pending' => 0,
        ];

        if (Schema::hasTable('res_users')) {
            try {
                $summary['users'] = (int) DB::table('res_users')->count();
            } catch (\Throwable $error) {
                $this->warn('User totals are unavailable.');
            }
        }
        if (Schema::hasTable('tbluserwallet') && Schema::hasColumn('tbluserwallet', 'totaltoken')) {
            try {
                $summary['recorded_wallet_balance'] = (float) DB::table('tbluserwallet')->sum('totaltoken');
                $summary['high_balance_users'] = (int) DB::table('tbluserwallet')
                    ->select('userid')
                    ->groupBy('userid')
                    ->havingRaw('SUM(totaltoken) >= ?', [self::HIGH_BALANCE_THRESHOLD])
                    ->get()
                    ->count();
            } catch (\Throwable $error) {
                $this->warn('Wallet totals are unavailable.');
            }
        }
        if (Schema::hasTable('verified_memberships')) {
            try {
                $membership = DB::table('verified_memberships');
                $summary['verified_members'] = (int) $membership
                    ->where('kyc_status', 'approved')
                    ->where('payment_status', 'paid')
                    ->when(Schema::hasColumn('verified_memberships', 'suspended_at'), function ($query) {
                        $query->whereNull('suspended_at');
                    })
                    ->count();
                $summary['payment_pending'] = (int) DB::table('verified_memberships')
                    ->where('kyc_status', 'approved')
                    ->where('payment_status', '!=', 'paid')
                    ->count();
            } catch (\Throwable $error) {
                $this->warn('KYC and payment totals are unavailable.');
            }
        }

        return $summary;
    }

    private function columnOrNull(string $alias, string $column, array $availableColumns)
    {
        if (in_array($column, $availableColumns, true)) {
            return $alias . '.' . $column;
        }

        return DB::raw('NULL as ' . $column);
    }

    private function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    private function sourceAvailability(): array
    {
        return [
            'Wallet aggregate' => Schema::hasTable('tbluserwallet'),
            'Mining records' => Schema::hasTable('tbl_user_mining'),
            'Mining sessions' => Schema::hasTable('button_clicks'),
            'Daily rewards' => Schema::hasTable('tblreward'),
            'Task rewards' => Schema::hasTable('task_claimeds'),
            'Referral rewards' => Schema::hasTable('referrals'),
            'KYC status' => Schema::hasTable('verified_memberships'),
            'Canonical transaction ledger' => Schema::hasTable('brc_ledger_entries'),
        ];
    }

    private function sourceTotals(string $table, string $userColumn, string $amountColumn, array $userIds): array
    {
        if (!$userIds || !Schema::hasTable($table) || !Schema::hasColumn($table, $userColumn) || !Schema::hasColumn($table, $amountColumn)) {
            return [];
        }

        try {
            return DB::table($table)
                ->select($userColumn)
                ->selectRaw('SUM(' . $amountColumn . ') as total')
                ->whereIn($userColumn, $userIds)
                ->groupBy($userColumn)
                ->pluck('total', $userColumn)
                ->mapWithKeys(function ($amount, $userId) {
                    return [(int) $userId => (float) $amount];
                })
                ->all();
        } catch (\Throwable $error) {
            return [];
        }
    }

    private function sourceCounts(string $table, string $userColumn, array $userIds): array
    {
        if (!$userIds || !Schema::hasTable($table) || !Schema::hasColumn($table, $userColumn)) {
            return [];
        }

        try {
            return DB::table($table)
                ->select($userColumn)
                ->selectRaw('COUNT(*) as total')
                ->whereIn($userColumn, $userIds)
                ->groupBy($userColumn)
                ->pluck('total', $userColumn)
                ->mapWithKeys(function ($amount, $userId) {
                    return [(int) $userId => (int) $amount];
                })
                ->all();
        } catch (\Throwable $error) {
            return [];
        }
    }

    private function referralRows(array $userIds): array
    {
        if (!Schema::hasTable('referrals') || !Schema::hasColumn('referrals', 'user_id')) {
            return [];
        }

        try {
            return DB::table('referrals')
                ->whereIn('user_id', $userIds)
                ->orderByDesc('id')
                ->get(['user_id', 'referral_code', 'referred_by'])
                ->mapWithKeys(function ($row) {
                    return [(int) $row->user_id => [
                        'referral_code' => $row->referral_code,
                        'referred_by' => $row->referred_by,
                    ]];
                })
                ->all();
        } catch (\Throwable $error) {
            return [];
        }
    }

    private function verificationRows(array $userIds): array
    {
        if (!$userIds || !Schema::hasTable('verified_memberships')) {
            return [];
        }

        try {
            return DB::table('verified_memberships')
                ->whereIn('user_id', $userIds)
                ->get(['user_id', 'kyc_status', 'payment_status'])
                ->mapWithKeys(function ($row) {
                    return [(int) $row->user_id => $row];
                })
                ->all();
        } catch (\Throwable $error) {
            return [];
        }
    }
}
