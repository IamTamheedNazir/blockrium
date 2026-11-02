<?php

namespace App\Services;

use App\Models\ResUser;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PartnerPromoCodeService
{
    private const CHUNK_SIZE = 500;
    private const MAX_CODES_PER_BATCH = 100000;
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private $walletRewards;

    public function __construct(WalletRewardService $walletRewards)
    {
        $this->walletRewards = $walletRewards;
    }

    public function createBatch(int $campaignId, array $data, int $adminId): object
    {
        $campaign = DB::table('partner_campaigns')->where('id', $campaignId)->first();
        if (!$campaign) {
            throw new RuntimeException('The partner campaign was not found.');
        }

        if (in_array((string) $campaign->status, ['expired', 'paused'], true)) {
            throw new RuntimeException('Promo codes cannot be generated for a paused or expired campaign.');
        }

        $count = (int) ($data['code_count'] ?? 0);
        if ($count < 1 || $count > self::MAX_CODES_PER_BATCH) {
            throw new RuntimeException('Generate between 1 and 100,000 codes per batch.');
        }

        $rewards = $this->resolveRewards($count, $data);
        $totalReward = round(array_sum($rewards), 4);
        $rewardLabel = $data['reward_label'] ?? 'BRC';
        $expiresAt = !empty($data['expires_at']) ? date('Y-m-d H:i:s', strtotime($data['expires_at'])) : $campaign->ends_at;

        if (!$expiresAt || strtotime($expiresAt) <= time()) {
            throw new RuntimeException('The promo-code expiry must be in the future.');
        }
        if (strtotime($campaign->ends_at) < strtotime($expiresAt)) {
            $expiresAt = $campaign->ends_at;
        }

        $reference = $this->newBatchReference();
        $batchId = DB::transaction(function () use ($campaignId, $reference, $count, $totalReward, $rewardLabel, $expiresAt, $adminId) {
            $lockedCampaign = DB::table('partner_campaigns')->where('id', $campaignId)->lockForUpdate()->first();
            if (!$lockedCampaign) {
                throw new RuntimeException('The partner campaign was not found.');
            }
            if (in_array((string) $lockedCampaign->status, ['expired', 'paused'], true)) {
                throw new RuntimeException('Promo codes cannot be generated for a paused or expired campaign.');
            }
            $this->assertAllocationAvailable($lockedCampaign, $count, $totalReward);

            return DB::table('partner_promo_batches')->insertGetId([
                'campaign_id' => $campaignId,
                'batch_reference' => $reference,
                'status' => 'generating',
                'code_count' => $count,
                'generated_count' => 0,
                'claimed_count' => 0,
                'total_reward_amount' => $totalReward,
                'reward_label' => $rewardLabel,
                'expires_at' => $expiresAt,
                'created_by_admin_id' => $adminId ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $vaultPath = $this->vaultPath($reference);
        $vaultHandle = null;

        try {
            File::ensureDirectoryExists(dirname($vaultPath));
            $vaultHandle = fopen($vaultPath, 'wb');
            if (!$vaultHandle) {
                throw new RuntimeException('The private promo-code vault could not be opened.');
            }

            for ($offset = 0; $offset < $count; $offset += self::CHUNK_SIZE) {
                $rows = [];
                $vaultLines = [];
                $limit = min(self::CHUNK_SIZE, $count - $offset);

                for ($index = 0; $index < $limit; $index++) {
                    $code = $this->generateCode();
                    $hash = $this->hashCode($code);
                    $rows[] = [
                        'batch_id' => $batchId,
                        'campaign_id' => $campaignId,
                        'code_hash' => $hash,
                        'code_suffix' => substr($code, -4),
                        'reward_amount' => $rewards[$offset + $index],
                        'reward_label' => $rewardLabel,
                        'status' => 'available',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $vaultLines[] = base64_encode(Crypt::encryptString(json_encode([
                        'code' => $code,
                        'reward_amount' => $rewards[$offset + $index],
                    ], JSON_UNESCAPED_SLASHES)));
                }

                DB::transaction(function () use ($rows, $batchId, $limit) {
                    DB::table('partner_promo_codes')->insert($rows);
                    DB::table('partner_promo_batches')->where('id', $batchId)->update([
                        'generated_count' => DB::raw('generated_count + ' . (int) $limit),
                        'updated_at' => now(),
                    ]);
                });

                fwrite($vaultHandle, implode(PHP_EOL, $vaultLines) . PHP_EOL);
            }

            fclose($vaultHandle);
            $vaultHandle = null;

            DB::table('partner_promo_batches')->where('id', $batchId)->update([
                'status' => 'generated',
                'updated_at' => now(),
            ]);
        } catch (Throwable $error) {
            if (is_resource($vaultHandle)) {
                fclose($vaultHandle);
            }
            DB::table('partner_promo_codes')->where('batch_id', $batchId)->delete();
            DB::table('partner_promo_batches')->where('id', $batchId)->update([
                'status' => 'failed',
                'generation_error' => Str::limit($error->getMessage(), 1000),
                'updated_at' => now(),
            ]);
            @unlink($vaultPath);
            throw $error;
        }

        return DB::table('partner_promo_batches')->where('id', $batchId)->first();
    }

    public function batchesForCampaign(int $campaignId)
    {
        return DB::table('partner_promo_batches')
            ->where('campaign_id', $campaignId)
            ->orderByDesc('id')
            ->paginate(25, ['*'], 'promo_page');
    }

    public function batch(int $batchId): ?object
    {
        return DB::table('partner_promo_batches')->where('id', $batchId)->first();
    }

    public function batchMetrics(int $batchId): array
    {
        $batch = $this->batch($batchId);
        if (!$batch) {
            return [];
        }

        $redemptions = DB::table('partner_promo_redemptions')->where('batch_id', $batchId);

        return [
            'generated' => (int) $batch->generated_count,
            'claimed' => (int) $batch->claimed_count,
            'available' => max(0, (int) $batch->generated_count - (int) $batch->claimed_count),
            'total_reward' => (float) $batch->total_reward_amount,
            'distributed_reward' => (float) (clone $redemptions)->where('status', 'succeeded')->sum('reward_amount'),
            'held' => (int) (clone $redemptions)->where('status', 'held')->count(),
            'rejected' => (int) (clone $redemptions)->where('status', 'rejected')->count(),
        ];
    }

    public function redemptions(int $batchId)
    {
        return DB::table('partner_promo_redemptions')
            ->leftJoin('res_users', 'partner_promo_redemptions.user_id', '=', 'res_users.id')
            ->leftJoin('partner_promo_codes', 'partner_promo_redemptions.promo_code_id', '=', 'partner_promo_codes.id')
            ->where('partner_promo_redemptions.batch_id', $batchId)
            ->select([
                'partner_promo_redemptions.*',
                'partner_promo_codes.code_suffix',
                'res_users.name as user_name',
                'res_users.email as user_email',
            ])
            ->orderByDesc('partner_promo_redemptions.id')
            ->paginate(50, ['*'], 'redemption_page');
    }

    public function exportCsv(int $batchId, int $adminId): array
    {
        $batch = $this->batch($batchId);
        if (!$batch || !in_array((string) $batch->status, ['generated', 'exported'], true)) {
            throw new RuntimeException('This promo-code batch is not ready for export.');
        }

        $vaultPath = $this->vaultPath($batch->batch_reference);
        if (!is_file($vaultPath)) {
            throw new RuntimeException('The private promo-code export source is unavailable.');
        }

        $tempPath = tempnam(storage_path('app'), 'partner-promo-export-');
        $input = fopen($vaultPath, 'rb');
        $output = $tempPath ? fopen($tempPath, 'wb') : false;
        if (!$input || !$output) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            @unlink($tempPath);
            throw new RuntimeException('The promo-code export could not be prepared.');
        }

        $hash = hash_init('sha256');
        try {
            $header = $this->csvLine(['code', 'reward_amount', 'reward_asset', 'expires_at']);
            fwrite($output, $header);
            hash_update($hash, $header);

            while (($line = fgets($input)) !== false) {
                $payload = json_decode(Crypt::decryptString(base64_decode(trim($line))), true);
                if (!is_array($payload) || empty($payload['code'])) {
                    throw new RuntimeException('The private promo-code export source is invalid.');
                }
                $csvLine = $this->csvLine([
                    $payload['code'],
                    number_format((float) ($payload['reward_amount'] ?? 0), 4, '.', ''),
                    $batch->reward_label,
                    $batch->expires_at,
                ]);
                fwrite($output, $csvLine);
                hash_update($hash, $csvLine);
            }

            fclose($input);
            fclose($output);
        } catch (Throwable $error) {
            fclose($input);
            fclose($output);
            @unlink($tempPath);
            throw $error;
        }

        $sha256 = hash_final($hash);
        DB::table('partner_promo_batches')->where('id', $batchId)->update([
            'status' => 'exported',
            'exported_at' => now(),
            'export_sha256' => $sha256,
            'export_downloaded_by_admin_id' => $adminId ?: null,
            'export_downloaded_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'path' => $tempPath,
            'filename' => 'partner-promo-' . $batch->batch_reference . '.csv',
            'sha256' => $sha256,
        ];
    }

    public function redeem(string $rawCode, int $userId, string $requestKey, ?string $deviceHash = null, ?string $visitorHash = null): array
    {
        $codeValue = $this->normalizeCode($rawCode);
        if (!$codeValue || strlen($codeValue) > 40) {
            return $this->rejectedResult('The promo code is invalid.', 422);
        }

        $hash = $this->hashCode($codeValue);

        return DB::transaction(function () use ($hash, $userId, $requestKey, $deviceHash, $visitorHash) {
            $code = DB::table('partner_promo_codes')->where('code_hash', $hash)->lockForUpdate()->first();
            if (!$code) {
                return $this->rejectedResult('The promo code is invalid or unavailable.', 422);
            }

            $batch = DB::table('partner_promo_batches')->where('id', $code->batch_id)->lockForUpdate()->first();
            $campaign = DB::table('partner_campaigns')->where('id', $code->campaign_id)->lockForUpdate()->first();
            if (!$batch || !$campaign) {
                return $this->rejectedResult('The promo code is unavailable.', 422, $code, $batch, $userId, $requestKey, $deviceHash, $visitorHash);
            }

            $existingRequest = DB::table('partner_promo_redemptions')
                ->where('campaign_id', $campaign->id)
                ->where('user_id', $userId)
                ->where('request_key', $requestKey)
                ->first();
            if ($existingRequest) {
                return $existingRequest->status === 'succeeded'
                    ? $this->successResult($existingRequest, $campaign)
                    : $this->rejectedResult('This redemption request was already rejected.', 422);
            }

            if ((int) ($code->claimed_by_user_id ?? 0) === $userId && $code->status === 'claimed') {
                $claim = DB::table('partner_campaign_claims')
                    ->where('campaign_id', $campaign->id)
                    ->where('user_id', $userId)
                    ->where('promo_code_id', $code->id)
                    ->first();
                return [
                    'status' => 409,
                    'success' => false,
                    'already_claimed' => true,
                    'message' => 'This promo code has already been redeemed by your account.',
                    'claim' => $claim ? $this->claimPayload($claim) : null,
                ];
            }

            if ($code->status !== 'available') {
                return $this->rejectedResult('The promo code is invalid or unavailable.', 409);
            }
            if ($batch->status === 'paused' || $batch->status === 'revoked') {
                return $this->rejectedResult('This promo-code campaign is not accepting redemptions.', 409, $code, $batch, $userId, $requestKey, $deviceHash, $visitorHash);
            }
            if ($batch->expires_at && strtotime($batch->expires_at) <= time()) {
                DB::table('partner_promo_codes')->where('id', $code->id)->update(['status' => 'expired', 'updated_at' => now()]);
                return $this->rejectedResult('This promo code has expired.', 409, $code, $batch, $userId, $requestKey, $deviceHash, $visitorHash);
            }
            if ($campaign->status !== 'active' || strtotime($campaign->starts_at) > time() || strtotime($campaign->ends_at) < time()) {
                return $this->rejectedResult('This campaign is not currently accepting redemptions.', 409, $code, $batch, $userId, $requestKey, $deviceHash, $visitorHash);
            }

            $user = DB::table('res_users')->where('id', $userId)->first();
            if (!$user || (Schema::hasColumn('res_users', 'is_active') && (int) ($user->is_active ?? 1) === 0)) {
                return $this->rejectedResult('Your account is not eligible to redeem this code.', 403, $code, $batch, $userId, $requestKey, $deviceHash, $visitorHash);
            }

            if (DB::table('partner_campaign_claims')->where('campaign_id', $campaign->id)->where('user_id', $userId)->exists()) {
                return $this->rejectedResult('Your account has already claimed this campaign reward.', 409, $code, $batch, $userId, $requestKey, $deviceHash, $visitorHash);
            }

            $claimedAmount = (float) DB::table('partner_campaign_claims')->where('campaign_id', $campaign->id)->sum('reward_amount');
            if ($campaign->budget_amount !== null && $claimedAmount + (float) $code->reward_amount > (float) $campaign->budget_amount) {
                return $this->rejectedResult('This campaign reward allocation is exhausted.', 409, $code, $batch, $userId, $requestKey, $deviceHash, $visitorHash);
            }
            if ($campaign->participant_limit !== null && DB::table('partner_campaign_claims')->where('campaign_id', $campaign->id)->count() >= (int) $campaign->participant_limit) {
                return $this->rejectedResult('This campaign has reached its participant limit.', 409, $code, $batch, $userId, $requestKey, $deviceHash, $visitorHash);
            }

            $wallet = $this->walletRewards->credit($userId, (float) $code->reward_amount);
            $reference = 'PRC-' . Str::upper(Str::random(20));
            $claimId = DB::table('partner_campaign_claims')->insertGetId([
                'campaign_id' => $campaign->id,
                'user_id' => $userId,
                'promo_code_id' => $code->id,
                'claim_source' => 'promo_code',
                'reward_amount' => $code->reward_amount,
                'reward_label' => $code->reward_label,
                'wallet_reference' => $wallet && isset($wallet->id) ? (string) $wallet->id : null,
                'claimed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $redemptionId = DB::table('partner_promo_redemptions')->insertGetId([
                'promo_code_id' => $code->id,
                'batch_id' => $batch->id,
                'campaign_id' => $campaign->id,
                'user_id' => $userId,
                'request_key' => $requestKey,
                'status' => 'succeeded',
                'wallet_reference' => $wallet && isset($wallet->id) ? (string) $wallet->id : null,
                'redemption_reference' => $reference,
                'reward_amount' => $code->reward_amount,
                'reward_label' => $code->reward_label,
                'device_hash' => $deviceHash,
                'visitor_hash' => $visitorHash,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('partner_promo_codes')->where('id', $code->id)->update([
                'status' => 'claimed',
                'claimed_by_user_id' => $userId,
                'claimed_at' => now(),
                'redemption_reference' => $reference,
                'updated_at' => now(),
            ]);
            DB::table('partner_promo_batches')->where('id', $batch->id)->update([
                'claimed_count' => DB::raw('claimed_count + 1'),
                'updated_at' => now(),
            ]);

            $this->insertRewardNotification($userId, (float) $code->reward_amount, (string) $campaign->title);

            return [
                'status' => 200,
                'success' => true,
                'already_claimed' => false,
                'message' => 'Promo reward redeemed successfully.',
                'redemption_reference' => $reference,
                'redemption_id' => $redemptionId,
                'claim' => [
                    'id' => $claimId,
                    'campaign_id' => (int) $campaign->id,
                    'amount' => (float) $code->reward_amount,
                    'label' => $code->reward_label,
                    'claimed_at' => now()->toDateTimeString(),
                ],
                'wallet' => $wallet,
            ];
        });
    }

    public function userRedemptions(int $userId)
    {
        return DB::table('partner_promo_redemptions')
            ->join('partner_campaigns', 'partner_promo_redemptions.campaign_id', '=', 'partner_campaigns.id')
            ->where('partner_promo_redemptions.user_id', $userId)
            ->where('partner_promo_redemptions.status', 'succeeded')
            ->select([
                'partner_promo_redemptions.id',
                'partner_promo_redemptions.reward_amount',
                'partner_promo_redemptions.created_at',
                'partner_campaigns.title as campaign_title',
            ])
            ->orderByDesc('partner_promo_redemptions.id')
            ->limit(50)
            ->get();
    }

    private function assertAllocationAvailable(object $campaign, int $count, float $totalReward): void
    {
        $allocatedCodes = (int) DB::table('partner_promo_batches')
            ->where('campaign_id', $campaign->id)
            ->whereNotIn('status', ['revoked', 'failed'])
            ->sum('code_count');
        if ($campaign->participant_limit !== null && $allocatedCodes + $count > (int) $campaign->participant_limit) {
            throw new RuntimeException('The promo batch exceeds the campaign participant limit.');
        }

        $claimedAmount = (float) DB::table('partner_campaign_claims')
            ->where('campaign_id', $campaign->id)
            ->where(function ($query) {
                $query->whereNull('claim_source')->orWhere('claim_source', '<>', 'promo_code');
            })
            ->sum('reward_amount');
        $allocatedAmount = (float) DB::table('partner_promo_batches')
            ->where('campaign_id', $campaign->id)
            ->whereNotIn('status', ['revoked', 'failed'])
            ->sum('total_reward_amount');
        if ($campaign->budget_amount !== null && $claimedAmount + $allocatedAmount + $totalReward > (float) $campaign->budget_amount) {
            throw new RuntimeException('The promo batch exceeds the remaining campaign BRC allocation.');
        }
    }

    private function resolveRewards(int $count, array $data): array
    {
        $rewards = $data['rewards'] ?? null;
        if (is_array($rewards) && count($rewards) === $count) {
            return array_map(function ($amount) {
                $value = round((float) $amount, 4);
                if ($value <= 0 || $value > 1000000) {
                    throw new RuntimeException('Every promo reward must be greater than zero and no more than 1,000,000 BRC.');
                }
                return $value;
            }, array_values($rewards));
        }

        $amount = round((float) ($data['reward_amount'] ?? 0), 4);
        if ($amount <= 0 || $amount > 1000000) {
            throw new RuntimeException('The promo reward must be greater than zero and no more than 1,000,000 BRC.');
        }

        return array_fill(0, $count, $amount);
    }

    private function generateCode(): string
    {
        $segments = [];
        for ($segment = 0; $segment < 3; $segment++) {
            $value = '';
            for ($index = 0; $index < 4; $index++) {
                $value .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            $segments[] = $value;
        }
        return 'BRC-' . implode('-', $segments);
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($code)));
    }

    private function newBatchReference(): string
    {
        do {
            $reference = 'PC-' . date('Ymd') . '-' . Str::upper(Str::random(8));
        } while (DB::table('partner_promo_batches')->where('batch_reference', $reference)->exists());

        return $reference;
    }

    private function vaultPath(string $reference): string
    {
        return storage_path('app/private/partner-promo/' . $reference . '.vault');
    }

    private function csvLine(array $values): string
    {
        return implode(',', array_map(function ($value) {
            $value = (string) $value;
            return '"' . str_replace('"', '""', $value) . '"';
        }, $values)) . "\r\n";
    }

    private function rejectedResult(string $message, int $status, ?object $code = null, ?object $batch = null, int $userId = 0, string $requestKey = '', ?string $deviceHash = null, ?string $visitorHash = null): array
    {
        if ($code && $batch && $userId > 0 && $requestKey !== '') {
            DB::table('partner_promo_redemptions')->insertOrIgnore([
                'promo_code_id' => $code->id,
                'batch_id' => $batch->id,
                'campaign_id' => $code->campaign_id,
                'user_id' => $userId,
                'request_key' => $requestKey,
                'status' => 'rejected',
                'failure_reason' => Str::limit($message, 120),
                'reward_amount' => (float) ($code->reward_amount ?? 0),
                'device_hash' => $deviceHash,
                'visitor_hash' => $visitorHash,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return ['status' => $status, 'success' => false, 'message' => $message];
    }

    private function successResult(object $redemption, object $campaign): array
    {
        return [
            'status' => 200,
            'success' => true,
            'already_claimed' => true,
            'message' => 'This promo reward was already redeemed successfully.',
            'redemption_id' => (int) $redemption->id,
            'redemption_reference' => $redemption->redemption_reference,
            'claim' => [
                'campaign_id' => (int) $campaign->id,
                'amount' => (float) $redemption->reward_amount,
                'label' => $redemption->reward_label,
                'claimed_at' => $redemption->created_at,
            ],
        ];
    }

    private function claimPayload(object $claim): array
    {
        return [
            'id' => (int) $claim->id,
            'campaign_id' => (int) $claim->campaign_id,
            'amount' => (float) $claim->reward_amount,
            'label' => $claim->reward_label,
            'claimed_at' => $claim->claimed_at,
        ];
    }

    private function insertRewardNotification(int $userId, float $amount, string $campaignTitle): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        $message = 'Congratulations! ' . $amount . ' BRC has been added for ' . $campaignTitle . '.';
        $payload = [
            'type' => 'Promo Reward',
            'title' => 'Partner promo reward received',
            'action' => 'CLAIM_REWARDS',
            'reward' => $amount,
            'data' => ['coin' => $amount, 'message' => $message, 'campaign' => $campaignTitle],
        ];
        $insert = [];
        if (Schema::hasColumn('notifications', 'type')) $insert['type'] = 'BRC_REWARD';
        if (Schema::hasColumn('notifications', 'notifiable_type')) $insert['notifiable_type'] = ResUser::class;
        if (Schema::hasColumn('notifications', 'notifiable_id')) $insert['notifiable_id'] = $userId;
        if (Schema::hasColumn('notifications', 'data')) $insert['data'] = json_encode($payload);
        if (Schema::hasColumn('notifications', 'notification_type')) $insert['notification_type'] = 'BRC_REWARD';
        if (Schema::hasColumn('notifications', 'status')) $insert['status'] = 1;
        if (Schema::hasColumn('notifications', 'n_type')) $insert['n_type'] = 1;
        if (Schema::hasColumn('notifications', 'read_at')) $insert['read_at'] = null;
        if (Schema::hasColumn('notifications', 'created_at')) $insert['created_at'] = now();
        if (Schema::hasColumn('notifications', 'updated_at')) $insert['updated_at'] = now();
        if ($insert) DB::table('notifications')->insert($insert);
    }
}
