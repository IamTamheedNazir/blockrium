@include('admin.includes.header')
@include('admin.includes.sidebar')

<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h1>Squads</h1>
          <p>Community activity teams, invite links, country groups, weekly points, and chest claims. Not profit sharing.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      @include('flash/flash-message')

      <div class="admin-panel mb-4">
        <form method="GET" action="{{ route('admin.engagement.squads') }}" class="row align-items-end">
          <div class="col-md-3 mb-3">
            <label>Country Code</label>
            <input class="form-control" name="country" maxlength="2" value="{{ request('country') }}" placeholder="IN, US, NG">
          </div>
          <div class="col-md-3 mb-3">
            <label>Status</label>
            <select class="form-control" name="status">
              <option value="">All</option>
              <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
              <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
          </div>
          <div class="col-md-3 mb-3">
            <button class="btn btn-admin-primary" type="submit"><i class="fa-solid fa-filter"></i>Filter</button>
            <a class="btn btn-admin-muted" href="{{ route('admin.engagement.squads') }}">Reset</a>
          </div>
        </form>
      </div>

      <div class="row">
        <div class="col-lg-8 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header">
              <div>
                <h2>Squad Directory</h2>
                <p>Share links use <code>/squad/{invite_code}</code> and can be opened before install.</p>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table admin-table">
                <thead>
                  <tr>
                    <th>Squad</th>
                    <th>Owner</th>
                    <th>Members</th>
                    <th>Country</th>
                    <th>Weekly</th>
                    <th>Shares</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($squads as $squad)
                    @php
                      $stats = $squadService->weeklyStats($squad->id);
                      $shareUrl = url('/squad/' . $squad->invite_code);
                      $isUnlimited = (int) $squad->max_members <= 0;
                    @endphp
                    <tr>
                      <td>
                        <strong>{{ $squad->name }}</strong><br>
                        <small>{{ $squad->slug }} / {{ $squad->invite_code }}</small><br>
                        <small><a href="{{ $shareUrl }}" target="_blank" rel="noopener">{{ $shareUrl }}</a></small>
                      </td>
                      <td>
                        {{ $squad->owner_name ?: $squad->owner_username ?: 'User #' . $squad->owner_user_id }}<br>
                        <small>{{ $squad->owner_email }}</small>
                      </td>
                      <td>
                        {{ number_format($squad->member_count) }} / {{ $isUnlimited ? 'Unlimited' : number_format($squad->max_members) }}
                      </td>
                      <td>
                        {{ $squad->country_code ?? 'Global' }}<br>
                        <small>{{ $squad->language_code ?? 'Any language' }}</small>
                      </td>
                      <td>
                        {{ number_format($stats['points']) }} / {{ number_format($squad->weekly_target_points) }}<br>
                        <small>{{ $stats['community_boost_percent'] }}% boost</small>
                      </td>
                      <td>{{ number_format($squad->share_count ?? 0) }}</td>
                      <td>
                        <span class="admin-badge {{ $squad->is_active ? 'admin-badge-success' : 'admin-badge-muted' }}">
                          {{ $squad->is_active ? 'Active' : 'Inactive' }}
                        </span>
                      </td>
                      <td>
                        <div class="d-flex flex-wrap">
                          <a class="btn btn-sm btn-admin-muted mr-2" href="{{ route('admin.engagement.squads.show', $squad->id) }}">
                            <i class="fa-solid fa-eye"></i>View
                          </a>
                          <a class="btn btn-sm btn-admin-muted mr-2" href="{{ route('admin.engagement.squads.settings', $squad->id) }}">
                            <i class="fa-solid fa-sliders"></i>Settings
                          </a>
                          <form method="POST" action="{{ route('admin.engagement.squads.toggle', $squad->id) }}">
                            @csrf
                            <button class="btn btn-sm {{ $squad->is_active ? 'btn-warning' : 'btn-success' }}" type="submit">
                              {{ $squad->is_active ? 'Disable' : 'Enable' }}
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  @empty
                    <tr><td colspan="8" class="admin-empty">No squads created yet.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
            <div class="mt-3">{{ $squads->links('vendor.pagination.custom') }}</div>
          </div>
        </div>

        <div class="col-lg-4 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header">
              <div>
                <h2>Weekly Leaderboard</h2>
                <p>Based on backend-validated activity.</p>
              </div>
            </div>
            @forelse($leaderboard as $index => $row)
              <div class="d-flex justify-content-between border-bottom py-2">
                <div>
                  <strong>#{{ $index + 1 }} {{ $row['name'] }}</strong><br>
                  <small>{{ $row['member_count'] }} members {{ $row['country_code'] ? '/ ' . $row['country_code'] : '' }}</small>
                </div>
                <div class="text-right">
                  <strong>{{ number_format($row['weekly_points']) }}</strong><br>
                  <small>{{ $row['community_boost_percent'] }}% boost</small>
                </div>
              </div>
            @empty
              <div class="admin-empty">No leaderboard data yet.</div>
            @endforelse
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

@include('admin.includes.footer')
