<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\ResUser;
use Illuminate\Notifications\DatabaseNotification;
use Auth;
use App\Models\ResUserModel;
use Illuminate\Support\Facades\Config;
use App\Notifications\CoinsSentNotification;
use DB;
use App\Jobs\SendCoinJob;
use App\Jobs\SendPushNotificationJob;
use App\Services\WalletRewardService;
class NotificationController extends Controller
{

    public function __construct() {
		
		
	}


    public function index()
    {
        $notifications = auth($this->guard)->user()->notifications;
        return view('notifications.index', compact('notifications'));
    }

    public function markAsRead(Request $request)
    {
        auth($this->guard)->user()->unreadNotifications
            ->when($request->input('id'), function ($query) use ($request) {
                return $query->where('id', $request->input('id'));
            })
            ->markAsRead();
        return response()->json(['success' => true]);
    }
    
    
    public function sendCoin(Request $request){
        $data = $request->validate([
            'token' => ['required', 'numeric', 'gt:0'],
        ]);
        $token = (float) $data['token'];
        $queued = 0;

        DB::table('res_users')->select('id')->orderBy('id')->chunkById(100, function ($users) use ($token, &$queued) {
            SendCoinJob::dispatch($users->pluck('id')->map(fn ($id) => (int) $id)->all(), $token);
            $queued += $users->count();
        });

        return redirect()->back()->with('success', "BRC reward queued for {$queued} users successfully.");

    }
    
     public function sendCoinSingleUser(Request $request){
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'numeric', 'gt:0'],
        ]);
        $user = ResUser::where('email', $data['email'])->first();

        if (!$user) {
            return redirect()->back()->with('error', 'User not found.');
        }

        $token = (float) $data['token'];
        DB::transaction(function () use ($user, $token) {
            app(WalletRewardService::class)->credit((int) $user->id, $token);
            $user->notify(new CoinsSentNotification($token, $user->name, 'BRC Reward', $user->name));

            if (!empty($user->device_id)) {
                SendPushNotificationJob::dispatch(
                    $user->device_id,
                    (int) $user->id,
                    'BRC reward received',
                    "Congratulations! {$token} BRC has been added to your wallet.",
                    [
                        'type' => 'BRC_REWARD',
                        'action' => 'CLAIM_REWARDS',
                        'amount' => (string) $token,
                    ]
                )->afterCommit();
            }
        });

        return redirect()->back()->with('success', 'BRC reward sent to the user successfully.');
    }
    
    
}
