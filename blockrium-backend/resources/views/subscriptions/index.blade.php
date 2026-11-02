@include('admin.includes.header')
@include('admin.includes.sidebar')

@php
  $stats = $stats ?? [];
  $planCounts = $stats['plan_counts'] ?? ['monthly' => 0, 'three_month' => 0, 'six_month' => 0, 'yearly' => 0];
  $statusOptions = [
    '' => 'All subscriptions',
    'active' => 'Active',
    'cancelled' => 'Cancelled but active',
    'billing_issue' => 'Billing issue',
    'paused' => 'Paused',
    'expired' => 'Expired',
    'refunded' => 'Refunded',
    'inactive' => 'Inactive',
  ];
@endphp

<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-md-7">
          <h1>Subscriptions</h1>
          <p>RevenueCat Pro access, purchase lifecycle, expiry dates, and mining boost status.</p>
        </div>
        <div class="col-md-5">
          <div class="admin-header-actions">
            <a href="{{ url('admin/manage/boost-plans') }}" class="btn btn-admin-muted">
              <i class="fa-solid fa-bolt"></i>
              Boost Plans
            </a>
            <a href="{{ url('/dashboard') }}" class="btn btn-admin-primary">
              <i class="fa-solid fa-chart-line"></i>
              Dashboard
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
        <div class="col-xl-3 col-md-6 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header mb-0">
              <div>
                <h2>{{ number_format($stats['total_pro_users'] ?? 0) }}</h2>
                <p>Total Pro Users</p>
              </div>
              <i class="fa-solid fa-crown"></i>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header mb-0">
              <div>
                <h2>{{ number_format($stats['active_subscriptions'] ?? 0) }}</h2>
                <p>Active Subscriptions</p>
              </div>
              <i class="fa-solid fa-circle-check"></i>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header mb-0">
              <div>
                <h2>${{ number_format((float) ($stats['revenue_this_month'] ?? 0), 2) }}</h2>
                <p>Revenue This Month</p>
              </div>
              <i class="fa-solid fa-dollar-sign"></i>
            </div>
          </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header mb-0">
              <div>
                <h2>{{ number_format($stats['expired_users'] ?? 0) }}</h2>
                <p>Expired / Refunded</p>
              </div>
              <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
          </div>
        </div>
      </div>

      <div class="admin-panel mb-4">
        <div class="admin-panel-header">
          <div>
            <h2>Plan Mix</h2>
            <p>Active users by RevenueCat product identifier.</p>
          </div>
        </div>
        <div class="admin-plan-breakdown">
          <span><strong>{{ number_format($planCounts['monthly'] ?? 0) }}</strong> Monthly</span>
          <span><strong>{{ number_format($planCounts['three_month'] ?? 0) }}</strong> Three Month</span>
          <span><strong>{{ number_format($planCounts['six_month'] ?? 0) }}</strong> Six Month</span>
          <span><strong>{{ number_format($planCounts['yearly'] ?? 0) }}</strong> Yearly</span>
        </div>
      </div>

      <div class="admin-panel mb-4">
        <form method="GET" action="{{ url('admin/subscriptions') }}" class="row align-items-end">
          <div class="col-md-4">
            <div class="form-group mb-md-0">
              <label>Status</label>
              <select name="status" class="form-control">
                @foreach($statusOptions as $value => $label)
                  <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
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
            <h2>RevenueCat Customers</h2>
            <p>Use this table to confirm Pro access before debugging mining boosts.</p>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table admin-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Plan</th>
                <th>Status</th>
                <th>Expiry</th>
                <th>Store</th>
                <th>Transaction</th>
                <th>Revenue</th>
              </tr>
            </thead>
            <tbody>
              @forelse($subscriptions as $subscription)
                <tr>
                  <td>
                    <div class="admin-user-cell">
                      <span>{{ strtoupper(substr($subscription->username ?? $subscription->user_name ?? 'U', 0, 1)) }}</span>
                      <div>
                        <strong>{{ $subscription->username ?? $subscription->user_name ?? 'User #' . $subscription->user_id }}</strong>
                        <small>{{ $subscription->email ?? 'RevenueCat ID ' . $subscription->app_user_id }}</small>
                      </div>
                    </div>
                  </td>
                  <td>
                    <strong>{{ $subscription->plan_name ?? 'Pro' }}</strong><br>
                    <small>{{ $subscription->product_id ?? '-' }}</small>
                  </td>
                  <td>
                    <span class="admin-badge {{ in_array($subscription->status, ['active', 'cancelled', 'billing_issue', 'paused'], true) ? 'admin-badge-success' : 'admin-badge-muted' }}">
                      {{ ucwords(str_replace('_', ' ', $subscription->status)) }}
                    </span>
                    @if((int) ($subscription->is_pro ?? 0) === 1)
                      <br><small>Pro enabled</small>
                    @endif
                  </td>
                  <td>
                    <small>
                      {{ $subscription->expires_at ? \Carbon\Carbon::parse($subscription->expires_at)->format('d M Y, h:i A') : 'No expiry' }}
                    </small>
                  </td>
                  <td>
                    <small>{{ $subscription->store ?? '-' }}<br>{{ $subscription->environment ?? '-' }}</small>
                  </td>
                  <td>
                    <small>{{ $subscription->original_transaction_id ?: ($subscription->transaction_id ?: '-') }}</small>
                  </td>
                  <td>
                    {{ $subscription->currency ?? 'USD' }} {{ number_format((float) ($subscription->price ?? 0), 2) }}
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="admin-empty">No RevenueCat subscriptions found yet. Send a test webhook or make a sandbox purchase.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if(method_exists($subscriptions, 'links'))
          <div class="mt-3">
            {{ $subscriptions->appends(request()->query())->links('vendor.pagination.custom') }}
          </div>
        @endif
      </div>
    </div>
  </section>
</div>

@include('admin.includes.footer')
