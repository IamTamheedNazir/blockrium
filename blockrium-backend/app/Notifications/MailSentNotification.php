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
class MailSentNotification extends Notification
{
    use Queueable;

   // protected $user;
    
    public $mailsubject;
    public $mailbody;
    public $username;
    public $type;
    
    public function __construct($username,$type,$mailsubject,$mailbody)
    {
        Config::set('jwt.user', 'App\Models\ResUser'); 
        Config::set('auth.providers.users.model', \App\Models\ResUser::class);
        $this->guard = "api_res_user";
        $this->mailsubject = $mailsubject;
        $this->username = $username;
        $this->type = $type;
        $this->mailbody = $mailbody;
        
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
            'type' => $this->type,
            'subject' => $this->mailsubject,
            'data' => [
                'type'=>$this->type,
                'username'=>$this->username,
                'message' => $this->mailbody,
                
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


