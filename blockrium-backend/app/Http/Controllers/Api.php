<?php

namespace App\Http\Controllers;

use DateTime;
use Auth;
use Illuminate\Support\Facades\DB;
use Validator;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;
use App\Models\ButtonClick;
use App\Models\Referral;
use App\Models\ResUser;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\VerificationCode;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
//use Mail;
class Api extends Controller {
	
	public function __construct() {
		
		//$this->middleware('auth:api', ['except' => ['login', 'register']]);
	}
	
	public function UserMining(Request $request){
	
		$user_list = DB::table('tbl_user_mining')  
        ->get();
		
		$hour_count = 1;
		foreach($user_list as $user){
			$buttonstatus = $user->button_status;
			$hour_count=$user->hour_count;
			if($hour_count <= 24){
			$minin_start_time= $user->created_at;
		
			$minin_start_time = date('Y-m-d H:i:s', strtotime('+1 hour'));
			// for ($hour_count;$hour_count >=24;$hour_count++){
			// 	$hour_count;
			// }
			$hour_rate= 10;
			$current_balence = $user->totalamount;
			$totalamount = $current_balence + $hour_rate;
				
		}
		$hour_count++;
	}

		$data =array(
			'hour_count' => $hour_count,
			'totalamount'=>$totalamount,	
		   ) ; 
		if($user->hour_count <= 24 && $user->button_status == 1 ){
			DB::table('tbl_user_mining')->where('id','=',1)->update($data);
		}
		$data2 =array(
			'hour_count' => 1,
				
		   ) ;

		if($user->hour_count == 0){
			DB::table('tbl_user_mining')->where('id','=',1)->update($data2);
		}
		$data1=array(
			'button_status' => 1,
			'hour_count' => 0,	
		);
		if($user->hour_count == 24 && $user->button_status == 1){
			DB::table('tbl_user_mining')->where('id','=',1)->update($data1);
		}
		$data3=array(
			'button_status' => 1,
			'created_at' =>now(),
		);
		if($user->hour_count == 0 && $user->button_status == 0){
			DB::table('tbl_user_mining')->where('id','=',1)->update($data3);
		}

		$response = array('status'=>1,'data'=> $user_list);
		return response()->json($response);
	}	

    public function banners(Request $request){
	
		$banners = DB::table('banners')->orderBy('id', 'DESC')->get();
        if($banners) {
		$url = 'https://maticlegend.com';
		$userData=[];
				$i=0;
				foreach($banners as $user => $value) {
					$userData[$i]['id'] = $value->id;
					$userData[$i]['title'] = $value->title;
					$userData[$i]['paragraph'] = $value->paragraph;
					$userData[$i]['date'] = $value->date;
					$userData[$i]['link'] = $value->link;
					$userData[$i]['image'] = $url.'/public/banners/'.$value->image;
					$i++;
				}
        
    
	$response = array('status'=> 1 ,'message'=> 'Success','userData'=> $userData);
	}
	else {
		$response = array('status'=> 0 ,'message'=> 'No records were found!','data'=>[]);
	}			
	return $response;

	}

