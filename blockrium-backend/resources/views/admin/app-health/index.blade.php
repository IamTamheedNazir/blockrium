@include('admin.includes.header')
@include('admin.includes.sidebar')

@php
  $statCards = [
    ['label' => 'Total Issues', 'value' => $stats['total'], 'hint' => 'reported by app devices', 'tone' => 'primary', 'icon' => 'fa-triangle-exclamation'],
    ['label' => 'Errors', 'value' => $stats['error_count'], 'hint' => $stats['fatal_count'] . ' fatal', 'tone' => 'orange', 'icon' => 'fa-circle-xmark'],
    ['label' => 'Timeouts', 'value' => $stats['timeout_count'], 'hint' => 'network/API waits', 'tone' => 'cyan', 'icon' => 'fa-clock'],
    ['label' => 'Users Hit', 'value' => $stats['unique_users'], 'hint' => 'logged-in users only', 'tone' => 'green', 'icon' => 'fa-users'],
  ];
  $healthCards = [
    ['label' => 'Login / Signup', 'value' => $stats['auth_count'], 'hint' => 'auth failures'],
    ['label' => 'Mining', 'value' => $stats['mining_count'], 'hint' => 'start/timer failures'],
    ['label' => 'Rewards', 'value' => $stats['reward_count'], 'hint' => 'streak, chest, ads'],
    ['label' => 'User Reports', 'value' => $stats['user_reports'], 'hint' => 'sent from Help Center'],
    ['label' => 'Latest Issue', 'value' => $stats['latest_at'] ? \Carbon\Carbon::parse($stats['latest_at'])->format('d M, h:i A') : 'None', 'hint' => 'server time'],
  ];
@endphp

