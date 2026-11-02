@include('admin.includes.header')
@include('admin.includes.sidebar')
<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header">
    <div class="container-fluid">
      <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div><h1>KYC Waiting List</h1><p>Review country demand and invite users in controlled batches.</p></div>
        <div><a href="{{ url('admin/verified-members/settings') }}" class="btn btn-admin-muted mr-1"><i class="fa-solid fa-key mr-1"></i> Settings</a><a href="{{ url('admin/verified-members') }}" class="btn btn-admin-muted"><i class="fa-solid fa-arrow-left mr-1"></i> Members</a></div>
      </div>
    </div>
  </section>
  <section class="content"><div class="container-fluid">
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
    <div class="alert alert-info"><strong>Controlled release:</strong> inviting users changes their status to invited. It does not create a Didit session until the user starts KYC.</div>
    <div class="admin-panel table-responsive">
      <table class="table table-striped align-middle">
        <thead><tr><th>Country</th><th>Waiting</th><th>Invited</th><th>In progress</th><th>Converted</th><th>Total demand</th><th>Invite batch</th></tr></thead>
        <tbody>
          @forelse($countries as $country)
            <tr>
              <td><strong>{{ $country->country_name }}</strong><br><small class="text-muted">{{ $country->country_code }}</small></td>
              <td><span class="admin-badge admin-badge-warning">{{ number_format($country->waiting_count) }}</span></td>
              <td>{{ number_format($country->invited_count) }}</td>
              <td>{{ number_format($country->started_count) }}</td>
              <td>{{ number_format($country->converted_count) }}</td>
              <td>{{ number_format($country->total_count) }}</td>
              <td>
                @if($country->waiting_count > 0)
                  <form method="post" action="{{ url('admin/verified-members/waitlist/invite') }}" class="form-inline">
                    @csrf
                    <input type="hidden" name="country_code" value="{{ $country->country_code }}">
                    <input type="number" name="limit" value="{{ min(100, $country->waiting_count) }}" min="1" max="500" class="form-control form-control-sm mr-1" style="width:90px" aria-label="Invite count">
                    <button class="btn btn-sm btn-admin-primary">Invite</button>
                  </form>
                @else
                  <span class="text-muted">No waiting users</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7"><div class="py-4 text-center"><i class="fa-solid fa-inbox fa-2x text-muted mb-2"></i><p class="mb-1">No KYC waiting-list registrations yet.</p><small class="text-muted">Country demand will appear here after users join from the app.</small></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div></section>
</div>
@include('admin.includes.footer')
