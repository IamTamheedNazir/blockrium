<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request; 
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\AdminModel;
use Session;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use App\Library\Encryption;
use ZipArchive;
use Validator;
use App\Http\Controllers\Response;
use Exception;
use App\Models\Payment;
use Kreait\Firebase\Contract\Database;
use App\Models\ResUserModel;
use App\Models\ResUser;
use App\Models\GgToken;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Auth;
use App\Notifications\CoinsSentNotification;
use App\Helpers\helpers;
use Illuminate\Support\Facades\File;
use Google_Client;
use Illuminate\Support\Facades\Http;
use App\Jobs\SendPushNotificationJob;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Services\RevenueCatService;
use App\Services\VerifiedMemberService;

date_default_timezone_set("Asia/Kolkata");




class AdminController extends BaseController{
	use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
	public function __construct(AdminModel $AdminModel, ResUserModel $ResUserModel, Encryption $Encryption, Database $database) {
        $this->AdminModel = $AdminModel;	
        $this->Encryption = $Encryption;
		$this->ResUserModel = $ResUserModel;		
		// $this->database = $database;
    }

	function index(){
		if(empty(Session::get('ad_id'))){
			return view('admin/login');
		}else{
			return redirect('/dashboard');	
		}
	}	

	public function  dologin(Request $request){
		$user_email = $request->user_email;		
		$user_password =$request->user_password;
		$data = $this->AdminModel->userlogin($user_email,$user_password);
		if(empty($data)){
			return back()->with('error','Invalid Login details');	
		}else{
		   Session::put('ad_id',$data->admin_id);
		   //echo "<pre>";print_r($data);die();
		  return redirect('/dashboard');	
		}
    }
	public function logout(){    
		 Session::flush();
         return redirect('/admin');		 
    }
	public function cms(){    
        return view('/admin/cms');		 
    }
	public function changepassword(){
		return view('admin/change_password');	
	}

	function forgotpassword(){

		return view('admin/forgot');

	}

    function forgot_password(Request $request){
		$data = $this->AdminModel->checkuser($request->email);
		if(empty($data)){
			return back()->with('error','Email id is not exit. please check you email');
		}else{
			/* Update data */
				$updatedata = [
					"forgot"=>'Y'
				];
				$this->AdminModel->user_update($updatedata,$data->admin_id);
			/* Update data */
			$username =  '';
			$encrypted = Crypt::encryptString($data->admin_id);
			$data2 =  [
				'username'=>$data->username,
				'ad_id'=>$encrypted
			];
			//$from="";
			$to = $data->username;
			$subject = "Forgot Password";
			$txt = " <p>Hi ".$data2['username']."</p>
			<a href='".env('APP_URL')."ad_resetpassword/".$data2['ad_id']."'>".env('APP_URL')."ad_resetpassword/".$data2['ad_id']."</a>";
			$headers = "MIME-Version: 1.0" . "\r\n";
			$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
			//$headers .= 'From: Your name <keshab@gmail.com>' . "\r\n";
			mail($to,$subject,$txt,$headers);
			return back()->with('success','Email has been sent in your email address please check');
		}
	}
	public function resetpassword($id){ 
		$decrypted = Crypt::decryptString($id);
		$getdetails = $this->AdminModel->checkdetailsuser($decrypted);
		if($getdetails->forgot == 'Y'){
			return view('admin/reset_password',compact('decrypted','id'));
		}else{
			return redirect('/admin')->with('error','Invalid Url');
		}
	}

	public function change_password(Request $request){
		$userid = Session::get('ad_id');
		$password = $request->new_password;
			$new_password=$request->current_password;
			$getdetails = $this->AdminModel->checkcurrentpassword($userid,$new_password);
             //print_r($getdetails); die;
			if(empty($getdetails)){
				return redirect('admin/change_password')->with('error','Current Password is wrong.');
			}else{
				$data=[
				"password"=>Hash::make($password),
			];
			$this->AdminModel->user_update($data,$userid);	
			return redirect('/admin')->with('success','Password has been changed successfully');
		}
	}

	public function updatePassword(Request $request){
			$id=Crypt::decryptString($request->id);
           $password = $request->new_password;
		   $data=[
				"password"=>Hash::make($password),
				"forgot"=> 'N'
			];
			$this->AdminModel->user_update($data,$id);	
			return redirect('/admin')->with('success','Password has been changed successfully');
        }

	private function safeCount($table, $callback = null)
	{
		try {
			if (!DB::getSchemaBuilder()->hasTable($table)) {
				return 0;
			}

			$query = DB::table($table);
			if ($callback) {
				$callback($query);
			}

			return $query->count();
		} catch (\Throwable $error) {
			return 0;
		}
	}

	private function safeSum($table, $column)
	{
		try {
			if (!DB::getSchemaBuilder()->hasTable($table)) {
				return 0;
			}

			return (float) DB::table($table)->sum($column);
		} catch (\Throwable $error) {
			return 0;
		}
	}

	private function safeGet($table, $callback = null, $limit = 5)
	{
		try {
			if (!DB::getSchemaBuilder()->hasTable($table)) {
				return collect();
			}

			$query = DB::table($table);
			if ($callback) {
				$callback($query);
			}

			return $query->limit($limit)->get();
		} catch (\Throwable $error) {
			return collect();
		}
	}

	private function hasColumn($table, $column)
	{
		try {
			return DB::getSchemaBuilder()->hasTable($table)
				&& DB::getSchemaBuilder()->hasColumn($table, $column);
		} catch (\Throwable $error) {
			return false;
		}
	}

	private function subscriptionProductKey($productId)
	{
		if (!$productId) {
			return null;
		}

		$parts = explode(':', (string) $productId);
		$key = end($parts);

		return in_array($key, ['monthly', 'three_month', 'six_month', 'yearly'], true) ? $key : null;
	}

	private function subscriptionOverview()
	{
		$overview = [
			'total_pro_users' => 0,
			'active_subscriptions' => 0,
			'expired_users' => 0,
			'revenue_this_month' => 0,
			'plan_counts' => [
				'monthly' => 0,
				'three_month' => 0,
				'six_month' => 0,
				'yearly' => 0,
			],
			'recent' => collect(),
		];

		try {
			$serviceStats = app(RevenueCatService::class)->subscriptionStats();
			$overview['total_pro_users'] = $serviceStats['total_pro_users'] ?? 0;
			$overview['active_subscriptions'] = $serviceStats['active_subscriptions'] ?? 0;
			$overview['expired_users'] = $serviceStats['expired_subscriptions'] ?? 0;
			$overview['revenue_this_month'] = $serviceStats['revenue']['this_month'] ?? 0;
			$overview['plan_counts'] = array_merge($overview['plan_counts'], $serviceStats['plan_counts'] ?? []);

			if (DB::getSchemaBuilder()->hasTable('revenuecat_subscriptions')) {
				$overview['recent'] = DB::table('revenuecat_subscriptions')
					->leftJoin('res_users', 'revenuecat_subscriptions.user_id', '=', 'res_users.id')
					->select(
						'revenuecat_subscriptions.*',
						'res_users.username',
						'res_users.name as user_name',
						'res_users.email'
					)
					->orderByDesc('revenuecat_subscriptions.last_event_at')
					->limit(6)
					->get();
			}
		} catch (\Throwable $error) {
			return $overview;
		}

		return $overview;
	}