	public function app_version(Request $request){
        if (!Schema::hasTable('app_version')) {
            return response()->json([
                'status' => 0,
                'message' => 'App version is not configured',
                'data' => null,
                'app_version' => [],
            ]);
        }

        $platform = strtolower((string) $request->query('platform', 'android'));
        $query = DB::table('app_version');

        if (Schema::hasColumn('app_version', 'status')) {
            $query->where(function ($where) {
                $where->where('status', 1)
                    ->orWhere('status', '1')
                    ->orWhere('status', 'active');
            });
        }

        if (Schema::hasColumn('app_version', 'platform')) {
            $query->where(function ($where) use ($platform) {
                $where->whereNull('platform')
                    ->orWhere('platform', '')
                    ->orWhere('platform', 'all')
                    ->orWhere('platform', $platform);
            });
        }

        if (Schema::hasColumn('app_version', 'latest_version_code')) {
            $query->orderByDesc('latest_version_code');
        }

        $latest = $query->orderByDesc('id')->first();

        if (!$latest) {
            return response()->json([
                'status' => 0,
                'message' => 'No active app version found',
                'data' => null,
                'app_version' => [],
            ]);
        }

        $latestVersionCode = (int) ($latest->latest_version_code ?? $latest->version_code ?? $latest->version ?? 0);
        $minimumVersionCode = (int) ($latest->minimum_version_code ?? 0);
        $forceUpdate = (bool) ($latest->force_update ?? false);
        $currentVersionCode = (int) $request->query('current_version_code', 0);
        $link = $latest->android_link ?? $latest->link ?? '';
        $releaseNotes = $latest->release_notes ?? $latest->title ?? 'A new Blockrium update is available.';

        $payload = [
            'id' => (int) $latest->id,
            'title' => $latest->title ?? 'Blockrium update available',
            'version' => (string) ($latest->version ?? $latestVersionCode),
            'version_code' => $latestVersionCode,
            'latest_version_code' => $latestVersionCode,
            'minimum_version_code' => $minimumVersionCode,
            'force_update' => $forceUpdate,
            'required' => $forceUpdate || ($currentVersionCode > 0 && $minimumVersionCode > 0 && $currentVersionCode < $minimumVersionCode),
            'update_available' => $currentVersionCode > 0 ? $currentVersionCode < $latestVersionCode : null,
            'platform' => $latest->platform ?? 'android',
            'status' => $latest->status ?? 1,
            'release_notes' => $releaseNotes,
            'link' => $link,
            'android_link' => $link,
            'ios_link' => $latest->ios_link ?? null,
        ];

        return response()->json([
            'status' => 1,
            'message' => 'Authenticated',
            'data' => $payload,
            'app_version' => [$payload],
        ]);
    }
    
    public function appStage(){
        $appStage = DB::table('appstage')->get();
        
        return response()->json(['message'=>'Authenticated', 'appStage'=>$appStage]);
    }
    
    // public function topMiners(){
    //     $top50Miners = ButtonClick::select('res_users.id', 'res_users.name', \DB::raw("SUM(wallet) as total"))
    //     ->join('res_users', 'res_users.id', '=', 'button_clicks.user_id')
    //     ->where('res_users.is_active', '=', 1)
    //     ->groupBy('res_users.id')
    //     ->orderByDesc('total')
    //     ->limit(50)
    //     ->get();

    //     return response()->json(['message'=>'Authenticated', 'data'=>$top50Miners]);
    // }
    
    public function topMiners(Request $request){
        return $this->leaderboardResponse($request, 'button_clicks', 'wallet');
    }

    
    // public function topRefferals(){
    //      $top50refferals = Referral::select('res_users.id', 'res_users.name', \DB::raw("SUM(rewards) as total"))
    //     ->join('res_users', 'res_users.id', '=', 'referrals.user_id')
    //     ->where('res_users.is_active', '=', 1)
    //     ->groupBy('res_users.id')
    //     ->orderByDesc('total')
    //     ->limit(50)
    //     ->get();

    //     return response()->json(['message'=>'Authenticated', 'data'=>$top50refferals]);
    // }
    
    public function topRefferals(Request $request){
        $referralCounts = DB::table('referrals')
            ->whereNotNull('referred_by')
            ->selectRaw('referred_by as referral_key, COUNT(*) as total')
            ->groupBy('referred_by');

        $rankedUsers = function () use ($referralCounts) {
            return DB::query()
                ->fromSub(clone $referralCounts, 'referral_counts')
                ->join('res_users', function ($join) {
                    $join->on('res_users.refferal_code', '=', 'referral_counts.referral_key')
                        ->orOn(DB::raw('CAST(res_users.id AS CHAR)'), '=', 'referral_counts.referral_key');
                })
                ->where('res_users.is_active', '=', 1)
                ->select('res_users.id', 'res_users.name', 'res_users.user_image', DB::raw('SUM(referral_counts.total) as total'))
                ->groupBy('res_users.id', 'res_users.name', 'res_users.user_image');
        };

        return $this->formatLeaderboardResponse($request, $rankedUsers);
    }

