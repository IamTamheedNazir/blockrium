@include('admin.includes.header')
@include('admin.includes.sidebar')

@php
  $editing = !empty($plan);
@endphp

<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header">
    <div class="container-fluid">
      <div class="row align-items-center">
        <div class="col-md-7">
          <h1>{{ $editing ? 'Edit Boost Plan' : 'Add Boost Plan' }}</h1>
          <p>Paid boost plans increase mining rewards only after payment is verified.</p>
        </div>
        <div class="col-md-5">
          <div class="admin-header-actions">
            <a href="{{ url('admin/manage/boost-plans') }}" class="btn btn-admin-muted">
              <i class="fa-solid fa-arrow-left"></i>
              Plans
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
        <form method="POST" action="{{ url('admin/add/boost-plan') }}">
          @csrf
          @if($editing)
            <input type="hidden" name="id" value="{{ $plan->id }}">
          @endif

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Plan Name</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $plan->name ?? '') }}" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Slug</label>
                <input type="text" name="slug" class="form-control" value="{{ old('slug', $plan->slug ?? '') }}" placeholder="auto-generated">
              </div>
            </div>
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3" class="form-control">{{ old('description', $plan->description ?? '') }}</textarea>
          </div>

          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label>Boost Percent</label>
                <input type="number" step="0.01" min="0" name="boost_percent" class="form-control" value="{{ old('boost_percent', $plan->boost_percent ?? 10) }}" required>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Duration Days</label>
                <input type="number" min="1" name="duration_days" class="form-control" value="{{ old('duration_days', $plan->duration_days ?? 7) }}" required>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Price</label>
                <input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ old('price', $plan->price ?? 99) }}" required>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Currency</label>
                <input type="text" name="currency" class="form-control" value="{{ old('currency', $plan->currency ?? 'INR') }}" required>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Payment Provider</label>
                <select name="provider" class="form-control">
                  <option value="" {{ old('provider', $plan->provider ?? '') === '' ? 'selected' : '' }}>Manual / legacy</option>
                  <option value="dodo" {{ old('provider', $plan->provider ?? '') === 'dodo' ? 'selected' : '' }}>Dodo Payments</option>
                  <option value="revenuecat" {{ old('provider', $plan->provider ?? '') === 'revenuecat' ? 'selected' : '' }}>RevenueCat</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Provider Product ID</label>
                <input type="text" name="provider_product_id" class="form-control" value="{{ old('provider_product_id', $plan->provider_product_id ?? '') }}" placeholder="pdt_... for Dodo or monthly for RevenueCat">
                <small class="text-muted">Dodo webhooks use this Product ID to match the paid boost plan.</small>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label>Provider Entitlement ID <small class="text-muted">(RevenueCat only)</small></label>
            <input type="text" name="provider_entitlement_id" class="form-control" value="{{ old('provider_entitlement_id', $plan->provider_entitlement_id ?? '') }}" placeholder="Optional RevenueCat entitlement">
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control" required>
                  <option value="1" {{ (string) old('status', $plan->status ?? 1) === '1' ? 'selected' : '' }}>Active</option>
                  <option value="0" {{ (string) old('status', $plan->status ?? 1) === '0' ? 'selected' : '' }}>Disabled</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Sort Order</label>
                <input type="number" min="0" name="sort_order" class="form-control" value="{{ old('sort_order', $plan->sort_order ?? 0) }}">
              </div>
            </div>
          </div>

          <div class="admin-header-actions justify-content-start">
            <button type="submit" class="btn btn-admin-primary">
              <i class="fa-solid fa-floppy-disk"></i>
              Save Plan
            </button>
            <a href="{{ url('admin/manage/boost-plans') }}" class="btn btn-admin-muted">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </section>
</div>

@include('admin.includes.footer')
