@include('admin.includes.header')
@include('admin.includes.sidebar')

<div class="content-wrapper admin-content">
  <section class="content admin-dashboard">
    <div class="container-fluid">
      <div class="admin-page-header">
        <span class="admin-eyebrow">Wallet and finance</span>
        <h1>Google Play purchases</h1>
        <p>Review Blockrium Verified membership payments and mining boosts verified against Google Play and linked to Blockrium accounts.</p>
      </div>

      @if(!$purchaseTablesInstalled)
        <div class="alert alert-warning">Google Play purchase tables are not installed yet. Run the supplied migration before enabling purchases.</div>
      @endif

      <div class="admin-queue-grid mb-4">
        <div class="admin-queue-card admin-queue-blue"><span class="admin-queue-icon"><i class="fa-solid fa-hourglass-half"></i></span><strong>{{ number_format($stats['pending']) }}</strong><b>Pending confirmation</b><small>Benefits are not active while pending.</small></div>
        <div class="admin-queue-card admin-queue-teal"><span class="admin-queue-icon"><i class="fa-solid fa-circle-check"></i></span><strong>{{ number_format($stats['paid']) }}</strong><b>Google-confirmed</b><small>Successful verified store purchases.</small></div>
        <div class="admin-queue-card admin-queue-blue"><span class="admin-queue-icon"><i class="fa-solid fa-bolt"></i></span><strong>{{ number_format($stats['active_boosts']) }}</strong><b>Active boosts</b><small>Verified subscription entitlements.</small></div>
        <div class="admin-queue-card {{ $stats['unmatched_events'] ? 'admin-queue-amber' : 'admin-queue-blue' }}"><span class="admin-queue-icon"><i class="fa-solid fa-link-slash"></i></span><strong>{{ number_format($stats['unmatched_events']) }}</strong><b>Unmatched store events</b><small>Require account reconciliation.</small></div>
        <div class="admin-queue-card {{ $stats['acknowledgement_pending'] ? 'admin-queue-amber' : 'admin-queue-blue' }}"><span class="admin-queue-icon"><i class="fa-solid fa-rotate"></i></span><strong>{{ number_format($stats['acknowledgement_pending']) }}</strong><b>Acknowledgement retry queue</b><small>Paid orders awaiting Play acknowledgement; retries run every five minutes.</small></div>
      </div>

      <section class="admin-panel mb-4">
        <div class="admin-panel-header"><div><span class="admin-eyebrow">Purchase controls</span><h2>Availability</h2></div><span class="badge {{ $configured ? 'badge-success' : 'badge-warning' }}">{{ $configured ? 'Google credentials configured' : 'Google Play not configured' }}</span></div>
        <div class="row">
          <div class="col-md-6"><strong>Membership activation</strong><p class="mb-0">{{ $kycEnabled ? 'Enabled for new purchases' : 'Paused' }}</p></div>
          <div class="col-md-6"><strong>Mining boosts</strong><p class="mb-0">{{ $boostEnabled ? 'Enabled for new purchases' : 'Paused' }}</p></div>
        </div>
        <div class="admin-form-actions"><a class="btn btn-admin-muted" href="{{ url('admin/integrations') }}">Configure Google Play</a></div>
      </section>

      <section class="admin-panel mb-4">
        <div class="admin-panel-header"><div><span class="admin-eyebrow">Account reconciliation</span><h2>Purchase records</h2><p>Each row is tied to the Blockrium account that created its purchase intent. Purchase tokens are never displayed.</p></div></div>
        <form method="GET" class="admin-filter-bar mb-3">
          <select class="form-control" name="purpose"><option value="">All purchase types</option><option value="kyc" {{ ($filters['purpose'] ?? '') === 'kyc' ? 'selected' : '' }}>KYC</option><option value="boost" {{ ($filters['purpose'] ?? '') === 'boost' ? 'selected' : '' }}>Mining boost</option></select>
          <select class="form-control" name="status"><option value="">All statuses</option>@foreach(['pending','paid','cancelled','expired','refunded','revoked','on_hold','failed','needs_review'] as $status)<option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ str_replace('_', ' ', ucfirst($status)) }}</option>@endforeach</select>
          <input class="form-control" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="User, email, order ID, product">
          <button class="btn btn-admin-primary" type="submit">Filter</button>
          <a class="btn btn-admin-muted" href="{{ url('admin/google-play/purchases') }}">Reset</a>
        </form>
        <div class="table-responsive">
          <table class="table admin-table">
            <thead><tr><th>User / account</th><th>Type / product</th><th>Store order</th><th>Payment</th><th>Store detail</th><th>Entitlement</th><th>KYC</th><th>Region</th><th>Purchased / expiry</th></tr></thead>
            <tbody>
              @forelse($purchases as $purchase)
                @php($purchaseMetadata = json_decode((string) ($purchase->metadata ?? ''), true) ?: [])
                <tr>
                  <td><strong>{{ $purchase->name ?: ($purchase->username ?: 'User #' . $purchase->user_id) }}</strong><small class="d-block text-muted">{{ $purchase->email ?: 'Account ID ' . $purchase->user_id }}</small></td>
                  <td><strong>{{ $purchase->purpose === 'kyc' ? 'Blockrium Verified membership' : ($purchase->boost_plan_name ?: 'Mining boost') }}</strong><small class="d-block text-muted">{{ $purchase->product_id }}{{ $purchase->base_plan_id ? ':' . $purchase->base_plan_id : '' }}</small></td>
                  <td>{{ $purchase->google_order_id ?: 'Waiting for Google confirmation' }}</td>
                  <td><span class="badge badge-{{ $purchase->status === 'paid' ? 'success' : (in_array($purchase->status, ['pending','on_hold','needs_review']) ? 'warning' : 'secondary') }}">{{ str_replace('_', ' ', ucfirst($purchase->status)) }}</span>@if($purchase->store_amount !== null)<small class="d-block text-muted">{{ rtrim(rtrim(number_format((float) $purchase->store_amount, 4, '.', ''), '0'), '.') }} {{ $purchase->store_currency }}</small>@else<small class="d-block text-muted">Order amount unavailable from Play API; use order ID in Play Console</small>@endif</td>
                  <td>@if($purchase->last_event_type)<small class="d-block">{{ str_replace('_', ' ', ucfirst($purchase->last_event_type)) }}</small>@endif
                    @if(!empty($purchaseMetadata['purchase_state']))<small class="d-block text-muted">Play state: {{ $purchaseMetadata['purchase_state'] }}</small>@elseif(!empty($purchaseMetadata['subscription_state']))<small class="d-block text-muted">Play state: {{ $purchaseMetadata['subscription_state'] }}</small>@endif
                    @if(!empty($purchaseMetadata['voided_refund_type']))<small class="d-block text-muted">Play refund: @if(in_array((string) $purchaseMetadata['voided_refund_type'], ['1', 'REFUND_TYPE_FULL_REFUND'], true))Full refund @elseif(in_array((string) $purchaseMetadata['voided_refund_type'], ['2', 'REFUND_TYPE_QUANTITY_BASED_PARTIAL_REFUND'], true))Partial quantity refund @elseUnknown type ({{ $purchaseMetadata['voided_refund_type'] }})@endif</small>@endif
                    @if($purchase->acknowledgement_state)<small class="d-block text-muted">Acknowledgement: {{ str_replace('_', ' ', strtolower($purchase->acknowledgement_state)) }}</small>@endif
                    @if(in_array($purchase->status, ['cancelled', 'refunded'], true))<small class="d-block text-warning">Store-reported status. Check the order in Google Play Console; this admin page does not issue refunds.</small>@endif
                    @if(!$purchase->last_event_type && empty($purchaseMetadata['purchase_state']) && empty($purchaseMetadata['subscription_state']) && empty($purchase->acknowledgement_state))<span class="text-muted">No extra store detail</span>@endif
                  </td>
                  <td>{{ str_replace('_', ' ', ucfirst($purchase->entitlement_status)) }}@if($purchase->purpose === 'boost' && $purchase->expires_at)<small class="d-block text-muted">Until {{ \Carbon\Carbon::parse($purchase->expires_at)->format('d M Y, H:i') }}</small>@endif</td>
                  <td>{{ $purchase->purpose === 'kyc' ? ucfirst(str_replace('_', ' ', $purchase->kyc_status ?: 'not started')) : 'Not applicable' }}</td>
                  <td>{{ $purchase->store_region ?: '—' }}</td>
                  <td>{{ $purchase->purchased_at ? \Carbon\Carbon::parse($purchase->purchased_at)->format('d M Y, H:i') : '—' }}<small class="d-block text-muted">Created {{ \Carbon\Carbon::parse($purchase->created_at)->format('d M Y, H:i') }}</small></td>
                </tr>
              @empty
            <tr><td colspan="9" class="admin-empty">No matching Google Play purchases.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if(method_exists($purchases, 'links'))<div class="mt-3">{{ $purchases->links() }}</div>@endif
      </section>

      <section class="admin-panel">
        <div class="admin-panel-header"><div><span class="admin-eyebrow">Google Play RTDN</span><h2>Recent notifications</h2><p>Unmatched notifications never grant benefits. They can arrive before an app completes purchase verification.</p></div></div>
        <div class="table-responsive"><table class="table admin-table"><thead><tr><th>Message ID</th><th>Blockrium account</th><th>Play order</th><th>Amount</th><th>Event</th><th>Processing</th><th>Received</th></tr></thead><tbody>
          @forelse($events as $event)
            <tr><td>{{ $event->message_id }}</td><td>{{ $event->name ?: ($event->username ?: ($event->user_id ? 'User #' . $event->user_id : 'Unmatched')) }}@if($event->email)<small class="d-block text-muted">{{ $event->email }}</small>@endif</td><td>{{ $event->google_order_id ?: '—' }}</td><td>{{ $event->store_amount !== null ? rtrim(rtrim(number_format((float) $event->store_amount, 4, '.', ''), '0'), '.') . ' ' . $event->store_currency : '—' }}</td><td>{{ $event->event_type ?: '—' }}</td><td><span class="badge badge-{{ $event->status === 'processed' ? 'success' : (in_array($event->status, ['unmatched','failed']) ? 'warning' : 'secondary') }}">{{ ucfirst($event->status) }}</span>@if($event->error_code)<small class="d-block text-muted">{{ $event->error_code }}</small>@endif</td><td>{{ \Carbon\Carbon::parse($event->created_at)->format('d M Y, H:i') }}</td></tr>
          @empty
            <tr><td colspan="7" class="admin-empty">No Google Play notifications received.</td></tr>
          @endforelse
        </tbody></table></div>
      </section>
    </div>
  </section>
</div>

@include('admin.includes.footer')
