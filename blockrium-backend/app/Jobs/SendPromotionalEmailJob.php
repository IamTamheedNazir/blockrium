<?php

namespace App\Jobs;

use App\Models\ResUser;
use App\Notifications\MailSentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class SendPromotionalEmailJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    private $userId;
    private $mailSubject;
    private $mailBody;

    public function __construct($user, string $mailSubject, string $mailBody)
    {
        $this->userId = (int) $user->id;
        $this->mailSubject = $mailSubject;
        $this->mailBody = $mailBody;
    }

    public function handle()
    {
        $user = ResUser::find($this->userId);
        if (!$user || !$user->email) {
            return;
        }

        if (Schema::hasTable('admin_email_preferences')) {
            $preference = DB::table('admin_email_preferences')->where('user_id', $this->userId)->first();
            if ($preference && $preference->bulk_email_opted_out_at) {
                return;
            }
        }

        $data = [
            'username' => $user->name ?: $user->username ?: 'Blockrium member',
            'mailsubject' => $this->mailSubject,
            'mailbody' => nl2br(e($this->mailBody)),
            'unsubscribe_url' => URL::signedRoute('email.bulk-opt-out', ['userId' => $this->userId]),
        ];

        $content = view('frontend.email_templates.promotional-email', compact('data'))->render();
        Mail::send('frontend.email_templates.promotional-email', compact('data'), function ($message) use ($user, $data) {
            $message->to($user->email)->subject($data['mailsubject']);
        });

        $user->notify(new MailSentNotification(
            $data['username'],
            'Promotional Mail',
            $this->mailSubject,
            $content
        ));
    }
}
