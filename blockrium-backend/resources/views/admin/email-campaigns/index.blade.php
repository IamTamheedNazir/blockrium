@include('admin.includes.header')
@include('admin.includes.sidebar')

<style>
  .email-campaign-page { padding-bottom: 32px; }
  .email-campaign-page .admin-panel { background:#fff; border:1px solid #e5eaf1; border-radius:14px; padding:22px; box-shadow:0 8px 24px rgba(17,27,46,.04); }
  .email-campaign-page .page-intro { margin-bottom:20px; }
  .email-campaign-page .page-intro h1 { margin-bottom:6px; font-weight:750; }
  .email-campaign-page .page-intro p { color:#657187; margin:0; }
  .email-campaign-page .audience-card { background:#f3f8f7; border:1px solid #d5eae6; border-radius:12px; padding:16px; }
  .email-campaign-page .audience-count { display:block; color:#087f73; font-size:28px; line-height:1.1; font-weight:800; }
  .email-campaign-page .form-control { border-radius:8px; }
  .email-campaign-page .table td, .email-campaign-page .table th { vertical-align:middle; }
  .email-campaign-page .campaign-status { display:inline-block; padding:4px 9px; border-radius:999px; background:#eef1f5; color:#46546a; font-size:12px; font-weight:700; }
  .email-campaign-page .campaign-status.completed { background:#e4f5ed; color:#13734d; }
  .email-campaign-page .campaign-status.queue_failed { background:#fdeceb; color:#a33d35; }
</style>

<div class="content-wrapper admin-content email-campaign-page">
  <section class="content-header admin-page-header">
    <div class="container-fluid page-intro">
      <h1>Batch email campaigns</h1>
      <p>Send factual Blockrium account updates to selected existing-user audiences in controlled batches.</p>
    </div>
  </section>

  <section class="content"><div class="container-fluid">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="row">
      <div class="col-lg-4 mb-3">
        <div class="audience-card h-100">
          <span class="text-uppercase small font-weight-bold">Eligible accounts</span>
          <p class="mb-1 mt-2">Enabled accounts, including app-inactive users: <strong>{{ number_format($eligibleCounts['all_active']) }}</strong></p>
          <p class="mb-1">Pending KYC: <strong>{{ number_format($eligibleCounts['pending_kyc']) }}</strong></p>
          <p class="mb-0 mt-2 small text-muted">Counts exclude opted-out accounts and accounts without an email. Invalid addresses are skipped during preparation.</p>
        </div>
      </div>
      <div class="col-lg-8 mb-3">
        <div class="admin-panel h-100">
          <strong>Delivery queue</strong>
          <p class="mb-1 mt-2">Configured connection: <code>{{ $queueConnection ?: 'not set' }}</code></p>
          @if($queueReady)
            <p class="mb-0 text-success"><i class="fa-solid fa-circle-check mr-1"></i> Asynchronous queue selected. Confirm that a worker is running before queuing a campaign.</p>
          @else
            <p class="mb-0 text-danger"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Sending is disabled until an asynchronous queue and worker are configured. This prevents a batch from sending inside an admin page request.</p>
          @endif
        </div>
      </div>
    </div>

    <div class="admin-panel mb-4">
      <h2 class="h5 mb-1">Shared email footer</h2>
      <p class="small text-muted">Set these once. Campaigns and test emails reuse the saved links automatically.</p>
      <form method="post" action="{{ route('admin.email-campaigns.brand-settings') }}">
        @csrf
        <div class="form-row">
          <div class="form-group col-md-6"><label for="brand-website">Website</label><input id="brand-website" name="website_url" type="url" class="form-control" value="{{ old('website_url', $brandSettings['website_url']) }}" required></div>
          <div class="form-group col-md-6"><label for="brand-app">Android app</label><input id="brand-app" name="app_url" type="url" class="form-control" value="{{ old('app_url', $brandSettings['app_url']) }}" required></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4"><label for="brand-telegram">Telegram</label><input id="brand-telegram" name="telegram_url" type="url" class="form-control" value="{{ old('telegram_url', $brandSettings['telegram_url']) }}" placeholder="Optional"></div>
          <div class="form-group col-md-4"><label for="brand-instagram">Instagram</label><input id="brand-instagram" name="instagram_url" type="url" class="form-control" value="{{ old('instagram_url', $brandSettings['instagram_url']) }}" placeholder="Optional"></div>
          <div class="form-group col-md-4"><label for="brand-facebook">Facebook</label><input id="brand-facebook" name="facebook_url" type="url" class="form-control" value="{{ old('facebook_url', $brandSettings['facebook_url']) }}" placeholder="Optional"></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4"><label for="brand-x">X</label><input id="brand-x" name="x_url" type="url" class="form-control" value="{{ old('x_url', $brandSettings['x_url']) }}" placeholder="Optional"></div>
          <div class="form-group col-md-4"><label for="brand-youtube">YouTube</label><input id="brand-youtube" name="youtube_url" type="url" class="form-control" value="{{ old('youtube_url', $brandSettings['youtube_url']) }}" placeholder="Optional"></div>
          <div class="form-group col-md-4"><label for="brand-support">Support email</label><input id="brand-support" name="support_email" type="email" class="form-control" value="{{ old('support_email', $brandSettings['support_email']) }}" placeholder="Optional"></div>
        </div>
        <button class="btn btn-admin-muted" type="submit">Save shared footer links</button>
      </form>
    </div>

    <div class="admin-panel mb-4">
      <h2 class="h5 mb-3">Create an account-update draft</h2>
      <form method="post" action="{{ route('admin.email-campaigns.store') }}">
        @csrf
        <div class="form-row">
          <div class="form-group col-md-5"><label for="campaign-name">Internal campaign name</label><input id="campaign-name" name="name" class="form-control" maxlength="160" value="{{ old('name') }}" required placeholder="October KYC reminder"></div>
          <div class="form-group col-md-7"><label for="campaign-subject">Email subject</label><input id="campaign-subject" name="subject" class="form-control" maxlength="190" value="{{ old('subject') }}" required placeholder="Please complete your Blockrium verification"></div>
        </div>
        <div class="form-group"><label for="campaign-body">Message</label><textarea id="campaign-body" name="body" class="form-control" rows="5" maxlength="5000" required placeholder="Remind the user why verification is needed and how to continue.">{{ old('body') }}</textarea><small class="form-text text-muted">Plain text only. It will be escaped before rendering. Never ask users to email identity documents.</small></div>
        <div class="form-row">
          <div class="form-group col-md-6"><label for="campaign-audience">Recipient audience</label><select id="campaign-audience" name="audience" class="form-control" required><option value="all_active" {{ old('audience', 'all_active') === 'all_active' ? 'selected' : '' }}>All enabled accounts (including app-inactive users)</option><option value="pending_kyc" {{ old('audience') === 'pending_kyc' ? 'selected' : '' }}>Pending KYC</option></select></div>
          <div class="form-group col-md-6"><label for="campaign-url">Secure action destination</label><input id="campaign-url" name="action_url" type="url" class="form-control" maxlength="2048" value="{{ old('action_url') }}" required placeholder="https://…"><small class="form-text text-muted">Must use HTTPS.</small></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4"><label for="campaign-batch">Recipients per batch</label><input id="campaign-batch" name="batch_size" type="number" class="form-control" min="100" max="1000" value="{{ old('batch_size', 250) }}" required></div>
          <div class="form-group col-md-4"><label for="campaign-daily-cap">Total daily cap</label><input id="campaign-daily-cap" name="daily_cap" type="number" class="form-control" min="100" max="1000" value="{{ old('daily_cap', 500) }}" required></div>
          <div class="form-group col-md-4"><label for="campaign-interval">Repeat interval</label><select id="campaign-interval" name="repeat_interval_hours" class="form-control" required><option value="24" {{ old('repeat_interval_hours', '24') == '24' ? 'selected' : '' }}>Every 24 hours</option><option value="12" {{ old('repeat_interval_hours') == '12' ? 'selected' : '' }}>Every 12 hours</option></select></div>
        </div>
        <button class="btn btn-admin-primary" type="submit"><i class="fa-solid fa-floppy-disk mr-1"></i> Save draft</button>
      </form>
    </div>

    <div class="admin-panel">
      <div class="d-flex flex-wrap align-items-center justify-content-between mb-3"><div><h2 class="h5 mb-1">Campaign history</h2><p class="text-muted mb-0 small">Recipient email addresses are not shown on this page.</p></div></div>
      <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Campaign</th><th>Audience</th><th>Status</th><th>Recipients</th><th>Accepted</th><th>Pre-send failures</th><th>Outcome unknown</th><th>Skipped</th><th>Created</th><th>Action</th></tr></thead>
        <tbody>
        @forelse($campaigns as $campaign)
          <tr>
            <td><a href="{{ route('admin.email-campaigns.show', $campaign->id) }}"><strong>{{ $campaign->name }}</strong></a><br><small class="text-muted">{{ $campaign->subject }}</small></td>
            <td>{{ $campaign->audience === 'pending_kyc' ? 'Pending KYC' : 'All enabled accounts' }}<br><small class="text-muted">{{ $campaign->batch_size ?? 250 }}/batch · {{ $campaign->daily_cap ?? 500 }}/day · {{ $campaign->repeat_interval_hours ?? 24 }}h</small></td>
            <td><span class="campaign-status {{ $campaign->status }}">{{ ucfirst(str_replace('_', ' ', $campaign->status)) }}</span></td>
            <td>{{ number_format($campaign->recipient_count) }}</td>
            <td>{{ number_format($campaign->sent_count) }}</td>
            <td>{{ number_format($campaign->failed_count) }}</td>
            <td>{{ number_format($campaign->delivery_unknown_count) }}</td>
            <td>{{ number_format($campaign->skipped_count) }}</td>
            <td>{{ $campaign->created_at }}</td>
            <td class="text-nowrap">
              <form method="post" action="{{ route('admin.email-campaigns.test', $campaign->id) }}" class="mb-2">
                @csrf
                <div class="input-group input-group-sm"><input type="email" name="test_email" class="form-control" maxlength="254" placeholder="Test email address" required><div class="input-group-append"><button class="btn btn-admin-muted" type="submit">Send test</button></div></div>
              </form>
              @if($campaign->status === 'draft' || $campaign->status === 'queue_failed')
                <form method="post" action="{{ route('admin.email-campaigns.queue', $campaign->id) }}" class="d-inline" onsubmit="return confirm('Prepare this factual account update for the selected audience and begin capped delivery?');">@csrf<input type="hidden" name="confirmation" value="SEND"><label class="d-block small mb-1"><input type="checkbox" name="service_basis" value="1" required> Existing-account service update, not unrelated promotion</label><button class="btn btn-sm btn-admin-primary" type="submit" {{ !$queueReady ? 'disabled' : '' }}>Queue send</button></form>
              @endif
              @if((int) $campaign->failed_count > 0 && $campaign->status === 'completed')
                <form method="post" action="{{ route('admin.email-campaigns.retry-failed', $campaign->id) }}" class="d-inline" onsubmit="return confirm('Retry only confirmed pre-send failures?');">@csrf<input type="hidden" name="confirmation" value="RETRY"><button class="btn btn-sm btn-outline-warning" type="submit" {{ !$queueReady ? 'disabled' : '' }}>Retry pre-send failures</button></form>
              @endif
              @if(!in_array($campaign->status, ['draft', 'queue_failed', 'completed'], true))<span class="small text-muted">Processing</span>@endif
            </td>
          </tr>
        @empty
          <tr><td colspan="9" class="text-center text-muted py-4">No campaigns yet. Saving a draft does not send email.</td></tr>
        @endforelse
        </tbody>
      </table></div>
      {{ $campaigns->links('vendor.pagination.custom') }}
    </div>
  </div></section>
</div>

@include('admin.includes.footer')
