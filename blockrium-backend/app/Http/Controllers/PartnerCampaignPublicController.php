<?php

namespace App\Http\Controllers;

use App\Services\PartnerCampaignService;
use Illuminate\Http\Request;

class PartnerCampaignPublicController extends Controller
{
    private $campaigns;

    public function __construct(PartnerCampaignService $campaigns)
    {
        $this->campaigns = $campaigns;
    }

    public function redirect(Request $request, string $slug)
    {
        $destination = $this->campaigns->recordPublicClick($slug, $request);

        return redirect()->away($destination);
    }

    public function dashboard(string $token)
    {
        $data = $this->campaigns->dashboardData($token);
        abort_unless($data, 404);

        return view('partner-campaigns.dashboard', $data);
    }

    public function overview(string $token)
    {
        $data = $this->campaigns->dashboardData($token);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Dashboard link is invalid or revoked.'], 404);
        }

        return response()->json([
            'success' => true,
            'partner' => ['id' => $data['partner']->id, 'name' => $data['partner']->name],
            'summary' => $data['summary'],
        ]);
    }

    public function campaigns(string $token)
    {
        $data = $this->campaigns->dashboardData($token);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Dashboard link is invalid or revoked.'], 404);
        }

        return response()->json(['success' => true, 'campaigns' => $data['campaigns']]);
    }

    public function metrics(string $token, int $campaignId)
    {
        $data = $this->campaigns->dashboardCampaignMetrics($token, $campaignId);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Campaign is not available for this dashboard.'], 404);
        }

        return response()->json([
            'success' => true,
            'campaign' => $data['campaign'],
            'metrics' => $data['metrics'],
        ]);
    }
}