	public  function home(Request $request){				
		$ad_id=Session::get('ad_id');
		$admindetails = $this->AdminModel->getRowById('tbl_admin',array('admin_id'=>$ad_id));
		$today = Carbon::now()->startOfDay();
		$subscriptionStats = $this->subscriptionOverview();
		$verificationStats = [
			'kyc_approved' => 0,
			'payment_pending' => 0,
			'in_progress' => 0,
			'waitlisted' => 0,
		];
		try {
			if (Schema::hasTable('verified_memberships')) {
				$verificationStats = array_merge($verificationStats, app(VerifiedMemberService::class)->adminSummary());
			} elseif (Schema::hasTable('kyc_waitlist_entries')) {
				$verificationStats['waitlisted'] = DB::table('kyc_waitlist_entries')->where('status', 'waiting')->count();
			}
		} catch (\Throwable $error) {
			report($error);
			// Keep the main dashboard available if verification storage is not ready.
		}

		$stats = [
			'total_users' => $this->safeCount('res_users'),
			'active_users' => $this->safeCount('res_users', function ($query) {
				$query->where('is_active', 1);
			}),
			'inactive_users' => $this->safeCount('res_users', function ($query) {
				$query->where('is_active', 0);
			}),
			'new_users_today' => $this->safeCount('res_users', function ($query) use ($today) {
				$query->where('created_at', '>=', $today);
			}),
			'total_wallet_balance' => $this->safeSum('tbluserwallet', 'totaltoken'),
			'total_tasks' => $this->safeCount('tasks'),
			'claimed_tasks' => $this->safeCount('task_claimeds'),
			'pending_boost_purchases' => $this->safeCount('user_boosts', function ($query) {
				$query->where('payment_status', 'pending');
			}),
			'pro_users' => $subscriptionStats['total_pro_users'],
			'subscription_revenue_month' => $subscriptionStats['revenue_this_month'],
			'pending_delete_requests' => $this->safeCount('delete_account_request'),
		];

		$recentUsers = $this->safeGet('res_users', function ($query) {
			$query->select('id', 'username', 'name', 'email', 'is_active', 'created_at')
				->orderBy('id', 'desc');
		}, 6);

		$topWallets = collect();
		try {
			if (DB::getSchemaBuilder()->hasTable('tbluserwallet')) {
				$topWallets = DB::table('tbluserwallet')
					->leftJoin('res_users', 'tbluserwallet.userid', '=', 'res_users.id')
					->select('tbluserwallet.userid', 'tbluserwallet.totaltoken', 'res_users.name', 'res_users.username', 'res_users.email')
					->orderByDesc('tbluserwallet.totaltoken')
					->limit(6)
					->get();
			}
		} catch (\Throwable $error) {
			$topWallets = collect();
		}

		$quickActions = [
			['label' => 'Manage Users', 'url' => url('admin/users'), 'icon' => 'fa-users', 'tone' => 'primary'],
			['label' => 'Send Push', 'url' => url('admin/add/push-notification'), 'icon' => 'fa-paper-plane', 'tone' => 'info'],
			['label' => 'Add Task', 'url' => url('admin/add/tasks'), 'icon' => 'fa-list-check', 'tone' => 'success'],
			['label' => 'Boost Plans', 'url' => url('admin/manage/boost-plans'), 'icon' => 'fa-bolt', 'tone' => 'success'],
			['label' => 'Boost Purchases', 'url' => url('admin/manage/boost-purchases'), 'icon' => 'fa-receipt', 'tone' => 'warning'],
			['label' => 'Subscriptions', 'url' => url('admin/subscriptions'), 'icon' => 'fa-crown', 'tone' => 'primary'],
			['label' => 'App Maintenance', 'url' => url('admin/manage/app-maintenance'), 'icon' => 'fa-screwdriver-wrench', 'tone' => 'warning'],
			['label' => 'View Logs', 'url' => url('admin/logs'), 'icon' => 'fa-file-lines', 'tone' => 'secondary'],
		];

		return view('admin/dashboard',compact('admindetails', 'stats', 'recentUsers', 'topWallets', 'quickActions', 'subscriptionStats', 'verificationStats'));

	}

	public function subscriptions(Request $request)
	{
		$status = (string) $request->query('status', '');
		$subscriptions = collect();
		$stats = $this->subscriptionOverview();

		if (DB::getSchemaBuilder()->hasTable('revenuecat_subscriptions')) {
			$query = DB::table('revenuecat_subscriptions')
				->leftJoin('res_users', 'revenuecat_subscriptions.user_id', '=', 'res_users.id')
				->select(
					'revenuecat_subscriptions.*',
					'res_users.username',
					'res_users.name as user_name',
					'res_users.email',
					'res_users.is_pro'
				)
				->orderByDesc('revenuecat_subscriptions.last_event_at');

			if ($status !== '') {
				$query->where('revenuecat_subscriptions.status', $status);
			}

			$subscriptions = $query->paginate(20);
		}

		return view('subscriptions/index', compact('subscriptions', 'stats', 'status'));
	}
	/*

	|======================================|
	|              Users                   |
	|======================================|

	*/

// 	public function userview(Request $request){
// 		$customer_data = DB::table('res_users')->paginate(10);
// 		// ->limitToFirst(10)->orderByKey()
// 		//echo "<pre>"; print_r($customer_data);die();
//         return view('users/index',compact('customer_data'));
//     }
    
