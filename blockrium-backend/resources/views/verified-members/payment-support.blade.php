@include('admin.includes.header')
@include('admin.includes.sidebar')

<div class="content-wrapper admin-content">
  <section class="content admin-dashboard">
    <div class="container-fluid">
      @include('flash/flash-message')
      <div class="admin-page-header d-flex flex-wrap align-items-center justify-content-between">
        <div>
          <span class="admin-eyebrow">Users and verification</span>
          <h1>Membership payment support</h1>
          <p>Review Blockrium Verified membership payment issues and reconcile them against payment records.</p>
        </div>
        <a class="btn btn-admin-muted" href="{{ url('admin/verified-members') }}">Verification cases</a>
      </div>

      <section class="admin-panel">
        <form method="GET" class="admin-filter-bar mb-3">
          <select name="status" class="form-control" aria-label="Support status">
            <option value="">All support statuses</option>
            @foreach(['open', 'in_review', 'resolved', 'rejected'] as $value)
              <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $value)) }}</option>
            @endforeach
          </select>
          <button class="btn btn-admin-primary" type="submit">Filter</button>
        </form>

        @unless($tableReady)
          <div class="alert alert-warning">Payment support storage is not installed. Deploy the backend patch and run the verification support migration.</div>
        @endunless

        <div class="table-responsive">
          <table class="table admin-table">
            <thead><tr><th>User</th><th>Case</th><th>Payment</th><th>KYC status</th><th>Message / notes</th><th>Update</th></tr></thead>
            <tbody>
              @if(!$tableReady)
                <tr><td colspan="6" class="admin-empty">Payment support is unavailable until its migration is installed.</td></tr>
              @else
                @forelse($cases as $case)
                  <tr>
                    <td><strong>{{ $case->legacy_username ?: ($case->name ?: 'User #'.$case->user_id) }}</strong><small class="d-block text-muted">{{ $case->email ?: 'No email' }}</small></td>
                    <td><span class="admin-badge {{ in_array($case->status, ['open', 'in_review'], true) ? 'admin-badge-warning' : ($case->status === 'resolved' ? 'admin-badge-success' : 'admin-badge-muted') }}">{{ ucwords(str_replace('_', ' ', $case->status)) }}</span><small class="d-block text-muted">{{ $case->created_at }}</small></td>
                    <td>{{ $case->provider ?: 'Unknown' }}<small class="d-block text-muted">{{ $case->provider_reference ?: ($case->provider_payment_id ?: 'No reference') }}</small><small class="d-block text-muted">Record: {{ $case->payment_record_status ?: 'not found' }} · {{ $case->amount ? number_format((float) $case->amount, 2).' '.$case->currency : 'amount unavailable' }}</small></td>
                    <td>{{ ucwords(str_replace('_', ' ', $case->kyc_status ?: 'not_started')) }}<small class="d-block text-muted">Payment: {{ $case->payment_status ?: 'pending' }}</small></td>
                    <td>{{ $case->user_message ?: 'No message provided.' }} @if($case->admin_notes)<small class="d-block text-muted">Admin: {{ $case->admin_notes }}</small>@endif</td>
                    <td>
                      <form method="POST" action="{{ route('admin.verified-members.payment-support.update', $case->id) }}" class="admin-compact-form">
                        @csrf
                        <select class="form-control form-control-sm mb-1" name="status">
                          @foreach(['open', 'in_review', 'resolved', 'rejected'] as $value)
                            <option value="{{ $value }}" {{ $case->status === $value ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $value)) }}</option>
                          @endforeach
                        </select>
                        <textarea class="form-control form-control-sm mb-1" name="admin_notes" rows="2" maxlength="2000" placeholder="Internal note">{{ $case->admin_notes }}</textarea>
                        <button class="btn btn-sm btn-admin-primary" type="submit">Save</button>
                      </form>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="admin-empty">No payment support cases match this filter.</td></tr>
                @endforelse
              @endif
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </section>
</div>

@include('admin.includes.footer')
