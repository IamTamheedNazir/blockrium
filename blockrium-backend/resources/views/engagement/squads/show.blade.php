@include('admin.includes.header')
@include('admin.includes.sidebar')

<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h1>{{ $squad->name }}</h1>
          <p>Invite code: <strong>{{ $squad->invite_code }}</strong> / {{ $payload['country_code'] ?: 'Global' }}</p>
        </div>
        <div class="col-md-4">
          <div class="admin-header-actions">
            <a class="btn btn-admin-primary" href="{{ route('admin.engagement.squads.settings', $squad->id) }}"><i class="fa-solid fa-sliders"></i>Settings</a>
            <a class="btn btn-admin-muted" href="{{ route('admin.engagement.squads') }}"><i class="fa-solid fa-arrow-left"></i>Squads</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-3 mb-4"><div class="admin-stat-card"><span class="admin-stat-label">Weekly Points</span><strong>{{ number_format($payload['weekly_points']) }}</strong><small>{{ $payload['weekly_progress_percent'] }}% of target</small></div></div>
        <div class="col-md-3 mb-4"><div class="admin-stat-card"><span class="admin-stat-label">Members</span><strong>{{ number_format($payload['member_count']) }}</strong><small>{{ $payload['is_unlimited'] ? 'Unlimited squad capacity' : $payload['spots_left'] . ' spots left of ' . $payload['max_members'] }}</small></div></div>
        <div class="col-md-3 mb-4"><div class="admin-stat-card"><span class="admin-stat-label">Community Boost</span><strong>{{ $payload['community_boost_percent'] }}%</strong><small>display/status boost</small></div></div>
        <div class="col-md-3 mb-4"><div class="admin-stat-card"><span class="admin-stat-label">Shares</span><strong>{{ number_format($payload['share_count']) }}</strong><small>{{ $payload['chest_unlocked'] ? 'Chest ready' : 'Chest locked' }}</small></div></div>
      </div>

      <div class="admin-panel mb-4">
        <div class="admin-panel-header">
          <div>
            <h2>Invite & Poster Payload</h2>
            <p>Use this link in app share sheets, social posts, posters, and deep links.</p>
          </div>
        </div>
        <div class="row">
          <div class="col-lg-7 mb-3">
            <label>Squad Invite Link</label>
            <input class="form-control" readonly value="{{ $payload['share_url'] }}">
            <small class="text-muted">When app deep linking is added, this same URL can open the squad join screen.</small>
          </div>
          <div class="col-lg-5 mb-3">
            <label>Share Text</label>
            <textarea class="form-control" rows="3" readonly>{{ $payload['share_text'] }}</textarea>
          </div>
        </div>
        <div class="row">
          <div class="col-md-3 mb-3"><span class="admin-stat-label">Country</span><div><strong>{{ $payload['country_code'] ?: 'Global' }}</strong></div></div>
          <div class="col-md-3 mb-3"><span class="admin-stat-label">Language</span><div><strong>{{ $payload['language_code'] ?: 'Any' }}</strong></div></div>
          <div class="col-md-3 mb-3"><span class="admin-stat-label">Join Policy</span><div><strong>{{ $payload['join_policy'] }}</strong></div></div>
          <div class="col-md-3 mb-3"><span class="admin-stat-label">Poster Template</span><div><strong>{{ $payload['poster_template'] }}</strong></div></div>
        </div>
        <a class="btn btn-admin-primary" href="{{ $payload['share_url'] }}" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square"></i>Open Invite Page</a>
        <a class="btn btn-admin-muted" href="{{ url('/squad/' . $payload['invite_code'] . '/poster.svg') }}" target="_blank" rel="noopener"><i class="fa-solid fa-image"></i>Open Poster SVG</a>
      </div>

      <div class="row">
        <div class="col-lg-6 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header"><div><h2>Activity Breakdown</h2></div></div>
            <table class="table admin-table">
              <tbody>
                @foreach($payload['activity_breakdown'] as $key=>$points)
                  <tr><td>{{ ucwords(str_replace('_', ' ', $key)) }}</td><td class="text-right">{{ number_format($points) }} pts</td></tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>

        <div class="col-lg-6 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header"><div><h2>Share Channels</h2></div></div>
            <table class="table admin-table">
              <thead><tr><th>Channel</th><th class="text-right">Shares</th></tr></thead>
              <tbody>
                @forelse($shares as $share)
                  <tr><td>{{ ucwords(str_replace('_', ' ', $share->channel)) }}</td><td class="text-right">{{ number_format($share->total) }}</td></tr>
                @empty
                  <tr><td colspan="2" class="admin-empty">No share events tracked yet.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-lg-6 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header"><div><h2>Members</h2></div></div>
            <div class="table-responsive">
              <table class="table admin-table">
                <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Source</th><th>Joined</th><th>Actions</th></tr></thead>
                <tbody>
                  @forelse($members as $member)
                    <tr>
                      <td><strong>{{ $member->name ?: $member->username ?: 'User #' . $member->user_id }}</strong><br><small>{{ $member->email }}</small></td>
                      <td><span class="admin-badge {{ $member->role === 'owner' ? 'admin-badge-success' : 'admin-badge-muted' }}">{{ str_replace('_', ' ', $member->role) }}</span></td>
                      <td>{{ $member->status }}</td>
                      <td>{{ $member->join_source ?? '-' }}</td>
                      <td>{{ $member->joined_at ?: '-' }}</td>
                      <td>
                        @if($member->role !== 'owner' && $member->status === 'active')
                          <form method="POST" action="{{ route('admin.engagement.squads.member-role', $squad->id) }}" class="d-flex mb-2">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $member->user_id }}">
                            <select class="form-control form-control-sm mr-2" name="role">
                              @foreach(['co_leader'=>'Co-leader', 'moderator'=>'Moderator', 'member'=>'Member'] as $value=>$label)
                                <option value="{{ $value }}" {{ $member->role === $value ? 'selected' : '' }}>{{ $label }}</option>
                              @endforeach
                            </select>
                            <button class="btn btn-sm btn-admin-muted" type="submit">Save</button>
                          </form>
                          <form method="POST" action="{{ route('admin.engagement.squads.remove-member', [$squad->id, $member->user_id]) }}">
                            @csrf
                            <button class="btn btn-sm btn-danger" data-confirm="Remove this squad member?" type="submit">Remove</button>
                          </form>
                        @else
                          -
                        @endif
                      </td>
                    </tr>
                  @empty
                    <tr><td colspan="6" class="admin-empty">No members.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-lg-6 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header"><div><h2>Weekly Chest Claims</h2></div></div>
            <div class="table-responsive">
              <table class="table admin-table">
                <thead><tr><th>Week</th><th>Points</th><th>XP</th><th>BRC</th><th>Claimed By</th><th>Claimed</th></tr></thead>
                <tbody>
                  @forelse($chests as $chest)
                    <tr>
                      <td>{{ $chest->week_start }} - {{ $chest->week_end }}</td>
                      <td>{{ number_format($chest->total_points) }}</td>
                      <td>{{ number_format($chest->reward_xp) }}</td>
                      <td>{{ $chest->reward_brc === null ? '-' : number_format($chest->reward_brc, 4) }}</td>
                      <td>{{ $chest->claimed_by_name ?: $chest->claimed_by_username ?: '-' }}</td>
                      <td>{{ $chest->claimed_at ?: '-' }}</td>
                    </tr>
                  @empty
                    <tr><td colspan="6" class="admin-empty">No chest claims yet.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
            <div class="mt-3">{{ $chests->links('vendor.pagination.custom') }}</div>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

@include('admin.includes.footer')
