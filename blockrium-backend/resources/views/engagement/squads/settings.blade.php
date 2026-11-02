@include('admin.includes.header')
@include('admin.includes.sidebar')

<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h1>Squad Settings</h1>
          <p>{{ $squad->name }} / {{ $squad->invite_code }}</p>
        </div>
        <div class="col-md-4">
          <div class="admin-header-actions">
            <a class="btn btn-admin-muted" href="{{ route('admin.engagement.squads.show', $squad->id) }}"><i class="fa-solid fa-arrow-left"></i>Squad</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      @include('flash/flash-message')

      <div class="admin-panel mb-4">
        <div class="admin-panel-header"><div><h2>Promotion & Controls</h2><p>Feature, disable, promote, and configure poster behavior.</p></div></div>
        <form method="POST" action="{{ route('admin.engagement.squads.settings.update', $squad->id) }}">
          @csrf
          <div class="row">
            <div class="col-md-3 mb-3">
              <label>Country Code</label>
              <input class="form-control" name="country_code" maxlength="2" value="{{ $squad->country_code ?? '' }}" placeholder="IN">
            </div>
            <div class="col-md-3 mb-3">
              <label>Language Code</label>
              <input class="form-control" name="language_code" maxlength="8" value="{{ $squad->language_code ?? '' }}" placeholder="en">
            </div>
            <div class="col-md-3 mb-3">
              <label>Poster Template</label>
              <select class="form-control" name="poster_template">
                @foreach(['white'=>'White', 'dark'=>'Dark', 'country'=>'Country Flag', 'weekly_winner'=>'Weekly Winner'] as $value=>$label)
                  <option value="{{ $value }}" {{ ($squad->poster_template ?? 'white') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3 mb-3">
              <label>Promotion Tier</label>
              <select class="form-control" name="promotion_tier">
                @foreach(['normal'=>'Normal', 'featured'=>'Featured', 'spotlight'=>'Spotlight'] as $value=>$label)
                  <option value="{{ $value }}" {{ ($squad->promotion_tier ?? 'normal') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="row">
            <div class="col-md-3 mb-3">
              <label>Featured Until</label>
              <input class="form-control" name="featured_until" value="{{ $squad->featured_until ?? '' }}" placeholder="2026-07-31 23:59:59">
            </div>
            <div class="col-md-3 mb-3">
              <label>Weekly Target Points</label>
              <input class="form-control" type="number" name="weekly_target_points" min="1" value="{{ $squad->weekly_target_points }}">
            </div>
            <div class="col-md-3 mb-3">
              <label>Chest XP Reward</label>
              <input class="form-control" type="number" name="weekly_chest_reward_xp" min="0" value="{{ $squad->weekly_chest_reward_xp }}">
            </div>
            <div class="col-md-3 mb-3">
              <label>Chest BRC Reward</label>
              <input class="form-control" type="number" step="0.0001" name="weekly_chest_reward_brc" min="0" value="{{ $squad->weekly_chest_reward_brc }}">
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label>Admin Note</label>
              <textarea class="form-control" name="admin_note" rows="3">{{ $squad->admin_note ?? '' }}</textarea>
            </div>
            <div class="col-md-3 mb-3">
              <label>Status</label>
              <div class="custom-control custom-switch mt-2">
                <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" {{ $squad->is_active ? 'checked' : '' }}>
                <label class="custom-control-label" for="is_active">Active</label>
              </div>
              <div class="custom-control custom-switch mt-2">
                <input type="checkbox" class="custom-control-input" id="is_public" name="is_public" {{ $squad->is_public ? 'checked' : '' }}>
                <label class="custom-control-label" for="is_public">Public Discover</label>
              </div>
              @if(property_exists($squad, 'allow_followers'))
                <div class="custom-control custom-switch mt-2">
                  <input type="checkbox" class="custom-control-input" id="allow_followers" name="allow_followers" {{ $squad->allow_followers ? 'checked' : '' }}>
                  <label class="custom-control-label" for="allow_followers">Allow Followers</label>
                </div>
              @endif
              @if(property_exists($squad, 'feed_enabled'))
                <div class="custom-control custom-switch mt-2">
                  <input type="checkbox" class="custom-control-input" id="feed_enabled" name="feed_enabled" {{ $squad->feed_enabled ? 'checked' : '' }}>
                  <label class="custom-control-label" for="feed_enabled">Feed Preview</label>
                </div>
              @endif
              @if(property_exists($squad, 'auto_feed_enabled'))
                <div class="custom-control custom-switch mt-2">
                  <input type="checkbox" class="custom-control-input" id="auto_feed_enabled" name="auto_feed_enabled" {{ $squad->auto_feed_enabled ? 'checked' : '' }}>
                  <label class="custom-control-label" for="auto_feed_enabled">Auto Feed Cards</label>
                </div>
              @endif
            </div>
            <div class="col-md-3 mb-3 d-flex align-items-end">
              <button class="btn btn-admin-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i>Save Settings</button>
            </div>
          </div>
        </form>
      </div>

      @if($globalSettings->count())
        <div class="admin-panel mb-4">
          <div class="admin-panel-header"><div><h2>Global Squad Settings</h2><p>System-level switches seeded by the latest squad migration.</p></div></div>
          <form method="POST" action="{{ route('admin.engagement.squads.settings.update', $squad->id) }}">
            @csrf
            <input type="hidden" name="country_code" value="{{ $squad->country_code ?? '' }}">
            <input type="hidden" name="language_code" value="{{ $squad->language_code ?? '' }}">
            <input type="hidden" name="poster_template" value="{{ $squad->poster_template ?? 'white' }}">
            <input type="hidden" name="promotion_tier" value="{{ $squad->promotion_tier ?? 'normal' }}">
            <input type="hidden" name="featured_until" value="{{ $squad->featured_until ?? '' }}">
            <input type="hidden" name="weekly_target_points" value="{{ $squad->weekly_target_points }}">
            <input type="hidden" name="weekly_chest_reward_xp" value="{{ $squad->weekly_chest_reward_xp }}">
            <input type="hidden" name="weekly_chest_reward_brc" value="{{ $squad->weekly_chest_reward_brc }}">
            <input type="hidden" name="admin_note" value="{{ $squad->admin_note ?? '' }}">
            @if($squad->is_active)<input type="hidden" name="is_active" value="1">@endif
            @if($squad->is_public)<input type="hidden" name="is_public" value="1">@endif
            @if(property_exists($squad, 'allow_followers') && $squad->allow_followers)<input type="hidden" name="allow_followers" value="1">@endif
            @if(property_exists($squad, 'feed_enabled') && $squad->feed_enabled)<input type="hidden" name="feed_enabled" value="1">@endif
            @if(property_exists($squad, 'auto_feed_enabled') && $squad->auto_feed_enabled)<input type="hidden" name="auto_feed_enabled" value="1">@endif
            <div class="table-responsive">
              <table class="table admin-table">
                <thead><tr><th>Setting</th><th>Value</th><th>Description</th></tr></thead>
                <tbody>
                  @foreach($globalSettings as $setting)
                    <tr>
                      <td><strong>{{ $setting->key }}</strong><br><small>{{ $setting->value_type }}</small></td>
                      <td><input class="form-control" name="squad_settings[{{ $setting->key }}]" value="{{ $setting->value }}"></td>
                      <td>{{ $setting->description }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            <button class="btn btn-admin-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i>Save Global Settings</button>
          </form>
        </div>
      @endif

      <div class="row">
        <div class="col-lg-7 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header"><div><h2>Weekly Squad Missions</h2><p>Server-validated mission goals and rewards.</p></div></div>
            <div class="table-responsive">
              <table class="table admin-table">
                <thead><tr><th>Mission</th><th>Metric</th><th>Target</th><th>XP</th><th>Status</th><th></th></tr></thead>
                <tbody>
                  @forelse($missions as $mission)
                    <tr>
                      <td><strong>{{ $mission->title }}</strong><br><small>{{ $mission->description }}</small></td>
                      <td>{{ $mission->metric }}</td>
                      <td>{{ number_format($mission->target_value) }}</td>
                      <td>{{ number_format($mission->reward_xp) }}</td>
                      <td><span class="admin-badge {{ $mission->is_active ? 'admin-badge-success' : 'admin-badge-muted' }}">{{ $mission->is_active ? 'Active' : 'Off' }}</span></td>
                      <td>
                        <form method="POST" action="{{ route('admin.engagement.squads.missions.toggle', $mission->id) }}">
                          @csrf
                          <button class="btn btn-sm btn-admin-muted" type="submit">{{ $mission->is_active ? 'Disable' : 'Enable' }}</button>
                        </form>
                      </td>
                    </tr>
                  @empty
                    <tr><td colspan="6" class="admin-empty">No squad missions available.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-lg-5 mb-4">
          <div class="admin-panel h-100">
            <div class="admin-panel-header"><div><h2>Safe Announcement</h2><p>Publish only predefined, non-spam templates.</p></div></div>
            <form method="POST" action="{{ route('admin.engagement.squads.announcements.store', $squad->id) }}">
              @csrf
              <div class="mb-3">
                <label>Template</label>
                <select class="form-control" name="template_id" required>
                  @foreach($templates as $template)
                    <option value="{{ $template->id }}">{{ $template->title }}</option>
                  @endforeach
                </select>
              </div>
              <div class="mb-3">
                <label>Scope</label>
                <select class="form-control" name="scope">
                  <option value="squad">This Squad</option>
                  <option value="global">All Squads</option>
                </select>
              </div>
              <div class="row">
                <div class="col-md-6 mb-3"><label>Starts At</label><input class="form-control" name="starts_at" placeholder="optional"></div>
                <div class="col-md-6 mb-3"><label>Ends At</label><input class="form-control" name="ends_at" placeholder="optional"></div>
              </div>
              <button class="btn btn-admin-primary" type="submit"><i class="fa-solid fa-bullhorn"></i>Publish</button>
            </form>
          </div>
        </div>
      </div>

      <div class="admin-panel mb-4">
        <div class="admin-panel-header"><div><h2>Recent Announcements</h2></div></div>
        <div class="table-responsive">
          <table class="table admin-table">
            <thead><tr><th>Title</th><th>Scope</th><th>Status</th><th>Window</th></tr></thead>
            <tbody>
              @forelse($announcements as $announcement)
                <tr>
                  <td><strong>{{ $announcement->title }}</strong><br><small>{{ $announcement->body }}</small></td>
                  <td>{{ $announcement->squad_id ? 'This Squad' : 'Global' }}</td>
                  <td>{{ $announcement->status }}</td>
                  <td>{{ $announcement->starts_at ?: 'Now' }} - {{ $announcement->ends_at ?: 'Open' }}</td>
                </tr>
              @empty
                <tr><td colspan="4" class="admin-empty">No announcements yet.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</div>

@include('admin.includes.footer')
