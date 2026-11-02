@include('admin.includes.header')
@include('admin.includes.sidebar')

<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-md-7">
          <h1>Referral Missions</h1>
          <p>Create daily and one-time goals that turn referrals into repeat engagement.</p>
        </div>
        <div class="col-md-5">
          <div class="admin-header-actions">
            <a href="{{ url('admin/referral/settings') }}" class="btn btn-admin-muted">
              <i class="fa-solid fa-sliders"></i>
              Settings
            </a>
            <a href="{{ url('admin/referral/mission/add') }}" class="btn btn-admin-primary">
              <i class="fa-solid fa-plus"></i>
              Add Mission
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      @include('flash/flash-message')

      <div class="admin-panel">
        <div class="admin-panel-header">
          <div>
            <h2>Missions</h2>
            <p>Only active missions appear in the mobile app.</p>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table admin-table">
            <thead>
              <tr>
                <th>Mission</th>
                <th>Metric</th>
                <th>Target</th>
                <th>Reward</th>
                <th>Frequency</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($missions as $mission)
                <tr>
                  <td>
                    <strong>{{ $mission->title }}</strong><br>
                    <small>{{ $mission->description ?: $mission->slug }}</small>
                  </td>
                  <td>{{ $metrics[$mission->metric] ?? $mission->metric }}</td>
                  <td>{{ number_format((int) $mission->target_count) }}</td>
                  <td>{{ number_format((float) $mission->reward_amount, 4) }} BRC</td>
                  <td>{{ ucfirst($mission->frequency) }}</td>
                  <td>
                    <span class="admin-badge {{ (int) $mission->status === 1 ? 'admin-badge-success' : 'admin-badge-muted' }}">
                      {{ (int) $mission->status === 1 ? 'Active' : 'Disabled' }}
                    </span>
                  </td>
                  <td>
                    <a href="{{ url('admin/referral/mission/edit/' . $mission->id) }}" class="btn btn-sm btn-admin-muted">
                      <i class="fa-solid fa-pen"></i>
                      Edit
                    </a>
                    <a href="{{ url('admin/referral/mission/delete/' . $mission->id) }}" class="btn btn-sm btn-danger" data-confirm="Disable this referral mission?">
                      <i class="fa-solid fa-ban"></i>
                      Disable
                    </a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="admin-empty">No referral missions found. Run migrations, then add your first mission.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

@include('admin.includes.footer')
