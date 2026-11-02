<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class MiningAuditService
{
    private const DEFAULT_THRESHOLD = 100000.0;

    public function summary(): array
    {
        $highBalanceUsers = $this->highBalanceUsers(self::DEFAULT_THRESHOLD);
        $openCases = $this->caseCount(['open', 'under_review', 'held']);
        $underReviewCases = $this->caseCount(['under_review']);
        $heldCases = $this->caseCount(['held']);
        $criticalCases = $this->caseSeverityCount('critical');

        return [
            'high_balance_users' => count($highBalanceUsers),
            'users_over_100k' => count($highBalanceUsers),
            'open_cases' => $openCases,
            'pending_reviews' => $openCases,
            'under_review_cases' => $underReviewCases,
            'held_cases' => $heldCases,
            'held_accounts' => $heldCases,
            'critical_cases' => $criticalCases,
            'suspicious_accounts' => $openCases,
            'audit_tables' => Schema::hasTable('mining_audit_cases') && Schema::hasTable('wallet_adjustment_audits'),
        ];
    }

    public function dashboard(array $filters = []): array
    {
        $threshold = isset($filters['threshold']) ? (float) $filters['threshold'] : self::DEFAULT_THRESHOLD;
        $users = $this->highBalanceUsers($threshold);
        $cases = $this->cases($filters['status'] ?? null);

        return [
            'threshold' => $threshold,
            'filters' => $filters,
            'users' => $users,
            'cases' => $cases,
            'summary' => [
                'high_balance_users' => count($users),
                'open_cases' => $this->caseCount(['open', 'under_review', 'held']),
                'held_cases' => $this->caseCount(['held']),
                'audit_tables' => Schema::hasTable('mining_audit_cases') && Schema::hasTable('wallet_adjustment_audits'),
            ],
        ];
    }

    public function scan(?int $adminId = null): array
    {
        $users = $this->highBalanceUsers(self::DEFAULT_THRESHOLD);
        $created = 0;

        if (Schema::hasTable('mining_audit_cases')) {
            foreach ($users as $user) {
                if ($this->upsertCase($user, $adminId)) {
                    $created++;
                }
            }
        }

        if (!$users) {
            return ['message' => 'Audit scan completed. No users exceeded the configured threshold.'];
        }

        if (!Schema::hasTable('mining_audit_cases')) {
            return ['message' => count($users) . ' high-balance user(s) found. Install the audit tables to persist cases.'];
        }

        return ['message' => 'Audit scan completed. ' . $created . ' new case(s) recorded from ' . count($users) . ' high-balance user(s).'];
    }

    public function updateCase(int $caseId, string $status, string $notes, ?int $adminId = null): void
    {
        if (!Schema::hasTable('mining_audit_cases')) {
            throw new RuntimeException('Mining audit cases table is not available.');
        }

        $columns = array_flip(Schema::getColumnListing('mining_audit_cases'));
        $payload = array_intersect_key([
            'status' => $status,
            'resolution_notes' => $notes !== '' ? $notes : null,
            'reviewed_by_admin_id' => $adminId,
            'reviewed_at' => now(),
            'resolved_at' => in_array($status, ['resolved', 'dismissed'], true) ? now() : null,
            'updated_at' => now(),
        ], $columns);

        DB::table('mining_audit_cases')->where('id', $caseId)->update($payload);
    }

    public function adjustWallet(int $userId, float $adjustment, string $reason, ?int $adminId = null): void
    {
        if ($userId <= 0 || $adjustment == 0.0) {
            throw new RuntimeException('A valid user and non-zero adjustment are required.');
        }
        if (!Schema::hasTable('tbluserwallet')) {
            throw new RuntimeException('Reward wallet is unavailable.');
        }
        if (!Schema::hasTable('wallet_adjustment_audits')) {
            throw new RuntimeException('Wallet audit table is not available.');
        }

        DB::transaction(function () use ($userId, $adjustment, $reason, $adminId) {
            $wallet = DB::table('tbluserwallet')
                ->where('userid', $userId)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new RuntimeException('Wallet record was not found.');
            }

            $before = (float) $wallet->totaltoken;
            $after = $before + $adjustment;
            if ($after < 0) {
                throw new RuntimeException('Adjustment cannot make the wallet balance negative.');
            }

            $walletUpdate = ['totaltoken' => $after];
            if (Schema::hasColumn('tbluserwallet', 'updated_at')) {
                $walletUpdate['updated_at'] = now();
            }
            DB::table('tbluserwallet')->where('id', $wallet->id)->update($walletUpdate);

            $columns = array_flip(Schema::getColumnListing('wallet_adjustment_audits'));
            $audit = array_intersect_key([
                'user_id' => $userId,
                'admin_id' => $adminId,
                'admin_user_id' => $adminId,
                'wallet_id' => $wallet->id,
                'before_balance' => $before,
                'adjustment_amount' => $adjustment,
                'after_balance' => $after,
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ], $columns);
            DB::table('wallet_adjustment_audits')->insert($audit);
        });
    }

    private function highBalanceUsers(float $threshold)
    {
        if (!Schema::hasTable('res_users') || !Schema::hasTable('tbluserwallet')) {
            return collect();
        }

        $rows = DB::table('tbluserwallet')
            ->join('res_users', 'tbluserwallet.userid', '=', 'res_users.id')
            ->select('res_users.id', 'res_users.name', 'res_users.username', 'res_users.email')
            ->selectRaw('SUM(tbluserwallet.totaltoken) as wallet_balance')
            ->groupBy('res_users.id', 'res_users.name', 'res_users.username', 'res_users.email')
            ->havingRaw('SUM(tbluserwallet.totaltoken) >= ?', [$threshold])
            ->orderByDesc('wallet_balance')
            ->limit(100)
            ->get();

        $userIds = $rows->pluck('id')->map(function ($id) {
            return (int) $id;
        })->all();
        $sources = [
            'mining_balance' => $this->sourceTotals('tbl_user_mining', 'userid', 'totalamount', $userIds),
            'reward_balance' => $this->sourceTotals('tblreward', 'userid', 'token', $userIds),
            'referral_balance' => $this->sourceTotals('referrals', 'user_id', 'rewards', $userIds),
            'activity_balance' => $this->sourceTotals('button_clicks', 'user_id', 'wallet', $userIds),
        ];

        return $rows->map(function ($row) use ($sources) {
            $id = (int) $row->id;
            $mining = (float) ($sources['mining_balance'][$id] ?? 0);
            $reward = (float) ($sources['reward_balance'][$id] ?? 0);
            $referral = (float) ($sources['referral_balance'][$id] ?? 0);
            $activity = (float) ($sources['activity_balance'][$id] ?? 0);
            $wallet = (float) $row->wallet_balance;
            $row->sources = compact('wallet', 'mining', 'reward', 'referral', 'activity');
            $row->audited_total = $wallet + $mining + $reward + $referral + $activity;
            $row->risk = $row->audited_total >= 1000000 ? 'critical' : 'review';
            return $row;
        });
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

    private function cases(?string $status = null)
    {
        if (!Schema::hasTable('mining_audit_cases')) {
            return collect();
        }

        $query = DB::table('mining_audit_cases');
        $query->select('mining_audit_cases.*');
        if (Schema::hasTable('res_users')) {
            $query->leftJoin('res_users', 'mining_audit_cases.user_id', '=', 'res_users.id')
                ->addSelect('res_users.name as user_name', 'res_users.username', 'res_users.email');
        }
        $query->orderByDesc('mining_audit_cases.updated_at')->limit(100);
        if ($status) {
            $query->where('mining_audit_cases.status', $status);
        }

        try {
            return $query->get();
        } catch (\Throwable $error) {
            return collect();
        }
    }

    private function caseCount(array $statuses): int
    {
        if (!Schema::hasTable('mining_audit_cases')) {
            return 0;
        }

        try {
            return (int) DB::table('mining_audit_cases')->whereIn('status', $statuses)->count();
        } catch (\Throwable $error) {
            return 0;
        }
    }

    private function caseSeverityCount(string $severity): int
    {
        if (!Schema::hasTable('mining_audit_cases') || !Schema::hasColumn('mining_audit_cases', 'severity')) {
            return 0;
        }

        try {
            return (int) DB::table('mining_audit_cases')
                ->where('severity', $severity)
                ->whereIn('status', ['open', 'under_review', 'held'])
                ->count();
        } catch (\Throwable $error) {
            return 0;
        }
    }

    private function upsertCase($user, ?int $adminId): bool
    {
        $existing = DB::table('mining_audit_cases')
            ->where('user_id', (int) $user->id)
            ->whereIn('status', ['open', 'under_review', 'held'])
            ->first();
        if ($existing) {
            return false;
        }

        $columns = array_flip(Schema::getColumnListing('mining_audit_cases'));
        $snapshot = json_encode($user->sources);
        $payload = array_intersect_key([
            'user_id' => (int) $user->id,
            'status' => 'open',
            'severity' => $user->risk,
            'reason' => 'Balance exceeds 100,000 BRC and requires source verification.',
            'total_balance' => $user->audited_total,
            'detected_balance' => $user->audited_total,
            'source_snapshot' => $snapshot,
            'metadata' => $snapshot,
            'detected_at' => now(),
            'created_by_admin_id' => $adminId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $columns);

        if (!isset($payload['user_id'])) {
            return false;
        }
        DB::table('mining_audit_cases')->insert($payload);
        return true;
    }
}
