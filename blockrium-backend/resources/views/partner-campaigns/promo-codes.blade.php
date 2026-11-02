@include('admin.includes.header')
@include('admin.includes.sidebar')
<div class="content-wrapper admin-content">
    <section class="content-header admin-page-header">
        <div class="container-fluid">
            <a href="{{ route('admin.partner-campaigns.index') }}" class="text-muted"><i class="fa-solid fa-arrow-left"></i> Partner campaigns</a>
            <div class="row align-items-center mt-2">
                <div class="col-md-8"><span class="campaign-kicker">PROMO CODE OPERATIONS</span><h1>{{ $campaign->title }}</h1><p>{{ $campaign->partner_name }} &middot; Generate single-use BRC codes for partner Crates.</p></div>
                <div class="col-md-4 text-md-right"><span class="admin-badge admin-badge-success">{{ ucfirst($campaign->status) }}</span></div>
            </div>
        </div>
    </section>
    <section class="content"><div class="container-fluid">
        @include('flash/flash-message')
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <div class="row">
            <div class="col-lg-5">
                <div class="admin-panel"><span class="campaign-kicker">CREATE BATCH</span><h2>Generate partner codes</h2><p class="text-muted">Codes are generated in small database chunks. Plain codes are kept only in a private encrypted export vault.</p>
                    <form method="POST" action="{{ route('admin.partner-campaigns.promo-codes.store', $campaign->id) }}">@csrf
                        <div class="form-group"><label>Number of codes</label><input class="form-control" type="number" name="code_count" min="1" max="100000" value="{{ old('code_count', 1000) }}" required><small class="text-muted">Maximum 100,000 per batch. Create another batch when needed.</small></div>
                        <div class="form-group"><label>Reward per code (BRC)</label><input class="form-control" type="number" name="reward_amount" step="0.0001" min="0.0001" value="{{ old('reward_amount', $campaign->reward_amount ?: 500) }}"><small class="text-muted">Use this for a fixed reward batch.</small></div>
                        <div class="form-group"><label>Reward label</label><input class="form-control" name="reward_label" maxlength="20" value="{{ old('reward_label', 'BRC') }}" required></div>
                        <div class="form-group"><label>Expiry (optional)</label><input class="form-control" type="datetime-local" name="expires_at" value="{{ old('expires_at', date('Y-m-d\\TH:i', strtotime($campaign->ends_at))) }}"><small class="text-muted">It will never exceed the campaign end date.</small></div>
                        <div class="form-group"><label>Variable rewards (optional)</label><textarea class="form-control" name="reward_amounts" rows="4" placeholder="One reward per code, separated by commas or spaces"></textarea><small class="text-muted">If used, enter exactly one amount for every code and leave the fixed reward blank.</small></div>
                        <button class="btn btn-admin-primary" type="submit"><i class="fa-solid fa-wand-magic-sparkles"></i> Generate codes</button>
                    </form>
                </div>
                <div class="admin-panel guide-panel"><span class="campaign-kicker">SAFE DELIVERY</span><h3>Send the CSV securely</h3><p>Download the CSV only from this protected admin page. Send the file and its password through separate channels if you zip it before sharing with Interstellar.</p></div>
            </div>
            <div class="col-lg-7"><div class="admin-panel"><div class="panel-topline"><div><span class="campaign-kicker">BATCHES</span><h2>Generated code batches</h2></div><span class="panel-count">{{ $batches->total() }} total</span></div>
                <div class="table-responsive"><table class="table admin-table"><thead><tr><th>Batch</th><th>Codes</th><th>BRC</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                @forelse($batches as $batch)
                    <tr><td><strong>{{ $batch->batch_reference }}</strong><small class="d-block text-muted">{{ $batch->created_at }}</small></td><td>{{ number_format($batch->metrics['generated']) }}<small class="d-block text-muted">{{ number_format($batch->metrics['available']) }} available</small></td><td>{{ number_format($batch->metrics['total_reward'], 4) }}<small class="d-block text-muted">{{ number_format($batch->metrics['distributed_reward'], 4) }} distributed</small></td><td><span class="admin-badge {{ $batch->status === 'exported' ? 'admin-badge-success' : 'admin-badge-muted' }}">{{ ucfirst($batch->status) }}</span></td><td class="promo-actions"><a class="btn btn-sm btn-admin-muted" href="{{ route('admin.partner-campaigns.promo-codes.export', $batch->id) }}">CSV</a><a class="btn btn-sm btn-admin-muted" href="{{ route('admin.partner-campaigns.promo-codes.redemptions', $batch->id) }}">Claims</a>@if($batch->status !== 'revoked')<form method="POST" action="{{ route('admin.partner-campaigns.promo-codes.revoke-unused', $batch->id) }}">@csrf<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Revoke all unused codes in this batch?')">Revoke</button></form>@endif</td></tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No promo-code batches yet.</td></tr>
                @endforelse
                </tbody></table></div><div class="mt-3">{{ $batches->links('vendor.pagination.custom') }}</div>
            </div></div>
        </div>
    </div></section>
</div>
<style>.promo-actions{display:flex;gap:5px;flex-wrap:wrap}.promo-actions form{margin:0}.guide-panel{background:linear-gradient(135deg,#102a43,#174b60);color:#fff}.guide-panel h3{margin:8px 0}.guide-panel p{color:#d4e7ea;margin:0;line-height:1.6}.admin-table th{white-space:nowrap}</style>
@include('admin.includes.footer')
