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
use Config;
class RefferalNotification extends Notification
{
    use Queueable;

   // protected $user;
    

    public function __construct($user,$username,$type,$refferal_name)
    {
        
        $this->user = $user;
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
            'type' => 'Refferal Add',
            'referral_user' => $this->user,
            'data' => [
                'message' => "$this->name! We're excited to let you know that $this->username has just joined  using your referral.",
                
            ],
        ];
    }

    public function toArray($notifiable)
    {
        return [
             $this->user = $user,
            $this->username = $username,
        ];
    }
}


