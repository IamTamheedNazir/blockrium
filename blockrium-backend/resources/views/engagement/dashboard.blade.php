@include('admin.includes.header')
@include('admin.includes.sidebar')

<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-md-7">
          <h1>Engagement Dashboard</h1>
          <p>XP, badge, season, and referral milestone operations.</p>
        </div>
        <div class="col-md-5">
          <div class="admin-header-actions">
            @foreach(['today' => 'Today', '7days' => '7 Days', '30days' => '30 Days'] as $key => $label)
              <a href="{{ route('admin.engagement.dashboard', ['period' => $key]) }}" class="btn {{ $period === $key ? 'btn-admin-primary' : 'btn-admin-muted' }}">{{ $label }}</a>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      @include('flash/flash-message')

      @php
        $cards = [
          ['label' => 'Total XP', 'value' => number_format($stats['total_xp']), 'note' => 'All validated events', 'icon' => 'fa-sparkles', 'tone' => 'admin-stat-blue'],
          ['label' => 'XP Today', 'value' => number_format($stats['xp_today']), 'note' => 'Since midnight', 'icon' => 'fa-calendar-day', 'tone' => 'admin-stat-green'],
          ['label' => 'XP Last 7 Days', 'value' => number_format($stats['xp_7_days']), 'note' => 'Rolling seven days', 'icon' => 'fa-chart-line', 'tone' => 'admin-stat-cyan'],
          ['label' => 'Users With XP', 'value' => number_format($stats['users_with_xp']), 'note' => 'Distinct users', 'icon' => 'fa-users', 'tone' => 'admin-stat-orange'],
          ['label' => 'Badges Earned', 'value' => number_format($stats['badges_earned']), 'note' => number_format($stats['active_badges']).' active badges', 'icon' => 'fa-award', 'tone' => 'admin-stat-green'],
          ['label' => 'Milestone Claims', 'value' => number_format($stats['milestone_claims']), 'note' => 'Unique user claims', 'icon' => 'fa-flag-checkered', 'tone' => 'admin-stat-blue'],
          ['label' => 'Milestone BRC', 'value' => number_format($stats['milestone_brc'], 4), 'note' => 'Credited through claims', 'icon' => 'fa-coins', 'tone' => 'admin-stat-orange'],
          ['label' => 'Active Season', 'value' => $season->title ?? 'None', 'note' => $season ? \Carbon\Carbon::parse($season->ends_at)->format('d M Y') : 'No active date window', 'icon' => 'fa-calendar-days', 'tone' => 'admin-stat-cyan'],
        ];
      @endphp
      <div class="row">
        @foreach($cards as $card)
          <div class="col-xl-3 col-md-6 mb-4">
            <div class="admin-stat-card {{ $card['tone'] }} h-100">
              <span class="admin-stat-icon"><i class="fa-solid {{ $card['icon'] }}"></i></span>
              <span class="admin-stat-copy">
                <span class="admin-stat-label">{{ $card['label'] }}</span>
                <strong>{{ $card['value'] }}</strong>
                <small>{{ $card['note'] }}</small>
              </span>
            </div>
          </div>
        @endforeach
      </div>

      <div class="row">
        <div class="col-lg-6 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header"><div><h2>Top XP Users</h2><p>Selected reporting period.</p></div></div>
            <div class="table-responsive">
              <table class="table admin-table">
                <thead><tr><th>User</th><th>XP</th></tr></thead>
                <tbody>
                  @forelse($top_xp_users as $row)
                    <tr><td><strong>{{ $row->username ?: ($row->name ?: 'User #'.$row->user_id) }}</strong><br><small>{{ $row->email }}</small></td><td>{{ number_format($row->xp) }}</td></tr>
                  @empty
                    <tr><td colspan="2" class="admin-empty">No XP events in this period.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="col-lg-6 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header"><div><h2>Top Active Referrers</h2><p>Direct referrals with recent participation.</p></div></div>
            <div class="table-responsive">
              <table class="table admin-table">
                <thead><tr><th>Referrer</th><th>Active</th></tr></thead>
                <tbody>
                  @forelse($top_referrers as $row)
                    <tr><td><strong>{{ $row->username ?: ($row->name ?: $row->referral_key) }}</strong><br><small>{{ $row->email }}</small></td><td>{{ number_format($row->active_referrals) }}</td></tr>
                  @empty
                    <tr><td colspan="2" class="admin-empty">No active referral data in this period.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="admin-panel mb-4">
        <div class="admin-panel-header"><div><h2>Recent XP Events</h2></div><a class="admin-panel-link" href="{{ route('admin.engagement.xp-events') }}">View all</a></div>
        <div class="table-responsive"><table class="table admin-table"><thead><tr><th>User</th><th>Event</th><th>XP</th><th>Awarded</th></tr></thead><tbody>
          @forelse($recent_xp as $row)<tr><td>{{ $row->username ?: ($row->user_name ?: 'User #'.$row->user_id) }}</td><td>{{ $row->event_key }}</td><td>+{{ $row->xp_amount }}</td><td>{{ $row->awarded_at }}</td></tr>@empty<tr><td colspan="4" class="admin-empty">No XP events recorded.</td></tr>@endforelse
        </tbody></table></div>
      </div>

      <div class="row">
        <div class="col-lg-6 mb-4"><div class="admin-panel h-100"><div class="admin-panel-header"><div><h2>Recent Badge Awards</h2></div></div><div class="table-responsive"><table class="table admin-table"><thead><tr><th>User</th><th>Badge</th><th>Awarded</th></tr></thead><tbody>@forelse($recent_badges as $row)<tr><td>{{ $row->username ?: ($row->user_name ?: 'User #'.$row->user_id) }}</td><td>{{ $row->badge_name }}</td><td>{{ $row->awarded_at }}</td></tr>@empty<tr><td colspan="3" class="admin-empty">No badge awards.</td></tr>@endforelse</tbody></table></div></div></div>
        <div class="col-lg-6 mb-4"><div class="admin-panel h-100"><div class="admin-panel-header"><div><h2>Recent Referral Claims</h2></div></div><div class="table-responsive"><table class="table admin-table"><thead><tr><th>User</th><th>Milestone</th><th>Reward</th></tr></thead><tbody>@forelse($recent_claims as $row)<tr><td>{{ $row->username ?: ($row->user_name ?: 'User #'.$row->user_id) }}</td><td>{{ $row->milestone_title ?: $row->milestone_key }}</td><td>{{ number_format($row->reward_amount, 4) }} {{ $row->reward_label ?? 'BRC' }}</td></tr>@empty<tr><td colspan="3" class="admin-empty">No milestone claims.</td></tr>@endforelse</tbody></table></div></div></div>
      </div>
    </div>
  </section>
</div>

@include('admin.includes.footer')