    public function userview(Request $request) {
        $search = trim((string) $request->query('search', ''));

    // Validate the search input
    $request->validate([
            'search' => 'nullable|string|max:120',
    ]);

        $customer_data = DB::table('res_users as u')
            ->leftJoin('country as c', 'c.code', '=', 'u.country')
            ->select('u.*', 'c.countryname as country_name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('u.id', 'like', '%' . $search . '%')
                          ->orWhere('u.name', 'like', '%' . $search . '%')
                          ->orWhere('u.username', 'like', '%' . $search . '%')
                          ->orWhere('u.email', 'like', '%' . $search . '%');
                });
        })
        ->paginate(10);

        $customer_data->appends($request->query());

     return view('users/index', compact('customer_data', 'search'));
}


	public function userstatus(Request $request){
		$status = $request->status;
		$id = $request->id;
		
		if($status=='0'){
			$newstatus='1';
		}else{
			$newstatus='0'; 
		}	
		
		$data=[
			"status"=>$newstatus,
			];
		$this->AdminModel->user_status_update($id,$data);
	}
	
	public function isActive(Request $request){
		$data = $request->validate([
			'userid' => 'required|integer|min:1',
			'isActive' => 'required|in:0,1',
		]);
		$newStatus = $data['isActive'] === '0' ? 1 : 0;
		$this->AdminModel->user_is_active_update($data['userid'], ['is_active' => $newStatus]);
		return response()->json(['success' => true, 'is_active' => $newStatus]);
	}

	public function deleteUsers(Request $request, int $id){
		$response = $this->AdminModel->deleteUsers($id);
		if (!$response) {
			return back()->with('error', 'User could not be deleted. It may already have been removed.');
		}
		return back()->with('success', 'User deleted successfully.');
	
	}

	public function transactionslist(){

		$transactions_data = DB::table('tbluserwallet')
		->leftJoin('res_users', 'tbluserwallet.userid', '=', 'res_users.id')
		->select('tbluserwallet.*','res_users.name')
		->paginate(10);
        return view('users/transactionslist',compact('transactions_data'));

	}



	public function managebanner(){

		$expiresAt = new \DateTime('tomorrow');

        $imageReference = app('firebase.storage')->getBucket()->object('Banners/demo_img.png');
        if ($imageReference->exists()) {
          $image = $imageReference->signedUrl($expiresAt);
		  //dd($imageReference);
        } else {
          $image = null;
        }
		// $bucket = app('firebase.storage')->getBucket("Banners");
		// $uploadedFile = $bucket->upload(fopen($source, 'r'));
		// dd($bucket);

		$banners = DB::table('banners')->get();
        return view('banner/manage',compact('banners'));

    }



	public function addbanner(){
        return view('banner/add');

	}

	public function submitbanner(Request $request){
		$input = $request->all();
        $request->validate([
          'image' => 'required',
		  'title' => 'required',
		  'paragraph' => 'required',
		  'date' =>'required',

        ]);

        
        $image = $request->file('image'); 
		$title = $request->title;
		$paragraph = $request->paragraph;
		$date = $request->date;
		
		$imageInstance = Image::make($image);
        $imageWidth = $imageInstance->width();  
        $imageHeight = $imageInstance->height();
    
        // Check if the image's dimensions are 1280x490
        if ($imageWidth != 1280 || $imageHeight != 490) {
            return back()->with('message', 'The image must be exactly 1280x490 pixels.');
        }
		
        $firebase_storage_path = 'Banners/';
        $name     = $image->getClientOriginalName();
        $localfolder = public_path('banners') .'/';
        $extension = $image->getClientOriginalExtension();
		$file      = $name;
		
        if ($image->move($localfolder, $file)) {
			$data['image']= $name;
			$doc=$data['image'];
			$current_date_time = now();
			$data = array(
				'image' => $doc,
				'title' => $title,
				'paragraph' => $paragraph,
				'date' => $date,
				'link' => $request->link,
				"created_at"=> $current_date_time,
				"deleted_at"=> '',
			   ) ;
			//    echo "<pre>"; print_r($data);die();
		  DB::table('banners')->insert($data);

          return back()->with('message', 'Banner Uploaded');

        }else{
			
			return back()->with('message', 'Something went wrong');
		}

        return back()->with('message', 'Banner Uploaded');

    }


	public function deleteBanner(Request $request){
		$id = $request->id;
		$response = $this->AdminModel->deleteBanner($id);
	 	return back()->with('error','Banner Deleted successfully');
	
	}
	

	/*
    |====================================================|
    |===============add_coin_to_multi_users==============|
    |====================================================|
    */


	public function add_coin_to_multi_users(Request $request){
		$users = DB::table('tbluserwallet')->orderBy('id','DESC')
		->paginate(10);
		// if ($users->count() > 0) {
        //     foreach($users as $value){
		// 		echo "<pre>"; print_r($value->id);die();
		// 	}
		//}
		//echo "<pre>"; print_r($users);die();
		return view('users/sendtokentomultiuser', compact('users'));
	}

// 	public function sendCoin(Request $request){
// 		$users = DB::table('tbluserwallet')->get();
// 		$postData = [
// 					'totaltoken'=>$request->token,         
// 				];
// 		if ($users->count() > 0) {
// 				$tok = $request->token;
// 				foreach ($users as $user) {
// 					DB::table('tbluserwallet')
// 						->where('id', $user->id)
// 						->update(['totaltoken' =>$user->totaltoken + $tok]);
// 				}
// 				return redirect()->back()->with('success', 'Send Coin to multi Users Successfully');
				
//         }
// 	}

    public function sendCoin(Request $request){
        $users = DB::table('tbluserwallet')->get();
        $postData = [
            'totaltoken'=>$request->token,         
        ];
    
        if ($users->count() > 0) {
            $tok = $request->token;
            foreach ($users as $user) {
               
                // DB::table('tbluserwallet')
                //     ->where('id', $user->id)
                //     ->update(['totaltoken' =>$user->totaltoken + $tok]);
                    $type = 'coin';
                    $username= $user->name;
                //$userInstance = ResUser::where('id',$user->userid)->first();
                 $userInstance = ResUser::find($user->userid);
                 //echo "<pre>"; print_r($userInstance);die;
                 $refferal_name = $userInstance->name;
                $userInstance->notify(new CoinsSentNotification($tok,$type,$username,$refferal_name));
            }
    
            return redirect()->back()->with('success', 'Send Coin to multi Users Successfully');
        }
    }

		/*
    |====================================================|
    |===============add_coin_to_Single_users==============|
    |====================================================|
    */


	public function add_coin_to_single_users(Request $request){
		$users = DB::table('tbluserwallet')->orderBy('id','DESC')
		->paginate(10);
		// if ($users->count() > 0) {
        //     foreach($users as $value){
		// 		echo "<pre>"; print_r($value->id);die();
		// 	}
		//}
		//echo "<pre>"; print_r($users);die();
		return view('users/sendtokentosingleuser', compact('users'));
	}


  
