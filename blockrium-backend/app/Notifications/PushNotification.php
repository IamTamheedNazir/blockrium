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
        return [
            'type' => 'Gift Reward',
            'reward' => $this->token,
            'data' => [
                'coin'=>$this->token,
                'message' => "Dear $this->username,
                We're pleased to inform you that you've received a reward for your recent activities. You've been rewarded $this->token which has been credited to your wallet.

                Thank you for your contributions to our platform, and we hope this reward encourages you to continue using our services.

                Best regards,
                Blockrium",
                
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


