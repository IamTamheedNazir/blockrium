<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request; 
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\HomeModel;
use App\Models\AdminModel;
use Session;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Library\Encryption;
use ZipArchive;
use App\Http\Controllers\Response;
use Illuminate\Support\Facades\URL;
use Mail;
use PDF;
//date_default_timezone_set("Asia/Kolkata");
class HomeController extends BaseController
{
	use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
	public function __construct(HomeModel $HomeModel,AdminModel $AdminModel, Encryption $Encryption) {
        $this->HomeModel = $HomeModel;
        $this->AdminModel = $AdminModel;
        $this->Encryption = $Encryption;	
    }
	
	public function home(){
	    
		return view('frontend/index');	
		
	
		
	}
	public function about(){
	
		return view('frontend/about');	
		
	}
	public function business(){
	
		return view('frontend/bussiness');	
		
	}
	
	public function RequestDelete(){
	    return view('deleteacount');
	}
	public function PrivacyPolicy(){
	    return view('privacy_policy');
	}
	public function AcountRequestDelete(Request $request){
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $wantsJson = $request->expectsJson() || $request->ajax();

        try {
            $checkUserData = DB::table('res_users')->where('email', $request->email)->first();

            if (!$checkUserData || !Hash::check($request->password, $checkUserData->password)) {
                $message = 'Please enter valid account credentials.';
                return $wantsJson
                    ? response()->json(['status' => 0, 'message' => $message], 422)
                    : back()->with('error', $message);
            }

            $requestData = [
                'userid' => $checkUserData->id,
            ];

            if (Schema::hasColumn('delete_account_request', 'created_at')) {
                $requestData['created_at'] = now();
            }

            if (Schema::hasColumn('delete_account_request', 'updated_at')) {
                $requestData['updated_at'] = now();
            }

            $alreadyRequested = DB::table('delete_account_request')
                ->where('userid', $checkUserData->id)
                ->exists();

            if (!$alreadyRequested) {
                DB::table('delete_account_request')->insert($requestData);
            }

            DB::table('res_users')->where('id', $checkUserData->id)->update(['is_active' => 0]);

            $message = 'Account deletion request submitted successfully. We will process it within 3 working days.';
            return $wantsJson
                ? response()->json(['status' => 1, 'message' => $message])
                : back()->with('success', $message);
        } catch (\Throwable $exception) {
            \Log::error('Account deletion request failed', [
                'email' => $request->email,
                'error' => $exception->getMessage(),
            ]);

            $message = 'We could not submit the request automatically. Please email support@blockrium.com from your registered email address and include your Blockrium user ID if available.';
            return $wantsJson
                ? response()->json(['status' => 0, 'message' => $message], 500)
                : back()->with('error', $message);
        }
		
	}

	

}
