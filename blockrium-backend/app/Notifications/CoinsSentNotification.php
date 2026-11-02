<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\ResUser;
use App\Models\ResUserModel;
use Tymon\JWTAuth\Facades\JWTAuth;
use Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;
use Config;
class CoinsSentNotification extends Notification
{
    use Queueable;

   // protected $user;
    
    public $tok;
    public $username;
    public $type;
    
    public function __construct($tok,$username,$type,$refferal_name)
    {
        Config::set('jwt.user', 'App\Models\ResUser'); 
        Config::set('auth.providers.users.model', \App\Models\ResUser::class);
        $this->guard = "api_res_user";
        $this->token = $tok;
        $this->username = $username;
        $this->type = $type;
         $this->name = $refferal_name;
        
    }

    public function via($notifiable)
    {
        return ['database'];
    }
    public function type()
    {
        
        return $this->type;
    }
    public function toDatabase($notifiable)
    {
        $message = "Congratulations! {$this->token} BRC has been added to your wallet. Open Rewards to view it.";

        return [
            'type' => 'Gift Reward',
            'reward' => $this->token,
            'title' => 'BRC reward received',
            'action' => 'CLAIM_REWARDS',
            'data' => [
                'coin'=>$this->token,
                'message' => $message,
                
            ],
        ];
    }

    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}