<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h1>App Health</h1>
          <p>Live app issue reports from Android users, API calls, login, mining, streaks, rewards, and versions.</p>
        </div>
        <div class="col-md-4">
          <div class="admin-header-actions">
            <span class="btn btn-admin-muted"><i class="fa-solid fa-rotate"></i>{{ now()->format('d M Y, h:i A') }}</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      @unless($tableReady)
        <div class="alert alert-warning">
          App issue logging table is not installed yet. Run the app issue log migration on the server.
        </div>
      @endunless

      <div class="admin-panel mb-4">
        <form method="GET">
          <div class="row align-items-end">
            <div class="col-lg-2 col-md-4">
              <div class="form-group">
                <label>Period</label>
                <select name="period" class="form-control">
                  <option value="24h" @if($period === '24h') selected @endif>Last 24 hours</option>
                  <option value="7d" @if($period === '7d') selected @endif>Last 7 days</option>
                  <option value="30d" @if($period === '30d') selected @endif>Last 30 days</option>
                </select>
              </div>
            </div>
            <div class="col-lg-2 col-md-4">
              <div class="form-group">
                <label>Severity</label>
                <select name="severity" class="form-control">
                  <option value="">All</option>
                  @foreach(['info', 'warning', 'error', 'fatal'] as $severity)
                    <option value="{{ $severity }}" @if(request('severity') === $severity) selected @endif>{{ ucfirst($severity) }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-lg-2 col-md-4">
              <div class="form-group">
                <label>Source</label>
                <select name="source" class="form-control">
                  <option value="">All</option>
                  @foreach(['app', 'api', 'api_latency', 'react', 'javascript', 'user_report'] as $source)
                    <option value="{{ $source }}" @if(request('source') === $source) selected @endif>{{ str_replace('_', ' ', ucfirst($source)) }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-lg-3 col-md-4">
              <div class="form-group">
                <label>Endpoint</label>
                <input class="form-control" name="endpoint" value="{{ request('endpoint') }}" placeholder="auth/click-button">
              </div>
            </div>
            <div class="col-lg-2 col-md-4">
              <div class="form-group">
                <label>Action</label>
                <input class="form-control" name="action" value="{{ request('action') }}" placeholder="mining_start">
              </div>
            </div>
            <div class="col-lg-2 col-md-4">
              <div class="form-group d-flex">
                <button class="btn btn-admin-primary mr-2" type="submit"><i class="fa-solid fa-filter"></i>Filter</button>
                <a class="btn btn-admin-muted" href="{{ route('admin.app-health') }}">Reset</a>
              </div>
            </div>
          </div>
        </form>
      </div>

      <div class="row">
        @foreach($statCards as $card)
          <div class="col-xl-3 col-md-6 mb-4">
            <div class="admin-stat-card admin-stat-{{ $card['tone'] }} h-100">
              <span class="admin-stat-icon"><i class="fa-solid {{ $card['icon'] }}"></i></span>
              <span class="admin-stat-copy">
                <span class="admin-stat-label">{{ $card['label'] }}</span>
                <strong>{{ is_numeric($card['value']) ? number_format($card['value']) : $card['value'] }}</strong>
                <small>{{ $card['hint'] }}</small>
              </span>
            </div>
          </div>
        @endforeach
      </div>

      <div class="row">
        @foreach($healthCards as $card)
          <div class="col-xl-3 col-md-6 mb-4">
            <div class="admin-panel h-100">
              <span class="admin-stat-label">{{ $card['label'] }}</span>
              <h2 class="mb-1">{{ is_numeric($card['value']) ? number_format($card['value']) : $card['value'] }}</h2>
              <small>{{ $card['hint'] }}</small>
            </div>
          </div>
        @endforeach
      </div>

      <div class="row">
        <div class="col-xl-3 col-lg-6 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header"><div><h2>Top Endpoints</h2><p>Most reported API paths.</p></div></div>
            <table class="table admin-table">
              <tbody>
                @forelse($topEndpoints as $row)
                  <tr><td><code>{{ $row->endpoint }}</code></td><td class="text-right">{{ number_format($row->total) }}</td></tr>
                @empty
                  <tr><td class="admin-empty">No endpoint reports.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        <div class="col-xl-3 col-lg-6 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header"><div><h2>Top Actions</h2><p>User flows where failures happen.</p></div></div>
            <table class="table admin-table">
              <tbody>
                @forelse($topActions as $row)
                  <tr><td>{{ $row->action }}</td><td class="text-right">{{ number_format($row->total) }}</td></tr>
                @empty
                  <tr><td class="admin-empty">No action reports.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        <div class="col-xl-3 col-lg-6 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header"><div><h2>App Versions</h2><p>Find broken builds quickly.</p></div></div>
            <table class="table admin-table">
              <tbody>
                @forelse($topVersions as $row)
                  <tr><td>{{ $row->app_version }}</td><td class="text-right">{{ number_format($row->total) }}</td></tr>
                @empty
                  <tr><td class="admin-empty">No version reports.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
        <div class="col-xl-3 col-lg-6 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header"><div><h2>Slow APIs</h2><p>Average latency by endpoint.</p></div></div>
            <table class="table admin-table">
              <tbody>
                @forelse($topSlowEndpoints as $row)
                  <tr>
                    <td><code>{{ $row->endpoint }}</code><br><small>{{ number_format((float) $row->average_duration_ms, 0) }} ms avg / {{ number_format((int) $row->max_duration_ms) }} ms max</small></td>
                    <td class="text-right">{{ number_format((int) $row->total) }}<br><small>reports</small></td>
                  </tr>
                @empty
                  <tr><td class="admin-empty">No latency reports.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="admin-panel mb-4">
        <div class="admin-panel-header">
          <div>
            <h2>Recent App Issues</h2>
            <p>Sanitized reports only. Passwords, tokens, and secrets are redacted before storage.</p>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table admin-table">
            <thead>
              <tr>
                <th>Time</th>
                <th>User</th>
                <th>Action</th>
                <th>Source</th>
                <th>Endpoint</th>
                <th>Status</th>
                <th>Version</th>
                <th>Device</th>
                <th>Message</th>
                <th>Diagnostics</th>
              </tr>
            </thead>
            <tbody>
              @if($tableReady)
                @forelse($recentIssues as $issue)
                  <tr>
                    <td>{{ $issue->created_at ? \Carbon\Carbon::parse($issue->created_at)->format('d M, h:i A') : '-' }}</td>
                    <td>
                      @if($issue->user_id)
                        <strong>{{ $issue->username ?: 'User #' . $issue->user_id }}</strong><br>
                        <small>{{ $issue->email }}</small>
                      @else
                        <span class="admin-badge admin-badge-muted">Guest</span>
                      @endif
                    </td>
                    <td>{{ $issue->action ?: '-' }}</td>
                    <td>{{ str_replace('_', ' ', $issue->source ?: '-') }}</td>
                    <td><code>{{ $issue->endpoint ?: '-' }}</code></td>
                    <td>
                      <span class="admin-badge {{ in_array($issue->severity, ['error', 'fatal']) ? 'admin-badge-muted' : 'admin-badge-success' }}">{{ ucfirst($issue->severity) }}</span>
                      <br><small>{{ $issue->http_status ?: '-' }}</small>
                    </td>
                    <td>{{ $issue->app_version ?: '-' }}<br><small>{{ $issue->build_number ?: '' }}</small></td>
                    <td>{{ $issue->platform ?: '-' }}<br><small>{{ $issue->device_model ?: '' }} {{ $issue->os_version ?: '' }}</small></td>
                    <td>
                      {{ $issue->message ?: '-' }}
                      @if($issue->request_duration_ms)
                        <br><small>{{ number_format($issue->request_duration_ms) }} ms</small>
                      @endif
                    </td>
                    <td>
                      <small>{{ $issue->error_code ?: 'No error code' }}</small>
                      @if(!empty($issue->metadata))
                        <details class="mt-1">
                          <summary>View stack and metadata</summary>
                          <pre class="mt-2 mb-0" style="max-width: 420px; white-space: pre-wrap; word-break: break-word;">{{ json_encode($issue->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="10" class="admin-empty">No app issues reported for this filter.</td></tr>
                @endforelse
              @else
                <tr><td colspan="10" class="admin-empty">Install the migration to start collecting reports.</td></tr>
              @endif
            </tbody>
          </table>
        </div>
        @if($tableReady)
          {{ $recentIssues->links() }}
        @endif
      </div>
    </div>
  </section>
</div>

@include('admin.includes.footer')
