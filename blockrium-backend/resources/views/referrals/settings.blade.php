@include('admin.includes.header')
@include('admin.includes.sidebar')

<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-md-7">
          <h1>Referral Settings</h1>
          <p>Control signup rewards, ping limits, referral code format, and active-team boost tiers.</p>
        </div>
        <div class="col-md-5">
          <div class="admin-header-actions">
            <a href="{{ url('admin/referral/missions') }}" class="btn btn-admin-muted">
              <i class="fa-solid fa-route"></i>
              Missions
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      @include('flash/flash-message')

      <div class="admin-panel">
        <div class="admin-panel-header">
          <div>
            <h2>Dynamic Referral Rules</h2>
            <p>Changes apply to new referral claims and new API responses after cache clear.</p>
          </div>
        </div>

        @if($settings->isEmpty())
          <div class="admin-empty">Referral settings are not installed yet. Run the latest database migrations.</div>
        @else
          <form method="POST" action="{{ url('admin/referral/settings') }}">
            @csrf

            <div class="row">
              @foreach($settings as $setting)
                <div class="col-md-6">
                  <div class="form-group">
                    <label>{{ $setting->label }}</label>
                    <input
                      type="{{ $setting->input_type === 'number' ? 'number' : 'text' }}"
                      step="{{ $setting->input_type === 'number' ? '0.01' : '' }}"
                      min="{{ $setting->input_type === 'number' ? '0' : '' }}"
                      name="settings[{{ $setting->setting_key }}]"
                      class="form-control"
                      value="{{ old('settings.' . $setting->setting_key, $setting->setting_value) }}"
                    >
                    <small class="text-muted">{{ $setting->setting_key }}</small>
                  </div>
                </div>
              @endforeach
            </div>

            <div class="admin-header-actions justify-content-start">
              <button type="submit" class="btn btn-admin-primary">
                <i class="fa-solid fa-floppy-disk"></i>
                Save Settings
              </button>
              <a href="{{ url('/dashboard') }}" class="btn btn-admin-muted">Dashboard</a>
            </div>
          </form>
        @endif
      </div>
    </div>
  </section>
</div>

@include('admin.includes.footer')
