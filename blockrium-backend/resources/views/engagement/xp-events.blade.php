@include('admin.includes.header')
@include('admin.includes.sidebar')
<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header"><div class="container-fluid"><h1>XP Events</h1><p>Read-only server-confirmed XP history. XP has no cash value and is not withdrawable.</p></div></section>
  <section class="content"><div class="container-fluid">
    <div class="admin-panel mb-4">
      <form method="GET" class="row align-items-end">
        <div class="col-lg-3 col-md-6"><div class="form-group"><label>User</label><input class="form-control" name="user" value="{{ request('user') }}" placeholder="ID, name, username or email"></div></div>
        <div class="col-lg-2 col-md-6"><div class="form-group"><label>Event</label><select class="form-control" name="event_key"><option value="">All events</option>@foreach($filters['event_keys'] as $key)<option value="{{ $key }}" @if(request('event_key')===$key) selected @endif>{{ $key }}</option>@endforeach</select></div></div>
        <div class="col-lg-2 col-md-6"><div class="form-group"><label>Source</label><select class="form-control" name="source_type"><option value="">All sources</option>@foreach($filters['source_types'] as $type)<option value="{{ $type }}" @if(request('source_type')===$type) selected @endif>{{ $type }}</option>@endforeach</select></div></div>
        <div class="col-lg-2 col-md-6"><div class="form-group"><label>From</label><input type="date" class="form-control" name="from" value="{{ request('from') }}"></div></div>
        <div class="col-lg-2 col-md-6"><div class="form-group"><label>To</label><input type="date" class="form-control" name="to" value="{{ request('to') }}"></div></div>
        <div class="col-lg-1 col-md-6"><div class="form-group"><button class="btn btn-admin-primary btn-block" title="Apply filters"><i class="fa-solid fa-filter"></i></button></div></div>
      </form>
    </div>
    <div class="admin-panel"><div class="table-responsive"><table class="table admin-table"><thead><tr><th>User</th><th>Event</th><th>Source</th><th>XP</th><th>Awarded</th><th>Metadata</th></tr></thead><tbody>
      @forelse($events as $event)<tr><td><strong>{{ $event->username ?: ($event->user_name ?: 'User #'.$event->user_id) }}</strong><br><small>{{ $event->email }}</small></td><td>{{ $event->event_key }}</td><td>{{ $event->source_type ?: '—' }} @if($event->source_id)<small>#{{ $event->source_id }}</small>@endif</td><td>+{{ number_format($event->xp_amount) }}</td><td>{{ $event->awarded_at }}</td><td><small>{{ $engagementService->metadataPreview($event->metadata) ?: '—' }}</small></td></tr>@empty<tr><td colspan="6" class="admin-empty">No XP events match these filters.</td></tr>@endforelse
    </tbody></table></div><div class="mt-3">{{ $events->links('vendor.pagination.custom') }}</div></div>
  </div></section>
</div>
@include('admin.includes.footer')
