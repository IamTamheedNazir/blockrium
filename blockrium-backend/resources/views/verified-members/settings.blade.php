@include('admin.includes.header')
@include('admin.includes.sidebar')
<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header">
    <div class="container-fluid">
      <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div>
          <h1>Verification &amp; Payment Settings</h1>
          <p>Manage identity checks and Blockrium Verified membership settings.</p>
        </div>
        <div><a href="{{ url('admin/verified-members/waitlist') }}" class="btn btn-admin-muted mr-1"><i class="fa-solid fa-list-ol mr-1"></i> KYC waiting list</a><a href="{{ url('admin/verified-members') }}" class="btn btn-admin-muted"><i class="fa-solid fa-arrow-left mr-1"></i> Member statuses</a></div>
      </div>
    </div>
  </section>
  <section class="content"><div class="container-fluid">
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

    <div class="alert alert-info">
      <strong>Secrets are protected.</strong> API keys and webhook secrets are encrypted before storage. Existing values are only shown as masked fingerprints; leave a secret blank to keep it unchanged.
    </div>

    <form method="post" action="{{ url('admin/verified-members/settings') }}">
      @csrf
      <div class="row">
        <div class="col-lg-6">
          <div class="admin-panel mb-4">
            <div class="d-flex align-items-center mb-3"><i class="fa-solid fa-id-card text-primary mr-2"></i><div><h3 class="mb-0">Didit KYC</h3><small class="text-muted">Hosted identity verification and webhook settings.</small></div></div>
            <div class="form-group"><label>Didit API key</label><input type="password" name="didit_api_key" class="form-control" autocomplete="new-password" placeholder="Leave blank to keep current key">@if($settings['didit_api_key'])<small class="text-success">Configured: {{ $settings['didit_api_key'] }}</small>@endif</div>
            <div class="form-group"><label>Webhook secret</label><input type="password" name="didit_webhook_secret" class="form-control" autocomplete="new-password" placeholder="Leave blank to keep current secret">@if($settings['didit_webhook_secret'])<small class="text-success">Configured: {{ $settings['didit_webhook_secret'] }}</small>@endif</div>
            <div class="form-group"><label>Workflow ID</label><input type="text" name="didit_workflow_id" value="{{ old('didit_workflow_id', $settings['didit_workflow_id']) }}" class="form-control" maxlength="190"></div>
            <div class="form-group"><label>API base URL</label><input type="url" name="didit_base_url" value="{{ old('didit_base_url', $settings['didit_base_url']) }}" class="form-control"></div>
            <div class="form-group"><label>User callback URL <span class="text-muted font-weight-normal">(optional)</span></label><input type="url" name="didit_callback_url" value="{{ old('didit_callback_url', $settings['didit_callback_url']) }}" class="form-control"><small class="form-text text-muted">This is the user redirect after verification. Didit webhook delivery remains <code>/api/webhooks/didit</code>.</small></div>
            <div class="form-row"><div class="form-group col-md-6"><label>Environment</label><select name="didit_environment" class="form-control"><option value="live" {{ old('didit_environment', $settings['didit_environment']) === 'live' ? 'selected' : '' }}>Live</option><option value="sandbox" {{ old('didit_environment', $settings['didit_environment']) === 'sandbox' ? 'selected' : '' }}>Sandbox</option></select></div><div class="form-group col-md-6"><label>Monthly session limit</label><input type="number" name="didit_monthly_limit" value="{{ old('didit_monthly_limit', $settings['didit_monthly_limit']) }}" min="1" class="form-control"></div></div>
            <div class="form-group"><label>Approved users per country</label><input type="number" name="didit_country_limit" value="{{ old('didit_country_limit', $settings['didit_country_limit']) }}" min="1" class="form-control"><small class="form-text text-muted">The country cap applies when Didit sends an approved result.</small></div>
            <div class="border-top pt-3 mt-3"><label class="text-danger">Remove Didit secrets</label><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="clear-didit-api" name="clear_didit_api_key" value="1"><label class="custom-control-label" for="clear-didit-api">Clear API key</label></div><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="clear-didit-secret" name="clear_didit_webhook_secret" value="1"><label class="custom-control-label" for="clear-didit-secret">Clear webhook secret</label></div></div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="admin-panel mb-4">
            <div class="d-flex align-items-center mb-3"><i class="fa-solid fa-credit-card text-success mr-2"></i><div><h3 class="mb-0">Dodo Payments</h3><small class="text-muted">Shared payment credentials for Blockrium Verified membership and mining-boost subscriptions.</small></div></div>
            <div class="form-group"><label>Dodo API key</label><input type="password" name="dodo_api_key" class="form-control" autocomplete="new-password" placeholder="Leave blank to keep current key">@if($settings['dodo_api_key'])<small class="text-success">Configured: {{ $settings['dodo_api_key'] }}</small>@endif</div>
            <div class="form-group"><label>Dodo webhook secret</label><input type="password" name="dodo_webhook_secret" class="form-control" autocomplete="new-password" placeholder="Leave blank to keep current secret">@if($settings['dodo_webhook_secret'])<small class="text-success">Configured: {{ $settings['dodo_webhook_secret'] }}</small>@endif</div>
            <div class="form-group"><label>Membership checkout URL</label><input type="url" name="dodo_verification_payment_url" value="{{ old('dodo_verification_payment_url', $settings['dodo_verification_payment_url']) }}" class="form-control"><small class="form-text text-muted">The app receives this URL after KYC approval when a user chooses to activate Blockrium Verified membership.</small></div>
            <div class="form-row"><div class="form-group col-md-6"><label>One-time membership price</label><input type="number" name="dodo_verification_amount" value="{{ old('dodo_verification_amount', $settings['dodo_verification_amount']) }}" min="0.01" step="0.01" class="form-control"></div><div class="form-group col-md-6"><label>Currency</label><input type="text" name="dodo_verification_currency" value="{{ old('dodo_verification_currency', $settings['dodo_verification_currency']) }}" maxlength="3" class="form-control text-uppercase"></div></div>
            <div class="border-top pt-3 mt-3"><label>Mining boost webhook endpoint</label><input type="text" readonly value="{{ url('/api/webhooks/dodo/boost') }}" class="form-control"><small class="form-text text-muted">Add this as a separate Dodo webhook endpoint and use the same Dodo webhook secret. Boosts activate only after a verified webhook.</small></div>
            <div class="border-top pt-3 mt-3"><label class="text-danger">Remove Dodo secrets</label><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="clear-dodo-api" name="clear_dodo_api_key" value="1"><label class="custom-control-label" for="clear-dodo-api">Clear API key</label></div><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="clear-dodo-secret" name="clear_dodo_webhook_secret" value="1"><label class="custom-control-label" for="clear-dodo-secret">Clear webhook secret</label></div></div>
          </div>
        </div>
      </div>
      <div class="admin-panel mb-4">
        <div class="d-flex align-items-center mb-3"><i class="fa-brands fa-google-play text-success mr-2"></i><div><h3 class="mb-0">Google Play Billing</h3><small class="text-muted">One-time Blockrium Verified membership purchase and mining-boost subscriptions.</small></div></div>
        <div class="form-row">
          <div class="form-group col-lg-6"><label>Service-account JSON</label><textarea name="google_play_service_account_json" class="form-control" rows="4" autocomplete="new-password" placeholder="{{ $settings['google_play_service_account_configured'] ? 'Credentials are saved encrypted. Paste only to rotate.' : 'Paste Google Play service-account JSON' }}"></textarea><small class="form-text text-muted">Stored encrypted. Leave blank to keep the saved credentials.</small>@if($settings['google_play_service_account_configured'])<small class="text-success">Credentials configured</small>@endif</div>
          <div class="form-group col-lg-6 d-flex align-items-end"><div class="custom-control custom-checkbox mb-3"><input type="checkbox" class="custom-control-input" id="clear-google-play-credentials" name="clear_google_play_service_account_json" value="1"><label class="custom-control-label" for="clear-google-play-credentials">Remove saved service-account credentials</label></div></div>
          <div class="form-group col-md-6"><label>Play Console package name</label><input class="form-control" name="google_play_package_name" value="{{ old('google_play_package_name', $settings['google_play_package_name']) }}" placeholder="com.blockrium"></div>
          <div class="form-group col-md-6"><label>Membership one-time product ID</label><input class="form-control" name="google_play_kyc_product_id" value="{{ old('google_play_kyc_product_id', $settings['google_play_kyc_product_id']) }}" placeholder="blockrium_verified_membership"></div>
          <div class="form-group col-md-6"><label>Pub/Sub OIDC audience</label><input class="form-control" type="url" name="google_play_rtdn_audience" value="{{ old('google_play_rtdn_audience', $settings['google_play_rtdn_audience']) }}"><small class="form-text text-muted">Must match the authenticated Pub/Sub push subscription audience.</small></div>
          <div class="form-group col-md-6"><label>Pub/Sub service-account email</label><input class="form-control" type="email" name="google_play_rtdn_service_account_email" value="{{ old('google_play_rtdn_service_account_email', $settings['google_play_rtdn_service_account_email']) }}"></div>
          <div class="form-group col-md-4"><input type="hidden" name="google_play_kyc_enabled" value="0"><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="google-play-kyc-enabled" name="google_play_kyc_enabled" value="1" {{ old('google_play_kyc_enabled', $settings['google_play_kyc_enabled']) ? 'checked' : '' }}><label class="custom-control-label" for="google-play-kyc-enabled">Enable membership purchase</label></div></div>
          <div class="form-group col-md-4"><input type="hidden" name="boost_store_enabled" value="0"><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="boost-store-enabled" name="boost_store_enabled" value="1" {{ old('boost_store_enabled', $settings['boost_store_enabled']) ? 'checked' : '' }}><label class="custom-control-label" for="boost-store-enabled">Show boost plans in app</label></div></div>
          <div class="form-group col-md-4"><input type="hidden" name="boost_checkout_enabled" value="0"><div class="custom-control custom-switch"><input type="checkbox" class="custom-control-input" id="boost-checkout-enabled" name="boost_checkout_enabled" value="1" {{ old('boost_checkout_enabled', $settings['boost_checkout_enabled']) ? 'checked' : '' }}><label class="custom-control-label" for="boost-checkout-enabled">Allow boost checkout</label></div></div>
          <div class="form-group col-12"><hr><label>Boost product mapping</label><p class="text-muted">Use exact Google Play product and base-plan IDs. Checkout remains unavailable until both IDs and credentials are valid.</p></div>
          @forelse($googlePlayBoostPlans as $plan)
            <div class="form-group col-lg-4"><label>{{ $plan['name'] }} ({{ number_format($plan['boost_percent'], 2) }}%)</label><input class="form-control mb-2" name="google_play_boost_products[{{ $plan['id'] }}][product_id]" value="{{ old('google_play_boost_products.' . $plan['id'] . '.product_id', $plan['product_id']) }}" placeholder="Product ID"><input class="form-control" name="google_play_boost_products[{{ $plan['id'] }}][base_plan_id]" value="{{ old('google_play_boost_products.' . $plan['id'] . '.base_plan_id', $plan['base_plan_id']) }}" placeholder="Base plan ID"></div>
          @empty
            <div class="form-group col-12"><small class="text-muted">Create active boost plans before mapping Google Play products.</small></div>
          @endforelse
          <div class="form-group col-12 mb-0"><small class="form-text text-muted">RTDN endpoint: <code>{{ url('/api/webhooks/google-play') }}</code></small></div>
        </div>
      </div>
      <div class="alert alert-info mb-4"><i class="fa-brands fa-telegram mr-2"></i>Telegram campaign tasks use a simple open-link-and-claim flow. The app does not track channel membership or require screenshots, bot setup, or admin confirmation.</div>
      <div class="admin-panel mb-4">
        <div class="d-flex align-items-center mb-3"><i class="fa-solid fa-hourglass-half text-warning mr-2"></i><div><h3 class="mb-0">KYC waiting list and verified benefits</h3><small class="text-muted">Collect country demand first, then invite users in controlled batches.</small></div></div>
        <div class="custom-control custom-checkbox mb-3"><input type="hidden" name="kyc_waitlist_enabled" value="0"><input type="checkbox" class="custom-control-input" id="kyc-waitlist-enabled" name="kyc_waitlist_enabled" value="1" {{ old('kyc_waitlist_enabled', $settings['kyc_waitlist_enabled']) ? 'checked' : '' }}><label class="custom-control-label" for="kyc-waitlist-enabled">Accept new KYC waiting-list registrations</label></div>
        <div class="form-group"><label>Waiting-list message</label><textarea name="kyc_waitlist_message" class="form-control" rows="2" maxlength="500" required>{{ old('kyc_waitlist_message', $settings['kyc_waitlist_message']) }}</textarea><small class="form-text text-muted">This message is returned to the app before a user is invited.</small></div>
        <div class="form-group mb-0"><label>Verified-user mining boost percentage</label><div class="input-group"><input type="number" name="kyc_verified_mining_boost_percent" value="{{ old('kyc_verified_mining_boost_percent', $settings['kyc_verified_mining_boost_percent']) }}" min="0" max="100" step="0.01" class="form-control"><div class="input-group-append"><span class="input-group-text">%</span></div></div><small class="form-text text-muted">Activates only after Didit approval and successful Blockrium Verified membership payment. Set to 0 to disable.</small></div>
      </div>
      <div class="admin-panel mb-4">
        <div class="d-flex align-items-center mb-3"><i class="fa-solid fa-wallet text-success mr-2"></i><div><h3 class="mb-0">Verified BRC transfer fees</h3><small class="text-muted">Route the fixed 2% transfer fee to a dedicated verified Blockrium wallet.</small></div></div>
        <div class="form-row align-items-end">
          <div class="form-group col-lg-8 mb-lg-0"><label for="brc-transfer-treasury-username">Fee wallet username</label><input id="brc-transfer-treasury-username" type="text" name="brc_transfer_treasury_username" value="{{ old('brc_transfer_treasury_username', $settings['brc_transfer_treasury_username']) }}" class="form-control" maxlength="20" pattern="[A-Za-z0-9_]{4,20}" placeholder="e.g. blockrium_treasury" autocomplete="off"><small class="form-text text-muted">Must belong to a paid, active verified member. Keep this account separate from users making transfers. Transfers that incur a fee stay disabled until this is configured.</small></div>
          <div class="form-group col-lg-4 mb-0"><label>Fee charged to sender</label><div class="form-control-plaintext font-weight-bold">2% on top; recipient receives the full amount</div></div>
        </div>
      </div>
      <div class="d-flex justify-content-end mb-4"><button class="btn btn-admin-primary" type="submit"><i class="fa-solid fa-lock mr-1"></i> Save securely</button></div>
    </form>
  </div></section>
</div>
@include('admin.includes.footer')
