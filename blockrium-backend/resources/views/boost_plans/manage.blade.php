@include('admin.includes.header')
@include('admin.includes.sidebar')

<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-md-7">
          <h1>Boost Plans</h1>
          <p>Control paid mining boosts, durations, and prices from one place.</p>
        </div>
        <div class="col-md-5">
          <div class="admin-header-actions">
            <a href="{{ url('admin/manage/boost-purchases') }}" class="btn btn-admin-muted">
              <i class="fa-solid fa-receipt"></i>
              Purchases
            </a>
            <a href="{{ url('admin/add/boost-plan') }}" class="btn btn-admin-primary">
              <i class="fa-solid fa-plus"></i>
              Add Plan
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      @include('flash/flash-message')

      <div class="row">
        <div class="col-md-4 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header">
              <div>
                <h2>{{ number_format($stats['active_plans'] ?? 0) }}</h2>
                <p>Active boost plans</p>
              </div>
              <i class="fa-solid fa-bolt"></i>
            </div>
          </div>
        </div>
        <div class="col-md-4 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header">
              <div>
                <h2>{{ number_format($stats['pending_purchases'] ?? 0) }}</h2>
                <p>Pending purchases</p>
              </div>
              <i class="fa-solid fa-hourglass-half"></i>
            </div>
          </div>
        </div>
        <div class="col-md-4 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header">
              <div>
                <h2>{{ number_format($stats['active_boosts'] ?? 0) }}</h2>
                <p>Active user boosts</p>
              </div>
              <i class="fa-solid fa-circle-check"></i>
            </div>
          </div>
        </div>
      </div>

      <div class="admin-panel">
        <div class="admin-panel-header">
          <div>
            <h2>Plans</h2>
            <p>Only active plans are returned to the mobile app.</p>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table admin-table">
            <thead>
              <tr>
                <th>Plan</th>
                <th>Boost</th>
                <th>Duration</th>
                <th>Price</th>
                <th>Status</th>
                <th>Order</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($plans as $plan)
                <tr>
                  <td>
                    <strong>{{ $plan->name }}</strong><br>
                    <small>{{ $plan->description ?: $plan->slug }}</small>
                    @if(!empty($plan->provider_product_id))
                      <br><small>{{ ucfirst($plan->provider ?: 'provider') }}: {{ $plan->provider_product_id }}</small>
                    @endif
                  </td>
                  <td>{{ number_format((float) $plan->boost_percent, 2) }}%</td>
                  <td>{{ (int) $plan->duration_days }} days</td>
                  <td>{{ $plan->currency }} {{ number_format((float) $plan->price, 2) }}</td>
                  <td>
                    <span class="admin-badge {{ (int) $plan->status === 1 ? 'admin-badge-success' : 'admin-badge-muted' }}">
                      {{ (int) $plan->status === 1 ? 'Active' : 'Disabled' }}
                    </span>
                  </td>
                  <td>{{ (int) $plan->sort_order }}</td>
                  <td>
                    <a href="{{ url('admin/edit-boost-plan/' . $plan->id) }}" class="btn btn-sm btn-admin-muted">
                      <i class="fa-solid fa-pen"></i>
                      Edit
                    </a>
                    <a href="{{ url('admin/delete_boost-plan/' . $plan->id) }}" class="btn btn-sm btn-danger" data-confirm="Disable this boost plan?">
                      <i class="fa-solid fa-ban"></i>
                      Disable
                    </a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="admin-empty">No boost plans found. Run migrations, then add your first plan.</td>
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
