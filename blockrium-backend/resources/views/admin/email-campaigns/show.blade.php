@include('admin.includes.header')
@include('admin.includes.sidebar')

<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header"><div class="container-fluid d-flex flex-wrap align-items-center justify-content-between">
    <div><h1>{{ $campaign->name }}</h1><p>{{ $campaign->subject }}</p></div>
    <a class="btn btn-admin-muted" href="{{ route('admin.email-campaigns.index') }}"><i class="fa-solid fa-arrow-left mr-1"></i> Campaigns</a>
  </div></section>
  <section class="content"><div class="container-fluid">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="admin-panel mb-3">
      <div class="row">
        <div class="col-sm-6 col-lg-2 mb-3"><small class="text-muted">Status</small><div><strong>{{ ucfirst(str_replace('_', ' ', $campaign->status)) }}</strong></div></div>
        <div class="col-sm-6 col-lg-2 mb-3"><small class="text-muted">Recipients</small><div><strong>{{ number_format($campaign->recipient_count) }}</strong></div></div>
        <div class="col-sm-6 col-lg-2 mb-3"><small class="text-muted">Accepted by SMTP</small><div><strong>{{ number_format($campaign->sent_count) }}</strong></div></div>
        <div class="col-sm-6 col-lg-2 mb-3"><small class="text-muted">Pre-send failures</small><div><strong>{{ number_format($campaign->failed_count) }}</strong></div></div>
        <div class="col-sm-6 col-lg-2 mb-3"><small class="text-muted">Outcome unknown</small><div><strong>{{ number_format($campaign->delivery_unknown_count) }}</strong></div></div>
        <div class="col-sm-6 col-lg-2 mb-3"><small class="text-muted">Skipped</small><div><strong>{{ number_format($campaign->skipped_count) }}</strong></div></div>
        <div class="col-sm-6 col-lg-2 mb-3"><small class="text-muted">Recipients opened*</small><div><strong>{{ number_format($trackingTotals->opened_recipients ?? 0) }}</strong></div></div>
        <div class="col-sm-6 col-lg-2 mb-3"><small class="text-muted">Recipients clicked</small><div><strong>{{ number_format($trackingTotals->clicked_recipients ?? 0) }}</strong></div></div>
        <div class="col-sm-6 col-lg-2 mb-3"><small class="text-muted">Queued at</small><div><strong>{{ $campaign->queued_at ?: '—' }}</strong></div></div>
      </div>
      <p class="small text-muted">{{ $campaign->audience === 'pending_kyc' ? 'Pending KYC' : 'All enabled accounts, including app-inactive users' }} · {{ $campaign->batch_size ?? 250 }} per batch · {{ $campaign->daily_cap ?? 500 }} daily max · repeats every {{ $campaign->repeat_interval_hours ?? 24 }} hours until all unique recipients are processed.</p>
      <p class="small text-muted">Accepted by mail server does not confirm inbox delivery; bounce tracking is not configured.</p>
      <p class="small text-muted">*Open counts are approximate; some email clients block or automatically fetch tracking images.</p>
      @if((int) $campaign->delivery_unknown_count > 0)
        <p class="small text-warning mb-2">An interrupted SMTP attempt may already have been accepted. These recipients are not retried to prevent duplicate email.</p>
      @endif
      @if((int) $campaign->failed_count > 0 && $campaign->status === 'completed')
        <form method="post" action="{{ route('admin.email-campaigns.retry-failed', $campaign->id) }}" onsubmit="return confirm('Queue only recipients whose email job could not be dispatched?');">@csrf<input type="hidden" name="confirmation" value="RETRY"><button type="submit" class="btn btn-outline-warning">Retry confirmed pre-send failures</button></form>
      @endif
    </div>

    <div class="admin-panel">
      <h2 class="h5 mb-3">Recipient delivery history</h2>
      <p class="small text-muted">Email addresses are masked. A recipient is skipped if their account, audience eligibility, email address, or email preference changes before delivery.</p>
      <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>User</th><th>Email</th><th>Status</th><th>Attempts</th><th>Opens*</th><th>Clicks</th><th>Sent at</th><th>Last error</th></tr></thead>
        <tbody>
        @forelse($recipients as $recipient)
          @php $emailParts = explode('@', $recipient->email, 2); $maskedEmail = count($emailParts) === 2 ? substr($emailParts[0], 0, 2).'•••@'.$emailParts[1] : 'Hidden'; @endphp
          <tr><td>{{ $recipient->name ?: 'User' }} <small class="text-muted">#{{ $recipient->user_id }}</small></td><td>{{ $maskedEmail }}</td><td>{{ $recipient->status === 'sent' ? 'Accepted by SMTP' : ($recipient->status === 'delivery_unknown' ? 'Outcome unknown (not retried)' : ($recipient->status === 'failed' ? 'Pre-send failure' : ucfirst($recipient->status))) }}</td><td>{{ $recipient->attempt_count }}</td><td>{{ $recipient->open_count ?? 0 }}</td><td>{{ $recipient->click_count ?? 0 }}</td><td>{{ $recipient->sent_at ?: '—' }}</td><td>{{ $recipient->last_error ?: '—' }}</td></tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-4">No recipients have been queued.</td></tr>
        @endforelse
        </tbody>
      </table></div>
      {{ $recipients->links('vendor.pagination.custom') }}
    </div>
  </div></section>
</div>

@include('admin.includes.footer')
