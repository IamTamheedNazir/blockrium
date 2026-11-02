@include('admin.includes.header')
@include('admin.includes.sidebar')
<div class="content-wrapper admin-content">
  <section class="content-header admin-page-header"><div class="container-fluid"><div class="row align-items-center"><div class="col-md-8"><h1>Daily Missions Monitor</h1><p>Read-only operational counts for the released mission modules.</p></div><div class="col-md-4"><div class="admin-header-actions"><span class="btn btn-admin-muted"><i class="fa-solid fa-clock"></i>{{ $refreshed_at->format('d M Y, h:i A') }}</span></div></div></div></div></section>
  <section class="content"><div class="container-fluid"><div class="admin-panel"><div class="table-responsive"><table class="table admin-table"><thead><tr><th>Mission</th><th>Source</th><th>Status</th><th>Completed Today</th><th>Ready / Active</th><th>Warning</th></tr></thead><tbody>
    @foreach($missions as $mission)<tr><td><strong>{{ $mission['key'] }}</strong></td><td>{{ $mission['module'] }}</td><td><span class="admin-badge {{ $mission['status']==='healthy' || $mission['status']==='ready' ? 'admin-badge-success' : 'admin-badge-muted' }}">{{ ucfirst($mission['status']) }}</span></td><td>{{ $mission['completed_today'] === null ? 'N/A' : number_format($mission['completed_today']) }}</td><td>{{ $mission['ready_active'] === null ? 'N/A' : number_format($mission['ready_active']) }}</td><td><small>{{ $mission['warning'] ?: '—' }}</small></td></tr>@endforeach
  </tbody></table></div></div></div></section>
</div>
@include('admin.includes.footer')
