<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\ResUser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class ReferralController extends Controller
{
    public function create(Request $request)
    {
        $user = ResUser::findOrFail($request->user_id);

        $referral_code = Str::random(12);

        $referred_by = Referral::find($request->referral_code);
    //echo "<pre>";print_r($referred_by); die();
        if ($referred_by) {
            $referred_by_referral = $referred_by->referral;
            $referred_by_referral->increment('rewards', 5);
            $reward = 5;
        }

        $referral = new Referral([
            'user_id' => $user->id,
            'referral_code' => $referral_code,
            'referred_by' => $request->referred_by,
            'rewards' => 5,
        ]);

        $user->referral()->save($referral);

        return response()->json(['referral_code' => $referral_code]);
    }
}

