@include('admin.includes.header')
@include('admin.includes.sidebar')

<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-md-7">
          <h1>Boost Purchases</h1>
          <p>Review paid boost requests and activate them after payment verification.</p>
        </div>
        <div class="col-md-5">
          <div class="admin-header-actions">
            <a href="{{ url('admin/manage/boost-plans') }}" class="btn btn-admin-muted">
              <i class="fa-solid fa-bolt"></i>
              Plans
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      @include('flash/flash-message')

      <div class="admin-panel mb-4">
        <form method="GET" action="{{ url('admin/manage/boost-purchases') }}" class="row align-items-end">
          <div class="col-md-4">
            <div class="form-group mb-md-0">
              <label>Status</label>
              <select name="status" class="form-control">
                <option value="" {{ $status === '' ? 'selected' : '' }}>All purchases</option>
                <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                <option value="expired" {{ $status === 'expired' ? 'selected' : '' }}>Expired</option>
                <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
              </select>
            </div>
          </div>
          <div class="col-md-4">
            <button type="submit" class="btn btn-admin-primary">
              <i class="fa-solid fa-filter"></i>
              Filter
            </button>
          </div>
        </form>
      </div>

      <div class="admin-panel">
        <div class="admin-panel-header">
          <div>
            <h2>Purchases</h2>
            <p>Pending purchases must be paid before activation.</p>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table admin-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Plan</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Provider</th>
                <th>Active Window</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @forelse($purchases as $purchase)
                <tr>
                  <td>
                    <strong>{{ $purchase->username ?: ($purchase->user_name ?: 'User #' . $purchase->user_id) }}</strong><br>
                    <small>{{ $purchase->email ?: 'ID ' . $purchase->user_id }}</small>
                  </td>
                  <td>
                    <strong>{{ $purchase->plan_name ?: 'Custom Boost' }}</strong><br>
                    <small>{{ number_format((float) $purchase->boost_percent, 2) }}% boost</small>
                  </td>
                  <td>{{ $purchase->currency }} {{ number_format((float) $purchase->amount_paid, 2) }}</td>
                  <td>
                    <span class="admin-badge {{ $purchase->status === 'active' ? 'admin-badge-success' : 'admin-badge-muted' }}">
                      {{ ucfirst($purchase->status) }}
                    </span>
                  </td>
                  <td>{{ ucfirst($purchase->payment_status) }}</td>
                  <td>
                    <small>{{ ucfirst($purchase->provider ?: 'manual') }}</small>
                    @if(!empty($purchase->provider_subscription_id))<br><small>Sub: {{ $purchase->provider_subscription_id }}</small>@endif
                    @if(!empty($purchase->provider_payment_id))<br><small>Pay: {{ $purchase->provider_payment_id }}</small>@endif
                  </td>
                  <td>
                    <small>
                      {{ $purchase->starts_at ?: 'Not started' }}<br>
                      {{ $purchase->ends_at ?: 'No end date' }}
                    </small>
                  </td>
                  <td>
                    @if($purchase->status !== 'active')
                      <a href="{{ url('admin/activate-user-boost/' . $purchase->id) }}" class="btn btn-sm btn-admin-primary" data-confirm="Activate this boost after payment verification?">
                        <i class="fa-solid fa-circle-check"></i>
                        Activate
                      </a>
                    @endif
                    @if($purchase->status !== 'cancelled')
                      <a href="{{ url('admin/cancel-user-boost/' . $purchase->id) }}" class="btn btn-sm btn-danger" data-confirm="Cancel this user boost?">
                        <i class="fa-solid fa-ban"></i>
                        Cancel
                      </a>
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="8" class="admin-empty">No boost purchases found yet.</td>
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
