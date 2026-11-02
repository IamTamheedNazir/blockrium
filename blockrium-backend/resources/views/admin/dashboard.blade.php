@include('admin.includes.header')
@include('admin.includes.sidebar')

@php
  $stats = $stats ?? [];
  $recentUsers = $recentUsers ?? collect();
  $topWallets = $topWallets ?? collect();
  $quickActions = $quickActions ?? [];
  $verificationStats = $verificationStats ?? [];
  $subscriptionStats = $subscriptionStats ?? [
    'total_pro_users' => 0,
    'active_subscriptions' => 0,
    'expired_users' => 0,
    'revenue_this_month' => 0,
    'plan_counts' => ['monthly' => 0, 'three_month' => 0, 'six_month' => 0, 'yearly' => 0],
    'recent' => collect(),
  ];

  $statCards = [
    [
      'label' => 'Total Users',
      'value' => number_format($stats['total_users'] ?? 0),
      'icon' => 'fa-users',
      'url' => url('admin/users'),
      'tone' => 'blue',
      'meta' => number_format($stats['new_users_today'] ?? 0) . ' joined today',
    ],
    [
      'label' => 'Active Users',
      'value' => number_format($stats['active_users'] ?? 0),
      'icon' => 'fa-user-check',
      'url' => url('admin/users'),
      'tone' => 'green',
      'meta' => number_format($stats['inactive_users'] ?? 0) . ' inactive',
    ],
    [
      'label' => 'Wallet Balance',
      'value' => number_format((float) ($stats['total_wallet_balance'] ?? 0), 4),
      'icon' => 'fa-wallet',
      'url' => url('admin/users/transactions'),
      'tone' => 'cyan',
      'meta' => 'BRC across user wallets',
    ],
    [
      'label' => 'Pro Users',
      'value' => number_format($stats['pro_users'] ?? 0),
      'icon' => 'fa-crown',
      'url' => url('admin/subscriptions'),
      'tone' => 'green',
      'meta' => '$' . number_format((float) ($stats['subscription_revenue_month'] ?? 0), 2) . ' this month',
    ],
    [
      'label' => 'Tasks',
      'value' => number_format($stats['total_tasks'] ?? 0),
      'icon' => 'fa-list-check',
      'url' => url('admin/manage/tasks'),
      'tone' => 'orange',
      'meta' => number_format($stats['claimed_tasks'] ?? 0) . ' task claims',
    ],
  ];
@endphp

