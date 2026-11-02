<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class EmailCampaignTrackingController extends Controller
{
    public function click(string $token)
    {
        $recipient = DB::table('admin_email_campaign_recipients as r')
            ->join('admin_email_campaigns as c', 'c.id', '=', 'r.campaign_id')
            ->where('r.tracking_token', $token)
            ->where('r.status', 'sent')
            ->select('r.id', 'c.action_url')
            ->first();
        abort_unless($recipient, 404);
        abort_unless(strtolower((string) parse_url($recipient->action_url, PHP_URL_SCHEME)) === 'https', 404);

        DB::table('admin_email_campaign_recipients')->where('id', $recipient->id)->update([
            'first_clicked_at' => DB::raw('COALESCE(first_clicked_at, CURRENT_TIMESTAMP)'),
            'click_count' => DB::raw('click_count + 1'),
            'updated_at' => now(),
        ]);

        return redirect()->away($recipient->action_url);
    }

    public function open(string $token)
    {
        $updated = DB::table('admin_email_campaign_recipients')
            ->where('tracking_token', $token)
            ->where('status', 'sent')
            ->update([
                'first_opened_at' => DB::raw('COALESCE(first_opened_at, CURRENT_TIMESTAMP)'),
                'open_count' => DB::raw('open_count + 1'),
                'updated_at' => now(),
            ]);
        abort_unless($updated, 404);

        $pixel = base64_decode('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');
        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => (string) strlen($pixel),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
