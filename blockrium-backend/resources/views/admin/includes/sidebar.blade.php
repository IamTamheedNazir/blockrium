@php
  $navGroups = [
    [
      'label' => 'Overview',
      'icon' => 'fa-gauge-high',
      'items' => [
        ['label' => 'Dashboard', 'url' => url('/dashboard'), 'match' => 'dashboard', 'icon' => 'fa-chart-line'],
        ['label' => 'Logs', 'url' => url('admin/logs'), 'match' => 'admin/logs*', 'icon' => 'fa-file-lines'],
      ],
    ],
    [
      'label' => 'Users & Wallets',
      'icon' => 'fa-users',
      'items' => [
        ['label' => 'Manage Users', 'url' => url('admin/users'), 'match' => 'admin/users', 'icon' => 'fa-user-group'],
        ['label' => 'Transactions', 'url' => url('admin/users/transactions'), 'match' => 'admin/users/transactions', 'icon' => 'fa-wallet'],
        ['label' => 'Wallet & Reward Monitor', 'url' => url('admin/wallet-monitor'), 'match' => 'admin/wallet-monitor*', 'icon' => 'fa-chart-line'],
        ['label' => 'Add Coin to All', 'url' => url('admin/add/addcoin'), 'match' => 'admin/add/addcoin', 'icon' => 'fa-coins'],
        ['label' => 'Add Coin to User', 'url' => url('admin/add/single-user-addcoin'), 'match' => 'admin/add/single-user-addcoin', 'icon' => 'fa-circle-plus'],
      ],
    ],
    [
      'label' => 'Growth',
      'icon' => 'fa-seedling',
      'items' => [
        ['label' => 'Partner Campaigns', 'url' => url('admin/partner-campaigns'), 'match' => 'admin/partner-campaigns*', 'icon' => 'fa-handshake'],
        ['label' => 'Promo Code Distribution', 'url' => url('admin/partner-promo-codes'), 'match' => 'admin/partner-promo-codes*', 'icon' => 'fa-ticket'],
        ['label' => 'Verified Members', 'url' => url('admin/verified-members'), 'match' => 'admin/verified-members*', 'icon' => 'fa-circle-check'],
        ['label' => 'KYC Waiting List', 'url' => url('admin/verified-members/waitlist'), 'match' => 'admin/verified-members/waitlist', 'icon' => 'fa-list-ol'],
        ['label' => 'Membership Payment Support', 'url' => url('admin/verified-members/payment-support'), 'match' => 'admin/verified-members/payment-support*', 'icon' => 'fa-credit-card'],
        ['label' => 'Verification Settings', 'url' => url('admin/verified-members/settings'), 'match' => 'admin/verified-members/settings', 'icon' => 'fa-key'],
        ['label' => 'Referral Settings', 'url' => url('admin/referral/settings'), 'match' => 'admin/referral/settings', 'icon' => 'fa-sliders'],
        ['label' => 'Referral Missions', 'url' => url('admin/referral/missions'), 'match' => 'admin/referral/missions', 'icon' => 'fa-route'],
        ['label' => 'Referral Milestones', 'url' => url('admin/engagement/referral-milestones'), 'match' => 'admin/engagement/referral-milestones*', 'icon' => 'fa-flag-checkered'],
        ['label' => 'Referral Claims', 'url' => url('admin/engagement/referral-claims'), 'match' => 'admin/engagement/referral-claims', 'icon' => 'fa-receipt'],
        ['label' => 'Squads', 'url' => url('admin/engagement/squads'), 'match' => 'admin/engagement/squads*', 'icon' => 'fa-people-group'],
      ],
    ],
    [
      'label' => 'Gamification',
      'icon' => 'fa-chart-simple',
      'items' => [
        ['label' => 'Engagement Dashboard', 'url' => url('admin/engagement'), 'match' => 'admin/engagement', 'icon' => 'fa-chart-pie'],
        ['label' => 'Daily Missions', 'url' => url('admin/engagement/daily-missions'), 'match' => 'admin/engagement/daily-missions', 'icon' => 'fa-list-check'],
        ['label' => 'XP Events', 'url' => url('admin/engagement/xp-events'), 'match' => 'admin/engagement/xp-events', 'icon' => 'fa-sparkles'],
        ['label' => 'Badges', 'url' => url('admin/engagement/badges'), 'match' => 'admin/engagement/badges*', 'icon' => 'fa-award'],
        ['label' => 'User Badges', 'url' => url('admin/engagement/user-badges'), 'match' => 'admin/engagement/user-badges', 'icon' => 'fa-medal'],
        ['label' => 'Seasons', 'url' => url('admin/engagement/seasons'), 'match' => 'admin/engagement/seasons*', 'icon' => 'fa-calendar-days'],
      ],
    ],
    [
      'label' => 'Rewards & Tasks',
      'icon' => 'fa-trophy',
      'items' => [
        ['label' => 'Manage Tasks', 'url' => url('admin/manage/tasks'), 'match' => 'admin/manage/tasks', 'icon' => 'fa-list-check'],
        ['label' => 'Add Task', 'url' => url('admin/add/tasks'), 'match' => 'admin/add/tasks', 'icon' => 'fa-plus'],
        ['label' => 'Boost Plans', 'url' => url('admin/manage/boost-plans'), 'match' => 'admin/manage/boost-plans', 'icon' => 'fa-bolt'],
        ['label' => 'Add Boost Plan', 'url' => url('admin/add/boost-plan'), 'match' => 'admin/add/boost-plan', 'icon' => 'fa-plus'],
        ['label' => 'Boost Purchases', 'url' => url('admin/manage/boost-purchases'), 'match' => 'admin/manage/boost-purchases', 'icon' => 'fa-receipt'],
        ['label' => 'Google Play Purchases', 'url' => url('admin/google-play/purchases'), 'match' => 'admin/google-play/purchases', 'icon' => 'fa-google-play'],
        ['label' => 'Subscriptions', 'url' => url('admin/subscriptions'), 'match' => 'admin/subscriptions', 'icon' => 'fa-crown'],
      ],
    ],
    [
      'label' => 'Learning',
      'icon' => 'fa-book-open',
      'items' => [
        ['label' => 'Trust Center', 'url' => url('admin/trust-center'), 'match' => 'admin/trust-center*', 'icon' => 'fa-shield-heart'],
        ['label' => 'Learn & Earn', 'url' => url('admin/engagement/learn'), 'match' => ['admin/engagement/learn', 'admin/engagement/learn/*'], 'icon' => 'fa-book-open'],
        ['label' => 'Learn Progress', 'url' => url('admin/engagement/learn-progress'), 'match' => 'admin/engagement/learn-progress', 'icon' => 'fa-graduation-cap'],
      ],
    ],
    [
      'label' => 'Content',
      'icon' => 'fa-layer-group',
      'items' => [
        ['label' => 'Banners', 'url' => url('admin/manage/banner'), 'match' => 'admin/manage/banner', 'icon' => 'fa-images'],
        ['label' => 'Add Banner', 'url' => url('admin/add/banner'), 'match' => 'admin/add/banner', 'icon' => 'fa-plus'],
        ['label' => 'News', 'url' => url('admin/manage/news'), 'match' => 'admin/manage/news', 'icon' => 'fa-newspaper'],
        ['label' => 'Add News', 'url' => url('admin/add/news'), 'match' => 'admin/add/news', 'icon' => 'fa-plus'],
        ['label' => 'Whitepaper', 'url' => url('admin/add/whitepaper'), 'match' => 'admin/add/whitepaper', 'icon' => 'fa-file-pdf'],
      ],
    ],
    [
      'label' => 'App Operations',
      'icon' => 'fa-mobile-screen',
      'items' => [
        ['label' => 'App Stage', 'url' => url('admin/manage/manage-stage'), 'match' => 'admin/manage/manage-stage', 'icon' => 'fa-toggle-on'],
        ['label' => 'App Version', 'url' => url('admin/manage/app-version'), 'match' => ['admin/manage/app-version', 'admin/add/app-version', 'admin/edit-app-version/*'], 'icon' => 'fa-code-branch'],
        ['label' => 'APK', 'url' => url('admin/manage/apk'), 'match' => ['admin/manage/apk', 'admin/add/apk'], 'icon' => 'fa-download'],
        ['label' => 'Maintenance', 'url' => url('admin/manage/app-maintenance'), 'match' => ['admin/manage/app-maintenance', 'admin/add/app-maintenance', 'admin/edit-app-maintenance/*'], 'icon' => 'fa-screwdriver-wrench'],
        ['label' => 'App Health', 'url' => url('admin/app-health'), 'match' => 'admin/app-health*', 'icon' => 'fa-bug'],
        ['label' => 'Production Health', 'url' => url('admin/engagement/health'), 'match' => 'admin/engagement/health', 'icon' => 'fa-heart-pulse'],
      ],
    ],
    [
      'label' => 'Games',
      'icon' => 'fa-gamepad',
      'items' => [
        ['label' => 'Game Categories', 'url' => url('admin/manage/game-category'), 'match' => 'admin/manage/game-category', 'icon' => 'fa-folder-tree'],
        ['label' => 'Add Category', 'url' => url('admin/add/game-category'), 'match' => 'admin/add/game-category', 'icon' => 'fa-plus'],
        ['label' => 'Manage Games', 'url' => url('admin/manage/game'), 'match' => 'admin/manage/game', 'icon' => 'fa-chess-board'],
        ['label' => 'Add Game', 'url' => url('admin/add/game'), 'match' => 'admin/add/game', 'icon' => 'fa-plus'],
      ],
    ],
    [
      'label' => 'Messaging',
      'icon' => 'fa-bell',
      'items' => [
        ['label' => 'Email Campaigns', 'url' => url('admin/email-campaigns'), 'match' => 'admin/email-campaigns*', 'icon' => 'fa-envelope-open-text'],
        ['label' => 'Push Notifications', 'url' => url('admin/add/push-notification'), 'match' => 'admin/add/push-notification', 'icon' => 'fa-paper-plane'],
      ],
    ],
    [
      'label' => 'Audit & Security',
      'icon' => 'fa-shield-halved',
      'items' => [
        ['label' => 'Mining Audit', 'url' => url('admin/audit/mining'), 'match' => 'admin/audit/mining*', 'icon' => 'fa-magnifying-glass-chart'],
      ],
    ],
  ];

  $matchesAdminNav = function ($match) {
    foreach ((array) $match as $pattern) {
      if (request()->is($pattern)) {
        return true;
      }
    }
    return false;
  };