<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-md-7">
          <h1>Dashboard</h1>
          <p>Monitor users, rewards, wallet activity, content, and app operations from one place.</p>
        </div>
        <div class="col-md-5">
          <div class="admin-header-actions">
            <a href="{{ url('admin/add/push-notification') }}" class="btn btn-admin-primary">
              <i class="fa-solid fa-paper-plane"></i>
              Send Push
            </a>
            <a href="{{ url('admin/add/tasks') }}" class="btn btn-admin-muted">
              <i class="fa-solid fa-plus"></i>
              Add Task
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="content admin-dashboard">
    <div class="container-fluid">
      @include('flash/flash-message')

      <div class="admin-panel-header mb-2">
        <div><h2>KYC &amp; Verification</h2></div>
      </div>
      <div class="row">
        @foreach([
          ['label' => 'KYC Approved', 'key' => 'kyc_approved', 'status' => 'approved', 'icon' => 'fa-id-card', 'tone' => 'green'],
          ['label' => 'Payment Pending', 'key' => 'payment_pending', 'status' => 'payment_pending', 'icon' => 'fa-credit-card', 'tone' => 'orange'],
          ['label' => 'Verified Members', 'key' => 'verified', 'status' => 'verified', 'icon' => 'fa-shield-halved', 'tone' => 'green'],
          ['label' => 'KYC In Progress', 'key' => 'in_progress', 'status' => 'in_progress', 'icon' => 'fa-hourglass-half', 'tone' => 'cyan'],
          ['label' => 'KYC Waiting List', 'key' => 'waitlisted', 'url' => url('admin/verified-members/waitlist'), 'icon' => 'fa-list-ol', 'tone' => 'blue'],
        ] as $card)
          <div class="col-xl-2 col-md-4 col-6 mb-3">
            <a class="admin-stat-card admin-stat-{{ $card['tone'] }}" href="{{ $card['url'] ?? route('admin.verified-members.index', ['status' => $card['status']]) }}">
              <span class="admin-stat-icon"><i class="fa-solid {{ $card['icon'] }}"></i></span>
              <span class="admin-stat-copy">
                <span class="admin-stat-label">{{ $card['label'] }}</span>
                <strong>{{ number_format($verificationStats[$card['key']] ?? 0) }}</strong>
              </span>
            </a>
          </div>
        @endforeach
      </div>

      <div class="row">
        @foreach($statCards as $card)
          <div class="col-xl-{{ count($statCards) > 4 ? '2' : '3' }} col-md-6 mb-4">
            <a class="admin-stat-card admin-stat-{{ $card['tone'] }}" href="{{ $card['url'] }}">
              <span class="admin-stat-icon"><i class="fa-solid {{ $card['icon'] }}"></i></span>
              <span class="admin-stat-copy">
                <span class="admin-stat-label">{{ $card['label'] }}</span>
                <strong>{{ $card['value'] }}</strong>
                <small>{{ $card['meta'] }}</small>
              </span>
            </a>
          </div>
        @endforeach
      </div>

      <div class="row">
        <div class="col-xl-8 mb-4">
          <div class="admin-panel">
            <div class="admin-panel-header">
              <div>
                <h2>Subscription Statistics</h2>
                <p>RevenueCat Pro access, product mix, and expiry state.</p>
              </div>
              <a href="{{ url('admin/subscriptions') }}" class="admin-panel-link">View all</a>
            </div>
            <div class="row">
              <div class="col-md-3 col-6 mb-3">
                <div class="admin-subscription-metric">
                  <span>Total Pro Users</span>
                  <strong>{{ number_format($subscriptionStats['total_pro_users'] ?? 0) }}</strong>
                </div>
              </div>
              <div class="col-md-3 col-6 mb-3">
                <div class="admin-subscription-metric">
                  <span>Active Subs</span>
                  <strong>{{ number_format($subscriptionStats['active_subscriptions'] ?? 0) }}</strong>
                </div>
              </div>
              <div class="col-md-3 col-6 mb-3">
                <div class="admin-subscription-metric">
                  <span>Revenue Month</span>
                  <strong>${{ number_format((float) ($subscriptionStats['revenue_this_month'] ?? 0), 2) }}</strong>
                </div>
              </div>
              <div class="col-md-3 col-6 mb-3">
                <div class="admin-subscription-metric">
                  <span>Expired</span>
                  <strong>{{ number_format($subscriptionStats['expired_users'] ?? 0) }}</strong>
                </div>
              </div>
            </div>
            <div class="admin-plan-breakdown">
              @foreach(['monthly' => 'Monthly', 'three_month' => 'Three Month', 'six_month' => 'Six Month', 'yearly' => 'Yearly'] as $key => $label)
                <span>
                  <strong>{{ number_format($subscriptionStats['plan_counts'][$key] ?? 0) }}</strong>
                  {{ $label }}
                </span>
              @endforeach
            </div>
          </div>
        </div>

        <div class="col-xl-4 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header">
              <div>
                <h2>Recent Pro Activity</h2>
                <p>Latest RevenueCat subscription updates.</p>
              </div>
            </div>
            <div class="admin-mini-list">
              @forelse(($subscriptionStats['recent'] ?? collect()) as $subscription)
                <a href="{{ url('admin/subscriptions') }}" class="admin-mini-row">
                  <span class="admin-mini-avatar">{{ strtoupper(substr($subscription->username ?? $subscription->user_name ?? 'P', 0, 1)) }}</span>
                  <span>
                    <strong>{{ $subscription->username ?? $subscription->user_name ?? 'User #' . $subscription->user_id }}</strong>
                    <small>{{ $subscription->plan_name ?? $subscription->product_id }} · {{ ucfirst($subscription->status) }}</small>
                  </span>
                </a>
              @empty
                <div class="admin-empty">No RevenueCat subscription events yet.</div>
              @endforelse
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-xl-8 mb-4">
          <div class="admin-panel">
            <div class="admin-panel-header">
              <div>
                <h2>Recent Users</h2>
                <p>Newest registrations and activation state.</p>
              </div>
              <a href="{{ url('admin/users') }}" class="admin-panel-link">View all</a>
            </div>
            <div class="table-responsive">
              <table class="table admin-table">
                <thead>
                  <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Joined</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($recentUsers as $user)
                    <tr>
                      <td>
                        <div class="admin-user-cell">
                          <span>{{ strtoupper(substr($user->username ?? $user->name ?? 'U', 0, 1)) }}</span>
                          <div>
                            <strong>{{ $user->username ?? $user->name ?? 'User #' . $user->id }}</strong>
                            <small>ID {{ $user->id }}</small>
                          </div>
                        </div>
                      </td>
                      <td>{{ $user->email ?? 'Not set' }}</td>
                      <td>
                        <span class="admin-badge {{ (int) ($user->is_active ?? 0) === 1 ? 'admin-badge-success' : 'admin-badge-muted' }}">
                          {{ (int) ($user->is_active ?? 0) === 1 ? 'Active' : 'Inactive' }}
                        </span>
                      </td>
                      <td>{{ $user->created_at ? \Carbon\Carbon::parse($user->created_at)->format('d M Y') : '-' }}</td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="4" class="admin-empty">No user records available.</td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-xl-4 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header">
              <div>
                <h2>Quick Actions</h2>
                <p>Common operational tasks.</p>
              </div>
            </div>
            <div class="admin-action-list">
              @foreach($quickActions as $action)
                <a href="{{ $action['url'] }}" class="admin-action-item admin-action-{{ $action['tone'] }}">
                  <i class="fa-solid {{ $action['icon'] }}"></i>
                  <span>{{ $action['label'] }}</span>
                  <i class="fa-solid fa-arrow-right"></i>
                </a>
              @endforeach
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-xl-8 mb-4">
          <div class="admin-panel">
            <div class="admin-panel-header">
              <div>
                <h2>Top Wallets</h2>
                <p>Highest BRC balances currently tracked.</p>
              </div>
              <a href="{{ url('admin/users/transactions') }}" class="admin-panel-link">Transactions</a>
            </div>
            <div class="admin-wallet-list">
              @forelse($topWallets as $wallet)
                <div class="admin-wallet-row">
                  <div class="admin-user-cell">
                    <span>{{ strtoupper(substr($wallet->username ?? $wallet->name ?? 'W', 0, 1)) }}</span>
                    <div>
                      <strong>{{ $wallet->username ?? $wallet->name ?? 'Wallet #' . $wallet->userid }}</strong>
                      <small>{{ $wallet->email ?? 'User ID ' . $wallet->userid }}</small>
                    </div>
                  </div>
                  <strong>{{ number_format((float) $wallet->totaltoken, 4) }} BRC</strong>
                </div>
              @empty
                <div class="admin-empty">No wallet records available.</div>
              @endforelse
            </div>
          </div>
        </div>

        <div class="col-xl-4 mb-4">
          <div class="admin-panel admin-health-panel">
            <div class="admin-panel-header">
              <div>
                <h2>Operations Health</h2>
                <p>Security and deployment reminders.</p>
              </div>
            </div>
            <ul class="admin-health-list">
              <li>
                <i class="fa-solid fa-shield-halved"></i>
                <span>Reward amounts are server controlled.</span>
              </li>
              <li>
                <i class="fa-solid fa-key"></i>
                <span>Rotate provider secrets before production deploy.</span>
              </li>
              <li>
                <i class="fa-solid fa-database"></i>
                <span>{{ number_format($stats['pending_delete_requests'] ?? 0) }} delete account requests tracked.</span>
              </li>
              <li>
                <i class="fa-solid fa-bolt"></i>
                <span>{{ number_format($stats['pending_boost_purchases'] ?? 0) }} boost purchases waiting for payment review.</span>
              </li>
              <li>
                <i class="fa-solid fa-clock"></i>
                <span>Last viewed {{ now()->format('d M Y, h:i A') }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>
</div>

@include('admin.includes.footer')
