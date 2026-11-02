<?php

namespace App\Http\Controllers;

use App\Services\GooglePlayBillingService;
use App\Services\IntegrationSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GooglePlayPurchaseAdminController extends Controller
{
    private $billing;
    private $settings;

    public function __construct(GooglePlayBillingService $billing, IntegrationSettingsService $settings)
    {
        $this->billing = $billing;
        $this->settings = $settings;
        $this->middleware('checklogin');
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'purpose' => 'nullable|in:kyc,boost',
            'status' => 'nullable|in:pending,paid,cancelled,expired,refunded,revoked,on_hold,failed,needs_review',
            'search' => 'nullable|string|max:120',
        ]);

        $hasPurchases = Schema::hasTable('google_play_purchases');
        $hasEvents = Schema::hasTable('google_play_rtdn_events');
        $purchases = $hasPurchases
            ? $this->billing->adminPurchases($filters)
            : collect();

        $stats = [
            'pending' => $hasPurchases ? DB::table('google_play_purchases')->where('status', 'pending')->count() : 0,
            'paid' => $hasPurchases ? DB::table('google_play_purchases')->where('status', 'paid')->count() : 0,
            'acknowledgement_pending' => $hasPurchases ? $this->billing->pendingAcknowledgementCount() : 0,
            'active_boosts' => $hasPurchases ? DB::table('google_play_purchases')->where('purpose', 'boost')->where('entitlement_status', 'active')->count() : 0,
            'unmatched_events' => $hasEvents ? DB::table('google_play_rtdn_events')->where('status', 'unmatched')->count() : 0,
        ];

        return view('google-play-purchases.index', [
            'purchases' => $purchases,
            'stats' => $stats,
            'filters' => $filters,
            'events' => $hasEvents ? $this->billing->rtdnEvents() : collect(),
            'configured' => $this->billing->isConfigured(),
            'purchaseTablesInstalled' => $hasPurchases && $hasEvents,
            'kycEnabled' => $this->settings->boolean('google_play_kyc_enabled', false),
            'boostEnabled' => $this->settings->boolean('boost_store_enabled', false)
                && $this->settings->boolean('boost_checkout_enabled', false),
        ]);
    }
}