@endphp

<aside class="main-sidebar admin-sidebar elevation-4">
  <a href="{{ url('/dashboard') }}" class="brand-link admin-brand">
    <span class="admin-brand-mark">B</span>
    <span class="brand-text">Blockrium</span>
  </a>

  <div class="sidebar">
    <div class="admin-sidebar-search">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="search" placeholder="Search menu" data-admin-nav-search>
    </div>

    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column admin-nav" data-widget="treeview" role="menu" data-accordion="true">
        @foreach($navGroups as $group)
          @php
            $groupActive = collect($group['items'])->contains(function ($item) use ($matchesAdminNav) {
              return $matchesAdminNav($item['match']);
            });
          @endphp
          <li class="nav-item {{ $groupActive ? 'menu-open' : '' }}" data-admin-nav-item>
            <a href="#" class="nav-link admin-nav-group {{ $groupActive ? 'is-open' : '' }}">
              <i class="nav-icon fa-solid {{ $group['icon'] }}"></i>
              <p>{{ $group['label'] }} <i class="right fas fa-angle-left"></i></p>
            </a>
            <ul class="nav nav-treeview">
              @foreach($group['items'] as $item)
                <li class="nav-item" data-admin-nav-item>
                  <a href="{{ $item['url'] }}" class="nav-link {{ $matchesAdminNav($item['match']) ? 'active' : '' }}">
                    <i class="nav-icon fa-solid {{ $item['icon'] }}"></i>
                    <p>{{ $item['label'] }}</p>
                  </a>
                </li>
              @endforeach
            </ul>
          </li>
        @endforeach
      </ul>
    </nav>
  </div>
</aside>
