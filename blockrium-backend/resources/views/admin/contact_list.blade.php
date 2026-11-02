@include('admin.includes.header')
@include('admin.includes.sidebar') 

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">

        <!-- /.row -->
        <div class="row">
          <div class="col-12">
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Contact US List </h3>
				<form action="contact_list" method="GET">			
                <div class="card-tools">
                  <div class="input-group input-group-sm" style="width: 150px; float: right;">
                    <input type="text" name="table_search" value=" @if(!empty($search)){{$search}} @endif" class="form-control float-right" placeholder="Search">

                    <div class="input-group-append">
                      <button type="submit" class="btn btn-default">
                        <i class="fas fa-search"></i>
                      </button>
                    </div>
                  </div>
                </div>
				</form>
              </div>
              <!-- /.card-header -->
			  @include('flash/flash-message')
              <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap">
                  <thead>
                    <tr>
                      
                      <th>Name</th>
                      <th>Email</th>
                      <th>Phone Number</th>
                      <th>Subject</th>
                      <th>Message</th>
                    </tr>
                  </thead>
                  <tbody>
				 
					  @if(!empty($contact_data))
				  @foreach($contact_data as $infographic_datas)
					<tr>
                    <td>{{$infographic_datas->name}}</td>
                    <td>{{$infographic_datas->email}}</td>
                    <td>{{$infographic_datas->phone_num}}</td>
                    <td>{{$infographic_datas->subject}}</td>
                    <td>{{$infographic_datas->message}}</td>
					
				
                    </tr>
		
                  @endforeach
				  @endif
		
                  </tbody>
                </table>
              </div>
			     {!! $contact_data->links('vendor.pagination.custom2') !!}
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
  
 
   