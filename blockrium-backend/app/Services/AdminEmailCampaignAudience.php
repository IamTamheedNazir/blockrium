<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AdminEmailCampaignAudience
{
    public function recipients(string $audience)
    {
        $query = DB::table('res_users as u')
            ->leftJoin('admin_email_preferences as p', 'p.user_id', '=', 'u.id')
            ->where('u.is_active', 1)
            ->whereNotNull('u.email')
            ->where('u.email', '<>', '')
            ->whereNull('p.bulk_email_opted_out_at');

        if ($audience === 'pending_kyc') {
            $query->leftJoin('verified_memberships as m', 'm.user_id', '=', 'u.id')
                ->where(function ($query) {
                    $query->whereNull('m.id')->orWhereIn('m.kyc_status', ['not_started', 'started']);
                })
                ->whereNull('m.suspended_at');
        }

        return $query->select('u.id', 'u.email', 'u.name', 'u.username');
    }

    public function isEligible(string $audience, $user, $preference, $membership): bool
    {
        if (!$user || (int) $user->is_active !== 1 || !filter_var(trim((string) $user->email), FILTER_VALIDATE_EMAIL)
            || ($preference && $preference->bulk_email_opted_out_at)) {
            return false;
        }

        if ($audience === 'pending_kyc') {
            return (!$membership || in_array($membership->kyc_status, ['not_started', 'started'], true))
                && (!$membership || !$membership->suspended_at);
        }

        return $audience === 'all_active';
    }
}