    private function leaderboardResponse(Request $request, string $activityTable, string $totalColumn)
    {
        $totals = DB::table($activityTable)
            ->selectRaw('user_id, SUM(' . $totalColumn . ') as total')
            ->groupBy('user_id');

        $rankedUsers = function () use ($totals) {
            return DB::table('res_users')
                ->leftJoinSub(clone $totals, 'activity_totals', function ($join) {
                    $join->on('res_users.id', '=', 'activity_totals.user_id');
                })
                ->where('res_users.is_active', '=', 1)
                ->select('res_users.id', 'res_users.name', 'res_users.user_image', 'activity_totals.total');
        };

        return $this->formatLeaderboardResponse($request, $rankedUsers);
    }

    private function formatLeaderboardResponse(Request $request, callable $rankedUsers)
    {
        $imageUrl = static function ($user) {
            $user->image = !empty($user->user_image)
                ? 'https://maticlegend.com/public/user_image/' . $user->user_image
                : null;
            return $user;
        };

        $rankedQuery = function () use ($rankedUsers) {
            return DB::query()
                ->fromSub($rankedUsers(), 'leaderboard')
                ->select('leaderboard.id', 'leaderboard.name', 'leaderboard.user_image', 'leaderboard.total');
        };

        $topUsers = $rankedQuery()
            ->orderByRaw('COALESCE(leaderboard.total, 0) DESC')
            ->orderBy('leaderboard.id')
            ->limit(50)
            ->get()
            ->values()
            ->map(function ($user, $index) use ($imageUrl) {
                $user->position = $index + 1;
                return $imageUrl($user);
            });

        $userId = $request->input('userId');
        $authUser = $userId === null ? null : $rankedQuery()->where('leaderboard.id', $userId)->first();

        if ($authUser) {
            $rankQuery = $rankedQuery()
                ->where(function ($query) use ($authUser) {
                    $query->whereRaw('COALESCE(leaderboard.total, 0) > COALESCE(?, 0)', [$authUser->total])
                        ->orWhere(function ($tie) use ($authUser) {
                            $tie->whereRaw('COALESCE(leaderboard.total, 0) = COALESCE(?, 0)', [$authUser->total])
                                ->where('leaderboard.id', '<', $authUser->id);
                        });
                });

            $authUser->position = (int) $rankQuery->count() + 1;
            $imageUrl($authUser);
        }

        return response()->json(['message' => 'Authenticated', 'data' => [
            'top50Miners' => $topUsers,
            'authUserMiner' => $authUser,
        ]]);
    }
    