/////Manage news 


	public function managenews(){

		$news = DB::table('custom_news')->get();
        return view('news/manage',compact('news'));

    }



	public function addnews(){
        return view('news/add');

	}

	public function submitnews(Request $request){
		$input = $request->all();
		//echo "<pre>"; print_r($input);
        $request->validate([
          'image' => 'required',
		  'title' => 'required',
		  'description' => 'required',
		  'categories' =>'required',

        ]);

        
        $image = $request->file('image'); //image file from frontend
		$title = $request->title;
		$paragraph = $request->description;
		$date = $request->date;
        $firebase_storage_path = 'News/';
        $name     = $image->getClientOriginalName();
        $localfolder = public_path('news') .'/';
        $extension = $image->getClientOriginalExtension();
		$file      = $name;
		//$path = Storage::putFile('image', $request->image);
		// echo "<pre>"; print_r($localfolder);
        if ($image->move($localfolder, $file)) {
			$data['image']= $name;
			$doc=$data['image'];
			$current_date_time = now();
			$data = array(
				'image' => $doc,
				'title' => $title,
				'description' => $paragraph,
				'categories' =>$request->categories,
				"created_at"=> $current_date_time,
				'link'=>$request->link,
				"deleted_at"=> '',
			   ) ;
			//    echo "<pre>"; print_r($data);die();
		  DB::table('custom_news')->insert($data);

          return back()->with('message', 'News Uploaded');

        }else{
			
			return back()->with('message', 'Something went wrong');
		}

        return back()->with('message', 'News Uploaded');

    }


	public function deletenews(Request $request){
		$id = $request->id;
		$response = $this->AdminModel->deletenews($id);
	 	return back()->with('error','News Deleted successfully');
	
	}
	
	
	/////Manage whitepaper 


	public function managewhitepaper(){

		$whitepaper = DB::table('tblwhitepaper')->get();
        return view('whitepaper/manage',compact('whitepaper'));

    }



	public function addwhitepaper(){
        return view('whitepaper/add');

	}

	public function submitwhitepaper(Request $request)
    {
        // Validate input fields
        $request->validate([
            'whitepaper_doc' => 'required|file', // Ensure the file is uploaded
            'title' => 'required|string|max:255', // Ensure the title is present
        ]);
    
        // Get the uploaded file and title from the request
        $image = $request->file('whitepaper_doc'); 
        $title = $request->input('title');
        
        // Check if the image is not null
        if ($image) {
            // Get file details
            $name = $image->getClientOriginalName();
            $localfolder = public_path('whitepaper_doc') . '/';
            $extension = $image->getClientOriginalExtension();
            $file = $name;
    
            // Ensure the folder exists
            if (!file_exists($localfolder)) {
                mkdir($localfolder, 0777, true); // Create the folder if not exists
            }
    
            // Move the file to the specified folder
            if ($image->move($localfolder, $file)) {
                // Prepare data for insertion
                $data = [
                    'whitepaper_doc' => $name,
                    'title' => $title,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
    
                // Insert the data into the database
                DB::table('tblwhitepaper')->insert($data);
    
                return back()->with('message', 'Whitepaper Uploaded');
            } else {
                return back()->with('message', 'File upload failed');
            }
        } else {
            return back()->with('message', 'No file uploaded');
        }
    }



// 	public function deletewhitepaper(Request $request){
// 		$id = $request->id;
// 		$response = $this->AdminModel->deletewhitepaper($id);
// 	 	return back()->with('error','Whitepaper Deleted successfully');
	
// 	}
   


/////Manage news 


	public function manageapk(){

		$news = DB::table('app_apk')->get();
        return view('apk/manage',compact('news'));

    }



	public function addapk(){
        return view('apk/add');

	}

	public function submitapk(Request $request){
		$input = $request->all();
		//echo "<pre>"; print_r($input);
        $request->validate([
          'file' => 'required',
		  'title' => 'required',
		 

        ]);

        
        $image = $request->file('file'); //image file from frontend
		$title = $request->title;
        
        $name     = $image->getClientOriginalName();
        $localfolder = public_path('apk') .'/';
        $extension = $image->getClientOriginalExtension();
		$file      = $name;
		//$path = Storage::putFile('image', $request->image);
		// echo "<pre>"; print_r($localfolder);
        if ($image->move($localfolder, $file)) {
			$data['file']= $name;
			$doc=$data['file'];
			$current_date_time = now();
			$data = array(
				'file' => $doc,
				'title' => $title,
				
			   ) ;
			//    echo "<pre>"; print_r($data);die();
		  DB::table('app_apk')->insert($data);

          return back()->with('message', 'APK Uploaded');

        }else{
			
			return back()->with('message', 'Something went wrong');
		}

        return back()->with('message', 'APK Uploaded');

    }


	public function deleteapk(Request $request){
		$id = $request->id;
		$response = $this->AdminModel->deleteapk($id);
	 	return back()->with('error','APK Deleted successfully');
	
	}
	
    /*    Games Category */
 
	public function ManageGameCategory(){

		$game_category = DB::table('game_category')->get();
        return view('game_category/manage',compact('game_category'));

    }



	public function addGameCategory(){
	    $id='';
    	$response='';
    	$gamecategory= $this->AdminModel->GameCategoryName();
        return view('game_category/add',compact('id','response','gamecategory'));

	}

	public function submitGameCategory(Request $request){
		$input = $request->all();
        $request->validate([
		  'title' => 'required',
        ]);
		$title = $request->title;
        if ($title) {
			$data = array(
				'title' => $title,
			   ) ;
		  DB::table('game_category')->insert($data);

          return back()->with('message', 'Game Category Uploaded');

        }else{
			
			return back()->with('message', 'Something went wrong');
		}

        return back()->with('message', 'Game Category Uploaded');

    }

	public function deleteGameCategory(Request $request){
		$id = $request->id;
		$response = $this->AdminModel->deleteGameCategory($id);
	 	return back()->with('error','Game Category Deleted successfully');
	
	}
 
   /*    Games */
 
	public function ManageGame(){

    	$games = DB::table('games')
        ->leftJoin('game_category', 'games.game_category_id', '=', 'game_category.id')
         ->select('games.*', DB::raw('game_category.title as gametitle'))
        ->paginate(10);
    
        //dd($games);
        return view('game/manage',compact('games'));

    }

	public function addGame(Request $request){
	    $id=$request->id;
    	$response='';
    	if(!empty($id)){
    		$response=DB::table('games')->where('id',$id)->first();
    	}
	    $game =DB::table('games')->get();
    	$gamecategory= $this->AdminModel->GameCategoryName();
        return view('game/add',compact('id','response','gamecategory','game'));

	}

	public function submitGame(Request $request){
		$input = $request->all();
        $request->validate([
          'logo' => 'required',
		  'title' => 'required',
		 'game_category_id'=>'required',
		 'url'=>'required',

        ]);

        
        $image = $request->file('logo'); 
		$title = $request->title;
        
        $name     = $image->getClientOriginalName();
        $localfolder = public_path('game') .'/';
        $extension = $image->getClientOriginalExtension();
		$file      = $name;
        if ($image->move($localfolder, $file)) {
			$data['logo']= $name;
			$doc=$data['logo'];
			$current_date_time = now();
			$data = array(
				'logo' => $doc,
				'title' => $title,
				'game_category_id'=>$request->game_category_id,
				'url'=>$request->url,
				
			   ) ;
			//    echo "<pre>"; print_r($data);die();
		  DB::table('games')->insert($data);

          return back()->with('message', 'Game Uploaded');

        }else{
			
			return back()->with('message', 'Something went wrong');
		}

        return back()->with('message', 'Game Uploaded');

    }

	public function deleteGame(Request $request){
		$id = $request->id;
		$response = $this->AdminModel->deleteGame($id);
	 	return back()->with('error','Game Deleted successfully');
	
	}

    

	/*-----------------------------------------------------------

	------------------Manage Task---------------------------

	-------------------------------------------------------------*/



	public function manageTask(Request $request,$id=null){

	

	$search='';

	$search=$request->search;

	$services = $this->AdminModel->manage_documents($search,$id);

	//echo "<pre>";print_r($services); die();

	return view('admin/manage_documents/managedocuments',compact('id','services','search'));

	

	}



	

public function addTask(Request $request){

	$response='';

	$id= $request->id;

	return view('admin/manage_documents/adddocuments',compact('id','response'));

}





public function taskSubmit(Request $request){

	

	//$image = array();

	$docid=$request->docid;

	$projectid=$request->projectid;

	if($request->file('description')){

		$file= $request->file('description');

		$filename= $projectid.date('-Y-m-d-H-i-s-').$file->getClientOriginalName();

		$file-> move(public_path('public/uploads/Properties_documents/'.$projectid), $filename);

		$data['description']= $filename;

		$doc=$data['description'];

		$current_date_time = date('Y-m-d H:i:s');



			$data = array(

				'description' => $doc,

				'title'=>$request->title,

				'projectid' =>$request->projectid,

				"dateadded"=> $current_date_time,

			   ) ;

//print_r($data);

			if(empty($docid)){

			DB::table('tblprojectsdocs')->insert($data);

			}else{

				DB::table('tblprojectsdocs')->where('docid',$docid)->update($data);

			}

		}

	return redirect('/admin/manage_projects')->with('message', 'More Documents Updated');

	   

}



public function EditDocuments(Request $request ,$docid){

	$docid=$request->id;

	$response='';

	if(!empty($docid)){

		$response= $this->AdminModel->EditDocuments($docid);

	}	

	return view('admin/manage_documents/adddocuments',compact('docid','response'));

} 



public function deleteTask(Request $request){

	$id = $request->id;

	$response = $this->AdminModel->deleteDocuments($id);

	

 return back()->with('error',' Deleted successfully');

}



public function Taskstatus(Request $request){
	$status = $request->status;
	$id = $request->id;
	if($status=='D'){
		$newstatus='A';
	}else{
		$newstatus='D'; 
	}	
	$data=[
		"status"=>$newstatus,
		];
	$this->AdminModel->documentstatus($id,$data);
}

public function AppStage(Request $request){
    $id=$request->id;
	$response='';
	if(!empty($id)){
		$response=DB::table('appstage')->where('id',$id)->first();
	}
	$appstage =DB::table('appstage')->get();
    return view('appstage/index',compact('response','appstage'));
}

public function submitStage(Request $request){
		$input = $request->all();
	
        $validator = Validator::make($request->all(), [
            'stage' => 'required',
		  'miningrate' => 'required',
        ]);
        
        $data=$_POST;
		if($request->id){
			$id=$request->id;
		}
		
		$stage = $request->stage;
		$miningrate = $request->miningrate;
       unset($data['_token']);
		unset($data['id']);
       if(!empty($id)){
			DB::table('appstage')->where('id',$id)->update($data);
		 return back()->with('success','App stage updated successfully');
		
		}else{
			DB::table('appstage')->insert($data);
			 return back()->with('message', 'App stage add successfully');
		}
		
		
//         if (!$validator->fails()) {
// 			$current_date_time = now();
// 			$data = array(
// 				'stage' => $stage,
// 				'miningrate' => $miningrate,
// 				"created_at"=> $current_date_time,
// 				"updated_at"=> $current_date_time,
// 			   ) ;
		
// 		  DB::table('appstage')->insert($data);

//           return back()->with('message', 'App stage add successfully');

//         }else{
			
// 			return back()->with('message', 'Something went wrong');
// 		}

//         return back()->with('message', 'App stage add successfully');

    }

    public function ManageAppStage(Request $request){
        $appstage = DB::table('appstage')->get();
        return view('appstage/manage',compact('appstage'));
    }
    
    public function deleteAppStage(Request $request){
		$id = $request->id;
		$response = DB::table('appstage')->where('id',$id)->delete();
	 	return back()->with('error','Banner Deleted successfully');
	
	}
	
	 public function ManageAppVersion(Request $request){
        $query = DB::table('app_version');
        if (Schema::hasColumn('app_version', 'latest_version_code')) {
            $query->orderByDesc('latest_version_code');
        }

        $appversion = $query->orderByDesc('id')->get();
        return view('appversion/manage',compact('appversion'));
    }
    
    
    public function Appversion(Request $request){
        $id=$request->id;
    	$response='';
    	if(!empty($id)){
    		$response=DB::table('app_version')->where('id',$id)->first();
    	}
    	$app_version =DB::table('app_version')->get();
        return view('appversion/index',compact('response','app_version'));
    }

    public function submitversion(Request $request){
        $id = $request->id;
        $latestVersionCode = (int) ($request->latest_version_code ?: $request->version);

        $data = [
            'title' => $request->title,
            'version' => $request->version ?: $latestVersionCode,
            'link' => $request->link ?: $request->android_link,
        ];

        $optionalFields = [
            'latest_version_code' => $latestVersionCode,
            'minimum_version_code' => (int) $request->minimum_version_code,
            'force_update' => $request->has('force_update') ? 1 : 0,
            'status' => $request->has('status') ? 1 : 0,
            'platform' => $request->platform ?: 'android',
            'release_notes' => $request->release_notes,
            'android_link' => $request->android_link ?: $request->link,
            'ios_link' => $request->ios_link,
        ];

        foreach ($optionalFields as $field => $value) {
            if (Schema::hasColumn('app_version', $field)) {
                $data[$field] = $value;
            }
        }

        if (Schema::hasColumn('app_version', 'updated_at')) {
            $data['updated_at'] = now();
        }

        if(!empty($id)){
			DB::table('app_version')->where('id',$id)->update($data);
		    return back()->with('success','App version updated successfully');
		}else{
            if (Schema::hasColumn('app_version', 'created_at')) {
                $data['created_at'] = now();
            }
			DB::table('app_version')->insert($data);
			return back()->with('message', 'App version added successfully');
		}
    
    
    }
    
    
    
    ///
    
    
	
	 public function ManageAppmaintenance(Request $request){
        $appversion = DB::table('app_maintenance')->get();
        return view('app_maintenance/manage',compact('appversion'));
    }
    
    
    public function Appmaintenance(Request $request){
        $id=$request->id;
    	$response='';
    	if(!empty($id)){
    		$response=DB::table('app_maintenance')->where('id',$id)->first();
    	}
    	$app_version =DB::table('app_maintenance')->get();
        return view('app_maintenance/index',compact('response','app_version'));
    }

    public function submitmaintenance(Request $request){
		$input = $request->all();
	
        $data=$_POST;
		if($request->id){
			$id=$request->id;
		}
		
		$stage = $request->stage;
		$miningrate = $request->miningrate;
       unset($data['_token']);
		unset($data['id']);
       if(!empty($id)){
			DB::table('app_maintenance')->where('id',$id)->update($data);
		 return back()->with('success','app_maintenance stage updated successfully');
		
		}else{
			DB::table('app_maintenance')->insert($data);
			 return back()->with('message', 'app_maintenance stage add successfully');
		}
    
    
    }
    ////
    
	public function Taskview($id){
		$row = DB::table('tbl_document')->where('id',$id)->first();
	}
	
	/////Manage Tasks 


	public function managetasks(){

		$news = DB::table('tasks')->get();
        return view('Tasks/manage',compact('news'));

    }



	public function addtasks(){
        return view('Tasks/add');

	}

    public function submittasks(Request $request)
    {
        $input = $request->all();
    
        // Validate the required fields, 'image' is not required anymore
        $request->validate([
            'title' => 'required',
            'link' => 'required',
            'coins' => 'required',
        ]);
    
        // Initialize variables
        $title = $request->title;
        $paragraph = $request->description;
        $date = $request->date;
        $taskIcon = null; // Default value if image is not provided
    
        // Check if image is provided in the request
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $name = $image->getClientOriginalName();
            $localfolder = public_path('taskicon') . '/';
            $extension = $image->getClientOriginalExtension();
            $file = $name;
    
            // Move the image to the local folder
            if ($image->move($localfolder, $file)) {
                $taskIcon = $name; // Store image name if it was successfully moved
            } else {
                // If moving the image fails, return with an error message
                return back()->with('message', 'Something went wrong while uploading the image.');
            }
        }
    
        // Get the current date and time
        $current_date_time = now();
    
        // Prepare the data array for insertion
        $data = [
            'task_icon' => $taskIcon,
            'task_name' => $title,
            'task_link' => $request->link,
            'coin_reward' => $request->coins,
            'is_featured' => $request->is_featured ?? 0,
            'created_at' => $current_date_time,
        ];
    
        // Insert the data into the database
        DB::table('tasks')->insert($data);
    
        // Return a success message
        return back()->with('message', 'Task successfully uploaded!');
    }



	public function deletetasks(Request $request){
		$id = $request->route('id') ?: $request->id;
		$response = $this->AdminModel->deleteTasks($id);
	 	return back()->with('error','Task Deleted successfully');
	
	}

    public function manageBoostPlans(Request $request)
    {
        $plans = collect();
        $stats = [
            'active_plans' => 0,
            'pending_purchases' => 0,
            'active_boosts' => 0,
        ];

        if (DB::getSchemaBuilder()->hasTable('boost_plans')) {
            $plans = DB::table('boost_plans')
                ->orderBy('sort_order')
                ->orderBy('price')
                ->get();

            $stats['active_plans'] = DB::table('boost_plans')->where('status', 1)->count();
        }

        if (DB::getSchemaBuilder()->hasTable('user_boosts')) {
            $stats['pending_purchases'] = DB::table('user_boosts')
                ->where('payment_status', 'pending')
                ->count();
            $stats['active_boosts'] = DB::table('user_boosts')
                ->where('status', 'active')
                ->where('payment_status', 'paid')
                ->count();
        }

        return view('boost_plans/manage', compact('plans', 'stats'));
    }

    public function addBoostPlan(Request $request)
    {
        $id = $request->route('id') ?: $request->id;
        $plan = null;

        if ($id && DB::getSchemaBuilder()->hasTable('boost_plans')) {
            $plan = DB::table('boost_plans')->where('id', $id)->first();
        }

        return view('boost_plans/add', compact('plan'));
    }

    public function submitBoostPlan(Request $request)
    {
        if (!DB::getSchemaBuilder()->hasTable('boost_plans')) {
            return back()->with('error', 'Run database migrations before creating boost plans.');
        }

        $request->validate([
            'name' => 'required|max:120',
            'boost_percent' => 'required|numeric|min:0|max:500',
            'duration_days' => 'required|integer|min:1|max:3650',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|max:12',
            'status' => 'required|in:0,1',
            'provider' => 'nullable|in:dodo,revenuecat,manual',
            'provider_product_id' => 'nullable|max:190',
            'provider_entitlement_id' => 'nullable|max:190',
        ]);

        $id = $request->id;
        $baseSlug = Str::slug($request->slug ?: $request->name);
        if ($baseSlug === '') {
            $baseSlug = 'boost-plan';
        }

        $slug = $baseSlug;
        $suffix = 2;
        while (DB::table('boost_plans')
            ->where('slug', $slug)
            ->when($id, function ($query) use ($id) {
                $query->where('id', '!=', $id);
            })
            ->exists()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        $data = [
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'boost_percent' => $request->boost_percent,
            'duration_days' => $request->duration_days,
            'price' => $request->price,
            'currency' => strtoupper($request->currency),
            'status' => (int) $request->status,
            'sort_order' => (int) ($request->sort_order ?: 0),
            'updated_at' => now(),
        ];

        if ($this->hasColumn('boost_plans', 'provider')) {
            $data['provider'] = $request->input('provider') ?: null;
        }

        if ($this->hasColumn('boost_plans', 'provider_product_id')) {
            $data['provider_product_id'] = $request->provider_product_id;
        }

        if ($this->hasColumn('boost_plans', 'provider_entitlement_id')) {
            $data['provider_entitlement_id'] = $request->input('provider_entitlement_id') ?: null;
        }

        if ($id) {
            DB::table('boost_plans')->where('id', $id)->update($data);
            return redirect('admin/manage/boost-plans')->with('success', 'Boost plan updated successfully.');
        }

        $data['created_at'] = now();
        DB::table('boost_plans')->insert($data);

        return redirect('admin/manage/boost-plans')->with('message', 'Boost plan added successfully.');
    }

    public function deleteBoostPlan($id)
    {
        if (!DB::getSchemaBuilder()->hasTable('boost_plans')) {
            return back()->with('error', 'Boost plans table is not available.');
        }

        DB::table('boost_plans')->where('id', $id)->update([
            'status' => 0,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Boost plan disabled successfully.');
    }

    public function manageBoostPurchases(Request $request)
    {
        $purchases = collect();
        $status = $request->status ?: '';

        if (DB::getSchemaBuilder()->hasTable('user_boosts')) {
            $query = DB::table('user_boosts')
                ->leftJoin('boost_plans', 'user_boosts.boost_plan_id', '=', 'boost_plans.id')
                ->leftJoin('res_users', 'user_boosts.user_id', '=', 'res_users.id')
                ->select(
                    'user_boosts.*',
                    'boost_plans.name as plan_name',
                    'boost_plans.duration_days',
                    'res_users.username',
                    'res_users.name as user_name',
                    'res_users.email'
                )
                ->orderByDesc('user_boosts.created_at');

            if ($status !== '') {
                $query->where('user_boosts.status', $status);
            }

            $purchases = $query->limit(250)->get();
        }

        return view('boost_plans/purchases', compact('purchases', 'status'));
    }

    public function activateUserBoost($id)
    {
        if (!DB::getSchemaBuilder()->hasTable('user_boosts')) {
            return back()->with('error', 'User boosts table is not available.');
        }

        $boost = DB::table('user_boosts')
            ->leftJoin('boost_plans', 'user_boosts.boost_plan_id', '=', 'boost_plans.id')
            ->where('user_boosts.id', $id)
            ->select('user_boosts.*', 'boost_plans.duration_days', 'boost_plans.boost_percent as plan_boost_percent')
            ->first();

        if (!$boost) {
            return back()->with('error', 'Boost purchase not found.');
        }

        if ($boost->provider === 'dodo' && $boost->payment_status !== 'paid') {
            return back()->with('error', 'Dodo boosts can only be activated after a verified payment webhook.');
        }

        $durationDays = max(1, (int) ($boost->duration_days ?: 7));
        $boostPercent = (float) ($boost->plan_boost_percent ?: $boost->boost_percent);
        $now = Carbon::now();

        DB::table('user_boosts')->where('id', $id)->update([
            'boost_percent' => $boostPercent,
            'starts_at' => $now,
            'ends_at' => $now->copy()->addDays($durationDays),
            'status' => 'active',
            'payment_status' => 'paid',
            'updated_at' => $now,
        ]);

        return back()->with('success', 'User boost activated successfully.');
    }

    public function cancelUserBoost($id)
    {
        if (!DB::getSchemaBuilder()->hasTable('user_boosts')) {
            return back()->with('error', 'User boosts table is not available.');
        }

        DB::table('user_boosts')->where('id', $id)->update([
            'status' => 'cancelled',
            'payment_status' => 'cancelled',
            'updated_at' => now(),
        ]);

        return back()->with('success', 'User boost cancelled successfully.');
    }

    public function referralSettings(Request $request)
    {
        $settings = collect();

        if (DB::getSchemaBuilder()->hasTable('referral_settings')) {
            $settings = DB::table('referral_settings')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        return view('referrals/settings', compact('settings'));
    }

    public function submitReferralSettings(Request $request)
    {
        if (!DB::getSchemaBuilder()->hasTable('referral_settings')) {
            return back()->with('error', 'Run database migrations before updating referral settings.');
        }

        $submittedSettings = $request->input('settings', []);
        $settings = DB::table('referral_settings')->get();

        foreach ($settings as $setting) {
            if (!array_key_exists($setting->setting_key, $submittedSettings)) {
                continue;
            }

            $value = trim((string) $submittedSettings[$setting->setting_key]);

            if ($setting->input_type === 'number') {
                $value = (string) max(0, (float) $value);
            }

            if ($setting->setting_key === 'referral_code_prefix') {
                $value = strtoupper(preg_replace('/[^A-Z0-9]/', '', $value));
                $value = $value ?: 'BRC';
            }

            DB::table('referral_settings')
                ->where('id', $setting->id)
                ->update([
                    'setting_value' => $value,
                    'updated_at' => now(),
                ]);
        }

        return back()->with('success', 'Referral settings updated successfully.');
    }

    private function referralMissionMetricOptions(): array
    {
        return [
            'direct_referrals' => 'Direct referrals',
            'active_referrals_today' => 'Active referrals today',
            'pinged_today' => 'Referrals pinged today',
            'inactive_referrals' => 'Inactive referrals',
            'referral_rewards' => 'Total referral rewards',
        ];
    }

    public function manageReferralMissions(Request $request)
    {
        $missions = collect();
        $metrics = $this->referralMissionMetricOptions();

        if (DB::getSchemaBuilder()->hasTable('referral_missions')) {
            $missions = DB::table('referral_missions')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        return view('referrals/missions', compact('missions', 'metrics'));
    }

    public function addReferralMission(Request $request)
    {
        $mission = null;
        $metrics = $this->referralMissionMetricOptions();
        $id = $request->route('id') ?: $request->id;

        if ($id && DB::getSchemaBuilder()->hasTable('referral_missions')) {
            $mission = DB::table('referral_missions')->where('id', $id)->first();
        }

        return view('referrals/mission_form', compact('mission', 'metrics'));
    }

    public function submitReferralMission(Request $request)
    {
        if (!DB::getSchemaBuilder()->hasTable('referral_missions')) {
            return back()->with('error', 'Run database migrations before creating referral missions.');
        }

        $request->validate([
            'title' => 'required|max:160',
            'metric' => 'required',
            'target_count' => 'required|integer|min:1',
            'reward_amount' => 'required|numeric|min:0',
            'frequency' => 'required|in:once,daily',
            'status' => 'required|in:0,1',
        ]);

        $metrics = $this->referralMissionMetricOptions();
        if (!array_key_exists($request->metric, $metrics)) {
            return back()->with('error', 'Unsupported mission metric.');
        }

        $id = $request->id;
        $baseSlug = Str::slug($request->slug ?: $request->title);
        $baseSlug = $baseSlug ?: 'referral-mission';
        $slug = $baseSlug;
        $suffix = 2;

        while (DB::table('referral_missions')
            ->where('slug', $slug)
            ->when($id, function ($query) use ($id) {
                $query->where('id', '!=', $id);
            })
            ->exists()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        $data = [
            'title' => $request->title,
            'slug' => $slug,
            'description' => $request->description,
            'metric' => $request->metric,
            'target_count' => (int) $request->target_count,
            'reward_amount' => (float) $request->reward_amount,
            'frequency' => $request->frequency,
            'status' => (int) $request->status,
            'sort_order' => (int) ($request->sort_order ?: 0),
            'updated_at' => now(),
        ];

        if ($id) {
            DB::table('referral_missions')->where('id', $id)->update($data);
            return redirect('admin/referral/missions')->with('success', 'Referral mission updated successfully.');
        }

        $data['created_at'] = now();
        DB::table('referral_missions')->insert($data);

        return redirect('admin/referral/missions')->with('message', 'Referral mission added successfully.');
    }

    public function deleteReferralMission($id)
    {
        if (!DB::getSchemaBuilder()->hasTable('referral_missions')) {
            return back()->with('error', 'Referral missions table is not available.');
        }

        DB::table('referral_missions')->where('id', $id)->update([
            'status' => 0,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Referral mission disabled successfully.');
    }
	
	public function addnotification(){
        return redirect()->route('admin.email-campaigns.index');

	}
	
// 	public function submitnotification(){
//         return view('Tasks/add');

// 	}
	
	public function addPushnotification(){
        return view('admin/notifications/addpushnotification');

	}
	
// 	public function submitPushnotification(Request $request){
// 	    $subject=$request->pn_subject;
// 	    $body=$request->pn_body;
	    
// 	    $userDeviceToken='dzhfUhQyTiSAORF71HnDza:APA91bHJwrKhCa8gYoUmgmPHxCWOnNw34wXie3KnvS5XiZLG00N-ufJE-ka1Bh4WRyVSoWwZbaepwyCLVlv7dTBdy25iYFr8CHgHxPklwSnW6_AVj4x0eWk';
//         $SentPushNotification=sendPushNotification($userDeviceToken);
//         // $SentPushNotification=sendPushNotificationNew($userDeviceToken);
//         dd($SentPushNotification);
//         return back()->with('success','Notification sent successfully');

// 	}

// // $sentPushNotification = sendPushNotificationNew($userDeviceToken, $subject, $body);

    public function submitPushnotification(Request $request){
        $subject = $request->pn_subject;
        $body = $request->pn_body;
        $url='';
        if ($request->hasFile('pn_image')) {
            $image = $request->file('pn_image');
            $name = $image->getClientOriginalName() . now();
            $localfolder = public_path('pushnotification') . '/';
            $extension = $image->getClientOriginalExtension();
            $file = $name;
            if ($image->move($localfolder, $file)) {
                $url = asset('pushnotification/' . $name);
            }
        }

        $users = ResUser::select('id', 'device_id')
            ->whereNotNull('device_id')
            ->where('device_id', '!=', '')
            ->get();

        foreach ($users as $pushUser) {
            SendPushNotificationJob::dispatch(
                $pushUser->device_id,
                $pushUser->id,
                $subject,
                $body,
                [
                    'type' => 'PUSH_NOTIFICATION',
                    'image' => $url,
                ]
            );
        }

        return back()->with('success', 'Notification queued for ' . $users->count() . ' users.');
    }
    


    private function sendNewPushNotification($data, $firebaseProjectId='blockrium-b3a41',$userDeviceToken)
    {
       
        $url = str_replace('{projectID}', $firebaseProjectId, env('FIREBASE_PUSH_NOTIFICATION_V1_URL'));

    
        $notification = [
            'message' => [
                'to' => $userDeviceToken,
                'notification' => [
                    'title' => 'Test',
                    'body' => 'Test',
                ],
                'data' => [
                    'click_action' => '',
                    'btn_label' =>  '',
                    "user_id" => '',
                    "category" => '',
                    "notificationId" =>'',
                ],
            ]
        ];
    
        $accessToken = $this->getFirebaseAccessToken();
        $payload = json_encode($notification);
        $curlParams = [
            "POST" => $payload,
            "CURLOPT_HEADER" => false,
            "REQUEST_HEADER" => [
                "Authorization" => "Bearer " . $accessToken,
                "Content-Type" => "application/json"
            ]
        ];

        $response = commonCURLRequest($url, $curlParams);
    
    
        return $response; 
    }
    
    
    private function getFirebaseAccessTokenDB() {
        $accessToken = GgToken::where('type', 1)->first();
        if(!empty($accessToken)){
            return $accessToken->access_token;
        }
        return '';
    }

    private function getUserTokenDB() {
        $deviceToken = ResUser::select('device_id')->where('device_id','!=', null)->get();
        if(!empty($deviceToken)){
            return $deviceToken;
        }
        return '';
    }

    
    private function getFirebaseAccessToken(){
        $serviceAccount = env('FIREBASE_CREDENTIALS_PATH');
        $accessToken = $this->generateFirebaseAccessToken($serviceAccount);
        return $accessToken;
    }


    private function generateFirebaseAccessToken($serviceAccount)
    {
        $firebaseCredentials = json_decode(file_get_contents(($serviceAccount)), true);
    
        $client = new Google_Client();
        $client->setAuthConfig($firebaseCredentials);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $token = $client->fetchAccessTokenWithAssertion();
        return $token['access_token'];
    }

	public function Logshow($lines = null)
    {
        // Define the path of the Laravel log file
        $logFile = storage_path('logs/laravel.log');

        // Check if the log file exists
        if (!File::exists($logFile)) {
            return response('Log file does not exist.', 404);
        }

        // Get the log file contents
        $logContent = File::get($logFile);
        $logLines = explode("\n", $logContent);  // Split the content into an array of lines

        // Determine how many lines to display
        $totalLines = count($logLines);

        // Default to the last 100 lines
        if ($lines === 'all') {
            $linesToShow = $logLines;
        } else {
            $lines = (int) $lines;
            if ($lines <= 0) {
                $lines = 100; // Default to 100 if the value is invalid
            }

            // Get the last 'n' lines from the log file
            $linesToShow = array_slice($logLines, max(0, $totalLines - $lines));
        }

        // Pass the lines to the view
        return view('logs.view', compact('linesToShow', 'lines'));
    }
	

}
