@include('admin.includes.header')
@include('admin.includes.sidebar')

@php
  $editing = !empty($mission);
@endphp

<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-md-7">
          <h1>{{ $editing ? 'Edit Referral Mission' : 'Add Referral Mission' }}</h1>
          <p>Set what users must do, how often they can claim, and how much BRC they earn.</p>
        </div>
        <div class="col-md-5">
          <div class="admin-header-actions">
            <a href="{{ url('admin/referral/missions') }}" class="btn btn-admin-muted">
              <i class="fa-solid fa-arrow-left"></i>
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
        <form method="POST" action="{{ url('admin/referral/mission/save') }}">
          @csrf
          @if($editing)
            <input type="hidden" name="id" value="{{ $mission->id }}">
          @endif

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Mission Title</label>
                <input type="text" name="title" class="form-control" value="{{ old('title', $mission->title ?? '') }}" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Slug</label>
                <input type="text" name="slug" class="form-control" value="{{ old('slug', $mission->slug ?? '') }}" placeholder="auto-generated">
              </div>
            </div>
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3" class="form-control">{{ old('description', $mission->description ?? '') }}</textarea>
          </div>

          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Metric</label>
                <select name="metric" class="form-control" required>
                  @foreach($metrics as $key => $label)
                    <option value="{{ $key }}" {{ old('metric', $mission->metric ?? 'direct_referrals') === $key ? 'selected' : '' }}>
                      {{ $label }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Target Count</label>
                <input type="number" min="1" name="target_count" class="form-control" value="{{ old('target_count', $mission->target_count ?? 1) }}" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Reward Amount</label>
                <input type="number" step="0.0001" min="0" name="reward_amount" class="form-control" value="{{ old('reward_amount', $mission->reward_amount ?? 5) }}" required>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Frequency</label>
                <select name="frequency" class="form-control" required>
                  <option value="once" {{ old('frequency', $mission->frequency ?? 'once') === 'once' ? 'selected' : '' }}>Once</option>
                  <option value="daily" {{ old('frequency', $mission->frequency ?? 'once') === 'daily' ? 'selected' : '' }}>Daily</option>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control" required>
                  <option value="1" {{ (string) old('status', $mission->status ?? 1) === '1' ? 'selected' : '' }}>Active</option>
                  <option value="0" {{ (string) old('status', $mission->status ?? 1) === '0' ? 'selected' : '' }}>Disabled</option>
                </select>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Sort Order</label>
                <input type="number" min="0" name="sort_order" class="form-control" value="{{ old('sort_order', $mission->sort_order ?? 0) }}">
              </div>
            </div>
          </div>

          <div class="admin-header-actions justify-content-start">
            <button type="submit" class="btn btn-admin-primary">
              <i class="fa-solid fa-floppy-disk"></i>
              Save Mission
            </button>
            <a href="{{ url('admin/referral/missions') }}" class="btn btn-admin-muted">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </section>
</div>

@include('admin.includes.footer')