    public function SendOtpupdatePassword(Request $request) {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Enter a valid email address.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = strtolower(trim($request->input('email')));
        $user = ResUser::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No Blockrium account was found for this email.',
            ], 404);
        }

        $otp = (string) random_int(100000, 999999);

        if (!$this->sendOtpEmail($email, $otp)) {
            return response()->json([
                'success' => false,
                'message' => 'OTP email could not be sent right now. Please try again.',
            ], 500);
        }

        try {
            $verificationCode = DB::transaction(function () use ($user, $otp) {
                VerificationCode::where('user_id', $user->id)->delete();

                return VerificationCode::create([
                    'user_id' => $user->id,
                    'email_otp' => $otp,
                    'expire_at' => Carbon::now()->addMinutes(10),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Failed storing password reset OTP', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Password reset could not be started right now. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent to your email.',
            'data' => [
                'email' => $email,
                'otp_sent' => true,
                'expires_in_seconds' => max(0, Carbon::now()->diffInSeconds($verificationCode->expire_at, false)),
            ],
        ]);
    }
    
    private function sendOtpEmail($to, $otp) {
        try {
            $data = [
                'otp' => $otp,
                'username' => $to,
            ];

            $subject = "Blockrium Account : OTP for Update Password";
            Mail::send('frontend.email_templates.email-otp', compact('data'), function ($message) use ($to, $subject) {
                $message
                    ->to($to)
                    ->from(
                        config('mail.from.address', 'no-reply@blockrium.com'),
                        config('mail.from.name', 'Blockrium')
                    )
                    ->subject($subject);
            });

            return count(Mail::failures()) === 0;
        } catch (\Throwable $e) {
            Log::error('Failed sending password reset OTP', [
                'email' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
    
    public function updatePassword(Request $request) {
        $validator = Validator::make($request->all(), [
            'new_password' => 'required|string|min:6|max:128',
            'email' => 'required|email',
            'otp' => 'required',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Enter the OTP and a password with at least 6 characters.',
                'errors' => $validator->errors(),
            ], 422);
        }
    
        $email = strtolower(trim($request->email));
        $user = ResUser::where('email', $email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }
    
        return DB::transaction(function () use ($request, $user) {
            $verificationCode = VerificationCode::where('user_id', $user->id)
                ->where('email_otp', trim($request->otp))
                ->where('expire_at', '>', Carbon::now())
                ->lockForUpdate()
                ->first();

            if (!$verificationCode) {
                return response()->json(['success' => false, 'message' => 'Your OTP is invalid or expired.'], 422);
            }

            $user->password = Hash::make($request->input('new_password'));
            $user->save();
            VerificationCode::where('user_id', $user->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully.',
                'data' => [
                    'password_updated' => true,
                ],
            ]);
        });
    }

    
    public function getCountries() {
       
        $allCountries = DB::table('country')->get();
        $countryList = [];
        
        $url = 'https://maticlegend.com';
					
        foreach ($allCountries as $key => $country) {
            $countryList[] = [
                'name' => $country->countryname,
                'code' => $country->code,
                'flag' => $url . '/public/new_flags/' . strtolower($country->code) . '.svg',
            ];
        }
    
        return response()->json([
            'countries' => $countryList,
        ], 200);
    }
    
    
    public function getNews(Request $request){
	
		$banners = DB::table('custom_news')->orderBy('id', 'DESC')->get();
        if($banners) {
		$url = 'https://maticlegend.com';
		$userData=[];
				$i=0;
				foreach($banners as $user => $value) {
					$userData[$i]['id'] = $value->id;
					$userData[$i]['title'] = $value->title;
					$userData[$i]['description'] = $value->description;
					$userData[$i]['categories'] = $value->categories;
					$userData[$i]['link'] = $value->link;
					$userData[$i]['created_at'] = $value->created_at;
					$userData[$i]['image'] = $url.'/public/news/'.$value->image;
					$i++;
				}
        
    
	$response = array('status'=> 1 ,'message'=> 'Success','userData'=> $userData);
	}
	else {
		$response = array('status'=> 0 ,'message'=> 'No records were found!','data'=>[]);
	}			
	return $response;

	}
	
	public function APkdownloadFile(){
        $apk = DB::table('app_apk')->first();

        // Assuming the file field contains the file path
        $filePath = public_path('apk') .'/' . $apk->file;

        return response()->download($filePath, $apk->title);
    }
    
    public function APpMaintenance(){
        $app_maintenance = DB::table('app_maintenance')->first();
        $app_maintenance_message = [
            $zero = 'Status 0=> Not App Maintenance',
            $one ='Status 1=> in App Maintenance',
            ];
        if($app_maintenance->status == 1){
            $app_status = true;
        }else{
             $app_status = false;
        }
        
        return response()->json(['message'=>'Authenticated', 'app_maintenance'=>$app_maintenance,'app_maintenance_message'=>$app_maintenance_message,'app_status'=>$app_status]);
    }
    
    public function AllGames(){
        $games = DB::table('games')
        ->leftJoin('game_category', 'games.game_category_id', '=', 'game_category.id')
         ->select('games.*', DB::raw('game_category.title as gametitle'))
        ->get();
        
        if($games) {
		$url = 'https://maticlegend.com';
		$all_games=[];
				$i=0;
				foreach($games as $user => $value) {
					$all_games[$i]['id'] = $value->id;
					$all_games[$i]['gamename'] = $value->title;
					$all_games[$i]['gamecategory'] = $value->gametitle;
					$all_games[$i]['url'] = $value->url;
					$all_games[$i]['logo'] = $url.'/public/game/'.$value->logo;
					$i++;
				}
        
    
	$response = array('status'=> 1 ,'message'=> 'Success','all_games'=> $all_games);
	}
	else {
		$response = array('status'=> 0 ,'message'=> 'No records were found!','data'=>[]);
	}			
	return $response;
	
        // $countGames = $games->count();
        // if($countGames>0){
            
        //     return response()->json(['message'=>'Success', 'all_games'=>$games]);
        // }else{
        //     return response()->json(['message'=>'No games found']);
        // }
    }
    
    public function RecommendedGames(){
        $games = DB::table('games')
        ->leftJoin('game_category', 'games.game_category_id', '=', 'game_category.id')
         ->select('games.*', DB::raw('game_category.title as gametitle'))
        ->whereIn('games.id', [2, 15, 17, 21, 45, 52, 54, 56, 60, 70])
        ->get();
        
        if($games) {
		$url = 'https://maticlegend.com';
		$all_games=[];
				$i=0;
				foreach($games as $user => $value) {
					$all_games[$i]['id'] = $value->id;
					$all_games[$i]['gamename'] = $value->title;
					$all_games[$i]['gamecategory'] = $value->gametitle;
					$all_games[$i]['url'] = $value->url;
					$all_games[$i]['logo'] = $url.'/public/game/'.$value->logo;
					$i++;
				}
        
    
	$response = array('status'=> 1 ,'message'=> 'Success','recommended_games'=> $all_games);
	}
	else {
		$response = array('status'=> 0 ,'message'=> 'No records were found!','data'=>[]);
	}			
	return $response;
	
    }
    
    public function TopGames(){
        
    }
    
    public function globalrank(Request $request){
         $subquery = DB::table('button_clicks')
        ->selectRaw('user_id, SUM(wallet) as total')
        ->groupBy('user_id');

        $users = DB::table('res_users')
            ->leftJoinSub($subquery, 'button_clicks', function ($join) {
                $join->on('res_users.id', '=', 'button_clicks.user_id');
            })
            ->leftJoin('tblreward', 'res_users.id', '=', 'tblreward.userid')
            ->leftJoin('tblattendance', 'res_users.id', '=', 'tblattendance.userid')
            ->leftJoin('tbl_user_mining', 'res_users.id', '=', 'tbl_user_mining.userid')
            ->leftJoin('tbluserwallet', 'res_users.id', '=', 'tbluserwallet.userid')
            ->leftJoin('referrals', 'res_users.id', '=', 'referrals.user_id')
            ->leftJoin('button_clicks as bc', 'res_users.id', '=', 'bc.user_id')
            ->where('res_users.is_active', '=', 1)
            ->select('res_users.id', 'res_users.name','res_users.user_image', 
                DB::raw('IFNULL(tblreward.token, 0) + IFNULL(tblattendance.coins, 0) + 
                    IFNULL(tbl_user_mining.totalamount, 0) + IFNULL(tbluserwallet.totaltoken, 0) + 
                    IFNULL(referrals.rewards, 0) + IFNULL(SUM(bc.wallet), 0) as total_balance')
            )
            ->groupBy('res_users.id')
            ->orderByDesc('total_balance')
            ->get();
    
        // If userId is provided, find the position of the user
        $userId = $request->userId;
        $authUserPosition = null;
        if ($userId) {
            $authUser = $users->where('id', $userId)->first();
            if ($authUser) {
                $position = $users->pluck('id')->search($userId) + 1;
                $authUserPosition = $position;
            }
        }
    
        return response()->json(['message' => 'Data retrieved successfully', 'data' => [
            'authUserPosition' => $authUserPosition,
        ]]);
    }
//tblwhitepaper

    public function whitepaper(){
        $getWhitepaper=DB::table('tblwhitepaper')->first();
        
    }
    
   public function totalReward()
    {
        $rewarddata= \DB::table('tblreward')
            ->where('status', '!=', 0)
            ->sum('token');
        $userCount = \DB::table('res_users')->count('id');
        
        $complexity = rand(1000, 9999); 
        $blocks = rand(1000000, 9999999);
            
             return response()->json(['message' => 'Data retrieved successfully', 'total_reward' =>$rewarddata,'online'=>$userCount,'complexity' => $complexity,
    'blocks' => '#'.$blocks,]);
    }

}
