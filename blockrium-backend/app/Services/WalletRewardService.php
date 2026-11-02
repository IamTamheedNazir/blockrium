<?php

namespace App\Services;

use App\Support\BrcAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class WalletRewardService
{
    public function credit(int $userId, $amount)
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('A valid user and non-negative reward amount are required.');
        }
        $amountUnits = BrcAmount::toUnits($amount);

        if (!Schema::hasTable('tbluserwallet')) {
            throw new RuntimeException('Reward wallet is unavailable.');
        }

        if ($amountUnits === 0) {
            return DB::table('tbluserwallet')->where('userid', $userId)->orderByDesc('id')->first();
        }

        return DB::transaction(function () use ($userId, $amountUnits) {
            // Lock the stable user row too, so first-credit races are serialized before a wallet row exists.
            if (Schema::hasTable('res_users') && Schema::hasColumn('res_users', 'id')) {
                DB::table('res_users')->where('id', $userId)->lockForUpdate()->first();
            }

            $wallet = DB::table('tbluserwallet')
                ->where('userid', $userId)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                $data = [
                    'userid' => $userId,
                    'totaltoken' => BrcAmount::fromUnits($amountUnits),
                ];

                if (Schema::hasColumn('tbluserwallet', 'created_at')) {
                    $data['created_at'] = now();
                }

                if (Schema::hasColumn('tbluserwallet', 'updated_at')) {
                    $data['updated_at'] = now();
                }

                DB::table('tbluserwallet')->insert($data);
            } else {
                $data = [
                    'totaltoken' => BrcAmount::fromUnits(BrcAmount::toUnits($wallet->totaltoken) + $amountUnits),
                ];

                if (Schema::hasColumn('tbluserwallet', 'updated_at')) {
                    $data['updated_at'] = now();
                }

                DB::table('tbluserwallet')->where('id', $wallet->id)->update($data);
            }

            return DB::table('tbluserwallet')->where('userid', $userId)->orderByDesc('id')->first();
        });
    }
}
