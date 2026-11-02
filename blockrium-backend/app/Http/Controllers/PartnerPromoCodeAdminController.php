<?php

namespace App\Http\Controllers;

use App\Services\EngagementAdminService;
use App\Services\PartnerPromoCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PartnerPromoCodeAdminController extends Controller
{
    private $promoCodes;
    private $audit;

    public function __construct(PartnerPromoCodeService $promoCodes, EngagementAdminService $audit)
    {
        $this->promoCodes = $promoCodes;
        $this->audit = $audit;
        $this->middleware('checklogin');
    }

    public function campaigns()
    {
        $query = DB::table('partner_campaigns as c')
            ->leftJoin('partners as p', 'c.partner_id', '=', 'p.id')
            ->select('c.id', 'c.title', 'c.status', 'c.starts_at', 'c.ends_at', 'c.reward_amount', 'p.name as partner_name')
            ->orderByDesc('c.id');

        if (Schema::hasTable('partner_promo_batches')) {
            $batchCounts = DB::table('partner_promo_batches')
                ->select('campaign_id', DB::raw('COUNT(*) as batch_count'))
                ->groupBy('campaign_id');
            $query->leftJoinSub($batchCounts, 'promo_batches', function ($join) {
                $join->on('promo_batches.campaign_id', '=', 'c.id');
            })->addSelect(DB::raw('COALESCE(promo_batches.batch_count, 0) as batch_count'));
        } else {
            $query->addSelect(DB::raw('0 as batch_count'));
        }

        return view('partner-campaigns.promo-hub', [
            'campaigns' => $query->paginate(25),
        ]);
    }

    public function index(int $campaignId)
    {
        $campaign = DB::table('partner_campaigns')
            ->join('partners', 'partner_campaigns.partner_id', '=', 'partners.id')
            ->where('partner_campaigns.id', $campaignId)
            ->select('partner_campaigns.*', 'partners.name as partner_name')
            ->first();
        abort_unless($campaign, 404);

        $batches = $this->promoCodes->batchesForCampaign($campaignId);
        $batches->getCollection()->transform(function ($batch) {
            $batch->metrics = $this->promoCodes->batchMetrics((int) $batch->id);
            return $batch;
        });

        return view('partner-campaigns.promo-codes', [
            'campaign' => $campaign,
            'batches' => $batches,
        ]);
    }

    public function store(Request $request, int $campaignId)
    {
        $data = $request->validate([
            'code_count' => 'required|integer|min:1|max:100000',
            'reward_amount' => 'required_without:reward_amounts|numeric|gt:0|max:1000000',
            'reward_label' => 'nullable|string|max:20|regex:/^[A-Za-z0-9._-]+$/',
            'expires_at' => 'nullable|date',
            'reward_amounts' => 'nullable|string|max:2000000',
        ]);

        try {
            $data['reward_label'] = $data['reward_label'] ?? 'BRC';
            if (!empty($data['reward_amounts'])) {
                $rewards = preg_split('/[\s,;]+/', trim($data['reward_amounts']), -1, PREG_SPLIT_NO_EMPTY);
                if (count($rewards) !== (int) $data['code_count']) {
                    throw new RuntimeException('Variable rewards must contain exactly one amount per code.');
                }
                $data['rewards'] = $rewards;
            }

            $batch = $this->promoCodes->createBatch($campaignId, $data, $this->adminId());
            $this->audit->recordAdminAudit($this->adminId(), 'create', 'partner_promo_batch', (int) $batch->id, [
                'campaign_id' => $campaignId,
                'batch_reference' => $batch->batch_reference,
                'code_count' => (int) $batch->code_count,
                'total_reward_amount' => (float) $batch->total_reward_amount,
            ]);

            return back()->with('success', 'Promo-code batch generated successfully. You can now download the CSV.');
        } catch (Throwable $error) {
            return back()->withInput()->withErrors(['promo_codes' => $error->getMessage()]);
        }
    }

    public function export(int $batchId)
    {
        try {
            $result = $this->promoCodes->exportCsv($batchId, $this->adminId());
            $this->audit->recordAdminAudit($this->adminId(), 'export', 'partner_promo_batch', $batchId, [
                'sha256' => $result['sha256'],
            ]);

            return response()->download($result['path'], $result['filename'], [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'X-Export-SHA256' => $result['sha256'],
            ])->deleteFileAfterSend(true);
        } catch (Throwable $error) {
            return back()->withErrors(['promo_codes' => $error->getMessage()]);
        }
    }

    public function redemptions(int $batchId)
    {
        $batch = $this->promoCodes->batch($batchId);
        abort_unless($batch, 404);

        return view('partner-campaigns.promo-redemptions', [
            'batch' => $batch,
            'metrics' => $this->promoCodes->batchMetrics($batchId),
            'redemptions' => $this->promoCodes->redemptions($batchId),
        ]);
    }

    public function revokeUnused(int $batchId)
    {
        $batch = $this->promoCodes->batch($batchId);
        abort_unless($batch, 404);

        $affected = DB::table('partner_promo_codes')
            ->where('batch_id', $batchId)
            ->where('status', 'available')
            ->update(['status' => 'revoked', 'updated_at' => now()]);
        DB::table('partner_promo_batches')->where('id', $batchId)->update([
            'status' => 'revoked',
            'updated_at' => now(),
        ]);
        $this->audit->recordAdminAudit($this->adminId(), 'revoke_unused', 'partner_promo_batch', $batchId, [
            'codes_revoked' => $affected,
        ]);

        return back()->with('success', $affected . ' unused promo codes were revoked.');
    }

    private function adminId(): int
    {
        return (int) session('ad_id');
    }
}
