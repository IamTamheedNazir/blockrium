@include('admin.includes.header')

@include('admin.includes.sidebar') 



  <!-- Content Wrapper. Contains page content -->

  <div class="content-wrapper">

    <!-- Content Header (Page header) -->

    <section class="content-header">

      <div class="container-fluid">

  @include('flash/flash-message')

        <!-- /.row -->

        <div class="row">

          <div class="col-12">

            <div class="card card-primary">

              <div class="card-header">

                <h3 class="card-title">Manage App Version </h3>

                </div>

              </div>

              <!-- /.card-header -->

              <div class="card-body table-responsive p-0">

                <table class="table table-hover text-nowrap">

                  <thead>

                    <tr>

                      <th>Id</th>
                      <th>App Version</th>
                      <th>Latest Code</th>
                      <th>Minimum Code</th>
                      <th>Force</th>
                      <th>Status</th>
                      <th>Title</th>
                      <th>Link</th>
                      <th>Action</th>

                    </tr>

                  </thead>

                  <tbody>

                  @foreach($appversion as $key => $app)

                    <tr>

                      <td>{{$app->id}}</td>  
                      <td>{{$app->version}}</td>
                      <td>{{isset($app->latest_version_code) ? $app->latest_version_code : $app->version}}</td>
                      <td>{{isset($app->minimum_version_code) ? $app->minimum_version_code : 0}}</td>
                      <td>{{!empty($app->force_update) ? 'Yes' : 'No'}}</td>
                      <td>{{!isset($app->status) || !empty($app->status) ? 'Active' : 'Inactive'}}</td>
                      <td>{{$app->title}}</td>
                      <td>{{isset($app->android_link) && $app->android_link ? $app->android_link : $app->link}}</td>

                    <td>

                      <a href="{{url('admin/edit-app-version')}}/{{ $app->id }}" class="btn btn-success btn-xs" ><i class="fa fa-edit" aria-hidden="true"></i></a>
				
                      <a class="btn btn-danger"  href="{{url('admin/delete_app-stage')}}/{{$app->id}}" data-id="{{$app->id}}"title="Delete"><i class="fa fa-trash" aria-hidden="true"></i></a>
                      </td>

                    </tr>

		            @endforeach

                

                  </tbody>

                </table>

              </div>

			    

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

    $(".deletepage").click(function(e){

		 var id = $(this).attr('data-id');

		if(confirm("Are you sure you want to delete this?")){

        e.preventDefault();

		$.ajax({

           type:'GET',

           url:"/admin/delete_banner",

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
