@include('admin.includes.header')
@include('admin.includes.sidebar')
<div class="content-wrapper admin-content">
    <section class="content-header admin-page-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <span class="campaign-kicker">PARTNER DISTRIBUTION</span>
                    <h1>Promo Code Distribution</h1>
                    <p>Select a campaign to generate, export, and monitor single-use BRC promo codes.</p>
                </div>
                <div class="col-md-4 text-md-right">
                    <a class="btn btn-admin-muted" href="{{ route('admin.partner-campaigns.index') }}"><i class="fa-solid fa-handshake"></i> Partner campaigns</a>
                </div>
            </div>
        </div>
    </section>
    <section class="content"><div class="container-fluid">
        <div class="admin-panel mb-3 promo-guide">
            <strong>How it works</strong>
            <span>Create a batch for a campaign, download the CSV, and send the unique codes to the partner. Each code can be redeemed only once.</span>
        </div>
        <div class="admin-panel table-responsive">
            <table class="table admin-table align-middle">
                <thead><tr><th>Campaign</th><th>Partner</th><th>Status</th><th>Campaign window</th><th>Promo batches</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($campaigns as $campaign)
                    <tr>
                        <td><strong>{{ $campaign->title }}</strong><small class="d-block text-muted">Reward: {{ number_format((float) ($campaign->reward_amount ?? 0), 4) }} BRC</small></td>
                        <td>{{ $campaign->partner_name ?: 'Partner' }}</td>
                        <td><span class="admin-badge {{ $campaign->status === 'active' ? 'admin-badge-success' : 'admin-badge-muted' }}">{{ ucfirst($campaign->status) }}</span></td>
                        <td>{{ $campaign->starts_at }}<br><small class="text-muted">to {{ $campaign->ends_at }}</small></td>
                        <td>{{ number_format((int) ($campaign->batch_count ?? 0)) }}</td>
                        <td><a class="btn btn-sm btn-admin-primary" href="{{ route('admin.partner-campaigns.promo-codes.index', $campaign->id) }}"><i class="fa-solid fa-ticket"></i> Manage codes</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="promo-empty"><i class="fa-solid fa-ticket"></i><h3>No campaigns available</h3><p>Create a partner campaign first, then return here to create its CSV promo-code batch.</p><a class="btn btn-admin-primary" href="{{ route('admin.partner-campaigns.create') }}">Create campaign</a></div></td></tr>
                @endforelse
                </tbody>
            </table>
            @if($campaigns->hasPages())<div class="mt-3">{{ $campaigns->links('vendor.pagination.custom') }}</div>@endif
        </div>
    </div></section>
</div>
<style>.promo-guide{display:flex;gap:12px;align-items:center;background:#eef8f6;color:#235b61}.promo-guide strong{color:#087f78}.promo-empty{text-align:center;padding:45px 20px;color:#6b7c93}.promo-empty i{font-size:30px;color:#0b8f84}.promo-empty h3{color:#102a43;margin:12px 0 5px}</style>
@include('admin.includes.footer')
