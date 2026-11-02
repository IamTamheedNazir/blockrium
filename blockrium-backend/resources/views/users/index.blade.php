@include('admin.includes.header')

@include('admin.includes.sidebar') 

<style>
  .user-directory-card { border: 1px solid #dce4ec; border-radius: 10px; box-shadow: 0 8px 24px rgba(16, 34, 54, .06); overflow: hidden; }
  .user-directory-card > .card-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; min-height: 72px; padding: 14px 20px; background: #fff; border-bottom: 1px solid #e5ebf0; }
  .user-directory-title { margin: 0; color: #14283b; font-size: 19px; font-weight: 700; }
  .user-directory-subtitle { margin: 3px 0 0; color: #708094; font-size: 12px; }
  .user-directory-search { width: min(100%, 380px); }
  .user-directory-search .input-group { width: 100%; }
  .user-directory-search .spc_item { display: flex; width: 100%; }
  .user-directory-search .form-control { min-width: 0; }
  #manage-users-table { min-width: 820px; margin: 0; }
  #manage-users-table th { padding: 12px 14px; color: #64758a; background: #f7f9fb; border-top: 0; border-bottom: 1px solid #e5ebf0; font-size: 11px; font-weight: 700; letter-spacing: .045em; text-transform: uppercase; white-space: nowrap; }
  #manage-users-table td { height: 68px; padding: 10px 14px; vertical-align: middle; border-top-color: #edf1f4; color: #33465a; font-size: 13px; }
  #manage-users-table .user-avatar-frame { position: relative; display: inline-flex; width: 44px; height: 44px; flex: 0 0 44px; align-items: center; justify-content: center; overflow: hidden; border: 1px solid #d9e5e7; border-radius: 50%; background: #eaf5f3; color: #087f73; font-size: 14px; font-weight: 700; }
  #manage-users-table .user-thumbnail { position: absolute; inset: 0; display: block; width: 44px !important; height: 44px !important; max-width: 44px !important; max-height: 44px !important; object-fit: cover; border-radius: 50%; }
  #manage-users-table .user-name { display: block; color: #182e42; font-weight: 700; }
  #manage-users-table .user-meta { display: block; margin-top: 2px; color: #7a8999; font-size: 11px; }
  #manage-users-table .user-contact { overflow-wrap: anywhere; }
  #manage-users-table .user-status { display: inline-flex; align-items: center; gap: 6px; padding: 4px 9px; border-radius: 20px; font-size: 11px; font-weight: 700; white-space: nowrap; }
  #manage-users-table .user-status:before { width: 6px; height: 6px; border-radius: 50%; content: ''; }
  #manage-users-table .user-status-active { color: #146b4a; background: #e6f6ee; }
  #manage-users-table .user-status-active:before { background: #22a26a; }
  #manage-users-table .user-status-inactive { color: #667386; background: #edf0f4; }
  #manage-users-table .user-status-inactive:before { background: #8995a5; }
  #manage-users-table .user-actions { display: flex; align-items: center; gap: 6px; white-space: nowrap; }
  #manage-users-table .user-actions .btn { padding: 5px 8px; font-size: 12px; }
  .user-directory-pagination { padding: 12px 18px; border-top: 1px solid #e5ebf0; background: #fff; }
  @media (max-width: 767.98px) {
    .user-directory-card > .card-header { align-items: stretch; flex-direction: column; }
    .user-directory-search { width: 100%; }
  }
</style>

  <!-- Content Wrapper. Contains page content -->

  <div class="content-wrapper">

    <!-- Content Header (Page header) -->

    <section class="content-header">

      <div class="container-fluid">



        <!-- /.row -->

        <div class="row">

          <div class="col-12">

            

              <!-- <a href="{{url('admin/manage_agent/add')}}"> 

                <button type="button" class="btn btn-success btn-add"> Add Users</button>

              </a> -->

            

          

            <div class="card user-directory-card">

              <div class="card-header">

                <div>
                  <h3 class="user-directory-title">User directory</h3>
                  <p class="user-directory-subtitle">{{ number_format($customer_data->total()) }} accounts · showing {{ $customer_data->firstItem() ?: 0 }}–{{ $customer_data->lastItem() ?: 0 }}</p>
                </div>

                  <form class="user-directory-search" method="GET" action="{{ url('/admin/users') }}" role="search">

                    <div>

                      <div class="input-group input-group-sm" >

                        <div class="spc_item">

                          <label class="sr-only" for="user-directory-search">Search users by name, email, or ID</label>
                          <input id="user-directory-search" type="search" name="search" class="form-control" value="{{ $search }}" placeholder="Name, email, or user ID" maxlength="120">



                          <div class="input-group-append">

                            <button type="submit" class="btn btn-primary" aria-label="Search users">

                              <i class="fas fa-search"></i>

                            </button>

                            @if($search !== '')<a href="{{ url('/admin/users') }}" class="btn btn-default">Reset</a>@endif

                          </div>

                        </div>

                      </div>

                    </div>

				          </form>

              </div>

              <!-- /.card-header -->

			        @include('flash/flash-message')

              <div class="card-body table-responsive p-0">

                <table id="manage-users-table" class="table table-hover">
                  <thead><tr>
                    <th scope="col">User</th>
                    <th scope="col">Referral code</th>
                    <th scope="col">Contact</th>
                    <th scope="col">Country</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                  </tr></thead>
                  <tbody>
                    @forelse($customer_data as $alldata)
                      <tr>
                        <td>
                          <div class="d-flex align-items-center">
                            <span class="user-avatar-frame" aria-hidden="true">
                              {{ strtoupper(substr($alldata->username ?: $alldata->name ?: 'U', 0, 1)) }}
                              @if($alldata->user_image)<img class="user-thumbnail" src="{{ asset('/user_image/'.ltrim($alldata->user_image, '/')) }}" alt="" width="44" height="44" loading="lazy" onerror="this.hidden=true">
                              @endif
                            </span>
                            <span class="ml-3"><span class="user-name">{{ $alldata->username ?: $alldata->name ?: 'Unnamed user' }}</span><span class="user-meta">ID {{ $alldata->id }} · {{ $alldata->email ?: 'No email' }}</span></span>
                          </div>
                        </td>
                        <td><span class="font-weight-bold">{{ $alldata->refferal_code ?: '—' }}</span></td>
                        <td class="user-contact">{{ $alldata->phone_number ?: '—' }}</td>
                        <td>{{ $alldata->country_name ?: 'Not set' }}</td>
                        <td><span class="user-status {{ $alldata->is_active == 1 ? 'user-status-active' : 'user-status-inactive' }}">{{ $alldata->is_active == 1 ? 'Active' : 'Inactive' }}</span></td>
                        <td>
                          <div class="user-actions">
                            <button type="button" class="btn {{ $alldata->is_active == 1 ? 'btn-outline-secondary' : 'btn-outline-success' }} changestatus" data-id="{{ $alldata->id }}" data-status="{{ $alldata->is_active == 1 ? '1' : '0' }}" aria-label="{{ $alldata->is_active == 1 ? 'Deactivate' : 'Activate' }} {{ $alldata->username ?: 'user '.$alldata->id }}">{{ $alldata->is_active == 1 ? 'Deactivate' : 'Activate' }}</button>
                            <form method="post" action="{{ url('/admin/delete_users/'.$alldata->id) }}" class="d-inline" onsubmit="return confirm('Delete this user account? This action cannot be undone.');">@csrf<button type="submit" class="btn btn-outline-danger" aria-label="Delete user {{ $alldata->id }}"><i class="fa fa-trash" aria-hidden="true"></i></button></form>
                          </div>
                        </td>
                      </tr>
                    @empty
                      <tr><td colspan="6" class="text-center text-muted p-4">No users match this search.</td></tr>
                    @endforelse
                  </tbody>
                </table>

			

              </div>
              <div class="user-directory-pagination">{!! $customer_data->links('vendor.pagination.custom2') !!}</div>
			         

              <!-- /.card-body -->

            </div>

            <!-- /.card -->

          </div>

        </div>

        <!-- /.row -->

        <!-- /.row -->

      </div><!-- /.container-fluid -->

    </section>

    <!-- /.content -->

  </div>

  <!-- /.content-wrapper -->

  @include('admin.includes.footer')

  

  

  

  <script type="text/javascript">

  

    $(".changestatus").click(function(e){

		 var pageid = $(this).attr('data-id');

        var isActive = $(this).attr('data-status');
        var token=$('meta[name="csrf_token"]').attr('content');

		if(isActive=='1'){

			var newstatus='Deactivate';

		}else{

			var newstatus='Activate';

			

		}

		

		if(confirm("Are you sure you want to "+newstatus+" this?")){

        e.preventDefault();

    		$.ajax({

           type:'post',
           headers: {'X-CSRF-TOKEN': token},

           url:"{{url('/admin/users/is-active')}}",

           data:{userid:pageid, isActive:isActive},

           success:function(data){

              if (data && data.success) { location.reload(); }
              else { alert('Unable to update this user. Please retry.'); }

           },
           error:function(){ alert('Unable to update this user. Please retry.'); }

        });

		}else{

        return false;

    }

  

    });

	

	

	

    $(".deletepage").click(function(e){

		 var id = $(this).attr('data-id');

		// console.log(id);

		if(confirm("Are you sure you want to delete this?")){

        e.preventDefault();

		$.ajax({

           type:'GET', 

           url:"",

           data:{id:id},

           success:function(data){

              location.reload();

           }

        });

		}else{

        return false;

    }

  

    });

</script>

   
