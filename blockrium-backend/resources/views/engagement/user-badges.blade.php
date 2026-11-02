@include('admin.includes.header')
@include('admin.includes.sidebar')
<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header"><div class="container-fluid"><h1>User Badges</h1><p>Read-only history of backend-awarded badges.</p></div></section>
  <section class="content"><div class="container-fluid">
    <div class="admin-panel mb-4"><form method="GET" class="row align-items-end">
      <div class="col-md-4"><div class="form-group"><label>User</label><input class="form-control" name="user" value="{{ request('user') }}" placeholder="ID, name, username or email"></div></div>
      <div class="col-md-3"><div class="form-group"><label>Badge</label><select class="form-control" name="badge_id"><option value="">All badges</option>@foreach($badges as $badge)<option value="{{ $badge->id }}" @if((string)request('badge_id')===(string)$badge->id) selected @endif>{{ $badge->name }}</option>@endforeach</select></div></div>
      <div class="col-md-2"><div class="form-group"><label>From</label><input type="date" class="form-control" name="from" value="{{ request('from') }}"></div></div>
      <div class="col-md-2"><div class="form-group"><label>To</label><input type="date" class="form-control" name="to" value="{{ request('to') }}"></div></div>
      <div class="col-md-1"><div class="form-group"><button class="btn btn-admin-primary btn-block"><i class="fa-solid fa-filter"></i></button></div></div>
    </form></div>
    <div class="admin-panel"><div class="table-responsive"><table class="table admin-table"><thead><tr><th>User</th><th>Badge</th><th>Source</th><th>Awarded</th></tr></thead><tbody>@forelse($awards as $award)<tr><td><strong>{{ $award->username ?: ($award->user_name ?: 'User #'.$award->user_id) }}</strong><br><small>{{ $award->email }}</small></td><td>{{ $award->badge_name }}<br><small>{{ $award->badge_key }}</small></td><td>{{ $award->source_type ?: '—' }} @if($award->source_id)#{{ $award->source_id }}@endif</td><td>{{ $award->awarded_at }}</td></tr>@empty<tr><td colspan="4" class="admin-empty">No badge awards match these filters.</td></tr>@endforelse</tbody></table></div><div class="mt-3">{{ $awards->links('vendor.pagination.custom') }}</div></div>
  </div></section>
</div>
@include('admin.includes.footer')
