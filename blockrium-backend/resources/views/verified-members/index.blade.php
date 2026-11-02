@include('admin.includes.header')
@include('admin.includes.sidebar')
<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header">
    <div class="container-fluid">
      <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div><h1>Verified Members</h1><p>Track KYC results, payment status, and badge activation.</p></div>
        <div><a href="{{ url('admin/verified-members/payment-support') }}" class="btn btn-admin-muted mr-1"><i class="fa-solid fa-credit-card mr-1"></i> Payment support</a><a href="{{ url('admin/verified-members/waitlist') }}" class="btn btn-admin-muted mr-1"><i class="fa-solid fa-list-ol mr-1"></i> KYC waiting list</a><a href="{{ url('admin/verified-members/settings') }}" class="btn btn-admin-primary"><i class="fa-solid fa-key mr-1"></i> Verification settings</a></div>
      </div>
    </div>
  </section>
  <section class="content"><div class="container-fluid">
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

    <div class="row">
      @foreach([
        ['label' => 'Total cases', 'value' => $summary['total'], 'icon' => 'fa-users', 'tone' => 'primary', 'url' => url('admin/verified-members')],
        ['label' => 'KYC in progress', 'value' => $summary['in_progress'], 'icon' => 'fa-hourglass-half', 'tone' => 'warning', 'url' => url('admin/verified-members?status=in_progress')],
        ['label' => 'Payment pending', 'value' => $summary['payment_pending'], 'icon' => 'fa-credit-card', 'tone' => 'info', 'url' => url('admin/verified-members?status=payment_pending')],
        ['label' => 'Verified', 'value' => $summary['verified'], 'icon' => 'fa-circle-check', 'tone' => 'success', 'url' => url('admin/verified-members?status=verified')],
        ['label' => 'Waiting list', 'value' => $summary['waitlisted'], 'icon' => 'fa-list-ol', 'tone' => 'secondary', 'url' => url('admin/verified-members/waitlist')],
      ] as $card)
        <div class="col-6 col-md"><a href="{{ $card['url'] }}" class="small-box bg-{{ $card['tone'] }} d-block text-white" style="text-decoration:none" aria-label="View {{ $card['label'] }}"><div class="inner"><h3>{{ number_format($card['value']) }}</h3><p>{{ $card['label'] }}</p></div><div class="icon"><i class="fa-solid {{ $card['icon'] }}"></i></div></a></div>
      @endforeach
    </div>

    <div class="admin-panel mb-3">
      <form method="get" class="form-row align-items-end">
        <div class="form-group col-md-4 mb-md-0"><label>Search user or reference</label><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Name, email, username, payment ref"></div>
        <div class="form-group col-md-3 mb-md-0"><label>Member status</label><select name="status" class="form-control"><option value="">All statuses</option>@foreach(['not_started' => 'Not started', 'started' => 'KYC started', 'submitted' => 'KYC submitted', 'in_review' => 'In review', 'in_progress' => 'KYC in progress', 'approved' => 'KYC approved', 'payment_pending' => 'Payment pending', 'verified' => 'Verified', 'rejected' => 'Rejected', 'revoked' => 'KYC revoked', 'access_revoked' => 'Member access revoked', 'country_limit' => 'Country limit'] as $value => $label)<option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
        <div class="form-group col-md-3 mb-md-0"><label>Payment status</label><select name="payment_status" class="form-control"><option value="">All payments</option>@foreach(['pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded', 'revoked' => 'Revoked'] as $value => $label)<option value="{{ $value }}" {{ ($filters['payment_status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
        <div class="form-group col-md-2 mb-md-0"><button class="btn btn-admin-primary mr-1" type="submit"><i class="fa-solid fa-filter mr-1"></i> Filter</button><a class="btn btn-admin-muted" href="{{ url('admin/verified-members') }}">Reset</a></div>
      </form>
    </div>

    <div class="admin-panel table-responsive">
      <div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
        <div><strong>Bulk reminders</strong><div class="text-muted small">Select eligible payment-pending or rejected accounts on this page. Maximum 100 per request.</div></div>
        <div class="d-flex flex-wrap">
          <form method="post" action="{{ route('admin.verified-members.sync-didit') }}" class="mr-2 mb-2" onsubmit="return confirm('Fetch the next 100 user KYC sessions from Didit and reconcile matching Blockrium user IDs? Existing paid and verified accounts will be protected.');">@csrf<input type="hidden" name="offset" value="{{ (int) session('didit_sync_next_offset', 0) }}"><button class="btn btn-admin-muted" type="submit"><i class="fa-solid fa-rotate mr-1"></i> {{ (int) session('didit_sync_next_offset', 0) > 0 ? 'Sync next Didit batch' : 'Sync Didit sessions' }}</button></form>
          <form id="bulk-reminders" method="post" action="{{ route('admin.verified-members.reminders.send') }}" onsubmit="return confirm('Send reminders to the selected accounts?');">@csrf<button class="btn btn-admin-primary" type="submit"><i class="fa-regular fa-paper-plane mr-1"></i> Send selected reminders</button></form>
        </div>
      </div>
      <p class="text-muted small">Didit sync checks 100 user sessions per run, newest first. Continue with the next batch when prompted to reconcile older sessions. Only exact Blockrium user IDs are linked; document images and identity details are not imported.</p>
      <table class="table table-striped align-middle">
      <thead><tr><th><input type="checkbox" id="select-eligible-reminders" aria-label="Select eligible users on this page"></th><th>User</th><th>KYC</th><th>Payment</th><th>Badge access</th><th>Actions</th></tr></thead>
      <tbody>
      @forelse($memberships as $membership)
        @php
          $isVerified = $membership->kyc_status === 'approved' && $membership->payment_status === 'paid' && !$membership->suspended_at;
          $paymentPending = $membership->kyc_status === 'approved' && $membership->payment_status !== 'paid';
          $canRevokeMember = $membership->kyc_status === 'approved' && $membership->payment_status === 'paid' && !$membership->suspended_at;
          $canRevokeKyc = $membership->kyc_status === 'approved';
          $diditTerminalFailure = in_array(strtolower(trim((string) $membership->didit_status)), ['declined', 'expired', 'abandoned', 'kyc expired'], true);
          $canConfirmDiditApproval = in_array($membership->kyc_status, ['submitted', 'in_review'], true)
            && $membership->kyc_provider === 'didit'
            && $membership->didit_session_id
            && $membership->kyc_country
            && !$diditTerminalFailure;
        @endphp
        <tr>
          <td>@if($paymentPending || $membership->kyc_status === 'rejected')<input type="checkbox" class="bulk-reminder-user" form="bulk-reminders" name="user_ids[]" value="{{ $membership->user_id }}" aria-label="Select {{ $membership->name ?: 'user '.$membership->user_id }}">@endif</td>
          <td><strong>{{ $membership->name ?: 'User #'.$membership->user_id }}</strong><br><small>{{ $membership->email }}</small></td>
          <td><span class="admin-badge {{ $membership->kyc_status === 'approved' ? 'admin-badge-success' : (in_array($membership->kyc_status, ['rejected', 'revoked'], true) ? 'admin-badge-danger' : 'admin-badge-muted') }}">{{ ucfirst(str_replace('_', ' ', $membership->kyc_status)) }}</span><br><small>{{ $membership->kyc_country ?: 'Country pending' }}@if($membership->didit_status) - Didit: {{ $membership->didit_status }}@endif</small></td>
          <td><span class="admin-badge {{ $membership->payment_status === 'paid' ? 'admin-badge-success' : (in_array($membership->payment_status, ['cancelled', 'refunded', 'revoked'], true) ? 'admin-badge-danger' : 'admin-badge-muted') }}">{{ ucfirst($membership->payment_status) }}</span><br><small>@if($membership->payment_provider === 'google_play' && (float) $membership->payment_amount <= 0)Google Play · amount unavailable@else{{ number_format((float) $membership->payment_amount, 2) }} {{ $membership->payment_currency }}@endif</small></td>
          <td>@if($membership->suspended_at)<span class="admin-badge admin-badge-danger">Access revoked</span>@elseif($isVerified)<span class="admin-badge admin-badge-success">Verified &#10003;</span>@elseif($paymentPending)<span class="admin-badge admin-badge-warning">Payment pending</span><br><small>Badge remains inactive</small>@else<span class="admin-badge admin-badge-muted">Not verified</span>@endif</td>
          <td>
            @if($canConfirmDiditApproval)
                <form method="post" action="{{ url('admin/verified-members/'.$membership->user_id.'/kyc/approve') }}" onsubmit="return confirm('Confirm that this user is approved in the Didit dashboard? Blockrium will approve KYC but keep payment pending.');">
                  @csrf
                  <label class="small d-block"><input type="checkbox" name="didit_dashboard_confirmed" value="1" required> I confirmed approval in Didit</label>
                  <button class="btn btn-sm btn-admin-primary" type="submit">Approve in Blockrium</button>
                </form>
            @elseif(in_array($membership->kyc_status, ['submitted', 'in_review']) && $membership->kyc_provider === 'didit' && $membership->didit_session_id && !$membership->kyc_country)
              <span class="text-warning">Sync Didit sessions to load document country before approval.</span>
            @elseif($diditTerminalFailure)
              <span class="text-danger">Didit returned a terminal failure result.</span>
            @elseif(in_array($membership->kyc_status, ['started', 'submitted', 'in_review']))
              <span class="text-muted">Waiting for Didit result</span>
            @elseif($membership->kyc_status === 'country_limit')
              <span class="text-warning">Country pilot limit reached</span>
            @elseif($paymentPending)
              <form method="post" action="{{ url('admin/verified-members/'.$membership->user_id.'/reminder') }}" class="mb-2">@csrf<button class="btn btn-sm btn-admin-muted" type="submit"><i class="fa-regular fa-bell mr-1"></i> Send payment reminder</button></form>
              <form method="post" action="{{ url('admin/verified-members/'.$membership->user_id.'/payment/confirm') }}">@csrf<input name="provider" class="form-control form-control-sm mb-1" required placeholder="Provider"><input name="reference" class="form-control form-control-sm mb-1" required placeholder="Payment reference"><div class="input-group input-group-sm mb-1"><input name="amount" type="number" min="0.01" step="0.01" value="{{ $membership->payment_amount ?: 5 }}" class="form-control" required aria-label="Payment amount"><input name="currency" value="{{ $membership->payment_currency ?: 'USD' }}" maxlength="3" pattern="[A-Za-z]{3}" class="form-control" required aria-label="Payment currency (three-letter code)"></div><button class="btn btn-sm btn-admin-primary">Confirm payment</button></form>
            @elseif($membership->kyc_status === 'rejected')
              <form method="post" action="{{ url('admin/verified-members/'.$membership->user_id.'/reminder') }}">@csrf<button class="btn btn-sm btn-admin-primary" type="submit"><i class="fa-regular fa-paper-plane mr-1"></i> Request KYC again</button></form>
            @elseif($isVerified)
              <span class="text-success">No action needed</span>
            @else
              <span class="text-muted">No action needed</span>
            @endif
            @if($canRevokeMember || $canRevokeKyc)
              <details class="mt-2">
                <summary class="text-danger" style="cursor:pointer">Revoke access</summary>
                <form method="post" action="{{ route('admin.verified-members.access.revoke', $membership->user_id) }}" class="mt-2" onsubmit="return confirm('This immediately disables the selected access. The payment record will not be changed. Continue?');">
                  @csrf
                  <select name="scope" class="form-control form-control-sm mb-1" required aria-label="Access to revoke">
                    @if($canRevokeMember)<option value="member_access">Blockrium Verified member access only</option>@endif
                    @if($canRevokeKyc)<option value="kyc_and_member_access">KYC approval and member access</option>@endif
                  </select>
                  <textarea name="reason" class="form-control form-control-sm mb-1" rows="2" minlength="5" maxlength="2000" placeholder="Required reason (recorded in admin audit)" required></textarea>
                  <button class="btn btn-sm btn-danger" type="submit">Confirm revocation</button>
                </form>
              </details>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="6"><div class="py-4 text-center"><i class="fa-solid fa-inbox fa-2x text-muted mb-2"></i><p class="mb-1">No verification cases match this filter.</p><small class="text-muted">A case appears here after a user starts KYC from the app.</small></div></td></tr>
      @endforelse
      </tbody>
      </table>
      <div class="mt-3">{{ $memberships->links() }}</div>
    </div>
    <script>
      (function () {
        var selectAll = document.getElementById('select-eligible-reminders');
        if (!selectAll) return;
        selectAll.addEventListener('change', function () {
          document.querySelectorAll('.bulk-reminder-user').forEach(function (input) {
            input.checked = selectAll.checked;
          });
        });
      })();
    </script>
  </div></section>
</div>
@include('admin.includes.footer')
