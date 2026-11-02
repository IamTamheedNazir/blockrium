@include('admin.includes.header')
@include('admin.includes.sidebar')
<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header">
    <div class="container-fluid">
      <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div>
          <div class="admin-eyebrow">AUDIT AND SECURITY</div>
          <h1>Mining audit</h1>
          <p>Review high-balance accounts and investigate reward-source anomalies before making any wallet adjustment.</p>
        </div>
        <form method="post" action="{{ url('admin/audit/mining/scan') }}">
          @csrf
          <button class="btn btn-admin-primary"><i class="fa-solid fa-magnifying-glass-chart mr-1"></i> Run audit scan</button>
        </form>
      </div>
    </div>
  </section>
  <section class="content"><div class="container-fluid">
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
    @if(!$summary['audit_tables'])
      <div class="alert alert-warning"><strong>Persistence unavailable:</strong> the audit case tables are not installed. The page is read-only until the admin operations migration is deployed.</div>
    @endif
    <div class="row">
      <div class="col-md-4"><div class="admin-stat-card"><span>High-balance users</span><strong>{{ number_format($summary['high_balance_users']) }}</strong><small>At or above {{ number_format($threshold, 0) }} BRC</small></div></div>
      <div class="col-md-4"><div class="admin-stat-card"><span>Open audit cases</span><strong>{{ number_format($summary['open_cases']) }}</strong><small>Open, under review, or held</small></div></div>
      <div class="col-md-4"><div class="admin-stat-card"><span>Held accounts</span><strong>{{ number_format($summary['held_cases']) }}</strong><small>Mining should remain paused while held</small></div></div>
    </div>
    <div class="admin-panel mb-4">
      <div class="d-flex flex-wrap align-items-center justify-content-between mb-3"><div><h2 class="h4 mb-1">High-balance review queue</h2><p class="text-muted mb-0">The scan compares the legacy wallet with available mining, reward, referral, and activity sources.</p></div></div>
      <div class="table-responsive"><table class="table table-striped align-middle">
        <thead><tr><th>User</th><th>Wallet</th><th>Mining</th><th>Rewards</th><th>Referrals</th><th>Activity</th><th>Audited total</th><th>Risk</th></tr></thead>
        <tbody>
          @forelse($users as $user)
            <tr><td><strong>{{ $user->name ?: $user->username ?: 'User #' . $user->id }}</strong><br><small class="text-muted">{{ $user->email ?: $user->username }}</small></td><td>{{ number_format($user->sources['wallet'], 4) }}</td><td>{{ number_format($user->sources['mining'], 4) }}</td><td>{{ number_format($user->sources['reward'], 4) }}</td><td>{{ number_format($user->sources['referral'], 4) }}</td><td>{{ number_format($user->sources['activity'], 4) }}</td><td><strong>{{ number_format($user->audited_total, 4) }}</strong></td><td><span class="admin-badge {{ $user->risk === 'critical' ? 'admin-badge-danger' : 'admin-badge-warning' }}">{{ ucfirst($user->risk) }}</span></td></tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-4">No users currently exceed the audit threshold.</td></tr>
          @endforelse
        </tbody>
      </table></div>
    </div>
    <div class="admin-panel">
      <h2 class="h4 mb-3">Persisted audit cases</h2>
      <div class="table-responsive"><table class="table table-striped align-middle">
        <thead><tr><th>User</th><th>Status</th><th>Severity</th><th>Reason</th><th>Updated</th><th>Action</th></tr></thead>
        <tbody>
          @forelse($cases as $case)
            <tr><td>{{ $case->user_name ?? $case->username ?? 'User #' . $case->user_id }}</td><td>{{ ucfirst(str_replace('_', ' ', $case->status ?? 'open')) }}</td><td>{{ ucfirst($case->severity ?? 'review') }}</td><td>{{ $case->reason ?? 'Source verification required.' }}</td><td>{{ $case->updated_at ?? $case->created_at ?? '-' }}</td><td><form method="post" action="{{ url('admin/audit/mining/cases/' . $case->id) }}" class="form-inline"><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="status" value="under_review"><button class="btn btn-sm btn-admin-muted">Review</button></form></td></tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">No persisted audit cases yet. Run an audit scan to create cases.</td></tr>
          @endforelse
        </tbody>
      </table></div>
    </div>
  </div></section>
</div>
@include('admin.includes.footer')
