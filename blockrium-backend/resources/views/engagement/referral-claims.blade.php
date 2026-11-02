@include('admin.includes.header')
@include('admin.includes.sidebar')
<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header"><div class="container-fluid"><div class="row align-items-center"><div class="col-md-8"><h1>Referral Claims</h1><p>Read-only milestone reward history.</p></div><div class="col-md-4"><div class="admin-header-actions"><span class="btn btn-admin-muted">{{ number_format($claimTotal, 4) }} BRC credited</span></div></div></div></div></section>
  <section class="content"><div class="container-fluid">
    <div class="admin-panel mb-4"><form method="GET" class="row align-items-end">
      <div class="col-md-4"><div class="form-group"><label>User</label><input class="form-control" name="user" value="{{ request('user') }}" placeholder="ID, name, username or email"></div></div>
      <div class="col-md-3"><div class="form-group"><label>Milestone</label><select class="form-control" name="milestone_id"><option value="">All milestones</option>@foreach($milestones as $milestone)<option value="{{ $milestone->id }}" @if((string)request('milestone_id')===(string)$milestone->id) selected @endif>{{ $milestone->title }}</option>@endforeach</select></div></div>
      <div class="col-md-2"><div class="form-group"><label>From</label><input type="date" class="form-control" name="from" value="{{ request('from') }}"></div></div>
      <div class="col-md-2"><div class="form-group"><label>To</label><input type="date" class="form-control" name="to" value="{{ request('to') }}"></div></div>
      <div class="col-md-1"><div class="form-group"><button class="btn btn-admin-primary btn-block"><i class="fa-solid fa-filter"></i></button></div></div>
    </form></div>
    <div class="admin-panel"><div class="table-responsive"><table class="table admin-table"><thead><tr><th>User</th><th>Milestone</th><th>Reward</th><th>Claimed</th><th>Created</th></tr></thead><tbody>@forelse($claims as $claim)<tr><td><strong>{{ $claim->username ?: ($claim->user_name ?: 'User #'.$claim->user_id) }}</strong><br><small>{{ $claim->email }}</small></td><td>{{ $claim->milestone_title ?: $claim->milestone_key }}</td><td>{{ number_format($claim->reward_amount, 4) }} {{ $claim->reward_label ?? 'BRC' }}</td><td>{{ $claim->claimed_at }}</td><td>{{ $claim->created_at }}</td></tr>@empty<tr><td colspan="5" class="admin-empty">No milestone claims match these filters.</td></tr>@endforelse</tbody></table></div><div class="mt-3">{{ $claims->links('vendor.pagination.custom') }}</div></div>
  </div></section>
</div>
@include('admin.includes.footer')
